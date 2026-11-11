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

    $res = $conn->query("SELECT id, idclte, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica
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

    // Folio y fecha de la cotización que se usó como base (la más reciente del CDMESS)
    $cdmess_folio = !empty($cdmess_actual) ? $cdmess_actual : ($stats['cdmess'] ?? '');
    $folio_base   = obtenerFolioBase($cdmess_folio, $conn, $idclte);

    // A1: contexto estadístico completo del CDMESS exacto del ítem
    // (null si no hay historial válido; el flujo sigue como antes).
    $cdmess_contexto = !empty($cdmess_actual) ? $cdmess_actual : ($stats['cdmess'] ?? '');
    $contexto_precio = obtenerContextoPrecio($cdmess_contexto, $conn, $idclte);

    $aprendizaje = obtenerAprendizajeHumano($cdmess_para_aprendizaje, $conn);

    // NOTA: preguntarOllamaConPrecios() devuelve un STRING con JSON.
    // Antes el código lo usaba directo como si fuera un array
    // ($respuesta_ia['precio_ia']), lo cual en PHP nunca funciona como se
    // esperaba sobre un string y hacía que la rama de "IA sugiere precio
    // distinto al promedio" jamás se aplicara en la práctica. Se decodifica
    // aquí explícitamente.
    $respuesta_ia_raw = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje, $contexto_precio);
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
    $precio_final = $precio_base;
    $precio_final_min = round($stats_item['min']?? 0, 2);
    $precio_final_max = round($stats_item['max']?? 0, 2);
    $precio_base_mxn = round($stats_item['avg_mxn']?? 0, 2);
    $precio_final_mxn = $precio_base_mxn;
    $nota_final = "Basado en histórico de cotizaciones_items";

    // A2: banda estadística en vez de min/max crudos, y proporción USD/MXN
    // del histórico (la usa tanto el precio humano como el de la IA).
    list($banda_min, $banda_max) = calcularBandaPrecio($contexto_precio, $stats_item);
    $ratio_mxn = ($precio_base > 0) ? ($precio_base_mxn / $precio_base) : 1;
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

    $data = [
        "cdmess" => $item['cdmess_historico'],
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
        "ia_error" => $ia_error,
        "ia_degradado" => $ia_degradado
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

    $t_total = microtime(true);
    echo sprintf("[OK] ID %d -> %s | TOTAL:%.3fs\n", $id, $estado_final, $t_total - $t_inicio);

    } else {
        usleep(200000);
    }
}