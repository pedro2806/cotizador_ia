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
/**
 * A5 — Reintentos con backoff en Ollama.
 *
 * Comportamiento actual antes de A5: ollamaRequest() hacía UN solo intento
 * y devolvía null ante cualquier fallo (timeout, HTTP != 200, cuerpo
 * vacío), sin validar el JSON. preguntarOllamaConPrecios() tampoco
 * validaba la respuesta interna: si el modelo devolvía JSON truncado
 * (num_predict corto) o sin precio_ia/veredicto, el worker decodificaba
 * a [] y caía en silencio al promedio histórico con el veredicto genérico
 * "Sin veredicto detallado de la IA para este ítem" (casos reales ids
 * 1714-1716: origen_precio='promedio'). Reglas A5:
 * - ollamaRequest(): hasta OLLAMA_MAX_INTENTOS (3) intentos HTTP, con
 *   backoff exponencial OLLAMA_BACKOFF_BASE_SEG * 2^(n-1) (1s, 2s) entre
 *   intentos. Se reintenta SOLO en fallos transitorios: error de red /
 *   timeout, HTTP 5xx o 429, respuesta vacía, o sobre JSON que no sea el
 *   sobre de Ollama (sin campo 'response'). NO se reintenta en HTTP 4xx
 *   (salvo 429): es un error de la petición, no del motor.
 * - preguntarOllamaConPrecios(): valida el JSON interno antes de
 *   aceptarlo (JSON válido + precio_ia > 0 + veredicto no vacío). Si viene
 *   truncado o incompleto, re-pregunta (máx. 3 llamadas en total; la 1ª
 *   con los reintentos de transporte de ollamaRequest, las siguientes con
 *   1 intento de transporte, para acotar el total a 5 peticiones HTTP).
 *   Un JSON válido CON veredicto pero sin precio_ia NO se reintenta: el
 *   modelo respondió y no propuso precio; se acepta tal cual y el worker
 *   cae al promedio marcado como degradado.
 * - Diagnóstico de la última llamada: $GLOBALS['ollama_intentos']
 *   (intentos HTTP realizados) y $GLOBALS['ollama_ultimo_error']
 *   (mensaje corto del último fallo, sin datos sensibles). Los lee
 *   preguntarOllamaConPrecios() para guardarlos en propuesta_ia.
 * - No cambian modelo, temperature ni top_p. num_predict en precio sube
 *   de 200 a 300 (ver comentario allí): 200 tokens se quedaban cortos
 *   para el JSON completo + veredicto de 2-4 frases y es la causa
 *   sospechosa de las respuestas truncadas/incompletas.
 * - Los timeouts por intento NO se tocan: viven en core/config.php
 *   (OLLAMA_TIMEOUT_PRECIO / OLLAMA_TIMEOUT_FILTRO), que no está en el
 *   repo; cada intento usa el timeout que le pasan, como antes.
 */
if (!defined('OLLAMA_MAX_INTENTOS')) define('OLLAMA_MAX_INTENTOS', 3);
if (!defined('OLLAMA_BACKOFF_BASE_SEG')) define('OLLAMA_BACKOFF_BASE_SEG', 1);
// A6 — Parámetros de las llamadas a Ollama en un solo lugar, para que la
// trazabilidad de propuesta_ia (worker_ia.php) los lea sin duplicar
// literales. OLLAMA_MODEL / OLLAMA_URL / OLLAMA_TIMEOUT_PRECIO /
// OLLAMA_TIMEOUT_FILTRO viven en core/config.php (no está en el repo).
if (!defined('OLLAMA_PRECIO_TEMPERATURE')) define('OLLAMA_PRECIO_TEMPERATURE', 0.6);
if (!defined('OLLAMA_PRECIO_TOP_P')) define('OLLAMA_PRECIO_TOP_P', 0.94);
if (!defined('OLLAMA_PRECIO_NUM_PREDICT')) define('OLLAMA_PRECIO_NUM_PREDICT', 300);
if (!defined('OLLAMA_FILTRO_TEMPERATURE')) define('OLLAMA_FILTRO_TEMPERATURE', 0.45);
if (!defined('OLLAMA_FILTRO_NUM_PREDICT')) define('OLLAMA_FILTRO_NUM_PREDICT', 200);
$GLOBALS['ollama_intentos'] = 0;
$GLOBALS['ollama_ultimo_error'] = null;

