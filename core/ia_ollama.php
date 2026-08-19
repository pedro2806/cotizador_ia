<?php
/**
 * ========================================================================
 * MÓDULO: INTEGRACIÓN CON INTELIGENCIA ARTIFICIAL (OLLAMA)
 * ========================================================================
 * Toda llamada HTTP al motor local de Ollama vive aquí. Antes la URL y el
 * nombre del modelo estaban repetidos en dos funciones distintas de
 * funcionesWorker.php; ahora ambas leen las constantes de core/config.php.
 */

/**
 * Llama a Ollama para seleccionar, de una lista de candidatos SQL, los
 * mejores (máx. 8) desde un punto de vista técnico y comercial.
 *
 * Antes tenía tope de 5 y umbral de salto también en 5. Se sube a 8 para
 * darle más libertad real de opciones: más candidatos entre los que elegir
 * (ver LIMIT 25 en core/historial.php) y más espacio para devolver varios
 * si genuinamente varios aplican, no solo los primeros 5 que aparezcan.
 */
function filtrarOpcionesConOllama($busqueda_usuario, $resultados_sql) {
    // Si SQL arrojó 8 o menos resultados, no gastamos tiempo en IA
    if (count($resultados_sql) <= 8) {
        return array_slice($resultados_sql, 0, 8);
    }

    // 1. Preparamos un mapa para la IA (incluyendo el precio promedio)
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

    // 2. Prompt con doble perfil: técnico y comercial (Business Intelligence)
    $prompt = "Eres un estratega comercial y experto técnico en metrología.
    El cliente está buscando cotizar: '$busqueda_usuario'.

    Aquí tienes el historial de candidatos encontrados con sus precios promedio en JSON:
    $json_candidatos

    INSTRUCCIONES DE SELECCIÓN:
    1. Filtro Técnico: Descarta cualquier opción que no coincida con la naturaleza del equipo o servicio solicitado.
    2. Filtro Comercial: Evalúa la viabilidad de los precios históricos. Prioriza opciones con precios coherentes y consistentes. Si ves anomalías evidentes (precios absurdamente bajos o altos sin justificación en la descripción), penalízalos en tu elección.
    3. Criterio propio: no te limites a devolver siempre el mismo número de opciones. Si genuinamente hay 6, 7 u 8 candidatos sólidos (técnica Y comercialmente razonables), inclúyelos todos; si solo 2 o 3 son realmente defendibles, regresa solo esos. Usa tu criterio, no rellenes por rellenar.
    4. Equilibrio: Selecciona los mejores candidatos (máximo 8) que representen la oferta más sólida tanto a nivel técnico como financiero.
    5. Formato: Devuelve ESTRICTAMENTE un JSON con un arreglo llamado 'mejores_ids' que contenga solo los números de 'id' seleccionados, ordenados del más recomendable al menos recomendable.

    Ejemplo de salida: {\"mejores_ids\": [0, 3, 4, 1, 7, 9]}";

    $data = [
        'model' => OLLAMA_MODEL,
        'format' => 'json',
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.45, // Antes 0.3: más criterio propio para decidir cuántas opciones son realmente válidas
            'num_predict' => 200   // Antes 150: espacio para un arreglo de hasta 8 ids
        ]
    ];

    $res = ollamaRequest($data, OLLAMA_TIMEOUT_FILTRO);

    if ($res !== null) {
        $json_res = json_decode($res, true);
        $respuesta_ia = json_decode($json_res['response'] ?? '{}', true);

        if (isset($respuesta_ia['mejores_ids']) && is_array($respuesta_ia['mejores_ids'])) {
            $resultados_finales = [];
            foreach ($respuesta_ia['mejores_ids'] as $id) {
                if (isset($resultados_sql[$id])) {
                    $resultados_finales[] = $resultados_sql[$id];
                }
                if (count($resultados_finales) == 8) break;
            }

            if (count($resultados_finales) > 0) {
                return $resultados_finales;
            }
        }
    }

    // Fallback: si Ollama no responde o falla, regresamos los 8 primeros del SQL
    return array_slice($resultados_sql, 0, 8);
}

