<?php
include 'conexion.php';
include 'funcionesWorker.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/**
 * Un worker CLI persistente (este script vive corriendo horas/días en un
 * while(true)) es la víctima clásica de "MySQL server has gone away": si
 * la conexión lleva más tiempo abierta e inactiva que el wait_timeout de
 * MySQL, el servidor la cierra de su lado sin avisar. La siguiente query
 * revienta. Antes no había ningún manejo de esto, así que el proceso se
 * moría solo y había que reiniciarlo a mano ("se detiene a cada rato").
 * Aquí probamos la conexión cada vuelta y reconectamos si hace falta.
 */
function asegurarConexionViva() {
    global $conn;
    try {
        $conn->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        error_log('Worker: conexión con la BD perdida (' . $e->getMessage() . '), reconectando...');
        try {
            $nueva = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $nueva->set_charset(DB_CHARSET);
            $conn = $nueva;
            error_log('Worker: reconexión exitosa.');
            return true;
        } catch (Throwable $e2) {
            error_log('Worker: no se pudo reconectar (' . $e2->getMessage() . '). Reintentando en 5s.');
            return false;
        }
    }
}

echo "====================================================\n";
echo " MOTOR IA MESS - OPTIMIZADO - CON APRENDIZAJE \n";
echo " Soportando: Min-Max, Promedio y Feedback \n";
echo "====================================================\n";

