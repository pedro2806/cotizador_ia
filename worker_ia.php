<?php
//C:\wamp64\bin\php\php8.3.14\php.exe C:\wamp64\www\cotizador_ia\worker_ia.php

/*
include 'conexion.php';
include 'funcionesWorker.php';

set_time_limit(0);

echo "====================================================\n";
echo "   MOTOR IA MESS - OPTIMIZADO - APRENDIENDO         \n";
echo "   Soportando: Min-Max, Promedio y Coincidencias    \n";
echo "====================================================\n";

while (true) {
    // CAMBIO 1: Agregamos las nuevas columnas al SELECT
    $res = $conn->query("SELECT id, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica FROM cola_procesamiento WHERE estatus = 'pendiente' LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $item = $res->fetch_assoc();
        $id = $item['id'];
        $entrada = trim($item['entrada_usuario']);
        
        $conn->query("UPDATE cola_procesamiento SET estatus = 'procesando' WHERE id = $id");

        // CAMBIO 2: Lógica de selección de historial (Para respetar la división)
        if (!empty($item['cdmess_historico'])) {
            // Si tiene clave histórica, forzamos a obtenerHistorialMESS a buscar SOLO esa clave
            $stats = obtenerHistorialMESS($item['cdmess_historico']);
        } else {
            // Si no tiene clave (es nuevo), busca por descripción normal
            $stats = obtenerHistorialMESS($entrada);
        }

        $aprendizaje = obtenerAprendizajeHumano($entrada, $conn);
        
        $respuesta_ia = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje);

        if (preg_match('/\{.*\}/s', $respuesta_ia, $matches)) {
            $data = json_decode($matches[0], true);
            
            // CAMBIO 3: Seguro de vida para el CDMESS
            // Si la IA intentó cambiar la clave, la regresamos a la que ya teníamos en la tabla
            if (!empty($item['cdmess_historico']) && ($data['cdmess'] ?? '') !== $item['cdmess_historico']) {
                $data['cdmess'] = $item['cdmess_historico'];
            }

            if (!isset($data['precio_min'])) $data['precio_min'] = $stats['min'];
            if (!isset($data['precio_max'])) $data['precio_max'] = $stats['max'];
            if (!isset($data['precio_promedio'])) $data['precio_promedio'] = $stats['avg'];
            if (empty($data['coincidencias'])) $data['coincidencias'] = $stats['alternativas'];
            if (empty($data['cdmess']) || $data['cdmess'] == "N/A") $data['cdmess'] = $stats['cdmess'];

            $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $json_final = json_encode([
                "cdmess" => (!empty($item['cdmess_historico'])) ? $item['cdmess_historico'] : $stats['cdmess'],
                "desc" => $entrada,
                "precio_min" => $stats['min'],
                "precio_max" => $stats['max'],
                "precio_promedio" => $stats['avg'],
                "precio_ia" => $stats['avg'],
                "notas" => "Recuperado automáticamente por el sistema.",
                "coincidencias" => $stats['alternativas']
            ]);
        }

        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia = ?, estatus = 'completado' WHERE id = ?");
        $stmt->bind_param("si", $json_final, $id);
        $stmt->execute();
        echo "[OK] Procesado ID $id\n";
    } else {
      usleep(500000);  
    //sleep(3);
    }
}

*/

//C:\wamp64\bin\php\php8.3.14\php.exe C:\wamp64\www\cotizador_ia\worker_ia.php
include 'conexion.php';
include 'funcionesWorker.php';

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

echo "====================================================\n";
echo "   MOTOR IA MESS - OPTIMIZADO - CON APRENDIZAJE    \n";
echo "   Soportando: Min-Max, Promedio y Feedback        \n";
echo "====================================================\n";

while (true) {
    if ($conn->connect_error) {
        error_log("BD MUERTA: " . $conn->connect_error);
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
    $stats = obtenerHistorialMESS($item['cdmess_historico'] ?: $entrada);
    
    // 2. CLAVE: Usa el CDMESS que regresó el historial para buscar aprendizaje
    $cdmess_para_aprendizaje = $stats['cdmess'] ?? $item['cdmess_historico'] ?? 'N/A';
    $aprendizaje = obtenerAprendizajeHumano($cdmess_para_aprendizaje, $conn);
    
    error_log("APRENDIZAJE PARA $cdmess_para_aprendizaje: " . json_encode($aprendizaje));

    $precio_base = round($stats['avg'] ?? 0, 2);
    $precio_final = $precio_base;
    $nota_final = "Basado en histórico de cotizaciones_items";
    
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
                    $nota_final = "ALERTA: " . ($aprendizaje['nota_humana'] ?? 'Descripción reportada como incorrecta');
                    break;
            }
        }
        
        if ($aprendizaje['alerta_descripcion'] && $aprendizaje['categoria_principal'] != 'Descripcion incorrecta') {
            $nota_final .= " | ATENCIÓN: " . $aprendizaje['nota_humana'];
        }
    }

    $data = [
        "cdmess" => $cdmess_para_aprendizaje,
        "desc" => $item['descripcion_historica'] ?? $entrada,
        "precio_min" => round($stats['min'] ?? 0, 2),
        "precio_max" => round($stats['max'] ?? 0, 2),
        "precio_promedio" => $precio_base,
        "precio_ia" => $precio_final,
        "notas" => $nota_final,
        "coincidencias" => $stats['alternativas'] ?? [],
        "aprendizaje_aplicado" => !empty($aprendizaje),
        "num_correcciones" => $aprendizaje['total_correcciones'] ?? 0
    ];

    $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);
    $estado_final = 'completado';

} catch (Exception $e) {
    error_log("ERROR procesando ID $id: " . $e->getMessage());
    $json_final = json_encode([
        "error" => true,
        "mensaje" => "Error al procesar: " . $e->getMessage(),
        "cdmess" => $item['cdmess_historico'] ?? 'N/A'
    ]);
    $estado_final = 'error';
}

catch (Exception $e) {
            // Si algo truena, no dejes el registro en 'procesando'
            error_log("ERROR procesando ID $id: " . $e->getMessage());
            $json_final = json_encode([
                "error" => true,
                "mensaje" => "Error al procesar: " . $e->getMessage(),
                "cdmess" => $item['cdmess_historico'] ?? 'N/A'
            ]);
            $estado_final = 'error';
        }
        // AQUÍ TERMINA EL TRY/CATCH

        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia = ?, estatus = ?, fecha_registro = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $json_final, $estado_final, $id);
        $stmt->execute();
        
        $t_total = microtime(true);
        echo sprintf("[OK] ID %d -> %s | TOTAL:%.3fs\n", $id, $estado_final, $t_total - $t_inicio);
        
    } else {
        usleep(200000);
    }
}