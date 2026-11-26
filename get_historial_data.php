<?php
/**
 * ========================================================================
 * get_historial_data.php — Endpoint JSON para historial_precios.php
 * ========================================================================
 * Histórico de TODOS los ítems cotizados (todos los proyectos) + datos
 * para el dashboard de resultados. La vista pide una sola vez y recibe:
 *
 *   {
 *     "proyectos":   [{id_proyecto, solicitante, total, fecha_reciente}, ...],
 *                    // ordenados por id_proyecto DESC (el más reciente
 *                    // primero); solicitante es u.nombre o null ("Sin registro")
 *     "usuarios":    [{id_us_registro, solicitante, total}, ...],
 *                    // usuarios distintos con ítems, ordenados por nombre;
 *                    // solo con solicitante real (los sin usuario van en
 *                    // sin_registro_total)
 *     "sin_registro_total": 0, // ítems sin usuario (id NULL o sin fila
 *                    // en usuarios)
 *     "dashboard":   { total, completados, pendientes, requieren_revision,
 *                       validacion_pendiente, con_precio_validado,
 *                       propuesta_invalida, propuesta_valida,
 *                       por_origen: [{origen, n, pct}], 
 *                       top_codigos: [{codigo, n, precio_promedio_sugerido}],
 *                       ultimos: [{id, id_proyecto, fecha_registro, codigo, precio}] },
 *     "items":       [ {id, id_proyecto, entrada_usuario, estatus,
 *                       es_sugerencia, propuesta_ia (decodificada o null),
 *                       propuesta_valida, fecha_registro, solicitante, ...} ],
 *     "paginacion":  {pagina, por_pagina, total, total_paginas}
 *   }
 *
 * Orden de la tabla: id_proyecto DESC (el formato PROY-YYYYMMDD-NNNN
 * ordena cronológicamente como texto) y dentro del proyecto id ASC
 * (mismo orden que el monitor), para agrupar por proyecto en la vista.
 *
 * El solicitante se resuelve con LEFT JOIN usuarios u ON
 * u.id_usuario = c.id_us_registro (el id es $_SESSION['usuario_id']
 * al subir la carga; ver procesar_carga.php). Si no hay usuario
 * (id NULL o sin fila en usuarios), solicitante es null y la vista
 * muestra "Sin registro". Un proyecto lo crea una sola carga de un
 * solo usuario, así que MAX(u.nombre) por proyecto es determinista.
 *
 * Filtros (GET, todos opcionales y combinables):
 *   proyecto, cdmess (LIKE sobre el código cotizado), desde / hasta
 *   (fecha_registro), usuario (id de usuario entero, o el literal
 *   'sin_registro' para los ítems sin usuario), q (texto en
 *   entrada_usuario), pagina, por_pagina (10 por defecto; permitidos
 *   10/25/50/100, cualquier otro se ignora).
 *   (Los filtros de origen, estatus, solo_revision y solo_validacion
 *   se quitaron el 2026-11-26 a petición de Pedro; el dashboard sigue
 *   mostrando su desglose por origen y sus tarjetas.)
 *
 * El dashboard respeta los filtros de proyecto, fechas y usuario; si
 * no hay filtro, es global. La lista de proyectos se acota por fechas
 * y usuario (no por el propio filtro de proyecto) y la lista de
 * usuarios por proyecto y fechas (no por el propio filtro de usuario).
 * La tabla sí aplica todos los filtros.
 *
 * Seguridad: misma protección de sesión que get_proyecto_data.php y el
 * monitor (401 sin usuario). Todo con sentencias preparadas.
 *
 * Las filas con propuesta_ia inválido (hoy 17 conocidas) se cuentan aparte
 * como "propuesta_invalida" / "sin propuesta válida" sin romper ninguna
 * consulta: los agregados JSON usan JSON_VALID(propuesta_ia) y solo se
 * hace JSON_EXTRACT donde el JSON es válido.
 *
 * Las funciones de abajo son puras (o solo dependen de $conn) para poder
 * probarlas en CLI sin sesión ni BD real: la entrada web solo corre
 * cuando php_sapi_name() !== 'cli' (ver el final del archivo).
 */

