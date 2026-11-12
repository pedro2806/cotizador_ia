<?php
/**
 * ========================================================================
 * MÓDULO: HISTORIAL Y APRENDIZAJE (consultas a BD)
 * ========================================================================
 * Todo lo que consulta cotizaciones_items / cotizaciones / cola_procesamiento
 * para construir el contexto que después se manda a Ollama.
 * Depende de: core/helpers.php (procesarContextoBusqueda, validaCDMESS)
 *             core/ia_ollama.php (filtrarOpcionesConOllama)
 */

/**
 * R1 — Ordena candidatos por RELEVANCIA, no solo por frecuencia.
 * Puntaje por candidato (sobre la descripción normalizada, sin acentos):
 *   +100 si contiene la acción pedida (calibración, revisión, ...)
 *    +30 extra si la descripción EMPIEZA con esa acción
 *    +20 por cada token de la búsqueda presente en la descripción
 * Desempate: total_veces (frecuencia histórica) y luego CDMESS.
 * Así 'calibración de cmm' pone primero los S1-5 / S5-85 / S1-459 de
 * calibración, aunque Revisión (S1-250, 1412 veces) sea más frecuente.
 */
function ordenarPorRelevancia(array $resultados, array $tokens, $accion) {
    $accion_norm = ($accion !== null && $accion !== '') ? normalizarTextoPlano($accion) : null;
    $tokens_norm = [];
    foreach ($tokens as $token) {
        $tn = normalizarTextoPlano($token);
        if ($tn !== '' && !in_array($tn, ['de', 'del', 'la', 'el', 'los', 'las', 'con', 'por', 'para', 'en', 'un', 'una', 'y'], true)) {
            $tokens_norm[$tn] = true;
        }
    }
    $tokens_norm = array_keys($tokens_norm);

    foreach ($resultados as $i => $fila) {
        $desc_norm = normalizarTextoPlano($fila['descripcion'] ?? '');
        $puntaje = 0;
        if ($accion_norm !== null && $accion_norm !== '') {
            if (strpos($desc_norm, $accion_norm) !== false) {
                $puntaje += 100;
                $primera = strtok($desc_norm, " \t");
                if ($primera === $accion_norm) $puntaje += 30;
            }
        }
        foreach ($tokens_norm as $token) {
            if (strpos($desc_norm, $token) !== false) $puntaje += 20;
        }
        $resultados[$i]['_puntaje_relevancia'] = $puntaje;
        $resultados[$i]['_total_veces'] = (int) ($fila['total_veces'] ?? 0);
    }

    usort($resultados, static function ($a, $b) {
        if ($a['_puntaje_relevancia'] !== $b['_puntaje_relevancia']) {
            return $b['_puntaje_relevancia'] <=> $a['_puntaje_relevancia'];
        }
        if ($a['_total_veces'] !== $b['_total_veces']) {
            return $b['_total_veces'] <=> $a['_total_veces'];
        }
        return strcmp((string) ($a['CDMESS'] ?? ''), (string) ($b['CDMESS'] ?? ''));
    });

    foreach ($resultados as $i => $fila) {
        unset($resultados[$i]['_puntaje_relevancia'], $resultados[$i]['_total_veces']);
    }
    return array_values($resultados);
}

/**
 * Función principal para obtener el historial con filtro holgado y análisis IA.
 * Devuelve 'noValido' si es un CDMESS que no existe/está inactivo, un array
 * de opciones, o un array vacío si no hay coincidencias.
 */
