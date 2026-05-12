<?php
include 'conexion.php';
include 'funcionesWorker.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

echo "====================================================\n";
echo " MOTOR IA MESS - OPTIMIZADO - CON APRENDIZAJE \n";
echo " Soportando: Min-Max, Promedio y Feedback \n";
echo "====================================================\n";

while (true) {

    $id = null;

    if ($conn->connect_error) {
        error_log("BD MUERTA: ". $conn->connect_error);
        sleep(5);
        continue;
    }
    
    $t_inicio = microtime(true);
    
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
    error_log("HISTORIAL ITEMS: ". json_encode($stats['items']?? []));

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
            'fecha_min' => null,
            'fecha_max' => null
        ];
    }

    $aprendizaje = obtenerAprendizajeHumano($cdmess_para_aprendizaje, $conn);
    $respuesta_ia = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje);
    //error_log("APRENDIZAJE PARA $cdmess_para_aprendizaje: ". json_encode($aprendizaje));

    $precio_base = round($stats_item['avg']?? 0, 2);
    $precio_final = $precio_base;
    $nota_final = "Basado en histórico de cotizaciones_items";
    
    // 3. APLICAR APRENDIZAJE SI EXISTE
    if (!empty($aprendizaje)) {
        if (!empty($aprendizaje['precio_sugerido'])) {
            $precio_final = $aprendizaje['precio_sugerido'];
            $nota_final = "Precio corregido por {$aprendizaje['total_correcciones']} revisiones humanas";
        } else {
            switch ($aprendizaje['categoria_principal']) {
                case 'Precio muy alto':
                    $precio_final = round($precio_base * 0.85, 2);
                    $nota_final = "Reducido 15% por reportes de 'precio muy alto'";
                    break;
                case 'Precio muy bajo':
                    $precio_final = round($precio_base * 1.15, 2);
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
        // Si no hay aprendizaje, pero la IA sugirió un precio diferente al promedio, lo anotamos también
        if (isset($respuesta_ia['precio_ia']) && $respuesta_ia['precio_ia']!= $precio_base) {
            $precio_final = $respuesta_ia['precio_ia'];
            $nota_final = "Precio sugerido por IA basado en análisis de datos";
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
            "Calculado con %d cotizaciones%s | Min: $%.2f | Max: $%.2f | Promedio: $%.2f",
            $stats_item['total'],
            $fecha_rango,
            $stats_item['min'],
            $stats_item['max'],
            $stats_item['avg']
        );
        
        if (!empty($aprendizaje) && $aprendizaje['total_correcciones'] > 0) {
            $detalle_calculo.= " | Ajustado por {$aprendizaje['total_correcciones']} revisiones humanas";
        }
    }

    $data = [
        "cdmess" => $item['cdmess_historico'],
        "desc" => $item['descripcion_historica']?? $entrada,
        "detalle_calculo" => $detalle_calculo,
        "precio_min" => round($stats_item['min']?? 0, 2),
        "precio_max" => round($stats_item['max']?? 0, 2),
        "precio_promedio" => $precio_base,
        "precio_ia" => $precio_final,
        "notas" => $nota_final,
        "coincidencias" => $stats['alternativas']?? '',
        "aprendizaje_aplicado" =>!empty($aprendizaje),
        "num_correcciones" => $aprendizaje['total_correcciones']?? 0
    ];

        $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);
    $estado_final = 'completado';

    }
      
    catch (Exception $e) {
            // Si algo truena, no dejes el registro en 'procesando'
            error_log("ERROR procesando ID $id: ". $e->getMessage());
            $json_final = json_encode([
                "error" => true,
                "mensaje" => "Error al procesar: ". $e->getMessage(),
                "cdmess" => $item['cdmess_historico']?? 'N/A'
            ]);
            $estado_final = 'error';
        }
        // AQUÍ TERMINA EL TRY/CATCH

}


    if ($id!== null) {
        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia =?, estatus =?, fecha_registro = NOW() WHERE id =?");
        $stmt->bind_param("ssi", $json_final, $estado_final, $id);
        $stmt->execute();
        
        $t_total = microtime(true);
        echo sprintf("[OK] ID %d -> %s | TOTAL:%.3fs\n", $id, $estado_final, $t_total - $t_inicio);
        
    } else {
        usleep(200000);
    }
}