/**
 * Le pide a Ollama que proponga un precio final, considerando el historial
 * estadístico (min/max/avg) y el aprendizaje humano (correcciones previas).
 *
 * A diferencia de antes, ahora también le pedimos explícitamente un
 * "veredicto": un párrafo corto donde compare las alternativas históricas
 * encontradas (stats['alternativas']) y explique en palabras por qué
 * recomienda el precio/CDMESS elegido por encima de las demás opciones.
 * Se le da un poco más de libertad de redacción (temperature/top_p/num_predict
 * más altos) para que el razonamiento no suene robótico, pero se mantiene
 * dentro de un límite: sigue respondiendo SOLO JSON con campos numéricos
 * validables, así que la "libertad" es en el texto explicativo, no en la
 * estructura de la respuesta ni en inventar precios fuera de lo razonable.
 *
 * Devuelve el JSON crudo de texto que regresa el modelo (string).
 */
function preguntarOllamaConPrecios($stats, $consulta_usuario, $aprendizaje = '') {
    $contexto = "CDMESS:{$stats['cdmess']} MIN:{$stats['min']} MAX:{$stats['max']} AVG:{$stats['avg']}";
    $prompt = "Contexto Histórico: $contexto\n";

    $alternativas = trim((string) ($stats['alternativas'] ?? ''));
    if ($alternativas !== '') {
        $prompt .= "Otras coincidencias históricas encontradas para comparar: $alternativas\n";
    }

    if (!empty($aprendizaje)) {
        if (is_array($aprendizaje)) {
            $aprendizaje = array_filter(array_slice($aprendizaje, 0, 3), function ($v) {
                return !is_array($v) && !is_object($v) && $v !== '' && $v !== null;
            });
            $aprendizaje = implode('. ', $aprendizaje);
        } elseif (is_object($aprendizaje)) {
            $aprendizaje = '';
        } else {
            $aprendizaje = (string) $aprendizaje;
        }

        $aprendizaje = substr(trim($aprendizaje), 0, 200);

        if ($aprendizaje !== '') {
            $prompt .= "REGLA HUMANA: $aprendizaje\n";
            $prompt .= "Prioridad: Si dice 'alto' baja precio. Si 'bajo' sube precio. Ignora AVG si contradice.\n";
        }
    }

    $prompt .= "INSTRUCCIONES:
        1. Analiza el precio promedio, el rango y las coincidencias históricas.
        2. Si la REGLA HUMANA indica una tendencia, úsala para ajustar el precio.
        3. Da tu recomendación como un RANGO, no un solo número fijo: un precio
           conservador (piso razonable), uno recomendado (tu mejor estimación) y
           uno optimista (techo razonable si el cliente/contexto lo permite).
           Tienes libertad para moverte fuera del MIN/MAX histórico si el
           contexto realmente lo amerita (equipo/servicio más complejo que el
           promedio, cliente distinto, antigüedad del dato, etc.) — pero
           explica ese salto en el veredicto, no lo hagas en silencio.
        4. Da un VEREDICTO: 2 a 4 frases, en tono de asesor comercial, explicando
           por qué este rango y esta descripción son la mejor recomendación
           frente a las otras coincidencias encontradas (si las hay). Puedes
           mencionar por nombre alguna de las coincidencias para justificar
           el contraste (ej. \"a diferencia de [otra opción], este caso...\").
        5. Responde SOLO en JSON, sin texto fuera del JSON, con este formato exacto:
           {\"cdmess\":\"{$stats['cdmess']}\", \"desc\":\"descripción técnica optimizada\",
            \"precio_ia\": 0.0, \"precio_ia_min\": 0.0, \"precio_ia_max\": 0.0,
            \"detalle_calculo\":\"razonamiento de 1 frase\",
            \"veredicto\":\"tu recomendación de 2 a 4 frases\",
            \"notas\":\"justificación corta de 1 frase para la tabla\"}\n";
    $prompt .= "Usuario: '$consulta_usuario'\n";

    $data = [
        'model' => OLLAMA_MODEL,
        'format' => 'json',
        'system' => 'Eres un Analista Senior de Business Intelligence en MESS. Devuelve JSON puro (nada de texto fuera del JSON). Tu objetivo es encontrar el precio justo y explicar tu veredicto como lo haría un asesor comercial experimentado: con criterio propio, comparando contra las alternativas históricas. Tienes libertad para proponer un rango que se salga del histórico si el contexto lo justifica (no estás obligado a quedarte pegado al promedio), pero siempre debes justificarlo en el veredicto — nunca inventes sin explicar el porqué. Si la REGLA HUMANA contradice el historial, dale prioridad absoluta a la regla humana pero justifica el porqué en el veredicto. Sé crítico: si el historial es antiguo o muy volátil, dilo en el veredicto. Cualquier recomendación que se aleje mucho del histórico quedará marcada para revisión humana de todas formas, así que prioriza dar tu mejor criterio real sobre jugar seguro.',
        'prompt' => $prompt,
        'stream' => false,
        'keep_alive' => '24h',
        'options' => [
            'temperature' => 0.6,  // Antes 0.55: un poco más de criterio propio para el rango
            'top_p' => 0.94,       // Antes 0.92
            // Antes 260: en CPU con un modelo de 8B (llama3:latest) generar
            // 260 tokens se pasaba fácil de los timeouts. 180 sigue dejando
            // espacio de sobra para un veredicto de 2-4 frases, pero tarda
            // notablemente menos.
            'num_predict' => 200
        ]
    ];

    $res = ollamaRequest($data, OLLAMA_TIMEOUT_PRECIO);

    if ($res === null) {
        return json_encode([
            'cdmess' => $stats['cdmess'],
            'precio_ia' => $stats['avg'],
            'precio_ia_min' => $stats['min'],
            'precio_ia_max' => $stats['max'],
            'notas' => 'Error IA',
            'veredicto' => 'No fue posible generar un veredicto: el motor de IA no respondió a tiempo. Se usó el precio promedio histórico como respaldo.'
        ], JSON_UNESCAPED_UNICODE);
    }

    $datos_res = json_decode($res, true);
    return $datos_res['response'] ?? json_encode(['precio_ia' => $stats['avg']]);
}

/**
 * Punto único de salida HTTP hacia Ollama. Antes cada función abría/cerraba
 * su propio cURL con la URL repetida; ahora solo se arma el payload y se
 * llama aquí.
 *
 * @return string|null Cuerpo de la respuesta HTTP, o null si falló/no fue 200.
 */
function ollamaRequest(array $data, int $timeout) {
    $modelo = $data['model'] ?? '?';
    $t0 = microtime(true);

    $ch = curl_init(OLLAMA_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $res = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $duracion = round(microtime(true) - $t0, 2);

    if ($res === false) {
        // errno 28 = CURLE_OPERATION_TIMEDOUT -> el modelo tardó más que
        // $timeout segundos en responder. Es la causa más común de que el
        // frontend muestre el veredicto de respaldo. Si ves esto seguido,
        // sube OLLAMA_TIMEOUT_PRECIO/OLLAMA_TIMEOUT_FILTRO en core/config.php,
        // o revisa que Ollama no esté cargando el modelo desde frío
        // (`ollama ps` debe mostrar el modelo ya cargado).
        // OJO: la constante real de PHP/cURL es CURLE_OPERATION_TIMEDOUT
        // (con "D", timed-out). Un typo aquí como CURLE_OPERATION_TIMEOUT
        // provoca un "Undefined constant" fatal justo cuando SÍ ocurre un
        // timeout real, tapando la causa verdadera con un error distinto.
        $motivo = ($curl_errno === CURLE_OPERATION_TIMEDOUT)
            ? "TIMEOUT tras {$duracion}s (límite: {$timeout}s)"
            : "error de conexión (errno $curl_errno): $curl_error";
        error_log("Ollama [$modelo] $motivo");
        return null;
    }

    if ($httpcode != 200) {
        // Se recorta el cuerpo para no inundar el log si Ollama regresa HTML/stacktrace largo
        error_log("Ollama [$modelo] HTTP $httpcode tras {$duracion}s | body: " . substr((string) $res, 0, 500));
        return null;
    }

    error_log("Ollama [$modelo] OK en {$duracion}s");
    return $res;
}