function obtenerOpcionesUnicasHistoricas($busqueda, $tipoBusqueda, $cliente, $conn) {
    $contexto = procesarContextoBusqueda($busqueda);
    $busqueda_limpia = $contexto['busqueda_limpia'];
    $termino = $contexto['termino'];
    // R1: acción principal (p. ej. 'calibracion'), o null si no hay.
    $accion = $contexto['accion'] ?? null;
    $texto_descripcion = preg_replace('/[^\p{L}\p{N}]+/u', ' ', trim($busqueda));
    $tokens_descripcion = is_string($texto_descripcion)
        ? preg_split('/\s+/u', trim($texto_descripcion), -1, PREG_SPLIT_NO_EMPTY)
        : [];
    if (!is_array($tokens_descripcion)) {
        $tokens_descripcion = [];
    }
    $tokens_sin_ruido = array_values(array_filter($tokens_descripcion, static function ($token) {
        return !in_array(strtolower($token), ['de', 'del', 'la', 'el', 'los', 'las', 'con', 'por', 'para', 'en'], true);
    }));
    if (!empty($tokens_sin_ruido)) {
        $tokens_descripcion = $tokens_sin_ruido;
    }
    $campo_descripcion = 'CONVERT(ci.DESCRIPCION USING utf8mb4) COLLATE utf8mb4_unicode_ci';

    // Validamos el cliente
    $filtrar_cliente = (!empty($cliente) && $cliente !== 'todos');
    $cliente_limpio = $filtrar_cliente ? $cliente : null;

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
            $types = 'ss';

            if ($filtrar_cliente) {
                $sql .= ' AND cotizaciones.IDCLTE = ? ';
                $params[] = $cliente_limpio;
                $types .= 's';
            }

            // Antes LIMIT 15: se sube a 25 para darle a la IA un pool más
            // amplio de candidatos entre los que elegir (más libertad real
            // de opciones, no solo margen para redactar).
            // La clave exacta va primero: el LIKE '%L1-9%' también trae
            // L1-90..L1-99, y sin esto la principal (es_sugerencia = 0) era la
            // más frecuente en vez de la que escribió el usuario. Va dentro de
            // MAX() para que sea válido con ONLY_FULL_GROUP_BY.
            $sql .= ' GROUP BY TRIM(CDMESS) ORDER BY MAX(TRIM(CDMESS) = ?) DESC, COUNT(*) DESC LIMIT 25';
            $params[] = $busqueda_limpia;
            $types .= 's';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return filtrarOpcionesConOllama($busqueda_limpia, $resultados_crudos);
        }
    }

    // Constructor dinámico para consultas generales
    $sql_base = "SELECT TRIM(ci.CDMESS) as CDMESS, MAX(ci.DESCRIPCION) as descripcion, ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) as precio_promedio, COUNT(*) as total_veces, t.STATUS
                 FROM cotizaciones_items ci
                 LEFT JOIN tarifario t ON ci.CDMESS = t.CDMESS
                 INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA ";

    $condiciones_globales = " AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0 AND ci.CDMESS IS NOT NULL AND ci.CDMESS != '' AND t.STATUS = 'ACTIVE' ";
    // Antes LIMIT 15: mismo criterio que la rama CDMESS, más candidatos
    // reales para que la IA elija entre ellos.
    $group_order = ' GROUP BY TRIM(ci.CDMESS), t.STATUS ORDER BY COUNT(*) DESC LIMIT 100';
    $parametros_orden = [];

    // Switch sin la barrera de "ci.TIPO = ?" para que encuentre equipos aunque parezcan servicios.
    // El 'default' cae en el mismo comportamiento de 'todas' para que un valor de
    // tipoBusqueda inesperado no genere una consulta SQL malformada (antes, un
    // $where_clause vacío producía "... WHERE  AND ci.PRECIO_VENTA..." inválido).
    switch ($tipoBusqueda) {
        case 'descripciones':
            if (empty($tokens_descripcion)) {
                $where_clause = "WHERE {$campo_descripcion} LIKE ?";
                $params = [$termino];
                $types = 's';
            } else {
                $where_clause = 'WHERE ' . implode(' AND ', array_fill(0, count($tokens_descripcion), "{$campo_descripcion} LIKE ?"));
                $params = array_map(static function ($token) {
                    return '%' . $token . '%';
                }, $tokens_descripcion);
                $types = str_repeat('s', count($tokens_descripcion));
                $group_order = " GROUP BY TRIM(ci.CDMESS), t.STATUS ORDER BY MAX({$campo_descripcion} LIKE ?) DESC, COUNT(*) DESC LIMIT 100";
                $parametros_orden[] = '%' . implode('%', $tokens_descripcion) . '%';
            }
            break;
        case 'modelo':
            $where_clause = 'WHERE ci.MODELO LIKE ?';
            $params = [$termino];
            $types = 's';
            break;
        case 'noSerie':
            $where_clause = 'WHERE ci.SERIE LIKE ?';
            $params = [$termino];
            $types = 's';
            break;
        case 'messTag':
            $where_clause = 'WHERE ci.MESSTAG LIKE ?';
            $params = [$termino];
            $types = 's';
            break;
        case 'IdEquipoCliente':
            $where_clause = 'WHERE ci.ID_EQ_CLIENTE LIKE ?';
            $params = [$termino];
            $types = 's';
            break;
        case 'codigos':
            $where_clause = 'WHERE ci.CDMESS LIKE ?';
            $params = [$termino];
            $types = 's';
            break;
        case 'todas':
        default:
            $condiciones_busqueda = ['MATCH(ci.DESCRIPCION) AGAINST(? IN BOOLEAN MODE)'];
            $params = [$busqueda_limpia];
            $types = 's';
            if (!empty($tokens_descripcion)) {
                $condiciones_busqueda[] = '(' . implode(' AND ', array_fill(0, count($tokens_descripcion), "{$campo_descripcion} LIKE ?")) . ')';
                foreach ($tokens_descripcion as $token) {
                    $params[] = '%' . $token . '%';
                    $types .= 's';
                }
            }
            $condiciones_busqueda[] = 'ci.CDMESS LIKE ?';
            $condiciones_busqueda[] = 'ci.MARCA LIKE ?';
            $condiciones_busqueda[] = 'ci.MODELO LIKE ?';
            $condiciones_busqueda[] = 'ci.SERIE LIKE ?';
            array_push($params, $termino, $termino, $termino, $termino);
            $types .= 'ssss';
            $where_clause = 'WHERE (' . implode(' OR ', $condiciones_busqueda) . ')';
            $group_order = " GROUP BY TRIM(ci.CDMESS), t.STATUS ORDER BY MAX({$campo_descripcion} LIKE ?) DESC, COUNT(*) DESC LIMIT 100";
            $parametros_orden[] = '%' . implode('%', $tokens_descripcion) . '%';
            break;
    }

    if ($filtrar_cliente) {
        $condiciones_globales .= ' AND c.IDCLTE = ? ';
        $params[] = $cliente_limpio;
        $types .= 's';
    }
    foreach ($parametros_orden as $parametro_orden) {
        $params[] = $parametro_orden;
        $types .= 's';
    }

    $sql_final = $sql_base . $where_clause . $condiciones_globales . $group_order;

    $stmt = $conn->prepare($sql_final);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // R1: reordenar el pool (hasta 100) por relevancia antes de recortar
    // a 25. Antes el ORDER BY COUNT(*) DESC dejaba fuera del top 25 a las
    // calibraciones, aunque existieran, porque Revisión/Movimiento se
    // cotizan mucho más veces. La frecuencia queda solo como desempate.
    $resultados_crudos = ordenarPorRelevancia($resultados_crudos, $tokens_descripcion, $accion);
    $resultados_crudos = array_slice($resultados_crudos, 0, 25);

    // La búsqueda semántica complementa el modo general; en "descripciones"
    // se buscan literalmente todos los términos para evitar resultados ajenos.
    if ($tipoBusqueda === 'todas') {
        $semanticos = buscarSemanticamente($busqueda, $conn, $cliente_limpio);
        $resultados_crudos = mezclarCandidatos($resultados_crudos, $semanticos, $accion);
    }

    return filtrarOpcionesConOllama($busqueda, $resultados_crudos, $accion);
}

