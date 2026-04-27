<?php
/**
 * CONFIGURACIÓN GLOBAL DEL COTIZADOR MESS a
 */
/**
 * Función para limpiar el texto de entrada del usuario
 */
function limpiarEntrada($texto) {
    // Quitamos comas y puntos, pero dejamos el guion medio para los códigos CDMESS
    $limpio = str_replace([',', '.', ';', ':', '(', ')'], ' ', $texto);
    return trim($limpio);
}

/**
 * Obtiene el contexto histórico de la base de datos de 58MB.
 * Prioriza códigos exactos (CDMESS) antes de buscar por descripción.
 */
function obtenerHistorialMESS($busqueda) {
    global $conn;
    $termino = "%" . str_replace(' ', '%', trim($busqueda)) . "%";
    
    // Traemos los últimos 10 para tener un buen rango y alternativas
    $sql = "SELECT CDMESS, DESCRIPCION, PRECIO_VENTA FROM cotizaciones_items 
            WHERE DESCRIPCION LIKE ? OR CDMESS LIKE ? AND PRECIO_VENTA > 0
            ";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $termino, $termino);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $precios = [];
    $alternativas = [];
    $cdmess_principal = "S/C";
    $detalle_ia = "";

    while ($row = $res->fetch_assoc()) {
        $precios[] = (float)$row['PRECIO_VENTA'];
        
        if (empty($alternativas)) $cdmess_principal = $row['CDMESS'];

        $item_str = "[" . $row['CDMESS'] . "] " . $row['DESCRIPCION'];
        if (!in_array($item_str, $alternativas)) {
            $alternativas[] = $item_str;
        }
        $detalle_ia .= "- $item_str: $" . $row['PRECIO_VENTA'] . "\n";
    }

    if (empty($precios)) {
        return ['min'=>0, 'max'=>0, 'avg'=>0, 'cdmess'=>'S/C', 'detalle'=>'Sin historial', 'alternativas'=>''];
    }

    // Coincidencias: todas menos la primera
    $coincidencias_str = implode(", ", array_slice($alternativas, 1, 4));

    return [
        'min' => min($precios),
        'max' => max($precios),
        'avg' => array_sum($precios) / count($precios),
        'cdmess' => $cdmess_principal,
        'detalle' => $detalle_ia,
        'alternativas' => $coincidencias_str
    ];
}

/**
 * Función para hablar con la IA (Llama 3)
 */
function preguntarOllama($historico, $consulta) {
    $url = "http://localhost:11434/api/generate";
    
    $prompt = "Analiza el historial de precios para: '$consulta'.\n";
    $prompt .= "HISTORIAL:\n$historico\n";
    $prompt .= "TAREA: Sugiere el precio de venta óptimo en USD y justifica por qué.\n";
    $prompt .= "Responde UNICAMENTE en formato JSON con esta estructura:\n";
    $prompt .= "{ \"cdmess\": \"código\", \"descripcion\": \"resumen técnico\", \"precio_ia\": 0.00, \"notas\": \"justificación breve\" }";

    $data = [
        "model" => "llama3",
        "system" => "Eres un analista de costos en metrología. Tu respuesta debe ser exclusivamente el objeto JSON.",
        "prompt" => $prompt,
        "format" => "json", // Forzamos a Llama 3 a responder en JSON
        "stream" => false,
        "options" => ["temperature" => 0.9] // Máxima precisión numérica
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    $response = curl_exec($ch);
    $json = json_decode($response, true);
    curl_close($ch);

    return $json['response'] ?? null;
}

function preguntarOllamaConPrecios($stats, $consulta_usuario) {
    $url = "http://localhost:11434/api/generate";
    
    $p_min = number_format($stats['min'], 2, '.', '');
    $p_max = number_format($stats['max'], 2, '.', '');
    $p_avg = number_format($stats['avg'], 2, '.', '');

    $prompt = "CONTEXTO HISTORICO MESS:\n" . $stats['detalle'] . "\n\n";
    $prompt .= "SOLICITUD: '$consulta_usuario'\n";
    $prompt .= "REGLA: Genera un JSON con TODOS los campos siguientes. Si no hay datos, usa los valores por defecto proporcionados.\n\n";
    
    $prompt .= "FORMATO JSON OBLIGATORIO:\n";
    $prompt .= "{\n";
    $prompt .= "  \"cdmess\": \"{$stats['cdmess']}\",\n";
    $prompt .= "  \"desc\": \"Descripción técnica breve\",\n";
    $prompt .= "  \"precio_min\": $p_min,\n";
    $prompt .= "  \"precio_max\": $p_max,\n";
    $prompt .= "  \"precio_promedio\": $p_avg,\n";
    $prompt .= "  \"precio_ia\": $p_avg,\n";
    $prompt .= "  \"notas\": \"Justificación del precio sugerido\",\n";
    $prompt .= "  \"coincidencias\": \"{$stats['alternativas']}\"\n";
    $prompt .= "}";

    $data = [
        "model" => "llama3",
        "format" => "json",
        "system" => "Eres un integrador de datos para MESS. Tu única función es devolver JSON puro con la estructura solicitada.",
        "prompt" => $prompt,
        "stream" => false,
        "options" => ["temperature" => 0.0]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $res = curl_exec($ch);
    $datos_res = json_decode($res, true);
    $respuesta_ia = $datos_res['response'] ?? '';

    if (preg_match('/\{.*\}/s', $respuesta_ia, $matches)) {
        return $matches[0];
    }
    return $respuesta_ia;
}

function verificarWorker() {
    // Buscamos en la lista de procesos si existe php.exe con el nombre de nuestro archivo
    // 'WMIC' es más preciso para ver qué archivo está ejecutando cada proceso
    $comando = 'wmic process where "name=\'php.exe\'" get commandline';
    $output = shell_exec($comando);
    
    if (strpos($output, 'worker_ia.php') !== false) {
        return true;
    }
    return false;
}

function obtenerDetalleProyectoAJAX($id_proyecto) {
    global $conn;
    $id_proyecto = $conn->real_escape_string($id_proyecto);
    
    $sql = "SELECT id, entrada_usuario, estatus, propuesta_ia FROM cola_procesamiento 
            WHERE id_proyecto = '$id_proyecto' ORDER BY id ASC";
    $res = $conn->query($sql);
    
    $items = [];
    while ($row = $res->fetch_assoc()) {
        // Decodificamos la propuesta de la IA si existe
        $row['propuesta_ia'] = json_decode($row['propuesta_ia'], true);
        $items[] = $row;
    }
    return $items;
}