// Orígenes de precio conocidos (los que escribe el worker en
// propuesta_ia.origen_precio). 'sin_origen' = JSON válido sin la clave;
// 'sin_propuesta' = sin propuesta_ia válida (NULL o JSON inválido).
const HISTORIAL_ORIGENES = ['ia', 'promedio', 'validado', 'humano', 'mixto'];
const HISTORIAL_POR_PAGINA = 10;
const HISTORIAL_POR_PAGINA_PERMITIDOS = [10, 25, 50, 100];

/**
 * Normaliza los filtros que vienen por GET: recorta texto, valida fechas
 * (Y-m-d) y el usuario (entero > 0 o el literal 'sin_registro'), y acota
 * la paginación. Todo lo inválido o vacío se vuelve null (sin filtro).
 * Las claves de filtros quitados (origen, estatus, solo_revision,
 * solo_validacion) ya no se leen: si vienen por GET se ignoran.
 */
function historialNormalizarFiltros(array $get) {
    $f = [];
    $f['proyecto'] = isset($get['proyecto']) ? trim((string) $get['proyecto']) : '';
    if ($f['proyecto'] === '') $f['proyecto'] = null;

    $f['cdmess'] = isset($get['cdmess']) ? trim((string) $get['cdmess']) : '';
    if ($f['cdmess'] === '') $f['cdmess'] = null;

    $f['desde'] = historialFechaValida($get['desde'] ?? null);
    $f['hasta'] = historialFechaValida($get['hasta'] ?? null);

    // Solicitante: id de usuario entero (> 0) o el literal
    // 'sin_registro' (ítems sin usuario). Cualquier otro valor se ignora.
    $f['usuario'] = null;
    if (isset($get['usuario'])) {
        $u = trim((string) $get['usuario']);
        if ($u === 'sin_registro') {
            $f['usuario'] = 'sin_registro';
        } elseif (ctype_digit($u) && (int) $u > 0) {
            $f['usuario'] = (int) $u;
        }
    }

    $f['q'] = isset($get['q']) ? trim((string) $get['q']) : '';
    if ($f['q'] === '') $f['q'] = null;

    $f['pagina'] = max(1, intval($get['pagina'] ?? 1));
    // Tamaño de página: solo se aceptan 10/25/50/100; cualquier otro
    // valor (o su ausencia) usa el valor por defecto (10).
    $f['por_pagina'] = intval($get['por_pagina'] ?? HISTORIAL_POR_PAGINA);
    if (!in_array($f['por_pagina'], HISTORIAL_POR_PAGINA_PERMITIDOS, true)) $f['por_pagina'] = HISTORIAL_POR_PAGINA;

    return $f;
}