/**
 * Trae estadísticas (min/max/avg en USD y MXN) del historial para un texto
 * de búsqueda dado. Usado por worker_ia.php como contexto principal para Ollama.
 * Con $idclte, solo considera cotizaciones de ese cliente (así las
 * coincidencias que ve la IA también son del cliente seleccionado).
 */
function obtenerHistorialMESS($busqueda, $idclte = null) {
    global $conn;

    $contexto = procesarContextoBusqueda($busqueda);
    $busqueda_limpia = $contexto['busqueda_limpia'];
    $tipo_val = $contexto['tipo'];
    $termino = $contexto['termino'];

    $params = [$termino, $termino, $termino, $termino, $termino, $termino, $termino, $tipo_val];
    $types = 'ssssssss';
    $join_cliente = '';
    $filtro_cliente = '';
    if ($idclte !== null) {
        $join_cliente = ' INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA';
        $filtro_cliente = ' AND c.IDCLTE = ?';
        $params[] = $idclte;
        $types .= 'i';
    }

    $sql = "SELECT ci.CDMESS, MAX(ci.DESCRIPCION) as DESCRIPCION,
            ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_VENTA,
            ROUND(MIN(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MIN,
            ROUND(MAX(ci.PRECIO_VENTA/ci.CANT), 2) AS PRECIO_MAX,
            ROUND(AVG(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN,
            ROUND(MIN(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN_MIN,
            ROUND(MAX(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS PRECIO_VENTA_MXN_MAX,
            COUNT(*) as TOTAL_VECES
        FROM cotizaciones_items ci{$join_cliente}
        WHERE (ci.DESCRIPCION LIKE ? OR ci.CDMESS LIKE ? OR ci.MARCA LIKE ? OR ci.MODELO LIKE ? OR ci.SERIE LIKE ? OR ci.MESSTAG LIKE ? OR ci.ID_EQ_CLIENTE LIKE ?)
        AND ci.TIPO = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0{$filtro_cliente}
        GROUP BY ci.CDMESS ORDER BY TOTAL_VECES DESC, ci.CDMESS LIMIT 10";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    // Reintento dinámico si no encuentra resultados con el tipo original
    if ($res->num_rows === 0) {
        $tipo_val = ($tipo_val === 'SERVICIO') ? 'EQUIPO' : 'SERVICIO';
        $params[7] = $tipo_val;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    }
    $stmt->close();

    $resultados = [];
    $alternativas = [];
    $detalle_ia = '';

    while ($row = $res->fetch_assoc()) {
        $resultados[] = [
            'cdmess' => $row['CDMESS'],
            'descripcion' => $row['DESCRIPCION'],
            'avg' => (float) $row['PRECIO_VENTA'],
            'min' => (float) $row['PRECIO_MIN'],
            'max' => (float) $row['PRECIO_MAX'],
            'avg_mxn' => (float) $row['PRECIO_VENTA_MXN'],
            'min_mxn' => (float) $row['PRECIO_VENTA_MXN_MIN'],
            'max_mxn' => (float) $row['PRECIO_VENTA_MXN_MAX'],
            'total' => (int) $row['TOTAL_VECES']
        ];

        $item_str = '[' . $row['CDMESS'] . '] ' . $row['DESCRIPCION'];
        $alternativas[] = $item_str;
        $detalle_ia .= "- $item_str: USD\$" . $row['PRECIO_VENTA'] . ' (min: $' . $row['PRECIO_MIN'] . ', max: $' . $row['PRECIO_MAX'] . ', veces: ' . $row['TOTAL_VECES'] . ') - MXN$' . $row['PRECIO_VENTA_MXN'] . ' (min: $' . $row['PRECIO_VENTA_MXN_MIN'] . ', max: $' . $row['PRECIO_VENTA_MXN_MAX'] . ")\n";
    }

    if (empty($resultados)) {
        return [
            'min' => 0, 'max' => 0, 'avg' => 0, 'min_mxn' => 0, 'max_mxn' => 0, 'avg_mxn' => 0,
            'cdmess' => 'S/C', 'detalle' => 'Sin historial', 'alternativas' => '', 'items' => []
        ];
    }

    $principal = $resultados[0];
    $coincidencias_str = implode(', ', array_slice($alternativas, 1, 4));

    return [
        'min' => $principal['min'], 'max' => $principal['max'], 'avg' => $principal['avg'],
        'min_mxn' => $principal['min_mxn'], 'max_mxn' => $principal['max_mxn'], 'avg_mxn' => $principal['avg_mxn'],
        'cdmess' => $principal['cdmess'], 'detalle' => $detalle_ia, 'alternativas' => $coincidencias_str,
        'items' => $resultados
    ];
}

/**
 * Trae el aprendizaje humano previo (correcciones de precio/descripción)
 * para un CDMESS o texto de búsqueda, para que la IA priorice el criterio
 * de los expertos por encima del promedio histórico crudo.
 */
/**
 * A4 — Aprendizaje humano gradual.
 * Antes: total_correcciones era solo el nº de filas del LIMIT (máx. 5 por
 * CDMESS, 2 por texto) y precio_sugerido era la media simple de los precios
 * humanos; el worker fijaba el precio exacto al 100%.
 * Ahora: total_correcciones es el conteo REAL (COUNT(*) con el mismo filtro,
 * sin LIMIT) para que el peso por nº crezca con cada corrección; se lee
 * fecha_registro de cada corrección (fecha en que el ítem quedó completado;
 * la validación de A7 no actualiza esa columna, así que es la fecha del
 * procesamiento, que es cuando se valida, normalmente el mismo día) y se
 * calcula precio_humano_ponderado con pesoPorAntiguedad() de A3 (<6m 1.0,
 * 6-12m 0.75, 12-24m 0.5, >24m 0.25, sin fecha 0.5). Las filas para el
 * precio siguen siendo las 5 (CDMESS) / 2 (texto) más recientes, que es lo
 * que cabe en el prompt; el conteo real solo alimenta el peso.
 * fecha_ultima_correccion es la fecha_registro de la fila más reciente
 * (ORDER BY id DESC). Devuelve [] si no hay correcciones. No se quita
 * ningún campo existente.
 */
function obtenerAprendizajeHumano($entrada, $conn) {
    $es_cdmess = preg_match('/^[SL]\d+/i', $entrada);

    if ($es_cdmess) {
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico, respuesta, fecha_registro
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND cdmess_historico = ? AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                ORDER BY id DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $entrada);
        // A4: conteo real de correcciones (sin LIMIT) para el peso por nº.
        $sql_count = "SELECT COUNT(*) AS total FROM cola_procesamiento
                      WHERE estatus = 'completado' AND cdmess_historico = ? AND precio_usuario > 0
                        AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param('s', $entrada);
    } else {
        $like = '%' . $entrada . '%';
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico, respuesta, fecha_registro
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                  AND entrada_usuario LIKE ?
                ORDER BY id DESC LIMIT 2";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $like);
        $sql_count = "SELECT COUNT(*) AS total FROM cola_procesamiento
                      WHERE estatus = 'completado' AND precio_usuario > 0
                        AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                        AND entrada_usuario LIKE ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param('s', $like);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows == 0) {
        $stmt->close();
        if (isset($stmt_count)) $stmt_count->close();
        return [];
    }

    // A4: conteo real (puede ser mayor que las filas del LIMIT).
    $total_correcciones = (int) $res->num_rows;
    if (isset($stmt_count)) {
        $stmt_count->execute();
        $row_count = $stmt_count->get_result()->fetch_assoc();
        if ($row_count && isset($row_count['total'])) $total_correcciones = (int) $row_count['total'];
        $stmt_count->close();
    }

    $aprendizaje = [
        'total_correcciones' => $total_correcciones,
        'precios_usuario' => [],
        'categoria_principal' => null,
        'nota_humana' => null,
        'alerta_descripcion' => false,
        'ejemplos_texto' => "\n--- APRENDIZAJE DE EXPERTOS MESS (Prioridad Alta) ---\n",
        // A4: precio humano ponderado por recencia y fecha de la
        // corrección más reciente (se rellenan abajo).
        'precio_humano_ponderado' => null,
        'fecha_ultima_correccion' => null
    ];

    $categorias = [];
    $correcciones = [];

    while ($row = $res->fetch_assoc()) {
        if ($row['precio_usuario'] > 0) {
            $aprendizaje['precios_usuario'][] = $row['precio_usuario'];
            $correcciones[] = ['precio' => (float) $row['precio_usuario'], 'fecha' => $row['fecha_registro'] ?? null];
        }

        $cat = $row['categoria_rechazo'];
        $categorias[$cat] = ($categorias[$cat] ?? 0) + 1;

        if ($cat == 'Descripcion incorrecta') {
            $aprendizaje['alerta_descripcion'] = true;
            $aprendizaje['nota_humana'] = $row['respuesta'] ?? 'Descripción incorrecta reportada';
        }

        $aprendizaje['ejemplos_texto'] .= 'Usuario buscó: ' . $row['entrada_usuario'] . "\n";
        $aprendizaje['ejemplos_texto'] .= 'Precio corregido: $' . ($row['precio_usuario'] ?? 'N/A') . "\n";
        $aprendizaje['ejemplos_texto'] .= 'Nota: ' . ($row['respuesta'] ?? 'Sin comentarios') . "\n";
        $aprendizaje['ejemplos_texto'] .= 'Categoría: ' . $cat . "\n\n";
    }
    $stmt->close();

    arsort($categorias);
    $aprendizaje['categoria_principal'] = key($categorias);

    if (!empty($aprendizaje['precios_usuario'])) {
        $aprendizaje['precio_sugerido'] = round(array_sum($aprendizaje['precios_usuario']) / count($aprendizaje['precios_usuario']), 2);
    }

    // A4: precio humano ponderado por recencia (mismos pesos que A3) y
    // fecha de la corrección más reciente (primera fila, id DESC).
    if (!empty($correcciones)) {
        $suma_pesos = 0.0;
        $suma_ponderada = 0.0;
        foreach ($correcciones as $c) {
            $peso = pesoPorAntiguedad($c['fecha']);
            $suma_pesos += $peso;
            $suma_ponderada += $c['precio'] * $peso;
        }
        if ($suma_pesos > 0) {
            $aprendizaje['precio_humano_ponderado'] = round($suma_ponderada / $suma_pesos, 2);
        }
        $aprendizaje['fecha_ultima_correccion'] = $correcciones[0]['fecha'] ?? null;
    }
    if ($aprendizaje['precio_humano_ponderado'] === null && isset($aprendizaje['precio_sugerido'])) {
        $aprendizaje['precio_humano_ponderado'] = $aprendizaje['precio_sugerido'];
    }

    return $aprendizaje;
}

/**
 * Estadísticas de precio de UNA clave CDMESS exacta (no búsqueda por texto).
 * Con $idclte, solo cuenta cotizaciones de ese cliente. Es lo que worker_ia.php
 * muestra como rango/promedio/conteo de cada ítem del proyecto.
 *
 * Antes el worker sacaba esto de obtenerHistorialMESS() (búsqueda por texto,
 * top 10): si la clave del ítem no quedaba en ese top, caía a datos de otra
 * clave con total 0, y nunca respetaba el cliente seleccionado.
 */
function obtenerEstadisticasCDMESS($cdmess, $conn, $idclte = null) {
    // FECHA > '1900-01-01' deja fuera fechas en cero ('0000-00-00'), que
    // si no saldrían como año "-0001" en el detalle del cálculo.
    $sql = "SELECT ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) AS avg_usd,
                   ROUND(MIN(ci.PRECIO_VENTA/ci.CANT), 2) AS min_usd,
                   ROUND(MAX(ci.PRECIO_VENTA/ci.CANT), 2) AS max_usd,
                   ROUND(AVG(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS avg_mxn,
                   ROUND(MIN(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS min_mxn,
                   ROUND(MAX(ci.PRECIO_VENTA_MXN/ci.CANT), 2) AS max_mxn,
                   COUNT(*) AS total,
                   MIN(CASE WHEN c.FECHA > '1900-01-01' THEN c.FECHA END) AS fecha_min,
                   MAX(CASE WHEN c.FECHA > '1900-01-01' THEN c.FECHA END) AS fecha_max
            FROM cotizaciones_items ci
            INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA
            WHERE ci.CDMESS = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0";
    $params = [trim($cdmess)];
    $types = 's';

    if ($idclte !== null) {
        $sql .= ' AND c.IDCLTE = ?';
        $params[] = $idclte;
        $types .= 'i';
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $total = (int) ($row['total'] ?? 0);

    return [
        'min' => (float) ($row['min_usd'] ?? 0),
        'max' => (float) ($row['max_usd'] ?? 0),
        'avg' => (float) ($row['avg_usd'] ?? 0),
        'total' => $total,
        'min_mxn' => (float) ($row['min_mxn'] ?? 0),
        'max_mxn' => (float) ($row['max_mxn'] ?? 0),
        'avg_mxn' => (float) ($row['avg_mxn'] ?? 0),
        'total_mxn' => $total,
        'fecha_min' => $row['fecha_min'] ?? null,
        'fecha_max' => $row['fecha_max'] ?? null
    ];
}

/**
 * Percentil con interpolación lineal sobre un array ya ordenado de menor a mayor.
 */
function calcularPercentil(array $valores_ordenados, float $percentil) {
    $n = count($valores_ordenados);
    if ($n === 0) return null;
    if ($n === 1) return (float) $valores_ordenados[0];
    $pos = ($percentil / 100) * ($n - 1);
    $base = (int) floor($pos);
    $resto = $pos - $base;
    if ($base + 1 >= $n) return (float) $valores_ordenados[$n - 1];
    return (float) ($valores_ordenados[$base] + $resto * ($valores_ordenados[$base + 1] - $valores_ordenados[$base]));
}

/**
 * A3 — Limpieza de atípicos por IQR sobre muestras
 * [['precio_usd'=>..., 'precio_mxn'=>..., 'fecha'=>..., 'folio'=>...], ...].
 * Con n>=8: Q1=P25, Q3=P75, IQR=Q3-Q1; se descartan las muestras fuera de
 * [Q1-1.5*IQR, Q3+1.5*IQR]. Con n<8 no se descarta nada. Si tras limpiar
 * quedaran menos de 3 muestras, se conservan las originales y se marca
 * que no se limpió. Si IQR=0 los límites colapsan a Q1=Q3 y solo sobreviven
 * los valores iguales a ese precio (con el mismo rescate de <3 muestras).
 * Devuelve ['muestras'=>..., 'n_atipicos'=>int, 'aplicada'=>bool].
 */
function limpiarAtipicosIQR(array $muestras) {
    $n = count($muestras);
    if ($n < 8) return ['muestras' => $muestras, 'n_atipicos' => 0, 'aplicada' => false];
    $precios = array_column($muestras, 'precio_usd');
    sort($precios, SORT_NUMERIC);
    $q1 = calcularPercentil($precios, 25);
    $q3 = calcularPercentil($precios, 75);
    $iqr = $q3 - $q1;
    $lim_inf = $q1 - 1.5 * $iqr;
    $lim_sup = $q3 + 1.5 * $iqr;
    $limpias = [];
    foreach ($muestras as $m) {
        if ($m['precio_usd'] >= $lim_inf && $m['precio_usd'] <= $lim_sup) $limpias[] = $m;
    }
    if (count($limpias) < 3) return ['muestras' => $muestras, 'n_atipicos' => 0, 'aplicada' => false];
    return ['muestras' => $limpias, 'n_atipicos' => $n - count($limpias), 'aplicada' => true];
}

/**
 * A3 — Peso por antigüedad de una muestra (fecha 'Y-m-d').
 * Regla simple y documentada: <6 meses => 1.0; 6-12 meses => 0.75;
 * 12-24 meses => 0.5; >24 meses => 0.25. Sin fecha válida => 0.5.
 * Una fecha futura se trata como reciente (1.0). Se mide en días
 * (180/365/730) para no depender del día del mes.
 */
function pesoPorAntiguedad($fecha, $hoy = null) {
    if (!is_string($fecha) || $fecha === '' || $fecha === '0000-00-00') return 0.5;
    try {
        $f = new DateTime($fecha);
    } catch (Exception $e) {
        return 0.5;
    }
    $hoy = ($hoy instanceof DateTime) ? $hoy : new DateTime();
    if ($f > $hoy) return 1.0;
    $dias = (int) $hoy->diff($f)->format('%a');
    if ($dias < 180) return 1.0;
    if ($dias < 365) return 0.75;
    if ($dias < 730) return 0.5;
    return 0.25;
}

/**
 * A3 — Mediana ponderada: se ordenan los pares por precio y se toma el
 * primer precio cuya masa acumulada llega a la mitad del peso total.
 * $pares es [['precio'=>float, 'peso'=>float], ...]. Null si no hay pesos.
 */
function calcularMedianaPonderada(array $pares) {
    $validos = [];
    foreach ($pares as $p) {
        if (($p['peso'] ?? 0) > 0) $validos[] = $p;
    }
    if (empty($validos)) return null;
    usort($validos, static function ($a, $b) {
        return $a['precio'] <=> $b['precio'];
    });
    $total = 0.0;
    foreach ($validos as $p) $total += $p['peso'];
    $acum = 0.0;
    foreach ($validos as $p) {
        $acum += $p['peso'];
        if ($acum >= $total / 2) return (float) $p['precio'];
    }
    return (float) $validos[count($validos) - 1]['precio'];
}

/**
 * A1 — Contexto estadístico completo de UN CDMESS exacto para Ollama.
 * Devuelve mediana, P25/P75, desviación estándar, nº de muestras, fechas
 * (min/max/última), tendencia de 12 meses y las 5 referencias más recientes
 * con folio, fecha y precio unitario USD/MXN.
 * A3 — Todas esas estadísticas se calculan sobre las muestras LIMPIAS:
 * atípicos descartados por IQR (n>=8) y promedio/mediana ponderados por
 * antigüedad (lo reciente pesa más). El retorno añade n_original, n_limpio,
 * n_atipicos, min/max_limpio, min/max_original, promedio_ponderado y
 * mediana_ponderada, sin quitar ningún campo existente.
 * El precio unitario es PRECIO_VENTA/CANT (y PRECIO_VENTA_MXN/CANT); se
 * excluyen filas con CANT <= 0 o precio unitario USD <= 0.
 * Con $idclte, solo cuenta cotizaciones de ese cliente.
 * Devuelve null si el CDMESS está vacío, es 'S/C' o no tiene muestras válidas.
 */
function obtenerContextoPrecio($cdmess, $conn, $idclte = null) {
    $cdmess = trim((string) $cdmess);
    if ($cdmess === '' || strtoupper($cdmess) === 'S/C') return null;

    $sql = "SELECT (ci.PRECIO_VENTA/ci.CANT) AS precio_usd,
                   (ci.PRECIO_VENTA_MXN/ci.CANT) AS precio_mxn,
                   c.FECHA AS fecha, ci.IDCOTIZA AS folio
            FROM cotizaciones_items ci
            INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA
            WHERE ci.CDMESS = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0";
    $params = [$cdmess];
    $types = 's';

    if ($idclte !== null) {
        $sql .= ' AND c.IDCLTE = ?';
        $params[] = $idclte;
        $types .= 'i';
    }

    // Las 5 primeras filas son las referencias más recientes que se mandan a Ollama.
    $sql .= ' ORDER BY c.FECHA DESC, ci.id_item DESC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // A3: se trabaja con muestras (precio + fecha) para poder limpiar
    // atípicos y ponderar por antigüedad. Las filas ya vienen ordenadas
    // por FECHA DESC, así que las 5 primeras limpias son las referencias
    // más recientes que se mandan a Ollama.
    $muestras = [];
    foreach ($filas as $fila) {
        $usd = (float) ($fila['precio_usd'] ?? 0);
        if ($usd <= 0) continue;
        $muestras[] = [
            'precio_usd' => $usd,
            'precio_mxn' => (float) ($fila['precio_mxn'] ?? 0),
            'fecha' => $fila['fecha'] ?? null,
            'folio' => $fila['folio'] ?? ''
        ];
    }

    $n_original = count($muestras);
    if ($n_original === 0) return null;

    // A3: limpieza IQR (solo con n>=8; si quedaran <3, se revierte y no
    // se descarta nada). min/max originales se conservan en el retorno.
    $limpieza = limpiarAtipicosIQR($muestras);
    $muestras_limpias = $limpieza['muestras'];
    $n_atipicos = $limpieza['n_atipicos'];

    $precios_usd = [];
    $precios_mxn = [];
    $fechas = [];
    $referencias = [];
    foreach ($muestras_limpias as $m) {
        $precios_usd[] = $m['precio_usd'];
        if ($m['precio_mxn'] > 0) $precios_mxn[] = $m['precio_mxn'];
        $fecha = $m['fecha'];
        if ($fecha && $fecha !== '0000-00-00') $fechas[] = $fecha;
        if (count($referencias) < 5) {
            $referencias[] = [
                'folio' => $m['folio'],
                'fecha' => $fecha,
                'precio_usd' => round($m['precio_usd'], 2),
                'precio_mxn' => $m['precio_mxn'] > 0 ? round($m['precio_mxn'], 2) : null
            ];
        }
    }

    $n = count($precios_usd);
    if ($n === 0) return null;

    sort($precios_usd, SORT_NUMERIC);
    $mediana = calcularPercentil($precios_usd, 50);
    $p25 = calcularPercentil($precios_usd, 25);
    $p75 = calcularPercentil($precios_usd, 75);
    $avg = array_sum($precios_usd) / $n;
    $varianza = 0.0;
    foreach ($precios_usd as $p) $varianza += ($p - $avg) * ($p - $avg);
    $desviacion = sqrt($varianza / $n);

    // A3: ponderación por antigüedad sobre las muestras LIMPIAS.
    // Peso: <6 meses 1.0, 6-12 meses 0.75, 12-24 meses 0.5, >24 meses 0.25;
    // sin fecha válida, 0.5. Promedio ponderado y mediana ponderada.
    $hoy = new DateTime();
    $suma_pesos = 0.0;
    $suma_ponderada = 0.0;
    $pares_ponderados = [];
    foreach ($muestras_limpias as $m) {
        $peso = pesoPorAntiguedad($m['fecha'], $hoy);
        $suma_pesos += $peso;
        $suma_ponderada += $m['precio_usd'] * $peso;
        $pares_ponderados[] = ['precio' => $m['precio_usd'], 'peso' => $peso];
    }
    $promedio_ponderado = $suma_pesos > 0 ? $suma_ponderada / $suma_pesos : $avg;
    $mediana_ponderada = calcularMedianaPonderada($pares_ponderados);
    if ($mediana_ponderada === null) $mediana_ponderada = $mediana;
    sort($fechas);
    $fecha_min = $fechas[0] ?? null;
    $fecha_max = !empty($fechas) ? end($fechas) : null;

    // Tendencia 12 meses: variación % entre el promedio del último
    // semestre y el del semestre anterior. Null si algún semestre tiene
    // menos de 2 muestras.
    $tendencia_12m = null;
    $hoy = new DateTime();
    $limite_12m = (clone $hoy)->modify('-12 months')->format('Y-m-d');
    $limite_6m = (clone $hoy)->modify('-6 months')->format('Y-m-d');
    $recientes = [];
    $anteriores = [];
    // A3: la tendencia también se calcula sobre las muestras LIMPIAS,
    // para que un atípico no infle el promedio de un semestre.
    foreach ($muestras_limpias as $fila) {
        $usd = (float) ($fila['precio_usd'] ?? 0);
        $fecha = (string) ($fila['fecha'] ?? '');
        if ($usd <= 0 || $fecha === '' || $fecha === '0000-00-00') continue;
        if ($fecha >= $limite_12m && $fecha < $limite_6m) $anteriores[] = $usd;
        elseif ($fecha >= $limite_6m) $recientes[] = $usd;
    }
    if (count($recientes) >= 2 && count($anteriores) >= 2) {
        $avg_reciente = array_sum($recientes) / count($recientes);
        $avg_anterior = array_sum($anteriores) / count($anteriores);
        if ($avg_anterior > 0) $tendencia_12m = round(($avg_reciente - $avg_anterior) / $avg_anterior * 100, 2);
    }

    return [
        'cdmess' => $cdmess,
        // A3: n_muestras, min, max, avg, mediana, P25/P75 y desviación ya
        // son sobre las muestras LIMPIAS; n_original/min_original/max_original
        // conservan el conteo y los extremos antes de limpiar.
        'n_muestras' => $n,
        'n_original' => $n_original,
        'n_limpio' => $n,
        'n_atipicos' => $n_atipicos,
        'mediana' => round($mediana, 2),
        'p25' => round($p25, 2),
        'p75' => round($p75, 2),
        'desviacion' => round($desviacion, 2),
        'min' => round($precios_usd[0], 2),
        'max' => round($precios_usd[$n - 1], 2),
        'min_limpio' => round($precios_usd[0], 2),
        'max_limpio' => round($precios_usd[$n - 1], 2),
        'min_original' => round(min(array_column($muestras, 'precio_usd')), 2),
        'max_original' => round(max(array_column($muestras, 'precio_usd')), 2),
        'avg' => round($avg, 2),
        'promedio_ponderado' => round($promedio_ponderado, 2),
        'mediana_ponderada' => round($mediana_ponderada, 2),
        'avg_mxn' => !empty($precios_mxn) ? round(array_sum($precios_mxn) / count($precios_mxn), 2) : 0,
        'fecha_min' => $fecha_min,
        'fecha_max' => $fecha_max,
        'fecha_ultima' => $fecha_max,
        'tendencia_12m' => $tendencia_12m,
        'referencias' => $referencias
    ];
}

/**
 * Folio y fecha de la cotización más reciente que sirvió de base para un CDMESS.
 * Con $idclte, la más reciente de ese cliente.
 */
function obtenerFolioBase($cdmess, $conn, $idclte = null) {
    if (empty($cdmess)) return ['folio' => '', 'fecha' => ''];

    $sql = "SELECT ci.IDCOTIZA AS folio, c.FECHA AS fecha
            FROM cotizaciones_items ci
            LEFT JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA
            WHERE ci.CDMESS = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0";
    $params = [$cdmess];
    $types = 's';

    if ($idclte !== null) {
        $sql .= ' AND c.IDCLTE = ?';
        $params[] = $idclte;
        $types .= 'i';
    }

    $sql .= ' ORDER BY ci.id_item DESC LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'folio' => $row['folio'] ?? '',
        'fecha' => $row['fecha'] ?? ''
    ];
}
