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
 * Función para hablar con la IA
 */
function preguntarOllamaConPrecios($stats, $consulta_usuario, $aprendizaje = "") {
    $url = "http://localhost:11434/api/generate";
    
    // 1. Preparar el contexto del historial de MESS para la IA
    $contexto_historico = "Datos históricos de MESS para esta búsqueda:\n";
    $contexto_historico .= "- Precio Mínimo: $" . $stats['min'] . "\n";
    $contexto_historico .= "- Precio Máximo: $" . $stats['max'] . "\n";
    $contexto_historico .= "- Promedio: $" . $stats['avg'] . "\n";
    $contexto_historico .= "- Coincidencias encontradas: " . $stats['alternativas'] . "\n";

    // 2. Construir el prompt (Corregido el = por .= para no borrar el historial)
    $prompt = "Eres un experto en metrología y ventas de Grupo MESS. Tu objetivo es sugerir el mejor precio.\n\n";
    $prompt .= "DETALLE HISTÓRICO ADICIONAL:\n" . ($stats['detalle'] ?? '') . "\n\n";
    $prompt .= $contexto_historico . "\n";
    
    // Si hay aprendizaje (respuestas humanas previas), se lo damos como REGLA DE ORO.
    if (!empty($aprendizaje)) {
        $prompt .= "--- REGLAS DE ORO (CRITERIO HUMANO RECIENTE) ---\n";
        $prompt .= "Los expertos de MESS han validado estos precios recientemente para casos similares:\n";
        $prompt .= $aprendizaje . "\n";
        $prompt .= "IMPORTANTE: Si los datos de 'REGLAS DE ORO' contradicen el promedio histórico, DEBES dar prioridad al criterio humano.\n\n";
    }

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
        "model" => "llama3.1:8b", // Modelo optimizado para tareas de integración de datos 3.1:8b
        "format" => "json",
        "system" => "Eres un integrador de datos para MESS. Tu única función es devolver JSON puro con la estructura solicitada.",
        "prompt" => $prompt,
        "stream" => false,
        "options" => ["temperature" => 0.1]
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

/**
 * Obtiene el aprendizaje basado en respuestas previas de usuarios.
 */
function obtenerAprendizajeReciente($busqueda, $conn) {
    $termino = "%" . $busqueda . "%";
    // Buscamos registros completados con precio o respuesta humana
    $sql = "SELECT entrada_usuario, respuesta, precio_usuario FROM cola_procesamiento 
            WHERE estatus = 'completado' 
            AND entrada_usuario LIKE ? 
            ORDER BY fecha_creacion DESC LIMIT 3";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $termino);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $aprendizaje = "";
    while ($row = $res->fetch_assoc()) {
        $texto = $row['respuesta'] ?? "Sin comentario";
        $precio = $row['precio_usuario'] ?? "No definido";
        $aprendizaje .= "- Para: " . $row['entrada_usuario'] . " | Precio validado: $" . $precio . " | Nota: " . $texto . "\n";
    }
    return $aprendizaje;
}

/**
 * Obtiene ejemplos de precios y descripciones que un humano ya aprobó en el sistema.
 */
function obtenerAprendizajeHumano($entrada, $conn) {
    $busqueda = $conn->real_escape_string($entrada);
    // Buscamos los últimos 2 casos similares completados donde hubo intervención humana
    $sql = "SELECT entrada_usuario, respuesta, precio_usuario FROM cola_procesamiento 
            WHERE estatus = 'completado' 
            AND (respuesta IS NOT NULL OR precio_usuario > 0)
            AND entrada_usuario LIKE '%$busqueda%' 
            ORDER BY id DESC LIMIT 2";
    
    $res = $conn->query($sql);
    $ejemplos = "";
    
    if ($res && $res->num_rows > 0) {
        $ejemplos = "\n--- APRENDIZAJE DE EXPERTOS MESS (Prioridad Alta) ---\n";
        while ($row = $res->fetch_assoc()) {
            $ejemplos .= "Usuario buscó: " . $row['entrada_usuario'] . "\n";
            $ejemplos .= "Precio aprobado: $" . ($row['precio_usuario'] ?? 'N/A') . "\n";
            $ejemplos .= "Nota humana: " . ($row['respuesta'] ?? 'Sin comentarios') . "\n\n";
        }
    }
    return $ejemplos;
}