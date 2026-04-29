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
echo "   MOTOR IA MESS         \n";
echo "   Soportando: Min-Max, Promedio y Coincidencias    \n";
echo "====================================================\n";

while (true) {
    $t_inicio = microtime(true);
    
    // CAMBIO 1: Agregamos las nuevas columnas al SELECT
    $res = $conn->query("SELECT id, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica 
                         FROM cola_procesamiento 
                         WHERE estatus = 'pendiente' 
                         LIMIT 1");

    if ($res && $res->num_rows > 0) {
        $item = $res->fetch_assoc();
        $id = $item['id'];
        $entrada = trim($item['entrada_usuario']);
        
        $conn->query("UPDATE cola_procesamiento SET estatus = 'procesando' WHERE id = $id");

        // CAMBIO 2: Lógica de selección de historial
        $t_mess = microtime(true);
        if (!empty($item['cdmess_historico'])) {
            // Si tiene clave histórica, busca SOLO esa clave
            $stats = obtenerHistorialMESS($item['cdmess_historico']);
        } else {
            // Si no tiene clave (es nuevo), busca por descripción normal
            $stats = obtenerHistorialMESS($entrada);
        }
        $t_mess_fin = microtime(true);

        // Obtener aprendizaje humano si existe
        $t_aprendizaje = microtime(true);
        $aprendizaje = obtenerAprendizajeHumano($entrada, $conn);
        $t_aprendizaje_fin = microtime(true);
        
        // SIN OLLAMA: Armamos el JSON directo con PHP - 0.0001s
        
        $respuesta_ia = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje);
        /*
        $data = [
            "cdmess" => (!empty($item['cdmess_historico'])) ? $item['cdmess_historico'] : ($stats['cdmess'] ?? 'N/A'),
            "desc" => $item['descripcion_historica'] ?? $entrada,
            "precio_min" => $stats['min'] ?? 0,
            "precio_max" => $stats['max'] ?? 0,
            "precio_promedio" => $stats['avg'] ?? 0,
            "precio_ia" => $stats['avg'] ?? 0,
            "notas" => "Recuperado automáticamente por el sistema.",
            "coincidencias" => $stats['alternativas'] ?? []
        ];
*/
        // Si hay aprendizaje humano, sobreescribe con eso
        if (!empty($aprendizaje)) {
            if (!empty($aprendizaje['cdmess_correcto'])) {
                $data['cdmess'] = $aprendizaje['cdmess_correcto'];
            }
            if (!empty($aprendizaje['nota'])) {
                $data['notas'] = $aprendizaje['nota'];
            }
            if (!empty($aprendizaje['precio_sugerido'])) {
                $data['precio_ia'] = $aprendizaje['precio_sugerido'];
            }
        }

        // CAMBIO 3: Seguro de vida para el CDMESS
        // Si la IA intentó cambiar la clave, la regresamos a la que ya teníamos en la tabla
        if (!empty($item['cdmess_historico']) && $data['cdmess'] !== $item['cdmess_historico']) {
            $data['cdmess'] = $item['cdmess_historico'];
        }

        $json_final = json_encode($data, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia = ?, estatus = 'completado', fecha_registro = NOW() WHERE id = ?");
        $stmt->bind_param("si", $json_final, $id);
        $stmt->execute();
        
        $t_total = microtime(true);
        echo sprintf(
            "[OK] ID %d | MESS:%.3fs Aprendizaje:%.3fs TOTAL:%.3fs\n", 
            $id, 
            $t_mess_fin - $t_mess, 
            $t_aprendizaje_fin - $t_aprendizaje,
            $t_total - $t_inicio
        );
        
    } else {
        // Sin pendientes: duerme 0.2s para no quemar CPU
        usleep(200000);
    }
}