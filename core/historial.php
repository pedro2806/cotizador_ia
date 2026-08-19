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
 * Función principal para obtener el historial con filtro holgado y análisis IA.
 * Devuelve 'noValido' si es un CDMESS que no existe/está inactivo, un array
 * de opciones, o un array vacío si no hay coincidencias.
 */
function obtenerOpcionesUnicasHistoricas($busqueda, $tipoBusqueda, $cliente, $conn) {
    $contexto = procesarContextoBusqueda($busqueda);
    $busqueda_limpia = $contexto['busqueda_limpia'];
    $termino = $contexto['termino'];

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
            $sql .= ' GROUP BY TRIM(CDMESS) ORDER BY COUNT(*) DESC LIMIT 25';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return filtrarOpcionesConOllama($busqueda_limpia, $resultados_crudos);
        }
    }

    // Constructor dinámico para consultas generales
    $sql_base = "SELECT TRIM(ci.CDMESS) as CDMESS, MAX(ci.DESCRIPCION) as descripcion, ROUND(AVG(ci.PRECIO_VENTA/ci.CANT), 2) as precio_promedio, t.STATUS
                 FROM cotizaciones_items ci
                 LEFT JOIN tarifario t ON ci.CDMESS = t.CDMESS
                 INNER JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA ";

    $condiciones_globales = " AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0 AND ci.CDMESS IS NOT NULL AND ci.CDMESS != '' AND t.STATUS = 'ACTIVE' ";
    // Antes LIMIT 15: mismo criterio que la rama CDMESS, más candidatos
    // reales para que la IA elija entre ellos.
    $group_order = ' GROUP BY TRIM(ci.CDMESS), t.STATUS ORDER BY COUNT(*) DESC LIMIT 25';

    // Switch sin la barrera de "ci.TIPO = ?" para que encuentre equipos aunque parezcan servicios.
    // El 'default' cae en el mismo comportamiento de 'todas' para que un valor de
    // tipoBusqueda inesperado no genere una consulta SQL malformada (antes, un
    // $where_clause vacío producía "... WHERE  AND ci.PRECIO_VENTA..." inválido).
    switch ($tipoBusqueda) {
        case 'descripciones':
            $where_clause = 'WHERE ci.DESCRIPCION LIKE ?';
            $params = [$termino];
            $types = 's';
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
            $where_clause = 'WHERE (MATCH(ci.DESCRIPCION) AGAINST(? IN BOOLEAN MODE) OR ci.CDMESS LIKE ? OR ci.MARCA LIKE ? OR ci.MODELO LIKE ? OR ci.SERIE LIKE ?)';
            $params = [$busqueda_limpia, $termino, $termino, $termino, $termino];
            $types = 'sssss';
            break;
    }

    if ($filtrar_cliente) {
        $condiciones_globales .= ' AND c.IDCLTE = ? ';
        $params[] = $cliente_limpio;
        $types .= 's';
    }

    $sql_final = $sql_base . $where_clause . $condiciones_globales . $group_order;

    $stmt = $conn->prepare($sql_final);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultados_crudos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Búsqueda semántica: solo tiene sentido cuando se busca por texto
    // libre/descripción. Para búsquedas por código exacto, serie, modelo,
    // etc. el usuario ya sabe qué está buscando literalmente, y "parecido
    // en significado" no aporta ahí (y podría meter ruido).
    if (in_array($tipoBusqueda, ['todas', 'descripciones'], true)) {
        $semanticos = buscarSemanticamente($busqueda_limpia, $conn);
        $resultados_crudos = mezclarCandidatos($resultados_crudos, $semanticos);
    }

    return filtrarOpcionesConOllama($busqueda_limpia, $resultados_crudos);
}

/**
 * Trae estadísticas (min/max/avg en USD y MXN) del historial para un texto
 * de búsqueda dado. Usado por worker_ia.php como contexto principal para Ollama.
 */
function obtenerHistorialMESS($busqueda) {
    global $conn;

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
    $stmt->bind_param('ssssssss', $termino, $termino, $termino, $termino, $termino, $termino, $termino, $tipo_val);
    $stmt->execute();
    $res = $stmt->get_result();

    // Reintento dinámico si no encuentra resultados con el tipo original
    if ($res->num_rows === 0) {
        $tipo_val = ($tipo_val === 'SERVICIO') ? 'EQUIPO' : 'SERVICIO';
        $stmt->bind_param('ssssssss', $termino, $termino, $termino, $termino, $termino, $termino, $termino, $tipo_val);
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
function obtenerAprendizajeHumano($entrada, $conn) {
    $es_cdmess = preg_match('/^[SL]\d+/i', $entrada);

    if ($es_cdmess) {
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico, respuesta
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND cdmess_historico = ? AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                ORDER BY id DESC LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $entrada);
    } else {
        $like = '%' . $entrada . '%';
        $sql = "SELECT entrada_usuario, precio_usuario, categoria_rechazo, cdmess_historico, respuesta
                FROM cola_procesamiento
                WHERE estatus = 'completado' AND precio_usuario > 0
                  AND categoria_rechazo IS NOT NULL AND categoria_rechazo != 'Acepta precio IA'
                  AND entrada_usuario LIKE ?
                ORDER BY id DESC LIMIT 2";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $like);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows == 0) {
        $stmt->close();
        return [];
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
        if ($row['precio_usuario'] > 0) {
            $aprendizaje['precios_usuario'][] = $row['precio_usuario'];
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

    return $aprendizaje;
}

/**
 * Folio y fecha de la cotización más reciente que sirvió de base para un CDMESS.
 */
function obtenerFolioBase($cdmess, $conn) {
    if (empty($cdmess)) return ['folio' => '', 'fecha' => ''];

    $sql = "SELECT ci.IDCOTIZA AS folio, c.FECHA AS fecha
            FROM cotizaciones_items ci
            LEFT JOIN cotizaciones c ON ci.IDCOTIZA = c.IDCOTIZA
            WHERE ci.CDMESS = ? AND ci.PRECIO_VENTA > 0 AND ci.CANT > 0
            ORDER BY ci.id_item DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $cdmess);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'folio' => $row['folio'] ?? '',
        'fecha' => $row['fecha'] ?? ''
    ];
}
