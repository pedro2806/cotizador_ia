<?php
/**
 * ========================================================================
 * CONFIGURACIÓN GLOBAL DEL COTIZADOR MESS (Business Intelligence)
 * ========================================================================
 */


/**
 * ========================================================================
 * MÓDULO 1: HELPERS Y LIMPIEZA DE DATOS
 * ========================================================================
 */

function limpiarEntrada($texto) {
    $limpio = str_replace([',', '.', ';', ':', '(', ')'], ' ', $texto);
    return trim($limpio);
}

/**
 * Centraliza la lógica para detectar si es SERVICIO o EQUIPO y limpia las cadenas
 */
function procesarContextoBusqueda($busqueda) {
    $busqueda_original = trim($busqueda);
    
    // 1. Detectamos el tipo (SERVICIO o EQUIPO)
    $busca_servicio = (stripos($busqueda_original, 'servicio') !== false || 
                       stripos($busqueda_original, 'calibracion') !== false || 
                       stripos($busqueda_original, 'calibración') !== false ||
                       stripos($busqueda_original, 'mantenimiento') !== false ||
                       preg_match('/^[SL]\d+/i', $busqueda_original));
                       
    $tipo_val = $busca_servicio ? 'SERVICIO' : 'EQUIPO';
    
    // 2. BORRAMOS EL RUIDO (Esto es lo que hace que funcione)
    $palabras_quitar = [
        'calibracion de', 'calibración de', 'calibracion ', 'calibración ',
        'servicio de', 'servicio ', 'mantenimiento de', 'mantenimiento '
    ];
    $busqueda_limpia = str_ireplace($palabras_quitar, '', $busqueda_original);
    
    // 3. Limpiamos caracteres extraños (Respetando el guion para s8-5)
    $busqueda_limpia = str_replace([',', ';', ':', '(', ')', '.'], ' ', $busqueda_limpia);
    
    // 4. Quitamos espacios en blanco sobrantes
    $busqueda_limpia = preg_replace('/\s+/', ' ', trim($busqueda_limpia));

    return [
        'busqueda_limpia' => $busqueda_limpia,
        'termino' => "%" . str_replace(' ', '%', $busqueda_limpia) . "%",
        'tipo' => $tipo_val,
        'es_cdmess' => preg_match('/^[SL]\d+/i', $busqueda_original)
    ];
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


/**
 * ========================================================================
 * MÓDULO 2: CONSULTAS A BASE DE DATOS (HISTORIAL Y APRENDIZAJE)
 * ========================================================================
 */

/**
 * Analiza el arreglo de resultados usando Ollama para seleccionar los 5 más relevantes.
 */
function filtrarOpcionesConOllama($busqueda_usuario, $resultados_sql) {
    // Si SQL arrojó 5 o menos resultados, no gastamos tiempo en IA
    if (count($resultados_sql) <= 5) {
        return array_slice($resultados_sql, 0, 5);
    }

// 1. Preparamos un mapa para la IA (¡Ahora incluimos el precio promedio!)
    $candidatos_ia = [];
    foreach ($resultados_sql as $index => $fila) {
        $candidatos_ia[] = [
            'id' => $index,
            'cdmess' => trim($fila['CDMESS']),
            'descripcion' => $fila['descripcion'],
            'precio_historico' => $fila['precio_promedio'] // Fundamental para la decisión comercial
        ];
    }

    $json_candidatos = json_encode($candidatos_ia, JSON_UNESCAPED_UNICODE);
    
    // 2. Prompt con doble perfil: Técnico y Comercial (Business Intelligence)
    $prompt = "Eres un estratega comercial y experto técnico en metrología.
    El cliente está buscando cotizar: '$busqueda_usuario'.
    
    Aquí tienes el historial de candidatos encontrados con sus precios promedio en JSON:
    $json_candidatos
    
    INSTRUCCIONES DE SELECCIÓN:
    1. Filtro Técnico: Descarta cualquier opción que no coincida con la naturaleza del equipo o servicio solicitado.
    2. Filtro Comercial: Evalúa la viabilidad de los precios históricos. Prioriza opciones con precios coherentes y consistentes. Si ves anomalías evidentes (precios absurdamente bajos o altos sin justificación en la descripción), penalízalos en tu elección.
    3. Equilibrio: Selecciona los mejores candidatos (máximo 5) que representen la oferta más sólida tanto a nivel técnico como financiero.
    4. Formato: Devuelve ESTRICTAMENTE un JSON con un arreglo llamado 'mejores_ids' que contenga solo los números de 'id' seleccionados.
    
    Ejemplo de salida: {\"mejores_ids\": [0, 3, 4, 1, 7]}";

    $data = [
        "model" => "llama3.2:1b", // Tu modelo local en WAMP
        "format" => "json",
        "prompt" => $prompt,
        "stream" => false,
        "options" => [
            "temperature" => 0.3, // Menor temperatura para respuestas más determinísticas
            "num_predict" => 150  // Respuesta ultracorta para máxima velocidad
        ]
    ];

    $ch = curl_init("http://localhost:11434/api/generate");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6); // Límite de 6 segundos
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    // Procesamos la respuesta
    if ($res) {
        $json_res = json_decode($res, true);
        $respuesta_ia = json_decode($json_res['response'] ?? '{}', true);
        
        if (isset($respuesta_ia['mejores_ids']) && is_array($respuesta_ia['mejores_ids'])) {
            $resultados_finales = [];
            foreach ($respuesta_ia['mejores_ids'] as $id) {
                if (isset($resultados_sql[$id])) {
                    $resultados_finales[] = $resultados_sql[$id];
                }
                if (count($resultados_finales) == 5) break; 
            }
            
            if (count($resultados_finales) > 0) {
                return $resultados_finales;
            }
        }
    }
    
    // Fallback: Si Ollama no responde o falla, regresamos los 5 primeros del SQL
    return array_slice($resultados_sql, 0, 5);
}

/**
 * Función principal para obtener el historial con filtro holgado y análisis IA.
 */
function obtenerOpcionesUnicasHistoricas($busqueda, $tipoBusqueda, $cliente, $conn) {
    $contexto = procesarContextoBusqueda($busqueda);
    $busqueda_limpia = $contexto['busqueda_limpia'];
    $termino = $contexto['termino'];
    
    // Validamos el cliente
    $filtrar_cliente = (!empty($cliente) && $cliente !== 'todos');
    $cliente_limpio = $filtrar_cliente ? $conn->real_escape_string($cliente) : null;
    
    // Validación de CDMESS estricto
    if ($contexto['es_cdmess']) {
        $esvalido = validaCDMESS($busqueda_limpia, $conn);
        if (!$esvalido && !in_array($tipoBusqueda, ['noSerie', 'modelo', 'messTag'])) {
            return 'noValido';
        }
        
        if ($esvalido) {
            $sql = "SELECT TRIM(CDMESS) as CDMESS, MAX(DESCRIPCION) as descripcion, ROUND(AVG(PRECIO_VENTA/CANT), 2) as precio_promedio                
                    FROM cotizaciones_items
                    INNER JOIN cotizaciones ON cotizaciones_items.IDCOTIZA = cotizaciones.IDCOTIZA                     
                    WHERE (MATCH(DESCRIPCION) AGAINST(? IN BOOLEAN MODE) OR CDMESS LIKE ?)
                        AND PRECIO_VENTA > 0 AND CANT > 0
                        AND CDMESS IS NOT NULL AND CDMESS != ''";
            
            $params = [$termino, $termino];
            $types = "ss";

            if ($filtrar_cliente) {
                $sql .= " AND cotizaciones.IDCLTE = ? ";
                $params[] = $cliente_limpio;
                $types .= "s";
            }

            // Ampliamos a LIMIT 15 para dar opciones a la IA
            $sql .= " GROUP BY TRIM(CDMESS) ORDER BY COUNT(*) DESC LIMIT 15";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            return filtrarOpcionesConOllama($busqueda_limpia, $resultados_crudos);
        }
    }

    // Constructor dinámico para consultas generales
    $sql_base = "SELECT TRIM(ci.CDMESS) as CDMESS, MAX(ci.DESCRIPCION) as descripcion, ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) as precio_promedio, t.STATUS
                 FROM cotizaciones_items ci
                 LEFT JOIN tarifario t ON ci.CDMESS = t.CDMESS 
                 INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA ";
                 
    $condiciones_globales = " AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0 AND ci.CDMESS IS NOT NULL AND ci.CDMESS != '' AND t.STATUS = 'ACTIVE' ";
    
    // Ampliamos a LIMIT 15
    $group_order = " GROUP BY TRIM(ci.CDMESS), t.STATUS ORDER BY COUNT(*) DESC LIMIT 15";
    
    $where_clause = "";
    $params = [];
    $types = "";

    // Switch sin la barrera de "ci.TIPO = ?" para que encuentre equipos aunque parezcan servicios
    switch ($tipoBusqueda) {
        case 'todas':
            $where_clause = "WHERE (MATCH(ci.DESCRIPCION) AGAINST(? IN BOOLEAN MODE) OR ci.CDMESS LIKE ? OR ci.MARCA LIKE ? OR ci.MODELO LIKE ? OR ci.SERIE LIKE ?)";
            $params = [$busqueda_limpia, $termino, $termino, $termino, $termino];
            $types = "sssss";
            break;
        case 'descripciones':
            $where_clause = "WHERE ci.DESCRIPCION LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
        case 'modelo':
            $where_clause = "WHERE ci.MODELO LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
        case 'noSerie':
            $where_clause = "WHERE ci.SERIE LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
        case 'messTag':
            $where_clause = "WHERE ci.MESSTAG LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
        case 'IdEquipoCliente':
            $where_clause = "WHERE ci.ID_EQ_CLIENTE LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
        case 'codigos':
            $where_clause = "WHERE ci.CDMESS LIKE ?";
            $params = [$termino];
            $types = "s";
            break;
    }

    if ($filtrar_cliente) {
        $condiciones_globales .= " AND c.IDCLTE = ? ";
        $params[] = $cliente_limpio;
        $types .= "s";
    }

    $sql_final = $sql_base . $where_clause . $condiciones_globales . $group_order;
    
    $stmt = $conn->prepare($sql_final);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return filtrarOpcionesConOllama($busqueda_limpia, $resultados_crudos);
}

function obtenerHistorialMESS($busqueda) {
    global $conn;
    $conn->set_charset("utf8mb4");
    
    $contexto = procesarContextoBusqueda($busqueda);
    $busqueda_limpia = $contexto['busqueda_limpia'];
    $tipo_val = $contexto['tipo'];
    $termino = $contexto['termino'];

    $sql = "SELECT ci.CDMESS, MAX(ci.DESCRIPCION) as DESCRIPCION,
            ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_VENTA,
            ROUND(MIN(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MIN,
            ROUND(MAX(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MAX,
            ROUND(AVG(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN,
            ROUND(MIN(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN_MIN,
            ROUND(MAX(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN_MAX,
            COUNT(*) as TOTAL_VECES
        FROM cotizaciones_items ci 
        WHERE (ci.DESCRIPCION LIKE ? OR ci.CDMESS LIKE ? OR ci.MARCA LIKE ? OR ci.MODELO LIKE ? OR ci.SERIE LIKE ? OR ci.MESSTAG LIKE ? OR ci.ID_EQ_CLIENTE LIKE ?)
        AND ci.TIPO = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0 
        GROUP BY ci.CDMESS ORDER BY TOTAL_VECES DESC, ci.CDMESS LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $termino, $termino, $termino, $termino, $termino, $termino, $termino, $tipo_val);
    $stmt->execute();
    $res = $stmt->get_result();

    // Reintento dinámico si no encuentra resultados con el tipo original
    if ($res->num_rows === 0) {
        $tipo_val = ($tipo_val === 'SERVICIO') ? 'EQUIPO' : 'SERVICIO';
        $stmt->bind_param("ssssssss", $termino, $termino, $termino, $termino, $termino, $termino, $termino, $tipo_val);
        $stmt->execute();
        $res = $stmt->get_result(); 
    }
    
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
            'avg_mxn' => (float)$row['PRECIO_VENTA_MXN'],
            'min_mxn' => (float)$row['PRECIO_VENTA_MXN_MIN'],
            'max_mxn' => (float)$row['PRECIO_VENTA_MXN_MAX'],
            'total' => (int)$row['TOTAL_VECES']
        ];

        $item_str = "[". $row['CDMESS']. "] ". $row['DESCRIPCION'];
        $alternativas[] = $item_str;
        $detalle_ia.= "- $item_str: USD$". $row['PRECIO_VENTA']. " (min: $". $row['PRECIO_MIN']. ", max: $". $row['PRECIO_MAX']. ", veces: ". $row['TOTAL_VECES']. ") - MXN$". $row['PRECIO_VENTA_MXN']. " (min: $". $row['PRECIO_VENTA_MXN_MIN']. ", max: $". $row['PRECIO_VENTA_MXN_MAX']. ")\n";
    }

    if (empty($resultados)) {
        return [
            'min'=>0, 'max'=>0, 'avg'=>0, 'min_mxn'=>0, 'max_mxn'=>0, 'avg_mxn'=>0, 
            'cdmess'=>'S/C', 'detalle'=>'Sin historial', 'alternativas'=>'', 'items'=>[]
        ];
    }

    $principal = $resultados[0];
    $coincidencias_str = implode(", ", array_slice($alternativas, 1, 4));

    return [
        'min' => $principal['min'], 'max' => $principal['max'], 'avg' => $principal['avg'],
        'min_mxn' => $principal['min_mxn'], 'max_mxn' => $principal['max_mxn'], 'avg_mxn' => $principal['avg_mxn'],
        'cdmess' => $principal['cdmess'], 'detalle' => $detalle_ia, 'alternativas' => $coincidencias_str,
        'items' => $resultados
    ];
}


function obtenerAprendizajeHumano($entrada, $conn) {
    $busqueda = $conn->real_escape_string($entrada);
    $es_cdmess = preg_match('/^[SL]\d+/i', $entrada);

    if ($es_cdmess) {
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND cdmess_historico = '$busqueda' AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                ORDER BY id DESC LIMIT 5";
    } else {
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                  AND entrada_usuario LIKE '%$busqueda%'
                ORDER BY id DESC LIMIT 2";
    }
    
    $res = $conn->query($sql);
    
    if (!$res || $res->num_rows == 0) return []; 
    
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
        if ($row['precio_usuario'] > 0) {
            $aprendizaje['precios_usuario'][] = $row['precio_usuario'];
        }
        
        $cat = $row['categoria_rechazo'];
        $categorias[$cat] = ($categorias[$cat] ?? 0) + 1;
        
        if ($cat == 'Descripcion incorrecta') {
            $aprendizaje['alerta_descripcion'] = true;
            $aprendizaje['nota_humana'] = $row['respuesta'] ?? 'Descripción incorrecta reportada';
        }
        
        $aprendizaje['ejemplos_texto'] .= "Usuario buscó: " . $row['entrada_usuario'] . "\n";
        $aprendizaje['ejemplos_texto'] .= "Precio corregido: $" . ($row['precio_usuario'] ?? 'N/A') . "\n";
        $aprendizaje['ejemplos_texto'] .= "Nota: " . ($row['respuesta'] ?? 'Sin comentarios') . "\n";
        $aprendizaje['ejemplos_texto'] .= "Categoría: " . $cat . "\n\n";
    }
    
    arsort($categorias);
    $aprendizaje['categoria_principal'] = key($categorias);
    
    if (!empty($aprendizaje['precios_usuario'])) {
        $aprendizaje['precio_sugerido'] = round(array_sum($aprendizaje['precios_usuario']) / count($aprendizaje['precios_usuario']), 2);
    }
    
    return $aprendizaje;
}

function obtenerFolioBase($cdmess, $conn) {
    if (empty($cdmess)) return ['folio' => '', 'fecha' => ''];
    $sql = "SELECT ci.IDCOTIZA AS folio, c.FECHA AS fecha
            FROM cotizaciones_items ci
            LEFT JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA
            WHERE ci.CDMESS = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0
            ORDER BY ci.id_item DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cdmess);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return [
        'folio' => $row['folio'] ?? '',
        'fecha' => $row['fecha'] ?? ''
    ];
}


/**
 * ========================================================================
 * MÓDULO 3: INTEGRACIÓN CON INTELIGENCIA ARTIFICIAL (OLLAMA)
 * ========================================================================
 */

function preguntarOllamaConPrecios($stats, $consulta_usuario, $aprendizaje = "") {
    $url = "http://localhost:11434/api/generate";
    
    $contexto = "CDMESS:{$stats['cdmess']} MIN:{$stats['min']} MAX:{$stats['max']} AVG:{$stats['avg']} N:{$stats['alternativas']}";
    $prompt = "Contexto Histórico: $contexto\n";
    $prompt .= "REGLA HUMANA: $aprendizaje\n";
    $prompt .= "INSTRUCCIONES: 
        1. Analiza el precio promedio y el rango.
        2. Si la REGLA HUMANA indica una tendencia, úsala para ajustar el precio.
        3. Justifica brevemente el ajuste considerando el mercado metrológico.
        4. Responde SOLO en JSON con este formato: 
           {\"cdmess\":\"{$stats['cdmess']}\", \"desc\":\"descripción técnica optimizada\", \"precio_ia\": 0.0, \"detalle_calculo\":\"razonamiento de 1 frase\"}";
    
    if (!empty($aprendizaje)) {
        if (is_array($aprendizaje)) {
            $aprendizaje = array_filter(array_slice($aprendizaje, 0, 3), function($v) {
                return !is_array($v) && !is_object($v) && $v !== '' && $v !== null;
            });
            $aprendizaje = implode(". ", $aprendizaje); 
        } elseif (is_object($aprendizaje)) {
            $aprendizaje = '';
        } else {
            $aprendizaje = (string)$aprendizaje;
        }

        $aprendizaje = substr(trim($aprendizaje), 0, 200); 
        
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
        "system" => "Devuelve JSON puro. Precio_ia ajustado por REGLA HUMANA.Eres un Analista Senior de Business Intelligence en MESS. Tu objetivo es encontrar el precio justo. Si la REGLA HUMANA contradice el historial, dale prioridad absoluta a la regla humana pero justifica el porqué. Sé crítico: si el historial es antiguo o muy volátil, búscale sentido técnico",
        "prompt" => $prompt,
        "stream" => false,
        "keep_alive" => "24h", 
        "options" => [
            "temperature" => 0.4, // Subimos de 0 a 0.4 para darle libertad creativa
            "top_p" => 0.9,       // Permite mayor variedad en las palabras elegidas
            "num_predict" => 150  // Aumentamos el límite para que pueda redactar la justificación
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
    
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


/**
 * ========================================================================
 * MÓDULO 4: FUNCIONES DE SISTEMA Y DEPURACIÓN
 * ========================================================================
 */

function verificarWorker() {
    $comando = 'wmic process where "name=\'php.exe\'" get commandline';
    $output = shell_exec($comando);
    return (strpos($output, 'worker_ia.php') !== false);
}

function obtenerDetalleProyectoAJAX($id_proyecto) {
    global $conn;
    $id_proyecto = $conn->real_escape_string($id_proyecto);
    
    $sql = "SELECT id, entrada_usuario, estatus, propuesta_ia FROM cola_procesamiento 
            WHERE id_proyecto = '$id_proyecto' ORDER BY id ASC";
    $res = $conn->query($sql);
    
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['propuesta_ia'] = json_decode($row['propuesta_ia'], true);
        $items[] = $row;
    }
    return $items;
}