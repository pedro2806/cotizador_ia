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
    $res = $conn->query("SELECT id, id_proyecto, entrada_usuario, cdmess_historico, precio_historico, descripcion_historica, es_sugerencia FROM cola_procesamiento WHERE estatus = 'pendiente' LIMIT 1");

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

            // Si la IA devolvió precio_ia=0 pero hay aprendizaje humano previo,
            // extraemos ese precio directamente para no perder la validación del experto.
            if (($data['precio_ia'] ?? 0) == 0 && !empty($aprendizaje)) {
                if (preg_match('/Precio aprobado: \$(\d+(?:\.\d+)?)/', $aprendizaje, $pm)) {
                    $precio_humano = floatval($pm[1]);
                    if ($precio_humano > 0) $data['precio_ia'] = $precio_humano;
                }
            }

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

        // Solo los ítems originales generan sugerencias; las sugerencias no producen más sugerencias
        $decoded_final = json_decode($json_final, true);
        if ((int)$item['es_sugerencia'] === 0 && !empty($decoded_final['coincidencias'])) {
            preg_match_all('/\[([^\]]+)\]/', $decoded_final['coincidencias'], $m);
            foreach ($m[1] as $cdmess_sug) {
                $cdmess_sug = trim($cdmess_sug);
                $chk = $conn->prepare("SELECT id FROM cola_procesamiento WHERE id_proyecto = ? AND cdmess_historico = ? LIMIT 1");
                $chk->bind_param("ss", $item['id_proyecto'], $cdmess_sug);
                $chk->execute();
                $existe = $chk->get_result()->num_rows;
                $chk->close();
                if ($existe === 0) {
                    $ins = $conn->prepare("INSERT INTO cola_procesamiento (id_proyecto, entrada_usuario, cdmess_historico, estatus, es_sugerencia) VALUES (?, ?, ?, 'pendiente', 1)");
                    $ins->bind_param("sss", $item['id_proyecto'], $cdmess_sug, $cdmess_sug);
                    $ins->execute();
                    $ins->close();
                    echo "  [+] Sugerencia insertada: $cdmess_sug\n";
                }
            }
        }
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

    $id = null;

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
    $respuesta_ia = preguntarOllamaConPrecios($stats, $entrada, $aprendizaje);
    //error_log("APRENDIZAJE PARA $cdmess_para_aprendizaje: " . json_encode($aprendizaje));

    $precio_base = round($stats['avg'] ?? 0, 2);
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
                    $nota_final = "ALERTA: " . ($aprendizaje['nota_humana'] ?? 'Descripción reportada como incorrecta');
                    break;
            }
        }
        
        if ($aprendizaje['alerta_descripcion'] && $aprendizaje['categoria_principal'] != 'Descripcion incorrecta') {
            $nota_final .= " | ATENCIÓN: " . $aprendizaje['nota_humana'];
        }
    }
    else {
        // Si no hay aprendizaje, pero la IA sugirió un precio diferente al promedio, lo anotamos también
        if (isset($respuesta_ia['precio_ia']) && $respuesta_ia['precio_ia'] != $precio_base) {
            $precio_final = $respuesta_ia['precio_ia'];
            $nota_final = "Precio sugerido por IA basado en análisis de datos";
        }
    }

    $data = [
        "cdmess" => $cdmess_para_aprendizaje ?? $respuesta_ia['cdmess'] ?? 'N/A',
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

    }


    if ($id !== null) {
        $stmt = $conn->prepare("UPDATE cola_procesamiento SET propuesta_ia = ?, estatus = ?, fecha_registro = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $json_final, $estado_final, $id);
        $stmt->execute();
        
        $t_total = microtime(true);
        echo sprintf("[OK] ID %d -> %s | TOTAL:%.3fs\n", $id, $estado_final, $t_total - $t_inicio);
        
    } else {
        usleep(200000);
    }
}