function historialFechaValida($v) {
    if ($v === null || $v === '') return null;
    $v = trim((string) $v);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/**
 * Arma el WHERE con sentencias preparadas. Devuelve
 * [sql_sin_where, tipos, params].
 *
 * $ambitoSolo = true: proyecto + fechas + usuario (el ámbito del
 * dashboard; la lista de proyectos y la de usuarios se arman con una
 * copia de $f que anula su propio filtro antes de llamar aquí).
 * $ambitoSolo = false: todos los filtros (la tabla).
 * El orden de las condiciones es fijo porque el orden de los parámetros
 * ligados va con él: proyecto, desde, hasta, usuario, cdmess, q.
 */
function historialCondiciones(array $f, $ambitoSolo) {
    $conds = [];
    $tipos = '';
    $params = [];

    if (!empty($f['proyecto'])) {
        $conds[] = 'c.id_proyecto = ?';
        $tipos .= 's';
        $params[] = $f['proyecto'];
    }
    if (!empty($f['desde'])) {
        $conds[] = 'DATE(c.fecha_registro) >= ?';
        $tipos .= 's';
        $params[] = $f['desde'];
    }
    if (!empty($f['hasta'])) {
        $conds[] = 'DATE(c.fecha_registro) <= ?';
        $tipos .= 's';
        $params[] = $f['hasta'];
    }
    if (!empty($f['usuario'])) {
        if ($f['usuario'] === 'sin_registro') {
            // Sin usuario: id NULL o sin fila en usuarios (el LEFT JOIN
            // deja u.nombre en NULL; también vale nombre vacío).
            $conds[] = "(u.nombre IS NULL OR u.nombre = '')";
        } else {
            $conds[] = 'c.id_us_registro = ?';
            $tipos .= 'i';
            $params[] = (int) $f['usuario'];
        }
    }

    if ($ambitoSolo) {
        return [$conds ? 'WHERE ' . implode(' AND ', $conds) : '', $tipos, $params];
    }

    if (!empty($f['cdmess'])) {
        // Código cotizado: vive dentro de propuesta_ia. Las filas sin
        // propuesta válida no tienen código y no entran en este filtro.
        $conds[] = "JSON_VALID(c.propuesta_ia) AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.cdmess')) LIKE ?";
        $tipos .= 's';
        $params[] = '%' . $f['cdmess'] . '%';
    }
    if (!empty($f['q'])) {
        $conds[] = 'c.entrada_usuario LIKE ?';
        $tipos .= 's';
        $params[] = '%' . $f['q'] . '%';
    }

    return [$conds ? 'WHERE ' . implode(' AND ', $conds) : '', $tipos, $params];
}

/**
 * Ejecuta una preparada y devuelve sus filas como array asociativo.
 * Devuelve [] si la consulta no trae nada.
 */
function historialConsultar($conn, $sql, $tipos = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($tipos !== '' && $params) {
        // bind_param exige referencias: se ligan variables, no valores.
        $refs = [];
        foreach ($params as $k => $v) $refs[$k] = &$params[$k];
        $stmt->bind_param($tipos, ...$refs);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $filas = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $filas[] = $row;
    }
    $stmt->close();
    return $filas;
}

/**
 * Dashboard sobre el ámbito (proyecto + fechas + usuario). Devuelve el bloque
 * 'dashboard' de la respuesta, con el desglose por origen ordenado en
 * orden fijo (ia, promedio, validado, humano, mixto, sin_origen) y el
 * bucket 'sin_propuesta' (sin propuesta_ia válida) para que los
 * porcentajes sumen 100%.
 */
function historialObtenerDashboard($conn, array $f) {
    [$where, $tipos, $params] = historialCondiciones($f, true);
    $y = $where === '' ? 'WHERE ' : $where . ' AND ';

    $tot = historialConsultar($conn,
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN c.estatus = 'completado' THEN 1 ELSE 0 END) AS completados,
                SUM(CASE WHEN c.estatus <> 'completado' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN JSON_VALID(c.propuesta_ia) AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.requiere_revision')) = 'true' THEN 1 ELSE 0 END) AS requieren_revision,
                SUM(CASE WHEN JSON_VALID(c.propuesta_ia) AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.atlas.requiere_validacion_experto')) = 'true' THEN 1 ELSE 0 END) AS validacion_pendiente,
                SUM(CASE WHEN JSON_VALID(c.propuesta_ia) AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.tiene_precio_validado')) = 'true' THEN 1 ELSE 0 END) AS con_precio_validado,
                SUM(CASE WHEN c.propuesta_ia IS NOT NULL AND NOT JSON_VALID(c.propuesta_ia) THEN 1 ELSE 0 END) AS propuesta_invalida,
                SUM(CASE WHEN JSON_VALID(c.propuesta_ia) THEN 1 ELSE 0 END) AS propuesta_valida
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro $where",
        $tipos, $params);
    $t = $tot[0] ?? [];
    $total = (int) ($t['total'] ?? 0);

    $porOrigenFilas = historialConsultar($conn,
        "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.origen_precio')), 'sin_origen') AS origen, COUNT(*) AS n
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro {$y}JSON_VALID(c.propuesta_ia)
         GROUP BY origen ORDER BY n DESC",
        $tipos, $params);
    $porOrigen = [];
    foreach (HISTORIAL_ORIGENES as $o) $porOrigen[$o] = 0;
    $porOrigen['sin_origen'] = 0;
    foreach ($porOrigenFilas as $r) {
        $o = (string) ($r['origen'] ?? 'sin_origen');
        if (!array_key_exists($o, $porOrigen)) $porOrigen[$o] = 0;
        $porOrigen[$o] += (int) $r['n'];
    }
    $desglose = [];
    foreach ($porOrigen as $o => $n) {
        if ($n <= 0) continue;
        $desglose[] = [
            'origen' => $o,
            'n' => $n,
            'pct' => $total > 0 ? round($n / $total * 100, 1) : 0,
        ];
    }
    // Bucket aparte: sin propuesta válida (NULL o JSON inválido), para que
    // el desglose sume el 100% del total.
    $sinPropuesta = $total - (int) ($t['propuesta_valida'] ?? 0);
    if ($sinPropuesta > 0) {
        $desglose[] = [
            'origen' => 'sin_propuesta',
            'n' => $sinPropuesta,
            'pct' => $total > 0 ? round($sinPropuesta / $total * 100, 1) : 0,
        ];
    }

    $top = historialConsultar($conn,
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.cdmess')) AS codigo, COUNT(*) AS n,
                ROUND(AVG(JSON_EXTRACT(c.propuesta_ia, '\$.precio_ia')), 2) AS precio_promedio_sugerido
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro {$y}JSON_VALID(c.propuesta_ia)
             AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.cdmess')) IS NOT NULL
             AND JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.cdmess')) NOT IN ('', 'S/C')
         GROUP BY codigo ORDER BY n DESC, codigo ASC LIMIT 10",
        $tipos, $params);
    $topLimpio = [];
    foreach ($top as $r) {
        $topLimpio[] = [
            'codigo' => (string) $r['codigo'],
            'n' => (int) $r['n'],
            'precio_promedio_sugerido' => $r['precio_promedio_sugerido'] !== null ? (float) $r['precio_promedio_sugerido'] : null,
        ];
    }

    $ultimos = historialConsultar($conn,
        "SELECT c.id, c.id_proyecto, c.fecha_registro,
                JSON_UNQUOTE(JSON_EXTRACT(c.propuesta_ia, '\$.cdmess')) AS codigo,
                JSON_EXTRACT(c.propuesta_ia, '\$.precio_ia') AS precio
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro {$y}JSON_VALID(c.propuesta_ia) AND c.estatus = 'completado'
         ORDER BY c.fecha_registro DESC, c.id DESC LIMIT 10",
        $tipos, $params);
    $ultimosLimpios = [];
    foreach ($ultimos as $r) {
        $ultimosLimpios[] = [
            'id' => (int) $r['id'],
            'id_proyecto' => (string) $r['id_proyecto'],
            'fecha_registro' => $r['fecha_registro'],
            'codigo' => $r['codigo'] !== null ? (string) $r['codigo'] : null,
            'precio' => $r['precio'] !== null ? (float) $r['precio'] : null,
        ];
    }

    return [
        'total' => $total,
        'completados' => (int) ($t['completados'] ?? 0),
        'pendientes' => (int) ($t['pendientes'] ?? 0),
        'requieren_revision' => (int) ($t['requieren_revision'] ?? 0),
        'validacion_pendiente' => (int) ($t['validacion_pendiente'] ?? 0),
        'con_precio_validado' => (int) ($t['con_precio_validado'] ?? 0),
        'propuesta_invalida' => (int) ($t['propuesta_invalida'] ?? 0),
        'propuesta_valida' => (int) ($t['propuesta_valida'] ?? 0),
        'por_origen' => $desglose,
        'top_codigos' => $topLimpio,
        'ultimos' => $ultimosLimpios,
    ];
}

/**
 * Lista de proyectos para el filtro y para los encabezados de grupo de
 * la vista: un renglón por proyecto con su solicitante (u.nombre),
 * total de ítems y fecha del más reciente. Ordenados por id_proyecto
 * DESC (el más reciente primero). El LEFT JOIN deja solicitante en
 * NULL cuando el proyecto no tiene usuario registrado ("Sin registro").
 * Se acota por fechas y usuario, no por el propio filtro de proyecto
 * (se anula antes de armar el WHERE).
 */
function historialObtenerProyectos($conn, array $f) {
    $fAmbito = $f;
    $fAmbito['proyecto'] = null;
    [$where, $tipos, $params] = historialCondiciones($fAmbito, true);
    $filas = historialConsultar($conn,
        "SELECT c.id_proyecto, MAX(u.nombre) AS solicitante, COUNT(*) AS total,
                MAX(c.fecha_registro) AS fecha_reciente
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro
         $where GROUP BY c.id_proyecto ORDER BY c.id_proyecto DESC",
        $tipos, $params);
    $out = [];
    foreach ($filas as $r) {
        if ($r['id_proyecto'] !== null && (string) $r['id_proyecto'] !== '') {
            $sol = $r['solicitante'] ?? null;
            $out[] = [
                'id_proyecto' => (string) $r['id_proyecto'],
                'solicitante' => ($sol !== null && (string) $sol !== '') ? (string) $sol : null,
                'total' => (int) $r['total'],
                'fecha_reciente' => $r['fecha_reciente'],
            ];
        }
    }
    return $out;
}

/**
 * Lista de usuarios (solicitantes) para el filtro de la vista: un
 * renglón por usuario con ítems en la cola, ordenados por nombre.
 * Devuelve ['lista' => [{id_us_registro, solicitante, total}], 
 * 'sin_registro_total' => n]: los ítems sin usuario (id NULL o sin
 * fila en usuarios) no entran en la lista; se cuentan aparte para la
 * opción "Sin registro" del desplegable. Se acota por proyecto y
 * fechas, no por el propio filtro de usuario (se anula antes de armar
 * el WHERE).
 */
function historialObtenerUsuarios($conn, array $f) {
    $fAmbito = $f;
    $fAmbito['usuario'] = null;
    [$where, $tipos, $params] = historialCondiciones($fAmbito, true);
    $y = $where === '' ? 'WHERE ' : $where . ' AND ';
    $filas = historialConsultar($conn,
        "SELECT c.id_us_registro, MAX(u.nombre) AS solicitante, COUNT(*) AS total
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro
         {$y}c.id_us_registro IS NOT NULL AND u.nombre IS NOT NULL AND u.nombre <> ''
         GROUP BY c.id_us_registro ORDER BY solicitante ASC",
        $tipos, $params);
    $lista = [];
    foreach ($filas as $r) {
        $lista[] = [
            'id_us_registro' => (int) $r['id_us_registro'],
            'solicitante' => (string) $r['solicitante'],
            'total' => (int) $r['total'],
        ];
    }
    $sin = historialConsultar($conn,
        "SELECT COUNT(*) AS sin_registro_total
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro
         {$y}(u.nombre IS NULL OR u.nombre = '')",
        $tipos, $params);
    return [
        'lista' => $lista,
        'sin_registro_total' => (int) ($sin[0]['sin_registro_total'] ?? 0),
    ];
}

/**
 * Una fila de la tabla: decodifica propuesta_ia con guarda (NULL si el
 * worker aún no procesó el ítem; JSON inválido también queda en null y
 * se marca propuesta_valida=false, sin romper nada).
 */
function historialArmarFila(array $row) {
    $propuesta = null;
    $valida = false;
    if ($row['propuesta_ia'] !== null && $row['propuesta_ia'] !== '') {
        $dec = json_decode($row['propuesta_ia'], true);
        if (is_array($dec)) {
            $propuesta = $dec;
            $valida = true;
        }
    }
    return [
        'id' => (int) $row['id'],
        'id_proyecto' => (string) $row['id_proyecto'],
        'entrada_usuario' => $row['entrada_usuario'],
        'cdmess_historico' => $row['cdmess_historico'] ?? null,
        'descripcion_historica' => $row['descripcion_historica'] ?? null,
        'estatus' => (string) $row['estatus'],
        'es_sugerencia' => (int) ($row['es_sugerencia'] ?? 0),
        'precio_usuario' => $row['precio_usuario'],
        'respuesta' => $row['respuesta'] ?? null,
        'categoria_rechazo' => $row['categoria_rechazo'] ?? null,
        'fecha_registro' => $row['fecha_registro'],
        'id_us_registro' => $row['id_us_registro'] ?? null,
        'solicitante' => $row['solicitante'] ?? null,
        'propuesta_ia' => $propuesta,
        'propuesta_valida' => $valida,
    ];
}

/** Tabla paginada con todos los filtros. Devuelve [items, total]. */
function historialObtenerItems($conn, array $f, $pagina, $porPagina) {
    [$where, $tipos, $params] = historialCondiciones($f, false);

    $tot = historialConsultar($conn,
        "SELECT COUNT(*) AS total_items FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro $where",
        $tipos, $params);
    $total = (int) ($tot[0]['total_items'] ?? 0);
    $totalPaginas = max(1, (int) ceil($total / $porPagina));
    $pagina = min(max(1, (int) $pagina), $totalPaginas);
    $inicio = ($pagina - 1) * $porPagina;

    $tiposItems = $tipos . 'ii';
    $paramsItems = $params;
    $paramsItems[] = $inicio;
    $paramsItems[] = $porPagina;
    $filas = historialConsultar($conn,
        "SELECT c.id, c.id_proyecto, c.entrada_usuario, c.cdmess_historico, c.descripcion_historica,
                c.estatus, c.es_sugerencia, c.propuesta_ia, c.fecha_registro, c.id_us_registro,
                c.precio_usuario, c.respuesta, c.categoria_rechazo, u.nombre AS solicitante
         FROM cola_procesamiento c LEFT JOIN usuarios u ON u.id_usuario = c.id_us_registro $where
         ORDER BY c.id_proyecto DESC, c.id ASC LIMIT ?, ?",
        $tiposItems, $paramsItems);

    $items = [];
    foreach ($filas as $r) $items[] = historialArmarFila($r);
    return [$items, $total, $pagina, $totalPaginas];
}

/** Arma la respuesta completa del endpoint (la usa la entrada web y las pruebas). */
function historialEjecutar($conn, array $get) {
    $f = historialNormalizarFiltros($get);
    $dashboard = historialObtenerDashboard($conn, $f);
    $proyectos = historialObtenerProyectos($conn, $f);
    $usuarios = historialObtenerUsuarios($conn, $f);
    [$items, $total, $pagina, $totalPaginas] = historialObtenerItems($conn, $f, $f['pagina'], $f['por_pagina']);
    return [
        'proyectos' => $proyectos,
        'usuarios' => $usuarios['lista'],
        'sin_registro_total' => $usuarios['sin_registro_total'],
        'dashboard' => $dashboard,
        'items' => $items,
        'paginacion' => [
            'pagina' => $pagina,
            'por_pagina' => $f['por_pagina'],
            'total' => $total,
            'total_paginas' => $totalPaginas,
        ],
    ];
}

// ==========================================
// ENTRADA WEB (no corre en CLI: las pruebas
// incluyen este archivo y llaman a las
// funciones de arriba directamente).
// ==========================================
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/core/json_api.php';
    iniciarRespuestaJSON();

    session_start();
    include 'conexion.php';

    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Sesion expirada o no valida']);
        exit;
    }

    // Permisos por vista (2026-11-26): sin acceso a 'historial_precios'
    // el endpoint no sirve los datos por URL directa (403 JSON). El
    // permiso es por noEmpleado de la cookie, no por id_usuario.
    require_once __DIR__ . '/core/permisos_vistas.php';
    if (!tieneAccesoVista($conn, 'historial_precios', (int)($_COOKIE['noEmpleado'] ?? 0))) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin acceso a esta vista']);
        exit;
    }

    echo json_encode(historialEjecutar($conn, $_GET));
    exit;
}