function filtrarOpcionesConOllama($busqueda_usuario, $resultados_sql, $accion = null) {
    // R1: sin acción, si SQL arrojó 8 o menos resultados no gastamos
    // tiempo en IA. CON acción (p. ej. 'calibracion'), el filtro corre
    // aunque haya pocos candidatos: es el que debe descartar las
    // revisiones/instalaciones/movimientos cuando se pidió una calibración.
    if (count($resultados_sql) <= 1) {
        return $resultados_sql;
    }
    if ($accion === null && count($resultados_sql) <= 8) {
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
    // R1: la acción pedida es un requisito, no una sugerencia. Se le
    // dice explícitamente a Ollama para que no devuelva revisiones cuando
    // se pidió una calibración, aunque el equipo (CMM) coincida.
    $linea_accion = '';
    if ($accion !== null && $accion !== '') {
        $linea_accion = "\n    ACCIÓN REQUERIDA: '$accion'. Solo valen las opciones cuya descripción contenga esa acción (acepta variantes con/sin acento, p. ej. calibración/calibracion). Descarta revisión, instalación, movimiento y cualquier otra acción distinta, aunque el equipo coincida. Solo si NINGUNA opción contiene la acción, devuelve las más cercanas y lo indicas en tu criterio.\n";
    }

    $prompt = "Eres un estratega comercial y experto técnico en metrología.
    El cliente está buscando cotizar: '$busqueda_usuario'.
$linea_accion

    Aquí tienes el historial de candidatos encontrados con sus precios promedio en JSON:
    $json_candidatos

    INSTRUCCIONES DE SELECCIÓN:
    1. Filtro Técnico: Descarta cualquier opción que no coincida con la naturaleza del equipo o servicio solicitado. Si hay una ACCIÓN REQUERIDA arriba, es excluyente: la descripción debe contener esa acción; una revisión no es una calibración, ni una instalación es un movimiento, aunque sea el mismo equipo.
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
            'temperature' => OLLAMA_FILTRO_TEMPERATURE, // Antes 0.3: más criterio propio para decidir cuántas opciones son realmente válidas
            'num_predict' => OLLAMA_FILTRO_NUM_PREDICT   // Antes 150: espacio para un arreglo de hasta 8 ids
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

    // Fallback: si Ollama no responde o falla, regresamos los 8 primeros
    // de la lista que ya viene ordenada por relevancia (R1), no los más
    // frecuentes: ahí las calibraciones ya van antes que las revisiones.
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
 *
 * A1: $contexto_precio es opcional (ver obtenerContextoPrecio() en
 * core/historial.php). Si llega con muestras, se inyecta en el prompt el
 * contexto estadístico completo (mediana, P25/P75, desviación, tendencia y
 * referencias con folio/fecha) en vez de solo MIN/MAX/AVG; si no llega,
 * el prompt se arma como antes.
 */
function preguntarOllamaConPrecios($stats, $consulta_usuario, $aprendizaje = '', $contexto_precio = null) {
    $contexto = "CDMESS:{$stats['cdmess']} MIN:{$stats['min']} MAX:{$stats['max']} AVG:{$stats['avg']}";
    $prompt = "Contexto Histórico: $contexto\n";

    // A1: contexto estadístico completo (mediana, percentiles, tendencia y
    // referencias con folio/fecha). Si no llega contexto, se mantiene el
    // comportamiento anterior (solo MIN/MAX/AVG).
    if (is_array($contexto_precio) && ($contexto_precio['n_muestras'] ?? 0) > 0) {
        $bloque = "Contexto estadístico completo (CDMESS {$contexto_precio['cdmess']}): "
            . "n=" . (int) $contexto_precio['n_muestras']
            . ", mediana USD " . $contexto_precio['mediana']
            . ", P25 USD " . $contexto_precio['p25']
            . ", P75 USD " . $contexto_precio['p75']
            . ", desviación estándar USD " . $contexto_precio['desviacion']
            . ", promedio USD " . ($contexto_precio['avg'] ?? $stats['avg'])
            . ", rango histórico USD " . $contexto_precio['min'] . "-" . $contexto_precio['max']
            . ", fechas " . ($contexto_precio['fecha_min'] ?? 's/d') . " a " . ($contexto_precio['fecha_max'] ?? 's/d')
            . ", última cotización " . ($contexto_precio['fecha_ultima'] ?? 's/d') . ".";
        // A3: la limpieza IQR y el promedio ponderado ya vienen calculados
        // en el contexto; se le muestran a Ollama para que sepa que los
        // percentiles excluyen atípicos y que lo reciente pesa más.
        if (isset($contexto_precio['n_atipicos']) && isset($contexto_precio['promedio_ponderado'])) {
            $bloque .= " Limpieza IQR: n=" . (int) ($contexto_precio['n_original'] ?? $contexto_precio['n_muestras'])
                . "->" . (int) ($contexto_precio['n_limpio'] ?? $contexto_precio['n_muestras'])
                . " (" . (int) $contexto_precio['n_atipicos'] . " atípicos descartados)"
                . ", promedio ponderado por antigüedad USD " . $contexto_precio['promedio_ponderado']
                . ", mediana ponderada USD " . ($contexto_precio['mediana_ponderada'] ?? $contexto_precio['mediana']) . ".";
        }
        if (($contexto_precio['tendencia_12m'] ?? null) !== null) {
            $bloque .= " Tendencia últimos 12 meses: " . $contexto_precio['tendencia_12m'] . "% (segundo semestre vs primero).";
        } else {
            $bloque .= " Tendencia 12 meses: sin datos suficientes.";
        }
        $refs = $contexto_precio['referencias'] ?? [];
        if (!empty($refs)) {
            $bloque .= "\nReferencias recientes (folio / fecha / precio unitario USD / MXN):";
            foreach ($refs as $ref) {
                $bloque .= "\n- Folio " . ($ref['folio'] ?? 's/d') . " / " . ($ref['fecha'] ?? 's/d')
                    . " / USD " . ($ref['precio_usd'] ?? 's/d')
                    . " / MXN " . ($ref['precio_mxn'] ?? 's/d');
            }
        }
        $prompt .= $bloque . "\n";
        $prompt .= "Usa la mediana y los percentiles (P25/P75) como ancla principal, no solo el promedio: el promedio se distorsiona con valores atípicos. Los percentiles y la mediana ya excluyen atípicos por IQR, y el promedio ponderado da más peso a las cotizaciones recientes: úsalo como referencia de precio actual, no el promedio simple. Si propones un precio fuera de la banda [P25 x 0.85, P75 x 1.15] (o [mediana x 0.8, mediana x 1.2] con pocas muestras), cita en el veredicto el folio y la fecha de la referencia que lo justifica.\n";
    }

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
            'temperature' => OLLAMA_PRECIO_TEMPERATURE,  // Antes 0.55: un poco más de criterio propio para el rango
            'top_p' => OLLAMA_PRECIO_TOP_P,       // Antes 0.92
            // Antes 260: en CPU con un modelo de 8B (llama3:latest) generar
            // 260 tokens se pasaba fácil de los timeouts. 180 sigue dejando
            // espacio de sobra para un veredicto de 2-4 frases, pero tarda
            // notablemente menos.
            // A5: 200 -> 300. El JSON completo + veredicto de 2-4 frases no
            // cabía con holgura en 200 tokens y las respuestas salían
            // truncadas o sin veredicto (origen 'promedio' en ids 1714-1716).
            // Subida conservadora: solo en la llamada de precio, no en el
            // filtro (que devuelve un arreglo corto). Con el reintento de
            // A5, si aun así se trunca, se re-pregunta en vez de caer al
            // promedio en silencio.
            'num_predict' => OLLAMA_PRECIO_NUM_PREDICT
        ]
    ];

    // A5: validar la respuesta ANTES de aceptarla y re-preguntar si viene
    // truncada o incompleta. Ver el bloque de constantes arriba para las
    // reglas exactas. $intentos_precio acumula los intentos HTTP reales
    // (los de transporte dentro de ollamaRequest + las re-preguntas).
    $intentos_precio = 0;
    $ultimo_error_precio = null;
    $datos_validos = null;
    $aceptada_sin_precio = null;
    for ($llamada = 1; $llamada <= 3; $llamada++) {
        $res = ollamaRequest($data, OLLAMA_TIMEOUT_PRECIO, $llamada === 1 ? OLLAMA_MAX_INTENTOS : 1);
        $intentos_precio += (int) ($GLOBALS['ollama_intentos'] ?? 0);
        $ultimo_error_precio = $GLOBALS['ollama_ultimo_error'] ?? $ultimo_error_precio;
        if ($res === null) {
            // Fallo de transporte agotado en ollamaRequest(): no tiene
            // sentido re-preguntar de inmediato si el motor no responde.
            break;
        }
        $datos_res = json_decode($res, true);
        $inner = isset($datos_res['response']) ? json_decode($datos_res['response'], true) : null;
        if (!is_array($inner)) {
            // Sobre válido pero 'response' con JSON truncado/inválido.
            $ultimo_error_precio = 'JSON truncado o inválido en la respuesta de la IA';
            continue;
        }
        $tiene_precio = isset($inner['precio_ia']) && (float) $inner['precio_ia'] > 0;
        $tiene_veredicto = trim((string) ($inner['veredicto'] ?? '')) !== '';
        if ($tiene_precio && $tiene_veredicto) {
            $datos_validos = $inner;
            break;
        }
        if ($tiene_veredicto && !$tiene_precio) {
            // JSON válido CON explicación pero sin precio: el modelo
            // respondió y no propuso precio; NO se reintenta. Se acepta
            // tal cual y el worker cae al promedio marcado como degradado.
            $aceptada_sin_precio = $inner;
            break;
        }
        // Respuesta incompleta (sin precio y sin veredicto, o con precio
        // pero sin veredicto): transitorio, se re-pregunta en la
        // siguiente vuelta del for (máx. 3 llamadas en total).
        $ultimo_error_precio = !$tiene_precio ? 'sin precio_ia en la respuesta de la IA' : 'sin veredicto en la respuesta de la IA';
    }

    if ($datos_validos !== null) {
        $datos_validos['ia_intentos'] = $intentos_precio;
        $datos_validos['ia_error'] = null;
        $datos_validos['ia_degradado'] = false;
        return json_encode($datos_validos, JSON_UNESCAPED_UNICODE);
    }

    if ($aceptada_sin_precio !== null) {
        $aceptada_sin_precio['ia_intentos'] = $intentos_precio;
        $aceptada_sin_precio['ia_error'] = 'sin precio_ia en la respuesta de la IA';
        $aceptada_sin_precio['ia_degradado'] = true;
        return json_encode($aceptada_sin_precio, JSON_UNESCAPED_UNICODE);
    }

    // Respaldo MARCADO al promedio histórico: el veredicto dice
    // explícitamente cuántos intentos se hicieron y por qué se cayó al
    // promedio, para que no se vea como una sugerencia de la IA.
    $motivo = $ultimo_error_precio !== null ? $ultimo_error_precio : 'sin propuesta válida de la IA';
    return json_encode([
        'cdmess' => $stats['cdmess'],
        'precio_ia' => $stats['avg'],
        'precio_ia_min' => $stats['min'],
        'precio_ia_max' => $stats['max'],
        'notas' => 'Error IA',
        'veredicto' => "Tras $intentos_precio intento(s) a Ollama ($motivo) no se obtuvo una propuesta válida de precio. Se usó el precio promedio histórico como respaldo.",
        'ia_intentos' => $intentos_precio,
        'ia_error' => $ultimo_error_precio,
        'ia_degradado' => true
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Punto único de salida HTTP hacia Ollama. Antes cada función abría/cerraba
 * su propio cURL con la URL repetida; ahora solo se arma el payload y se
 * llama aquí.
 *
 * @return string|null Cuerpo de la respuesta HTTP, o null si falló/no fue 200.
 */
function ollamaRequest(array $data, int $timeout, int $max_intentos = OLLAMA_MAX_INTENTOS) {
    $modelo = $data['model'] ?? '?';
    $max_intentos = max(1, $max_intentos);
    // A5: diagnóstico de ESTA llamada (se reinicia en cada llamada).
    $GLOBALS['ollama_intentos'] = 0;
    $GLOBALS['ollama_ultimo_error'] = null;
    $ultimo_error = null;

    for ($intento = 1; $intento <= $max_intentos; $intento++) {
        $GLOBALS['ollama_intentos'] = $intento;
        $t0 = microtime(true);

        $ch = curl_init(OLLAMA_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $res = curl_exec($ch);
        $httpcode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
            // A5: fallo de transporte -> REINTENTABLE (backoff abajo).
            $ultimo_error = ($curl_errno === CURLE_OPERATION_TIMEDOUT)
                ? "TIMEOUT tras {$duracion}s (límite: {$timeout}s)"
                : "error de conexión (errno $curl_errno)" . ($curl_error !== '' ? ': ' . substr($curl_error, 0, 120) : '');
            error_log("Ollama [$modelo] intento $intento/$max_intentos: $ultimo_error");
        } elseif ($httpcode === 429 || $httpcode >= 500) {
            // A5: 429 / 5xx -> REINTENTABLE (el motor está saturado o falló).
            $ultimo_error = "HTTP $httpcode tras {$duracion}s";
            error_log("Ollama [$modelo] intento $intento/$max_intentos: $ultimo_error | body: " . substr((string) $res, 0, 500));
        } elseif ($httpcode !== 200) {
            // A5: HTTP 4xx (salvo 429) -> NO reintentable: es un error de la
            // petición, no del motor. Se sale sin más intentos.
            $ultimo_error = "HTTP $httpcode tras {$duracion}s (no reintentable)";
            error_log("Ollama [$modelo] $ultimo_error | body: " . substr((string) $res, 0, 500));
            $GLOBALS['ollama_ultimo_error'] = $ultimo_error;
            return null;
        } elseif ($res === '' || $res === null) {
            // A5: HTTP 200 con cuerpo vacío -> REINTENTABLE.
            $ultimo_error = "respuesta vacía tras {$duracion}s";
            error_log("Ollama [$modelo] intento $intento/$max_intentos: $ultimo_error");
        } else {
            // A5: HTTP 200 con cuerpo: debe ser el sobre JSON de Ollama con
            // campo 'response' (string). Si no lo es, es una respuesta
            // truncada o corrupta -> REINTENTABLE. El contenido interno
            // ('response') lo valida cada llamador (preguntarOllamaConPrecios
            // exige JSON con precio_ia y veredicto; el filtro exige
            // mejores_ids y si no, cae al orden por relevancia).
            $env = json_decode($res, true);
            if (!is_array($env) || !isset($env['response']) || !is_string($env['response'])) {
                $ultimo_error = "respuesta no-JSON o sin 'response' tras {$duracion}s";
                error_log("Ollama [$modelo] intento $intento/$max_intentos: $ultimo_error");
            } else {
                error_log("Ollama [$modelo] OK en {$duracion}s (intento $intento/$max_intentos)");
                $GLOBALS['ollama_ultimo_error'] = null;
                return $res;
            }
        }

        $GLOBALS['ollama_ultimo_error'] = $ultimo_error;
        if ($intento < $max_intentos) {
            // A5: backoff exponencial 1s, 2s (OLLAMA_BACKOFF_BASE_SEG * 2^(n-1)).
            sleep(OLLAMA_BACKOFF_BASE_SEG * (int) pow(2, $intento - 1));
        }
    }

    error_log("Ollama [$modelo] agotados $max_intentos intentos; último error: $ultimo_error");
    return null;
}
