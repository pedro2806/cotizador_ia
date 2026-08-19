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

    $res = $conn->query("SELECT id, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica 
                         FROM cola_procesamiento 
                         WHERE estatus = 'pendiente' 
                         LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $item = $res->fetch_assoc();
        $id = $item['id'];
        $entrada = trim($item['entrada_usuario']);
        
        $conn->query("UPDATE cola_procesamiento SET estatus = 'procesando' WHERE id = $id");
    
  
  try {

    // 1. Obtén historial - esto ya te da el CDMESS aunque busques por texto
    $stats = obtenerHistorialMESS($entrada);
    
    // DEBUG: Ve qué regresó el historial
    //error_log("HISTORIAL ITEMS: ". json_encode($stats['items']?? []));

    // Crear mapa CDMESS => datos para buscar precio específico del item actual
    $mapa_precios = [];
    if (!empty($stats['items'])) {
        foreach ($stats['items'] as $hist) {
            $mapa_precios[$hist['cdmess']] = $hist;
        }
    }

    // 2. CLAVE: Usa el CDMESS que regresó el historial para buscar aprendizaje
    $cdmess_para_aprendizaje = $stats['cdmess']?? $item['cdmess_historico']?? 'N/A';

    // Si el item actual tiene cdmess_historico y existe en el mapa, usa sus datos específicos
    $cdmess_actual = $item['cdmess_historico'];
    if (!empty($cdmess_actual) && isset($mapa_precios[$cdmess_actual])) {
        $datos_item = $mapa_precios[$cdmess_actual];
        $stats_item = [
            'min' => $datos_item['min'],
            'max' => $datos_item['max'],
            'avg' => $datos_item['avg'],
            'total' => $datos_item['total'],
            'min_mxn' => $datos_item['min_mxn'],
            'max_mxn' => $datos_item['max_mxn'],
            'avg_mxn' => $datos_item['avg_mxn'],
            'total_mxn' => $datos_item['total'], // Asumiendo que el conteo de veces es el mismo para ambos
            'fecha_min' => null,//$datos_item['fecha_min'],
            'fecha_max' => null //$datos_item['fecha_max']
        ];
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
    $folio_base   = obtenerFolioBase($cdmess_folio, $conn);

    $aprendizaje = obtenerAprendizajeHumano($cdmess_para_aprendizaje, $conn);

    // NOTA: preguntarOllamaConPrecios() devuelve un STRING con JSON.
    // Antes el código lo usaba directo como si fuera un array
    // ($respuesta_ia['precio_ia']), lo cual en PHP nunca funciona como se
    // esperaba sobre un string y hacía que la rama de "IA sugiere precio
    // distinto al promedio" jamás se aplicara en la práctica. Se decodifica
    // aquí explícitamente.
    $respuesta_ia_raw = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje);
    $respuesta_ia = json_decode($respuesta_ia_raw, true);
    if (!is_array($respuesta_ia)) {
        $respuesta_ia = [];
    }
    $veredicto_ia = trim((string) ($respuesta_ia['veredicto'] ?? ''));
    //error_log("APRENDIZAJE PARA $cdmess_para_aprendizaje: ". json_encode($aprendizaje));

    $precio_base = round($stats_item['avg']?? 0, 2);
    $precio_final = $precio_base;
    $precio_final_min = round($stats_item['min']?? 0, 2);
    $precio_final_max = round($stats_item['max']?? 0, 2);
    $precio_base_mxn = round($stats_item['avg_mxn']?? 0, 2);
    $precio_final_mxn = $precio_base_mxn;
    $nota_final = "Basado en histórico de cotizaciones_items";

    // 3. APLICAR APRENDIZAJE SI EXISTE (el criterio humano sigue teniendo
    // la última palabra sobre el precio: la IA solo "opina" en el veredicto,
    // no se le da libertad para pisar una corrección humana ya validada)
    if (!empty($aprendizaje)) {
        if (!empty($aprendizaje['precio_sugerido'])) {
            $precio_final = $aprendizaje['precio_sugerido'];
            $precio_final_mxn = round($aprendizaje['precio_sugerido_mxn']?? 0, 2);
            // El rango colapsa al precio corregido: cuando ya hay una
            // corrección humana validada no tiene sentido mostrar un rango,
            // el número ya es la decisión final.
            $precio_final_min = $precio_final;
            $precio_final_max = $precio_final;
            $nota_final = "Precio corregido por {$aprendizaje['total_correcciones']} revisiones humanas";
        } else {
            switch ($aprendizaje['categoria_principal']) {
                case 'Precio muy alto':
                    $precio_final = round($precio_base * 0.85, 2);
                    $precio_final_mxn = round($precio_base_mxn * 0.85, 2);
                    $precio_final_min = $precio_final;
                    $precio_final_max = $precio_final;
                    $nota_final = "Reducido 15% por reportes de 'precio muy alto'";
                    break;
                case 'Precio muy bajo':
                    $precio_final = round($precio_base * 1.15, 2);
                    $precio_final_mxn = round($precio_base_mxn * 1.15, 2);
                    $precio_final_min = $precio_final;
                    $precio_final_max = $precio_final;
                    $nota_final = "Aumentado 15% por reportes de 'precio muy bajo'";
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
        $precio_ia_sugerido = isset($respuesta_ia['precio_ia']) ? (float) $respuesta_ia['precio_ia'] : null;
        if ($precio_ia_sugerido !== null && $precio_ia_sugerido > 0 && abs($precio_ia_sugerido - $precio_base) > 0.01) {
            $precio_final = round($precio_ia_sugerido, 2);
            $ratio_mxn = ($precio_base > 0) ? ($precio_base_mxn / $precio_base) : 1;
            $precio_final_mxn = round($precio_final * $ratio_mxn, 2);
            $nota_final = "Precio sugerido por IA basado en análisis de datos";
        }

        // Rango propuesto por la IA. Si no lo mandó o vino inconsistente
        // (min > max, o precio_ia fuera del rango que ella misma dio), se
        // usa el histórico como respaldo en vez de mostrar algo incoherente.
        $ia_min = isset($respuesta_ia['precio_ia_min']) ? (float) $respuesta_ia['precio_ia_min'] : null;
        $ia_max = isset($respuesta_ia['precio_ia_max']) ? (float) $respuesta_ia['precio_ia_max'] : null;
        if ($ia_min !== null && $ia_max !== null && $ia_min > 0 && $ia_max >= $ia_min) {
            $precio_final_min = round(min($ia_min, $precio_final), 2);
            $precio_final_max = round(max($ia_max, $precio_final), 2);
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
        
        $detalle_calculo = sprintf(
            "Calculado con %d cotizaciones%s | Min: $%.2f usd| Max: $%.2f usd| Promedio: $%.2f usd | Min: $%.2f mxn| Max: $%.2f mxn| Promedio: $%.2f mxn",
            $stats_item['total'],
            $fecha_rango,
            $stats_item['min'],
            $stats_item['max'],
            $stats_item['avg'],
            $stats_item['min_mxn'],
            $stats_item['max_mxn'],
            $stats_item['avg_mxn']
        );
        
        if (!empty($aprendizaje) && $aprendizaje['total_correcciones'] > 0) {
            $detalle_calculo.= " | Ajustado por {$aprendizaje['total_correcciones']} revisiones humanas";
        }
    }

    // Si el modelo no devolvió veredicto (timeout, formato inesperado, etc.),
    // dejamos un texto de respaldo claro en vez de un campo vacío en el modal.
    if ($veredicto_ia === '') {
        $veredicto_ia = !empty($stats['alternativas'])
            ? "Sin veredicto detallado de la IA para este ítem. Precio calculado sobre {$stats_item['total']} cotizaciones históricas; coincidencias consideradas: {$stats['alternativas']}."
            : "Sin veredicto detallado de la IA para este ítem. Precio calculado sobre {$stats_item['total']} cotizaciones históricas, sin otras coincidencias relevantes para comparar.";
    }

    $data = [
        "cdmess" => $item['cdmess_historico'],
        "desc" => $item['descripcion_historica']?? $entrada,
        "folio" => $folio_base['folio'],
        "fecha" => $folio_base['fecha'],
        "detalle_calculo" => $detalle_calculo,
        "precio_min" => round($stats_item['min']?? 0, 2),
        "precio_max" => round($stats_item['max']?? 0, 2),
        "precio_promedio" => $precio_base,
        "precio_ia" => $precio_final,
        "precio_ia_min" => $precio_final_min,
        "precio_ia_max" => $precio_final_max,
        "precio_min_mxn" => round($stats_item['min_mxn']?? 0, 2),
        "precio_max_mxn" => round($stats_item['max_mxn']?? 0, 2),
        "precio_promedio_mxn" => $precio_base_mxn,
        "precio_ia_mxn" => $precio_final_mxn,
        "notas" => $nota_final,
        "veredicto_ia" => $veredicto_ia,
        "coincidencias" => $stats['alternativas']?? '',
        "aprendizaje_aplicado" =>!empty($aprendizaje),
        "num_correcciones" => $aprendizaje['total_correcciones']?? 0
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