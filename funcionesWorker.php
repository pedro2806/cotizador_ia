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
    $sql = "SELECT CDMESS, DESCRIPCION, (PRECIO_VENTA/CANT) AS PRECIO_VENTA, CANT 
            FROM cotizaciones_items 
            /*WHERE (DESCRIPCION LIKE ? OR CDMESS LIKE ?) AND PRECIO_VENTA > 0 OR CANT > 0 AND PRECIO_VENTA IS NOT NULL*/
            WHERE (DESCRIPCION LIKE ? OR CDMESS LIKE ?) AND PRECIO_VENTA > 0 AND CANT > 0
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
        $prompt .= "Los expertos de MESS han validado estos precios para casos similares:\n";
        $prompt .= $aprendizaje . "\n";
        $prompt .= "INSTRUCCIONES DE AJUSTE:\n";        
        $prompt .= "1. Si el aprendizaje dice 'Precio muy alto', tu nuevo 'precio_ia' DEBE ser menor al precio validado anteriormente.\n";
        $prompt .= "2. IGNORA el promedio histórico si este contradice el precio validado por el humano.\n";
        $prompt .= "3. El valor de 'precio_ia' que generes debe reflejar esta corrección AHORA.\n\n";
        $prompt .= "IMPORTANTE: Si los datos de 'REGLAS DE ORO' contradicen el promedio histórico, DEBES dar prioridad al criterio humano.\n\n";
    }

    $prompt .= "SOLICITUD: '$consulta_usuario'\n";    
    $prompt .= "REGLA: Genera un JSON con TODOS los campos siguientes. Si no hay datos, usa los valores por defecto proporcionados.\n\n";
    
    $prompt .= "FORMATO JSON OBLIGATORIO:\n";
    $prompt .= "{\n";
    $prompt .= "  \"cdmess\": \"{$stats['cdmess']}\",\n";
    $prompt .= "  \"desc\": \"Descripción técnica breve\",\n";
    $prompt .= "  \"precio_min\": {$stats['min']},\n"; // Corregido: antes era $p_min
    $prompt .= "  \"precio_max\": {$stats['max']},\n"; // Corregido: antes era $p_max
    $prompt .= "  \"precio_promedio\": {$stats['avg']},\n"; // Corregido: antes era $p_avg
    $prompt .= "  \"precio_ia\": {$stats['avg']},\n"; // Usamos el promedio como base para que la IA lo ajuste
    $prompt .= "  \"notas\": \"Justificación del precio sugerido\",\n";
    $prompt .= "  \"coincidencias\": \"{$stats['alternativas']}\"\n";
    $prompt .= "}";

    $data = [
        "model" => "llama3.1:8b", // Modelo optimizado para tareas de integración de datos 3.1:8b
        "format" => "json",
        //"system" => "Eres un integrador de datos para MESS. Tu única función es devolver JSON puro con la estructura solicitada.",
        "system" => "Eres un integrador de precios para MESS. Tu prioridad número 1 es el APRENDIZAJE HUMANO. Si recibes una retroalimentación de 'Precio muy alto' o 'Precio muy bajo', ajusta el valor de 'precio_ia' inmediatamente ignorando los promedios antiguos. Tu única función es devolver JSON puro con la estructura solicitada.",
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
    $sql = "SELECT entrada_usuario, respuesta, precio_usuario, categoria_rechazo
            FROM cola_procesamiento 
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
            $ejemplos .= "Nota humana: " . ($row['respuesta'] ?? 'Sin comentarios') . "\n";
            $ejemplos .= "Categoría de rechazo: " . ($row['categoria_rechazo'] ?? 'No especificada') . "\n\n";
        }
    }
    return $ejemplos;
}

/**
 * Busca opciones únicas basadas en CDMESS para evitar duplicados en la cola
 */
function obtenerOpcionesUnicasHistoricas($busqueda, $conn) {
    $termino = "%" . $busqueda . "%";
    
    // TRUNCATE o TRIM para asegurar que 'P27-59 ' sea igual a 'P27-59'
    $sql = "SELECT 
                TRIM(CDMESS) as CDMESS, 
                MAX(DESCRIPCION) as descripcion, 
                AVG(PRECIO_VENTA / CANT) as precio_promedio
            FROM cotizaciones_items 
            WHERE (DESCRIPCION LIKE ? OR CDMESS LIKE ?)
                AND PRECIO_VENTA > 0 AND CANT > 0
                AND CDMESS IS NOT NULL AND CDMESS != ''
            GROUP BY TRIM(CDMESS) 
            ORDER BY COUNT(*) DESC 
            LIMIT 5";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $termino, $termino);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}