while (true) {

    $id = null;

    // 0. Verificamos/reconectamos la BD ANTES de tocar nada. Si no se logra
    // reconectar, esperamos y reintentamos en la siguiente vuelta en vez de
    // seguir adelante con una conexión muerta.
    if (!asegurarConexionViva()) {
        sleep(5);
        continue;
    }

    // Red de seguridad total: pase lo que pase procesando este ítem
    // (incluyendo errores fatales de PHP como TypeError, no solo
    // Exception), el worker NUNCA debe morir por un solo ítem problemático.
    // Antes el catch solo cubría `Exception`, y la actualización final en
    // BD ni siquiera estaba dentro de un try/catch — cualquier fatal ahí
    // tumbaba el proceso completo y había que reiniciarlo a mano.
    try {
        procesarSiguientePendiente();
    } catch (Throwable $e) {
        error_log('Worker: error inesperado en la vuelta del bucle: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        sleep(2);
    }
}

/**
 * A2 — Banda estadística para el precio final, en vez de min/max crudos.
 * Si hay 5 o más muestras: [P25 x 0.85, P75 x 1.15]. Si hay menos muestras
 * pero hay mediana: [mediana x 0.8, mediana x 1.2]. Si no hay estadísticas
 * válidas: min/max históricos del ítem como respaldo. Devuelve [0, 0] si
 * tampoco hay min/max válidos.
 */
function calcularBandaPrecio($contexto_precio, $stats_item) {
    if (is_array($contexto_precio)) {
        $n = (int) ($contexto_precio['n_muestras'] ?? 0);
        $p25 = (float) ($contexto_precio['p25'] ?? 0);
        $p75 = (float) ($contexto_precio['p75'] ?? 0);
        $mediana = (float) ($contexto_precio['mediana'] ?? 0);
        if ($n >= 5 && $p25 > 0 && $p75 > 0 && $p75 >= $p25) {
            return [round($p25 * 0.85, 2), round($p75 * 1.15, 2)];
        }
        if ($mediana > 0) {
            return [round($mediana * 0.8, 2), round($mediana * 1.2, 2)];
        }
    }
    $min = round((float) ($stats_item['min'] ?? 0), 2);
    $max = round((float) ($stats_item['max'] ?? 0), 2);
    if ($min > 0 && $max >= $min) return [$min, $max];
    return [0, 0];
}

/**
 * A4 — Peso del aprendizaje humano por nº de correcciones: w = n/(n+2),
 * con tope 0.8. Una sola corrección pesa 0.33 (la IA sigue mandando 2/3),
 * 2 pesan 0.50, 5 pesan 0.71 y 8 o más llegan al tope 0.80. Así una
 * corrección aislada no fija el precio al 100% como hacía antes; solo
 * varias correcciones consistentes acercan el precio al valor humano.
 */
function calcularPesoAprendizaje($n) {
    $n = max(0, (int) $n);
    if ($n <= 0) return 0.0;
    return min($n / ($n + 2), 0.8);
}

/**
 * A4 — Ajuste gradual por categoría (sin precio_sugerido explícito):
 * 1 corrección ±5%, 2-3 correcciones ±10%, 4 o más ±15%. Antes era
 * ±15% fijo desde la primera corrección.
 */
function ajustePorCategoria($n) {
    $n = max(0, (int) $n);
    if ($n <= 1) return 0.05;
    if ($n <= 3) return 0.10;
    return 0.15;
}

/**
 * Fase 4 — Inserta las filas hermanas de un ítem con Atlas: una por cada
 * alternativa validada (origen 'exacta') o, con clave muerta (origen
 * 'similar'), una por cada sugerencia por similitud. Las hijas nacen
 * 'completado' con su propuesta_ia ya llena (precio solo histórico, sin
 * Ollama) y el worker NUNCA las reprocesa (su SELECT solo toma
 * estatus='pendiente'). El vínculo padre/hija va en propuesta_ia.atlas
 * (es_alternativa/alternativa_de o es_sugerencia/sugerencia_de); no hay
 * cambio de esquema. Idempotente: si el padre se reprocesa, las hijas
 * que ya existan no se duplican. Devuelve cuántas hijas insertó.
 * No lanza excepción: ante error lo registra y devuelve lo insertado.
 */
function insertarFilasHermanasAtlas($conn, $idPadre, $idProyecto, $idclte, $idUsRegistro, $entrada, $atlas, $cdmessCotizado) {
    if (empty($atlas) || !is_array($atlas)) return 0;
    $origen = $atlas['origen'] ?? '';
    $codigoEncontrado = (string) ($atlas['codigo_encontrado'] ?? '');
    $codigoPrincipal = $atlas['codigo_principal'] ?? null;

    // Candidatos: alternativas validadas (exacta) o sugerencias (similar,
    // solo con clave muerta). Cualquier otro caso no genera hijas.
    $candidatos = [];
    $esSugerencia = false;
    if ($origen === 'exacta' && !empty($atlas['alternativas']) && is_array($atlas['alternativas'])) {
        foreach ($atlas['alternativas'] as $alt) {
            $candidatos[] = ['codigo' => $alt['codigo_scot'] ?? '', 'alt' => $alt, 'sug' => null];
        }
    } elseif ($origen === 'similar' && !empty($atlas['sugerencias']) && is_array($atlas['sugerencias'])) {
        $esSugerencia = true;
        foreach ($atlas['sugerencias'] as $sug) {
            $candidatos[] = ['codigo' => $sug['codigo_scot'] ?? '', 'alt' => null, 'sug' => $sug];
        }
    }
    if (empty($candidatos)) return 0;

    $insertados = 0;
    // OJO: mysqli_stmt::bind_param() recibe los valores POR REFERENCIA;
    // pasarle una expresión como (string)$idPadre lanza Error en PHP 8
    // ("could not be passed by reference") y el catch de abajo lo tragaba
    // en silencio, así que NUNCA se insertaba ninguna hija. Por eso el
    // cast va en una variable antes del bucle.
    $idPadreStr = (string) $idPadre;
    try {
        // Idempotencia: hija igual = mismo proyecto, mismo padre
        // (alternativa_de) y mismo cdmess_historico. El CASE es
        // deliberado: hay filas viejas con propuesta_ia no válido y MySQL
        // no garantiza en qué orden evalúa el WHERE; sin el CASE, el ->>
        // podría evaluarse sobre un JSON inválido y tronar la consulta
        // (mismo patrón de fase3_medicion.sql).
        $chk = $conn->prepare(
            "SELECT id FROM cola_procesamiento
             WHERE id_proyecto = ? AND propuesta_ia IS NOT NULL AND JSON_VALID(propuesta_ia)
               AND CASE WHEN JSON_VALID(propuesta_ia) THEN propuesta_ia ->> '$.atlas.alternativa_de' END = ?
               AND cdmess_historico = ?
             LIMIT 1"
        );
        $ins = $conn->prepare(
            "INSERT INTO cola_procesamiento
                (id_proyecto, idclte, entrada_usuario, cdmess_historico, descripcion_historica,
                 precio_historico, estatus, es_sugerencia, id_us_registro, propuesta_ia, fecha_registro)
             VALUES (?, ?, ?, ?, ?, ?, 'completado', 1, ?, ?, NOW())"
        );
        foreach ($candidatos as $cand) {
            $codigo = trim((string) ($cand['codigo'] ?? ''));
            // Nunca una hija con el código cotizado del padre (el principal
            // tras el re-base D1) ni con la clave encontrada.
            if ($codigo === '' || $codigo === $cdmessCotizado || $codigo === $codigoEncontrado) continue;
            $chk->bind_param('sss', $idProyecto, $idPadreStr, $codigo);
            // En PHP < 8.1 mysqli NO lanza excepciones: execute() devuelve
            // false y el fallo quedaba en silencio (así se perdió el bug
            // del bind_param en producción). Registrarlo siempre.
            if ($chk->execute() === false) {
                error_log('Atlas: insertarFilasHermanasAtlas(padre ' . $idPadre . ') la comprobación de idempotencia falló: ' . $conn->error);
                return $insertados;
            }
            $resChk = $chk->get_result();
            if ($resChk === false) {
                error_log('Atlas: insertarFilasHermanasAtlas(padre ' . $idPadre . ') get_result falló (¿sin mysqlnd?): ' . $conn->error);
                return $insertados;
            }
            if ($resChk->fetch_assoc()) continue;

            if ($esSugerencia) {
                $sug = $cand['sug'];
                $precio = isset($sug['precio_promedio']) && $sug['precio_promedio'] !== null ? round((float) $sug['precio_promedio'], 2) : null;
                $precioMxn = isset($sug['precio_promedio_mxn']) && $sug['precio_promedio_mxn'] !== null ? round((float) $sug['precio_promedio_mxn'], 2) : null;
                $nMuestras = (int) ($sug['n_muestras'] ?? 0);
                $filaCat = atlasFilaCatalogo($conn, $codigo);
                $servicio = $sug['servicio'] ?? ($filaCat['servicio'] ?? null);
                $histTxt = $nMuestras > 0 ? "Precio histórico con {$nMuestras} cotizaciones" : 'Sin historial';
                $notaCorta = 'Sugerencia para ' . $codigoEncontrado . ', ya no activa';
                $detalle = $notaCorta . ' | ' . $histTxt;
                $veredicto = 'Sugerencia por similitud de ' . $codigoEncontrado . ', ya no activa. Precio por histórico, sin propuesta de IA.';
                $atlasHija = [
                    'encontrado' => true,
                    'origen' => 'exacta',
                    'codigo_scot' => $codigo,
                    'servicio' => $servicio,
                    'usar_cuando' => $filaCat['usar_cuando'] ?? null,
                    'preguntas' => $filaCat['preguntas'] ?? null,
                    'precaucion' => $filaCat['precaucion'] ?? null,
                    'tipo_coincidencia' => $sug['tipo_coincidencia'] ?? ($filaCat['tipo_coincidencia'] ?? null),
                    'ejemplos' => $sug['ejemplos'] ?? ($filaCat['ejemplos'] ?? null),
                    'descripcion_original' => $filaCat['descripcion_original'] ?? null,
                    'modelos' => [],
                    'alternativas' => [],
                    'codigo_principal' => null,
                    'codigo_encontrado' => $codigoEncontrado,
                    // La sugerencia siempre exige validación de experto
                    // (nunca es principal ni validada como elección).
                    'requiere_validacion_experto' => true,
                    'motivo_validacion' => ['código no cubierto por la validación de experto técnico; sugerencia por similitud, sin validar'],
                    'puntaje_similitud' => $sug['puntaje_similitud'] ?? null,
                    'tokens_comunes' => $sug['tokens_comunes'] ?? [],
                    'es_sugerencia' => true,
                    'sugerencia_de' => $codigoEncontrado,
                    'alternativa_de' => (int) $idPadre,
                ];
                $requiere = true;
            } else {
                $alt = $cand['alt'];
                $precio = isset($alt['precio_promedio']) && $alt['precio_promedio'] !== null ? round((float) $alt['precio_promedio'], 2) : null;
                $precioMxn = isset($alt['precio_promedio_mxn']) && $alt['precio_promedio_mxn'] !== null ? round((float) $alt['precio_promedio_mxn'], 2) : null;
                $nMuestras = (int) ($alt['n_muestras'] ?? 0);
                $val = atlasValidacionCodigo($conn, $codigo);
                $filaCat = $val['fila'];
                $servicio = $alt['servicio'] ?? ($filaCat['servicio'] ?? null);
                $histTxt = $nMuestras > 0 ? "Precio histórico con {$nMuestras} cotizaciones" : 'Sin historial';
                $detalle = 'Alternativa validada por experto técnico de ' . $codigoEncontrado . ' | ' . $histTxt;
                $notaCorta = 'Alternativa de ' . $codigoEncontrado . ' validada por experto técnico.';
                $veredicto = 'Clave alternativa validada por experto técnico de ' . $codigoEncontrado . '. Precio por histórico, sin propuesta de IA.';
                $atlasHija = [
                    'encontrado' => true,
                    'origen' => 'exacta',
                    'codigo_scot' => $codigo,
                    'servicio' => $servicio,
                    'usar_cuando' => $filaCat['usar_cuando'] ?? null,
                    'preguntas' => $filaCat['preguntas'] ?? null,
                    'precaucion' => $filaCat['precaucion'] ?? null,
                    'tipo_coincidencia' => $alt['tipo_coincidencia'] ?? ($filaCat['tipo_coincidencia'] ?? null),
                    'ejemplos' => $filaCat['ejemplos'] ?? null,
                    'descripcion_original' => $filaCat['descripcion_original'] ?? null,
                    'modelos' => [],
                    'alternativas' => [],
                    'codigo_principal' => $codigoPrincipal,
                    'codigo_encontrado' => $codigoEncontrado,
                    'requiere_validacion_experto' => $val['requiere'],
                    'motivo_validacion' => $val['motivos'],
                    'es_alternativa' => true,
                    'alternativa_de' => (int) $idPadre,
                ];
                $requiere = $val['requiere'];
            }

            $dataHija = [
                'cdmess' => $codigo,
                'desc' => $servicio,
                'folio' => null,
                'fecha' => null,
                'detalle_calculo' => $detalle,
                'precio_min' => null,
                'precio_max' => null,
                'precio_promedio' => $precio,
                'precio_ia' => null,
                'precio_ia_min' => null,
                'precio_ia_max' => null,
                'precio_min_mxn' => null,
                'precio_max_mxn' => null,
                'precio_promedio_mxn' => $precioMxn,
                'precio_ia_mxn' => null,
                'notas' => $notaCorta,
                'veredicto_ia' => $veredicto,
                'coincidencias' => '',
                'aprendizaje_aplicado' => false,
                'num_correcciones' => 0,
                'peso_aprendizaje' => null,
                'precio_humano_ponderado' => null,
                'aprendizaje_detalle' => '',
                'banda_min' => null,
                'banda_max' => null,
                'contexto_precio' => null,
                'requiere_revision' => $requiere,
                'origen_precio' => $esSugerencia ? 'sugerencia' : 'alternativa',
                'ia_intentos' => 0,
                'ia_error' => null,
                'ia_degradado' => false,
                'trazabilidad' => null,
                'atlas' => $atlasHija,
            ];
            $jsonHija = json_encode($dataHija, JSON_UNESCAPED_UNICODE);
            $precioHist = $precio;
            $ins->bind_param('sisssdis', $idProyecto, $idclte, $entrada, $codigo, $servicio, $precioHist, $idUsRegistro, $jsonHija);
            if ($ins->execute() === false) {
                error_log('Atlas: insertarFilasHermanasAtlas(padre ' . $idPadre . ') no se pudo insertar la hija ' . $codigo . ': ' . $ins->error . ' / ' . $conn->error);
                continue;
            }
            if ($ins->affected_rows > 0) $insertados++;
        }
        $chk->close();
        $ins->close();
    } catch (Throwable $e) {
        error_log('Atlas: insertarFilasHermanasAtlas(padre ' . $idPadre . ') falló: ' . $e->getMessage());
    }
    return $insertados;
}

/**
 * Procesa un único ítem pendiente (si hay alguno) de principio a fin:
 * lo marca 'procesando', calcula la propuesta de la IA, y guarda el
 * resultado final. Aislado en su propia función para que el try/catch de
 * arriba pueda envolver TODO, incluyendo la actualización final en BD.
 */
function procesarSiguientePendiente() {
    global $conn;

    $id = null;
    $t_inicio = microtime(true);

    // Latido de vida: index.php (via verificarWorker()) lee este archivo
    // para saber si el motor sigue corriendo, en vez de depender de "wmic"
    // (que solo existe en Windows y ya está deprecado ahí también).
    @file_put_contents(WORKER_HEARTBEAT_FILE, (string) time());

    $res = $conn->query("SELECT id, id_proyecto, idclte, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica, id_us_registro, propuesta_ia
                         FROM cola_procesamiento
                         WHERE estatus = 'pendiente' 
                         LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $item = $res->fetch_assoc();
        $id = $item['id'];
        $entrada = trim($item['entrada_usuario']);
        
        $conn->query("UPDATE cola_procesamiento SET estatus = 'procesando' WHERE id = $id");
    
  
  try {

    // Cliente elegido en el cargador (NULL = "Todos los clientes"). Con
    // cliente, historial, rango, promedio y folio salen SOLO de sus cotizaciones.
    $idclte = $item['idclte'] !== null ? (int) $item['idclte'] : null;

    // 1. Obtén historial - esto ya te da el CDMESS aunque busques por texto
    $stats = obtenerHistorialMESS($entrada, $idclte);

    // DEBUG: Ve qué regresó el historial
    //error_log("HISTORIAL ITEMS: ". json_encode($stats['items']?? []));

    // 2. CLAVE: Usa el CDMESS que regresó el historial para buscar aprendizaje
    $cdmess_para_aprendizaje = $stats['cdmess']?? $item['cdmess_historico']?? 'N/A';

    // Si el item actual tiene cdmess_historico, sus estadísticas se calculan
    // directo por esa clave exacta (antes se buscaban en el top 10 por texto
    // de obtenerHistorialMESS y, si no aparecía, se usaban datos de otra clave)
    $cdmess_actual = $item['cdmess_historico'];
    if (!empty($cdmess_actual)) {
        $stats_item = obtenerEstadisticasCDMESS($cdmess_actual, $conn, $idclte);
        // Si hay aprendizaje, búscalo con el CDMESS específico del item
        $cdmess_para_aprendizaje = $cdmess_actual;
    } else {
        // Fallback: usa el primero que regresó obtenerHistorialMESS
        $stats_item = [
            'min' => $stats['min']?? 0,
            'max' => $stats['max']?? 0,
            'avg' => $stats['avg']?? 0,
            'total' => 0,
            'min_mxn' => 0,
            'max_mxn' => 0,
            'avg_mxn' => 0,
            'total_mxn' => 0,
            'fecha_min' => null,
            'fecha_max' => null
        ];
    }

    // A1: contexto estadístico completo del CDMESS exacto del ítem
    // (null si no hay historial válido; el flujo sigue como antes).
    $cdmess_contexto = !empty($cdmess_actual) ? $cdmess_actual : ($stats['cdmess'] ?? '');

    // Fase 1 (Atlas): info experta del Atlas para la clave ya encontrada.
    // null si no hay fila Atlas ni sugerencia por similitud que supere
    // el umbral. Se llama ANTES de folio/contexto/aprendizaje/Ollama
    // porque la Fase 2 puede re-basar el código cotizado al principal.
    $atlas = obtenerInfoAtlas($cdmess_contexto, $conn, $idclte, $item['descripcion_historica'] ?? '', $entrada);

    // Fase 2 (D1, lectura literal): si entre las claves relacionadas hay
    // un principal distinto de la clave encontrada, EL CÓDIGO COTIZADO
    // PASA A SER EL PRINCIPAL y todo se recalcula sobre él (stats,
    // contexto, aprendizaje, Ollama, banda, folio). La clave encontrada
    // queda en $atlas['codigo_encontrado'] para auditarlo. No se re-basa
    // si no hay Atlas, si el origen es 'similar' (su codigo_principal es
    // null por construcción) o si el principal es la propia clave.
    // Guarda: si el principal no tiene historial (total = 0) no se
    // re-basa, para no cotizar en $0 pudiendo cotizar la clave encontrada.
    if (!empty($atlas)
        && ($atlas['origen'] ?? '') === 'exacta'
        && !empty($atlas['codigo_principal'])
        && $atlas['codigo_principal'] !== $cdmess_contexto
    ) {
        $stats_principal = obtenerEstadisticasCDMESS($atlas['codigo_principal'], $conn, $idclte);
        if (!empty($stats_principal['total'])) {
            $codigo_principal = $atlas['codigo_principal'];
            $cdmess_contexto = $codigo_principal;
            $cdmess_actual = $codigo_principal;
            $stats_item = $stats_principal;
            $cdmess_para_aprendizaje = $codigo_principal;
            // El prompt de Ollama se arma con $stats (CDMESS/MIN/MAX/AVG
            // y el "cdmess" del JSON de respuesta): sin esto seguiría
            // nombrando la clave original. Las coincidencias
            // (alternativas/items) se dejan como las dio la búsqueda por
            // texto: describen lo que se buscó, no el código cotizado.
            $stats['cdmess'] = $codigo_principal;
            $stats['min'] = $stats_item['min'];
            $stats['max'] = $stats_item['max'];
            $stats['avg'] = $stats_item['avg'];
            $stats['min_mxn'] = $stats_item['min_mxn'];
            $stats['max_mxn'] = $stats_item['max_mxn'];
            $stats['avg_mxn'] = $stats_item['avg_mxn'];
        }
    }

    // Folio y fecha de la cotización que se usó como base (la más reciente del CDMESS).
    // Se calcula DESPUÉS del re-base de la Fase 2: si hay principal, el
    // folio es el del principal, no el de la clave encontrada.
    $cdmess_folio = !empty($cdmess_actual) ? $cdmess_actual : ($stats['cdmess'] ?? '');
    $folio_base   = obtenerFolioBase($cdmess_folio, $conn, $idclte);

    // A1: contexto estadístico completo del CDMESS exacto del ítem.
    // Se calcula DESPUÉS del re-base: si hay principal, el contexto es
    // el del principal, no el de la clave encontrada.
    $contexto_precio = obtenerContextoPrecio($cdmess_contexto, $conn, $idclte);

    $aprendizaje = obtenerAprendizajeHumano($cdmess_para_aprendizaje, $conn);

    // NOTA: preguntarOllamaConPrecios() devuelve un STRING con JSON.
    // Antes el código lo usaba directo como si fuera un array
    // ($respuesta_ia['precio_ia']), lo cual en PHP nunca funciona como se
    // esperaba sobre un string y hacía que la rama de "IA sugiere precio
    // distinto al promedio" jamás se aplicara en la práctica. Se decodifica
    // aquí explícitamente.
    // A6: latencia de la llamada a Ollama (incluye reintentos y
    // re-preguntas de A5). $t_inicio (arriba) ya mide el ítem completo.
    $t_ia_inicio = microtime(true);
    $respuesta_ia_raw = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje, $contexto_precio);
    $latencia_ia_ms = (int) round((microtime(true) - $t_ia_inicio) * 1000);
    $respuesta_ia = json_decode($respuesta_ia_raw, true);
    if (!is_array($respuesta_ia)) {
        $respuesta_ia = [];
    }
    $veredicto_ia = trim((string) ($respuesta_ia['veredicto'] ?? ''));
    // A5: diagnóstico de la llamada a Ollama que trae preguntarOllamaConPrecios()
    // en la propia respuesta (van a propuesta_ia). ia_degradado=true significa
    // que Ollama no devolvió una propuesta válida de precio en esta vuelta y
    // el precio de la IA es el respaldo del promedio (o el ancla humana si
    // hay aprendizaje); no afirma por sí solo que el precio final sea el
    // promedio cuando hay mezcla humana (ahí origen_precio es 'mixto'/'humano').
    $ia_intentos = isset($respuesta_ia['ia_intentos']) ? (int) $respuesta_ia['ia_intentos'] : 0;
    $ia_error = isset($respuesta_ia['ia_error']) && $respuesta_ia['ia_error'] !== '' && $respuesta_ia['ia_error'] !== null ? (string) $respuesta_ia['ia_error'] : null;
    $ia_degradado = !empty($respuesta_ia['ia_degradado']);
    //error_log("APRENDIZAJE PARA $cdmess_para_aprendizaje: ". json_encode($aprendizaje));

    $precio_base = round($stats_item['avg']?? 0, 2);
    // A+B (2026-11-23): el precio de RESPALDO ("Promedio (respaldo)",
    // origen_precio 'promedio', cuando Ollama no propuso precio válido)
    // es el promedio PONDERADO por antigüedad del contexto (A3), no el
    // simple: lo reciente pesa más. Solo afecta al respaldo: $precio_base
    // (simple) se conserva para $ratio_mxn, para el ancla del aprendizaje
    // humano (A4) y para el campo precio_promedio que muestra el monitor
    // (promedio simple histórico). Sin contexto o con ponderado no válido,
    // se queda el simple de hoy. NO se reasigna $precio_base: hacerlo
    // cambiaría precio_promedio a ponderado y movería el ancla de la
    // categoría A4 sin IA; el humano seguiría mandando en sus ramas, pero
    // el respaldo es lo único que debe cambiar.
    $precio_base_respaldo = (!empty($contexto_precio) && isset($contexto_precio['promedio_ponderado']) && (float) $contexto_precio['promedio_ponderado'] > 0)
        ? round((float) $contexto_precio['promedio_ponderado'], 2)
        : $precio_base;
    $precio_final = $precio_base_respaldo;
    $precio_final_min = round($stats_item['min']?? 0, 2);
    $precio_final_max = round($stats_item['max']?? 0, 2);
    $precio_base_mxn = round($stats_item['avg_mxn']?? 0, 2);
    $precio_final_mxn = $precio_base_mxn;
    $nota_final = "Basado en histórico de cotizaciones_items";

    // A2: banda estadística en vez de min/max crudos, y proporción USD/MXN
    // del histórico (la usa tanto el precio humano como el de la IA).
    list($banda_min, $banda_max) = calcularBandaPrecio($contexto_precio, $stats_item);
    $ratio_mxn = ($precio_base > 0) ? ($precio_base_mxn / $precio_base) : 1;
    // A+B: el MXN del respaldo deriva del base ponderado con el ratio
    // simple, para que USD y MXN queden consistentes.
    $precio_final_mxn = round($precio_base_respaldo * $ratio_mxn, 2);
    // A5: el Rango que muestra el monitor (precio_min/max en propuesta_ia)
    // usa los extremos LIMPIOS por IQR cuando hay contexto (A3), no los
    // crudos con atípicos como el Min $5.67 / Max $6650 de S2-5. El contexto
    // no trae min/max MXN limpios, así que se derivan con la misma
    // proporción USD/MXN del histórico que ya se usa para precio_ia_mxn.
    // Sin contexto, se cae a los crudos de obtenerEstadisticasCDMESS().
    // El promedio histórico (precio_promedio) NO cambia: sigue siendo el
    // promedio simple. La banda tampoco cambia (ya usaba P25/P75 limpios).
    $display_min = !empty($contexto_precio) && isset($contexto_precio['min_limpio']) ? round((float) $contexto_precio['min_limpio'], 2) : round($stats_item['min'] ?? 0, 2);
    $display_max = !empty($contexto_precio) && isset($contexto_precio['max_limpio']) ? round((float) $contexto_precio['max_limpio'], 2) : round($stats_item['max'] ?? 0, 2);
    $display_min_mxn = !empty($contexto_precio) && isset($contexto_precio['min_limpio']) ? round($display_min * $ratio_mxn, 2) : round($stats_item['min_mxn'] ?? 0, 2);
    $display_max_mxn = !empty($contexto_precio) && isset($contexto_precio['max_limpio']) ? round($display_max * $ratio_mxn, 2) : round($stats_item['max_mxn'] ?? 0, 2);
    $requiere_revision = false;
    $origen_precio = 'promedio';

    // A4: ancla de la IA para la mezcla gradual. Se extrae ANTES del
    // bloque de aprendizaje porque la mezcla lo necesita como base; si
    // Ollama no propuso precio válido, el ancla es el promedio histórico.
    $precio_ia_sugerido = isset($respuesta_ia['precio_ia']) ? (float) $respuesta_ia['precio_ia'] : null;
    $precio_ia_valido = ($precio_ia_sugerido !== null && $precio_ia_sugerido > 0);
    $precio_ancla = $precio_ia_valido ? $precio_ia_sugerido : $precio_base;
    // A4: salidas de trazabilidad del aprendizaje (van a propuesta_ia).
    $peso_aprendizaje = null;
    $precio_humano_ponderado_out = null;
    $aprendizaje_detalle = '';
    $n_correcciones = !empty($aprendizaje) ? (int) ($aprendizaje['total_correcciones'] ?? 0) : 0;
    $fecha_ultima_correccion = !empty($aprendizaje) ? ($aprendizaje['fecha_ultima_correccion'] ?? null) : null;

    // 3. APLICAR APRENDIZAJE SI EXISTE.
    // A4 — Aprendizaje GRADUAL, no binario. Antes, un precio_sugerido
    // humano fijaba el precio exacto al 100% y las categorías aplicaban
    // ±15% fijo desde la primera corrección. Ahora:
    //  - Con precio_sugerido: mezcla precio_final = (1-w)*precio_ia +
    //    w*precio_humano_ponderado, con w = n/(n+2) topado a 0.8. Una
    //    corrección (w=0.33) mueve el precio solo un tercio hacia lo
    //    humano; hacen falta varias para que lo humano domine.
    //  - Sin precio_sugerido pero con categoría: ajuste gradual sobre
    //    el precio de la IA (o el promedio si no hay IA): ±5% con 1
    //    corrección, ±10% con 2-3, ±15% con 4 o más.
    //  - El rango NUNCA colapsa: sigue siendo ±5% alrededor del precio
    //    final (rama humana) o la banda/rango de la IA.
    //  - origen_precio: 'humano' cuando w>=0.5 o no hay precio de IA;
    //    'mixto' cuando hay mezcla con w<0.5 (la IA aún pesa más).
    // NOTA: la rama de categoría sin precio_sugerido hoy es casi
    // inalcanzable en la práctica, porque obtenerAprendizajeHumano()
    // exige precio_usuario>0 y por tanto precio_sugerido siempre viene
    // calculado cuando hay aprendizaje; queda como red de seguridad.
    if (!empty($aprendizaje)) {
        if (!empty($aprendizaje['precio_sugerido'])) {
            $peso_aprendizaje = calcularPesoAprendizaje($n_correcciones);
            $precio_humano = (float) ($aprendizaje['precio_humano_ponderado'] ?? $aprendizaje['precio_sugerido']);
            $precio_humano_ponderado_out = round($precio_humano, 2);
            $precio_humano_mxn = !empty($aprendizaje['precio_sugerido_mxn'])
                ? (float) $aprendizaje['precio_sugerido_mxn']
                : round($precio_humano * $ratio_mxn, 2);
            if ($precio_ia_valido) {
                $precio_final = round((1 - $peso_aprendizaje) * $precio_ancla + $peso_aprendizaje * $precio_humano, 2);
                $precio_final_mxn = round((1 - $peso_aprendizaje) * $precio_ancla * $ratio_mxn + $peso_aprendizaje * $precio_humano_mxn, 2);
                $origen_precio = ($peso_aprendizaje >= 0.5) ? 'humano' : 'mixto';
            } else {
                // Sin precio de IA válido, el precio humano ponderado se
                // aplica tal cual (no hay nada con qué mezclarlo).
                $precio_final = round($precio_humano, 2);
                $precio_final_mxn = round($precio_humano_mxn, 2);
                $origen_precio = 'humano';
            }
            // A2: el rango YA NO colapsa a un solo número: se mantiene
            // ±5% alrededor del precio final.
            $precio_final_min = round($precio_final * 0.95, 2);
            $precio_final_max = round($precio_final * 1.05, 2);
            $aprendizaje_detalle = sprintf(
                "Aprendizaje humano: %d correcciones, peso %.2f, última %s, humano $%.2f",
                $n_correcciones,
                $peso_aprendizaje,
                !empty($fecha_ultima_correccion) ? substr((string) $fecha_ultima_correccion, 0, 10) : 'sin fecha',
                $precio_humano
            );
            $nota_final = ($origen_precio === 'mixto')
                ? sprintf("Mezcla IA/humano (peso humano %.2f, %d revisión%s): IA $%.2f -> humano $%.2f", $peso_aprendizaje, $n_correcciones, $n_correcciones === 1 ? '' : 'es', $precio_ancla, $precio_humano)
                : sprintf("Precio corregido por %d revisiones humanas (peso %.2f)", $n_correcciones, $peso_aprendizaje);
        } else {
            switch ($aprendizaje['categoria_principal']) {
                case 'Precio muy alto':
                    $ajuste_cat = ajustePorCategoria($n_correcciones);
                    $precio_final = round($precio_ancla * (1 - $ajuste_cat), 2);
                    $precio_final_mxn = round($precio_ancla * $ratio_mxn * (1 - $ajuste_cat), 2);
                    // A2: el rango no colapsa al precio ajustado.
                    $precio_final_min = round($precio_final * 0.95, 2);
                    $precio_final_max = round($precio_final * 1.05, 2);
                    $origen_precio = 'humano';
                    $nota_final = sprintf("Reducido %.0f%% por %d reportes de 'precio muy alto'", $ajuste_cat * 100, $n_correcciones);
                    $aprendizaje_detalle = sprintf("Categoría 'Precio muy alto': %d correcciones, ajuste -%.0f%% sobre precio IA $%.2f", $n_correcciones, $ajuste_cat * 100, $precio_ancla);
                    break;
                case 'Precio muy bajo':
                    $ajuste_cat = ajustePorCategoria($n_correcciones);
                    $precio_final = round($precio_ancla * (1 + $ajuste_cat), 2);
                    $precio_final_mxn = round($precio_ancla * $ratio_mxn * (1 + $ajuste_cat), 2);
                    // A2: el rango no colapsa al precio ajustado.
                    $precio_final_min = round($precio_final * 0.95, 2);
                    $precio_final_max = round($precio_final * 1.05, 2);
                    $origen_precio = 'humano';
                    $nota_final = sprintf("Aumentado %.0f%% por %d reportes de 'precio muy bajo'", $ajuste_cat * 100, $n_correcciones);
                    $aprendizaje_detalle = sprintf("Categoría 'Precio muy bajo': %d correcciones, ajuste +%.0f%% sobre precio IA $%.2f", $n_correcciones, $ajuste_cat * 100, $precio_ancla);
                    break;
                case 'Descripcion incorrecta':
                    $nota_final = "ALERTA: ". ($aprendizaje['nota_humana']?? 'Descripción reportada como incorrecta');
                    break;
            }
        }
        
        if ($aprendizaje['alerta_descripcion'] && $aprendizaje['categoria_principal']!= 'Descripcion incorrecta') {
            $nota_final.= " | ATENCIÓN: ". $aprendizaje['nota_humana'];
        }
    }
    else {
        // Si no hay aprendizaje humano previo, la IA sí puede proponer un
        // precio distinto al promedio (dentro de lo razonable) Y un rango
        // (conservador/optimista) en vez de un solo número fijo. El MXN se
        // deriva manteniendo la misma proporción USD/MXN del histórico,
        // porque el modelo no tiene tipo de cambio propio y no queremos que
        // "invente" una cifra en MXN sin respaldo.
        // A4: $precio_ia_sugerido / $precio_ia_valido ya se extrajeron
        // antes del bloque de aprendizaje y se reutilizan aquí.
        if ($precio_ia_sugerido !== null && $precio_ia_sugerido > 0 && abs($precio_ia_sugerido - $precio_base) > 0.01) {
            $precio_final = round($precio_ia_sugerido, 2);
            $precio_final_mxn = round($precio_final * $ratio_mxn, 2);
            $origen_precio = 'ia';
            $nota_final = "Precio sugerido por IA basado en análisis de datos";
        }

        // Rango propuesto por la IA, tal como lo dio (A2: ya NO se fuerza
        // el precio final dentro de ese rango con min()/max()). Si no lo
        // mandó o vino inconsistente, se usa la banda estadística como
        // respaldo en vez de mostrar algo incoherente.
        $ia_min = isset($respuesta_ia['precio_ia_min']) ? (float) $respuesta_ia['precio_ia_min'] : null;
        $ia_max = isset($respuesta_ia['precio_ia_max']) ? (float) $respuesta_ia['precio_ia_max'] : null;
        if ($ia_min !== null && $ia_max !== null && $ia_min > 0 && $ia_max >= $ia_min) {
            $precio_final_min = round($ia_min, 2);
            $precio_final_max = round($ia_max, 2);
        } elseif ($banda_min > 0 && $banda_max >= $banda_min) {
            $precio_final_min = $banda_min;
            $precio_final_max = $banda_max;
        } else {
            $precio_final_min = round(min($precio_final_min, $precio_final), 2);
            $precio_final_max = round(max($precio_final_max, $precio_final), 2);
        }
    }

    // Genera el texto que va debajo de la descripción
    $detalle_calculo = "Sin historial disponible";
    if (!empty($stats_item['total']) && $stats_item['total'] > 0) {
        $fecha_rango = "";
        if ($stats_item['fecha_min'] && $stats_item['fecha_max']) {
            $anio_min = date('Y', strtotime($stats_item['fecha_min']));
            $anio_max = date('Y', strtotime($stats_item['fecha_max']));
            $fecha_rango = $anio_min == $anio_max? " $anio_min" : " $anio_min-$anio_max";
        }
        
        // A5: el Min/Max del detalle es el LIMPIO (igual que el Rango del
        // monitor); el Promedio sigue siendo el promedio simple histórico.
        $detalle_calculo = sprintf(
            "Calculado con %d cotizaciones%s%s | Min: $%.2f usd| Max: $%.2f usd| Promedio: $%.2f usd | Min: $%.2f mxn| Max: $%.2f mxn| Promedio: $%.2f mxn",
            $stats_item['total'],
            $idclte !== null ? ' del cliente' : '',
            $fecha_rango,
            $display_min,
            $display_max,
            $stats_item['avg'],
            $display_min_mxn,
            $display_max_mxn,
            $stats_item['avg_mxn']
        );
        
        if (!empty($contexto_precio)) {
            $detalle_calculo .= sprintf(
                " | Banda: $%.2f-$%.2f usd | Mediana: $%.2f usd (n=%d)",
                $banda_min, $banda_max, $contexto_precio['mediana'], $contexto_precio['n_muestras']
            );
        }

        // A3: la línea de limpieza solo se muestra cuando hubo atípicos
        // descartados (o cambió el conteo), para no ensuciar el detalle.
        if (!empty($contexto_precio)
            && ((($contexto_precio['n_atipicos'] ?? 0) > 0)
                || (($contexto_precio['n_limpio'] ?? $contexto_precio['n_muestras']) !== ($contexto_precio['n_original'] ?? $contexto_precio['n_muestras'])))) {
            $detalle_calculo .= sprintf(
                " | Limpieza IQR: n=%d->%d (%d atípicos) | Prom. ponderado: $%.2f usd",
                $contexto_precio['n_original'] ?? $contexto_precio['n_muestras'],
                $contexto_precio['n_limpio'] ?? $contexto_precio['n_muestras'],
                $contexto_precio['n_atipicos'] ?? 0,
                $contexto_precio['promedio_ponderado'] ?? $contexto_precio['avg']
            );
        }

        if (!empty($aprendizaje) && $aprendizaje['total_correcciones'] > 0) {
            $detalle_calculo.= " | Ajustado por {$aprendizaje['total_correcciones']} revisiones humanas";
            // A4: peso y fecha de la última corrección en el detalle.
            if ($aprendizaje_detalle !== '') {
                $detalle_calculo .= " | " . $aprendizaje_detalle;
            }
        }
    }

    // Si el modelo no devolvió veredicto (timeout, formato inesperado, etc.),
    // dejamos un texto de respaldo claro en vez de un campo vacío en el modal.
    if ($veredicto_ia === '') {
        $veredicto_ia = !empty($stats['alternativas'])
            ? "Sin veredicto detallado de la IA para este ítem. Precio calculado sobre {$stats_item['total']} cotizaciones históricas; coincidencias consideradas: {$stats['alternativas']}."
            : "Sin veredicto detallado de la IA para este ítem. Precio calculado sobre {$stats_item['total']} cotizaciones históricas, sin otras coincidencias relevantes para comparar.";
    }

    // A2: un precio de IA fuera de la banda NO se descarta ni se fuerza
    // dentro: se mantiene como propuesto y se marca para revisión humana.
    // Dentro de la banda, requiere_revision = false. Un precio con origen
    // humano o promedio (ya validado o sin propuesta de IA) no requiere
    // revisión extra. Si el precio está fuera de banda, se deja constancia
    // en la nota de si el veredicto citó o no la justificación (folio).
    // A4: también se evalúa con origen 'mixto' (la mezcla lleva
    // componente de IA y puede quedar fuera de banda). Con origen
    // 'humano' (w>=0.5 o sin precio de IA) o 'promedio' no se exige
    // revisión extra, como antes.
    if ($origen_precio === 'ia' || $origen_precio === 'mixto') {
        $dentro_banda = ($banda_min > 0 && $banda_max > 0 && $precio_final >= $banda_min && $precio_final <= $banda_max);
        $requiere_revision = !$dentro_banda;
        if ($requiere_revision) {
            $tiene_justificacion = (bool) preg_match('/folio/i', $veredicto_ia)
                || (bool) preg_match('/\b\d{5,}\b/', $veredicto_ia);
            $nota_final .= $tiene_justificacion
                ? " | Precio fuera de banda, justificado en veredicto; requiere revisión humana"
                : " | Precio fuera de banda sin justificación citada en el veredicto; requiere revisión humana";
        }
    } else {
        $requiere_revision = false;
    }

    // Fase 1 (Atlas): OR de revisión cuando Atlas exige validación de
    // experto. No quita la regla de banda, solo suma. El caso similar
    // siempre dispara este OR (su requiere_validacion_experto es true
    // por construcción), así que ningún ítem con sugerencia similar queda
    // como completado sin marca de revisión.
    if (!empty($atlas['requiere_validacion_experto'])) {
        $requiere_revision = true;
        $nota_final .= " | Requiere validación de experto técnico";
    }

    // A6 — Trazabilidad completa de esta propuesta (subobjeto nuevo en
    // propuesta_ia; no se quita ni se cambia ningún campo existente).
    // Modelo y parámetros se leen de las constantes (A6 en ia_ollama.php);
    // si alguna no está definida, se usa el literal que usa el código.
    // NO se guardan el prompt completo ni la respuesta cruda de Ollama.
    // tipo_busqueda del cargador ('todas', etc.) NO se guarda en
    // cola_procesamiento, así que el worker no lo conoce: queda null.
    // n_candidatos_semanticos tampoco: esta ruta usa obtenerHistorialMESS
    // (SQL, máx. 10); la mezcla semántica vive en el encolado (R1).
    $contexto_busqueda = function_exists('procesarContextoBusqueda')
        ? procesarContextoBusqueda($entrada) : null;
    $candidatos_traza = [];
    if (!empty($stats['items']) && is_array($stats['items'])) {
        foreach (array_slice($stats['items'], 0, 8) as $cand) {
            $candidatos_traza[] = [
                'cdmess' => $cand['cdmess'] ?? '',
                'precio_promedio' => isset($cand['avg']) ? round((float) $cand['avg'], 2) : null,
            ];
        }
    }
    $trazabilidad = [
        'modelo' => defined('OLLAMA_MODEL') ? (string) OLLAMA_MODEL : 'desconocido',
        'parametros_precio' => [
            'temperature' => defined('OLLAMA_PRECIO_TEMPERATURE') ? OLLAMA_PRECIO_TEMPERATURE : 0.6,
            'top_p' => defined('OLLAMA_PRECIO_TOP_P') ? OLLAMA_PRECIO_TOP_P : 0.94,
            'num_predict' => defined('OLLAMA_PRECIO_NUM_PREDICT') ? (int) OLLAMA_PRECIO_NUM_PREDICT : 300,
            'timeout_seg' => defined('OLLAMA_TIMEOUT_PRECIO') ? (int) OLLAMA_TIMEOUT_PRECIO : null,
        ],
        'parametros_filtro' => [
            'temperature' => defined('OLLAMA_FILTRO_TEMPERATURE') ? OLLAMA_FILTRO_TEMPERATURE : 0.45,
            'num_predict' => defined('OLLAMA_FILTRO_NUM_PREDICT') ? (int) OLLAMA_FILTRO_NUM_PREDICT : 200,
            'timeout_seg' => defined('OLLAMA_TIMEOUT_FILTRO') ? (int) OLLAMA_TIMEOUT_FILTRO : null,
        ],
        'fecha_hora_procesamiento' => date('Y-m-d H:i:s'),
        'latencia_ia_ms' => $latencia_ia_ms,
        'duracion_total_ms' => (int) round((microtime(true) - $t_inicio) * 1000),
        'entrada_usuario' => $entrada,
        'busqueda_limpia' => $contexto_busqueda['busqueda_limpia'] ?? null,
        'accion' => $contexto_busqueda['accion'] ?? null,
        'tipo' => $contexto_busqueda['tipo'] ?? null,
        'tipo_busqueda' => null,
        'id_cliente' => $idclte,
        'n_candidatos' => count($stats['items'] ?? []),
        'n_candidatos_semanticos' => null,
        'candidatos' => $candidatos_traza,
        'folio_base' => $folio_base['folio'] ?? '',
        'fecha_base' => $folio_base['fecha'] ?? '',
        // Rama de trabajo (literal documentado; no se lee git en runtime).
        'rama' => 'atlas-alternativas-filas',
    ];

    // Experimento rendimiento-filtro-accion: el marcador filtro_ia lo pone
    // la carga masiva (procesar_carga.php) según si el filtro de Ollama
    // corrió para esa línea; el worker lo conserva en la propuesta final
    // para medir el experimento por renglón. null = sin dato (filas viejas
    // o cargadas sin marca). OJO: el filtro NO corre en el worker — la
    // única llamada a Ollama aquí es la de precio — así que este marcador
    // no explica la latencia del worker; el experimento mide el tiempo
    // que el filtro cuesta en la carga, no en el procesado por ítem.
    $filtro_ia_carga = null;
    if (!empty($item['propuesta_ia'])) {
        $propuesta_previa = json_decode($item['propuesta_ia'], true);
        if (is_array($propuesta_previa) && array_key_exists('filtro_ia', $propuesta_previa)) {
            $filtro_ia_carga = (bool) $propuesta_previa['filtro_ia'];
        }
    }

    $data = [
        // Fase 2 (D1): tras el re-base $cdmess_contexto es el principal;
        // sin re-base es la clave encontrada (igual que antes).
        "cdmess" => $cdmess_contexto,
        "desc" => $item['descripcion_historica']?? $entrada,
        "folio" => $folio_base['folio'],
        "fecha" => $folio_base['fecha'],
        "detalle_calculo" => $detalle_calculo,
        "precio_min" => $display_min,
        "precio_max" => $display_max,
        "precio_promedio" => $precio_base,
        "precio_ia" => $precio_final,
        "precio_ia_min" => $precio_final_min,
        "precio_ia_max" => $precio_final_max,
        "precio_min_mxn" => $display_min_mxn,
        "precio_max_mxn" => $display_max_mxn,
        "precio_promedio_mxn" => $precio_base_mxn,
        "precio_ia_mxn" => $precio_final_mxn,
        "notas" => $nota_final,
        "veredicto_ia" => $veredicto_ia,
        "coincidencias" => $stats['alternativas']?? '',
        "aprendizaje_aplicado" =>!empty($aprendizaje),
        "num_correcciones" => $aprendizaje['total_correcciones']?? 0,
        // A4: peso del aprendizaje en la mezcla, precio humano ponderado
        // por recencia y detalle corto (n, peso, última corrección).
        // aprendizaje_aplicado sigue siendo booleano; el texto va en
        // aprendizaje_detalle para no cambiar su tipo.
        "peso_aprendizaje" => $peso_aprendizaje,
        "precio_humano_ponderado" => $precio_humano_ponderado_out,
        "aprendizaje_detalle" => $aprendizaje_detalle,
        "banda_min" => $banda_min,
        "banda_max" => $banda_max,
        "contexto_precio" => !empty($contexto_precio) ? [
            "mediana" => $contexto_precio['mediana'],
            "p25" => $contexto_precio['p25'],
            "p75" => $contexto_precio['p75'],
            // A6: completar lo que A1 ya calcula y el worker descartaba:
            // desviación, fechas y las 5 referencias (folio/fecha/precio).
            "desviacion" => $contexto_precio['desviacion'] ?? null,
            "fecha_min" => $contexto_precio['fecha_min'] ?? null,
            "fecha_max" => $contexto_precio['fecha_max'] ?? null,
            "fecha_ultima" => $contexto_precio['fecha_ultima'] ?? null,
            "referencias" => $contexto_precio['referencias'] ?? [],
            "n_muestras" => $contexto_precio['n_muestras'],
            "tendencia" => $contexto_precio['tendencia_12m'],
            // A3: conteos de la limpieza IQR y promedios ponderados por antigüedad.
            "n_original" => $contexto_precio['n_original'] ?? $contexto_precio['n_muestras'],
            "n_limpio" => $contexto_precio['n_limpio'] ?? $contexto_precio['n_muestras'],
            "n_atipicos" => $contexto_precio['n_atipicos'] ?? 0,
            "min_limpio" => $contexto_precio['min_limpio'] ?? $contexto_precio['min'],
            "max_limpio" => $contexto_precio['max_limpio'] ?? $contexto_precio['max'],
            "promedio_ponderado" => $contexto_precio['promedio_ponderado'] ?? null,
            "mediana_ponderada" => $contexto_precio['mediana_ponderada'] ?? null
        ] : null,
        "requiere_revision" => $requiere_revision,
        "origen_precio" => $origen_precio,
        // A5: diagnóstico de la llamada a Ollama en esta vuelta.
        "ia_intentos" => $ia_intentos,
        // Experimento rendimiento-filtro-accion: si el filtro de Ollama
        // corrió para esta línea en la carga (true) o se saltó (false);
        // null si la fila no trae marca (ver $filtro_ia_carga arriba).
        "filtro_ia" => $filtro_ia_carga,
        "ia_error" => $ia_error,
        "ia_degradado" => $ia_degradado,
        // A6: trazabilidad completa de esta propuesta (ver arriba).
        "trazabilidad" => $trazabilidad,
        // Fase 1 (Atlas): info experta del Atlas (o null si no hay).
        "atlas" => $atlas
    ];

        $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);
    $estado_final = 'completado';

    }
      
    catch (Throwable $e) {
            // Si algo truena (incluye Errors fatales de PHP, no solo
            // Exception, ej. TypeError), no dejes el registro en 'procesando'
            // y NO dejes que tumbe el worker completo.
            error_log("ERROR procesando ID $id: ". $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            $json_final = json_encode([
                "error" => true,
                "mensaje" => "Error al procesar: ". $e->getMessage(),
                "cdmess" => $item['cdmess_historico']?? 'N/A'
            ]);
            $estado_final = 'error';
        }
        // AQUÍ TERMINA EL TRY/CATCH

    // La actualización final también queda protegida por el try/catch(Throwable)
    // del bucle principal en while(true): antes vivía totalmente desprotegida
    // y si prepare() fallaba (p.ej. conexión caída a medio proceso) mataba
    // todo el worker de un solo golpe.
    $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia =?, estatus =?, fecha_registro = NOW() WHERE id =?");
    $stmt->bind_param("ssi", $json_final, $estado_final, $id);
    $stmt->execute();

    // Fase 4: filas hermanas (alternativas validadas o, con clave muerta,
    // sugerencias por similitud). Solo si el padre quedó completado; no
    // tocan el resultado del padre y un fallo aquí no tumba el worker
    // (la inserción ya trae su propio try/catch y este también).
    if ($estado_final === 'completado') {
        try {
            insertarFilasHermanasAtlas($conn, $id, $item['id_proyecto'] ?? '', $idclte, $item['id_us_registro'] ?? null, $entrada, $atlas, $cdmess_contexto);
        } catch (Throwable $e) {
            error_log('Atlas: hijas del ítem ' . $id . ' fallaron: ' . $e->getMessage());
        }
    }

    $t_total = microtime(true);
    echo sprintf("[OK] ID %d -> %s | TOTAL:%.3fs\n", $id, $estado_final, $t_total - $t_inicio);

    } else {
        usleep(200000);
    }
}
