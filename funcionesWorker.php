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
    
    $conn->set_charset("utf8mb4");
    
    $busqueda_original = trim($busqueda);
    
    $busca_servicio = (stripos($busqueda, 'servicio')!== false || 
                       stripos($busqueda, 'calibracion')!== false ||
                       stripos($busqueda, 'calibración')!== false ||
                       stripos($busqueda, 'mantenimiento')!== false ||
                       preg_match('/^[SL]\d+/i', $busqueda));
                       
    
    $tipo_val = $busca_servicio? 'SERVICIO' : 'EQUIPO';
    
    if ($busca_servicio) {
        $frases = [
            'servicio de calibracion',
            'servicio de calibración',
            'calibracion de',
            'calibración de',
            'revision de',
            'revisión de',
            'diagnostico de',
            'diagnóstico de',
            'instalacion de',
            'instalación de',
            'capacitacion de',
            'capacitación de',
            'servicio de',
            'servicio'
        ];
        $busqueda = str_ireplace($frases, '', $busqueda);
        $busqueda = preg_replace('/\s+/', ' ', trim($busqueda));
        if (empty($busqueda)) $busqueda = $busqueda_original;
    }
    
    $terminoCDMESS = "%". str_replace(' ', '%', trim($busqueda)). "%";
    $terminoDESCRIPCION = '%'. $busqueda. '%'; 

    // Traemos los últimos 10 para tener un buen rango y alternativas
    $sql = "SELECT
            ci.CDMESS,
            MAX(ci.DESCRIPCION) as DESCRIPCION,
            ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_VENTA,
            ROUND(MIN(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MIN,
            ROUND(MAX(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MAX,
            COUNT(*) as TOTAL_VECES
            FROM cotizaciones_items ci 
            WHERE (ci.DESCRIPCION LIKE? OR ci.CDMESS LIKE?)
            AND ci.TIPO =?
            AND ci.PRECIO_VENTA > 0
            AND ci.CANT > 0 
            GROUP BY ci.CDMESS
            ORDER BY TOTAL_VECES DESC, ci.CDMESS
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $terminoDESCRIPCION, $terminoCDMESS, $tipo_val);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $resultados = [];
    $alternativas = [];
    $detalle_ia = "";

    while ($row = $res->fetch_assoc()) {
        $resultados[] = [
            'cdmess' => $row['CDMESS'],
            'descripcion' => $row['DESCRIPCION'],
            'avg' => (float)$row['PRECIO_VENTA'],
            'min' => (float)$row['PRECIO_MIN'],
            'max' => (float)$row['PRECIO_MAX'],
            'total' => (int)$row['TOTAL_VECES']
        ];

        $item_str = "[". $row['CDMESS']. "] ". $row['DESCRIPCION'];
        $alternativas[] = $item_str;
        $detalle_ia.= "- $item_str: $". $row['PRECIO_VENTA']. " (min: $". $row['PRECIO_MIN']. ", max: $". $row['PRECIO_MAX']. ", veces: ". $row['TOTAL_VECES']. ")\n";
    }

    if (empty($resultados)) {
        return [
            'min'=>0, 
            'max'=>0, 
            'avg'=>0, 
            'cdmess'=>'S/C', 
            'detalle'=>'Sin historial', 
            'alternativas'=>'',
            'items'=>[]
        ];
    }

    // El primero es el principal
    $principal = $resultados[0];
    
    // Coincidencias: todas menos la primera
    $coincidencias_str = implode(", ", array_slice($alternativas, 1, 4));

    return [
        'min' => $principal['min'],
        'max' => $principal['max'],
        'avg' => $principal['avg'],
        'cdmess' => $principal['cdmess'],
        'detalle' => $detalle_ia,
        'alternativas' => $coincidencias_str,
        'items' => $resultados // CAMBIO: Ahora regresa todos los items con sus precios
    ];
}









//Nueva funcion obtenerhistorialmess optimizada.
/*
function obtenerHistorialMESS($busqueda) {
    global $conn;
    
    $busca_servicio = (stripos($busqueda, 'servicio') !== false || 
                       stripos($busqueda, 'calibracion') !== false ||
                       stripos($busqueda, 'mantenimiento') !== false ||
                       preg_match('/^S\d+/i', $busqueda));
    
    $tipo_val = $busca_servicio ? 'SERVICIO' : 'EQUIPO';
    
    $termino = iconv('UTF-8', 'ASCII//TRANSLIT', $busqueda);
    $termino = preg_replace('/[^a-zA-Z0-9\s]/', '', $termino);
    $termino = trim($termino) . '*';
    
    // Subquery para traer solo el id_item más reciente por cada CDmess + DESCRIPCION
    $stmt = $conn->prepare("
        SELECT 
            ci.CDmess, 
            ci.DESCRIPCION, 
            ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_VENTA
        FROM cotizaciones_items ci
        INNER JOIN (
            SELECT CDmess, DESCRIPCION, MAX(id_item) as max_id
            FROM cotizaciones_items
            WHERE MATCH(DESCRIPCION) AGAINST(? IN BOOLEAN MODE)
              AND TIPO = ?
              AND PRECIO_VENTA > 0 
              AND CANT > 0
            GROUP BY CDmess, DESCRIPCION
        ) ultimos ON ci.id_item = ultimos.max_id
        GROUP BY ci.CDmess, ci.DESCRIPCION
        LIMIT 10
    ");
    $stmt->bind_param("ss", $termino, $tipo_val);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    
    if (empty($rows)) {
        return ['min' => 0, 'max' => 0, 'avg' => 0, 'cdmess' => 'N/A', 'alternativas' => []];
    }
    
    $precios = array_column($rows, 'PRECIO_VENTA');
    return [
        'min' => round(min($precios), 2),
        'max' => round(max($precios), 2),
        'avg' => round(array_sum($precios) / count($precios), 2),
        'cdmess' => $rows[0]['CDmess'],
        'alternativas' => array_slice(array_column($rows, 'DESCRIPCION'), 0, 3)
    ];
}
*/
/**
 * Función para hablar con la IA
 */
function preguntarOllamaConPrecios($stats, $consulta_usuario, $aprendizaje = "") {
    $url = "http://localhost:11434/api/generate";
    
    // 1. Contexto MÍNIMO. No le mandes $stats['detalle'] si trae 30 líneas
    $contexto = "CDMESS:{$stats['cdmess']} MIN:{$stats['min']} MAX:{$stats['max']} AVG:{$stats['avg']} N:{$stats['alternativas']}";

    // 2. Prompt corto. 150 tokens vs 800 tokens
    $prompt = "Eres experto precios MESS. $contexto\n";
    

    // Solo agrega aprendizaje si existe. Cada línea extra = +0.3s
if (!empty($aprendizaje)) {
    // Asegura que sea string antes de concatenar
    if (is_array($aprendizaje)) {
        // Filtra valores vacíos y asegura que sean strings
        $aprendizaje = array_filter(array_slice($aprendizaje, 0, 3), function($v) {
            return !is_array($v) && !is_object($v) && $v !== '' && $v !== null;
        });
        $aprendizaje = implode(". ", $aprendizaje); // Max 3 reglas
    } elseif (is_object($aprendizaje)) {
        $aprendizaje = '';
    } else {
        $aprendizaje = (string)$aprendizaje;
    }

    $aprendizaje = substr(trim($aprendizaje), 0, 200); // Max 200 chars
    
    if ($aprendizaje !== '') {
        $prompt .= "REGLA HUMANA: $aprendizaje\n";
        $prompt .= "Prioridad: Si dice 'alto' baja precio. Si 'bajo' sube precio. Ignora AVG si contradice.\n";
    }
}



    $prompt .= "Usuario: '$consulta_usuario'\n";
    $prompt .= "Responde SOLO JSON: {\"cdmess\":\"{$stats['cdmess']}\",\"desc\":\"texto breve\",\"precio_ia\":0.0,\"notas\":\"justificación corta\"}";

    $data = [
        "model" => "llama3.2:1b",
        "format" => "json",
        "system" => "Devuelve JSON puro. Precio_ia ajustado por REGLA HUMANA.",
        "prompt" => $prompt,
        "stream" => false,
        "keep_alive" => "24h", // <-- CLAVE: no descarga el modelo cada vez
        "options" => [
            "temperature" => 0,
            "num_predict" => 20, // <-- CLAVE: corta a 60 tokens max
            "top_k" => 1,
            "num_ctx" => 256
           //"top_p" => 0.9
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // No esperes más de 10s
    
    $res = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode != 200) {
        error_log("Ollama HTTP $httpcode");
        return json_encode(["cdmess" => $stats['cdmess'], "precio_ia" => $stats['avg'], "notas" => "Error IA"]);
    }
    
    $datos_res = json_decode($res, true);
    return $datos_res['response'] ?? json_encode(["precio_ia" => $stats['avg']]);
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
 *//*
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
    */


/**
 * Obtiene aprendizaje humano previo y lo regresa estructurado para aplicar al precio
 */
function obtenerAprendizajeHumano($entrada, $conn) {
    $busqueda = $conn->real_escape_string($entrada);
    $es_cdmess = preg_match('/^[SL]\d+/i', $entrada);

    
    
    // Prioridad 1: Si es CDMESS, busca exacto por cdmess_historico
    if ($es_cdmess) {
        $sql = "SELECT 
                    entrada_usuario, 
                    precio_usuario, 
                    categoria_rechazo,
                    cdmess_historico
                FROM cola_procesamiento
                WHERE estatus = 'completado'
                  AND cdmess_historico = '$busqueda'
                  AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL
                  AND categoria_rechazo != 'Acepta precio IA'
                ORDER BY id DESC 
                LIMIT 5"; // Trae los últimos 5 feedbacks
    } else {
        // Prioridad 2: Si es descripción, busca por LIKE pero solo los últimos 2
        $sql = "SELECT 
                    entrada_usuario, 
                    precio_usuario, 
                    categoria_rechazo,
                    cdmess_historico
                FROM cola_procesamiento
                WHERE estatus = 'completado'
                  AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL
                  AND categoria_rechazo != 'Acepta precio IA'
                  AND entrada_usuario LIKE '%$busqueda%'
                ORDER BY id DESC 
                LIMIT 2";
    }
    
    $res = $conn->query($sql);
    
    if (!$res || $res->num_rows == 0) {
        return []; // No hay aprendizaje
    }
    
    $aprendizaje = [
        'total_correcciones' => $res->num_rows,
        'precios_usuario' => [],
        'categoria_principal' => null,
        'nota_humana' => null,
        'alerta_descripcion' => false,
        'ejemplos_texto' => "\n--- APRENDIZAJE DE EXPERTOS MESS (Prioridad Alta) ---\n"
    ];
    
    $categorias = [];
    
    while ($row = $res->fetch_assoc()) {
        // Guarda precios para sacar promedio
        if ($row['precio_usuario'] > 0) {
            $aprendizaje['precios_usuario'][] = $row['precio_usuario'];
        }
        
        // Cuenta qué categoría se repite más
        $cat = $row['categoria_rechazo'];
        $categorias[$cat] = ($categorias[$cat] ?? 0) + 1;
        
        // Si hay "Descripcion incorrecta", marca alerta
        if ($cat == 'Descripcion incorrecta') {
            $aprendizaje['alerta_descripcion'] = true;
            $aprendizaje['nota_humana'] = $row['respuesta'] ?? 'Descripción incorrecta reportada';
        }
        
        // Arma el texto para debug/logs
        $aprendizaje['ejemplos_texto'] .= "Usuario buscó: " . $row['entrada_usuario'] . "\n";
        $aprendizaje['ejemplos_texto'] .= "Precio corregido: $" . ($row['precio_usuario'] ?? 'N/A') . "\n";
        $aprendizaje['ejemplos_texto'] .= "Nota: " . ($row['respuesta'] ?? 'Sin comentarios') . "\n";
        $aprendizaje['ejemplos_texto'] .= "Categoría: " . $cat . "\n\n";
    }
    
    // Determina la categoría más común
    arsort($categorias);
    $aprendizaje['categoria_principal'] = key($categorias);
    
    // Si hay precios de usuario, calcula el promedio
    if (!empty($aprendizaje['precios_usuario'])) {
        $aprendizaje['precio_sugerido'] = round(array_sum($aprendizaje['precios_usuario']) / count($aprendizaje['precios_usuario']), 2);
    }
    
    return $aprendizaje;
}

/**
 * Busca opciones únicas basadas en CDMESS para evitar duplicados en la cola
 */
function obtenerOpcionesUnicasHistoricas($busqueda, $conn) {



// 1. Detecta si es servicio
    $busca_servicio = (stripos($busqueda, 'servicio') !== false || 
                       stripos($busqueda, 'calibracion') !== false || 
                       stripos($busqueda, 'calibración') !== false ||
                       stripos($busqueda, 'mantenimiento') !== false);

    $tipo_val = $busca_servicio ? 'SERVICIO' : 'EQUIPO';

    // 2. Si es servicio, quita las frases y deja solo el equipo
    if ($busca_servicio) {
        $frases = [
            'servicio de calibracion',
            'servicio de calibración', 
            'calibracion de',
            'calibración de',
            'mantenimiento de',
            'servicio de mantenimiento',
            'servicio de medicion',
            'servicio de medición',
            'medicion de',
            'medición de',
            'servicio de'
        ];
    
        // quita sin importar mayúsculas
        $busqueda = str_ireplace($frases, '', $busqueda);
        
        // quita palabras sueltas que quedaron
        $busqueda = preg_replace('/\b(servicio|calibracion|calibración|mantenimiento|medicion|medición)\b/iu', '', $busqueda);
        
        // limpia espacios dobles
        $busqueda = preg_replace('/\s+/', ' ', trim($busqueda));
    }
    $termino = "%" . $busqueda . "%";
    error_log("Búsqueda procesada para opciones únicas: '$busqueda' con tipo '$tipo_val', termino: '$termino'");
    
    // TRUNCATE o TRIM para asegurar que 'P27-59 ' sea igual a 'P27-59'
    $es_cdmess = preg_match('/^[SL]\d+/i', $busqueda);
   
    $esvalido = validaCDMESS($busqueda, $conn); // Valida si el CDMESS existe y es válido antes de hacer la consulta principal
    //echo "¿Es CDMESS? " . ($es_cdmess ? "Sí" : "No") . " | ¿Es válido? " . ($esvalido ? "Sí" : "No") . "<br>";
    if($es_cdmess){
        if(!$esvalido){
            error_log("CDMESS '$busqueda' no es válido según tarifario activo.");
            return $stmt ='noValido';
        }
        else{
            $sql = "SELECT 
                    TRIM(CDMESS) as CDMESS, 
                    MAX(DESCRIPCION) as descripcion, 
                    ROUND(AVG(PRECIO_VENTA/CANT), 2) as precio_promedio                
                FROM cotizaciones_items 
                WHERE (MATCH(DESCRIPCION) AGAINST(? IN BOOLEAN MODE) OR CDMESS LIKE ?)
                    AND PRECIO_VENTA > 0 AND CANT > 0
                    AND CDMESS IS NOT NULL AND CDMESS != ''
                GROUP BY TRIM(CDMESS) 
                ORDER BY COUNT(*) DESC 
                LIMIT 5";

                //echo "Consulta SQL para CDMESS: $sql con termino '$termino' y tipo '$tipo_val'";
    
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $termino, $termino);
                $stmt->execute();
                return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
    else{
        $sql = "SELECT 
                TRIM(ci.CDMESS) as CDMESS, 
                MAX(ci.DESCRIPCION) as descripcion, 
                ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) as precio_promedio,
                t.STATUS
            FROM cotizaciones_items ci
            LEFT JOIN tarifario t ON ci.CDMESS = t.CDMESS 
            WHERE (MATCH(ci.DESCRIPCION) AGAINST(? IN BOOLEAN MODE) OR ci.CDMESS LIKE ? OR ci.MARCA LIKE ? OR ci.MODELO LIKE ? OR ci.SERIE LIKE ?)
                AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0
                AND ci.CDMESS IS NOT NULL AND ci.CDMESS != ''
                AND ci.TIPO != ?
                AND t.STATUS = 'ACTIVE'
            GROUP BY TRIM(ci.CDMESS)
            ORDER BY COUNT(*) DESC 
            LIMIT 5";    
    //echo "Consulta SQL para CDMESS: $sql con termino '$termino' y tipo '$tipo_val'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $termino, $termino, $termino, $termino, $termino, $tipo_val);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    }


            
}

function validaCDMESS($cdmess, $conn) {
        $cdmess = $conn->real_escape_string($cdmess);
        $sql = "SELECT COUNT(*) as count FROM tarifario WHERE STATUS = 'ACTIVE' AND CDMESS='$cdmess'";
        $res = $conn->query($sql);
        if ($res) {
            $row = $res->fetch_assoc();
            return $row['count'] > 0;
        }
        return false;
}