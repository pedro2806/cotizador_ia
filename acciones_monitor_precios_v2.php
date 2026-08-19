<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'funcionesWorker.php';

// ==========================================
// ENRUTADOR PRINCIPAL
// ==========================================
function ejecutarAccion($accion, $datos = []) {
    switch ($accion) {
        case 'OBTENER_RESUMEN_PAGINADO':
            return acc_ObtenerResumenPaginado($datos);

        case 'CONTAR_TOTAL_PROYECTOS':
            return acc_ContarTotalProyectos();

        case 'OBTENER_DETALLE_PROYECTO':
            return acc_ObtenerDetalleProyecto($datos);

        case 'GUARDAR_APROBACION_HUMANA':
            return acc_GuardarAprobacionHumana($datos);

        case 'OBTENER_DESVIACIONES':
            return acc_ObtenerDesviaciones($datos);

        default:
            return null;
    }
}

// ==========================================
// FUNCIONES AISLADAS DE BASE DE DATOS
// (todas usan sentencias preparadas; antes varias interpolaban directo
// en el SQL con real_escape_string/intval, lo cual funciona pero es más
// fragil ante descuidos futuros que un bind_param explicito)
// ==========================================

function acc_ObtenerResumenPaginado($datos) {
    global $conn;
    $por_pagina = max(1, intval($datos['por_pagina'] ?? 10));
    $pagina = max(1, intval($datos['pagina'] ?? 1));
    $inicio = ($pagina - 1) * $por_pagina;
    $id_usuario = intval($_SESSION['usuario_id'] ?? 0);

    $sql = "SELECT id_proyecto, MAX(fecha_registro) as fecha,
            SUM(CASE WHEN es_sugerencia = 0 THEN 1 ELSE 0 END) as total,
            SUM(CASE WHEN es_sugerencia = 0 AND estatus = 'completado' THEN 1 ELSE 0 END) as listos
            FROM cola_procesamiento
            WHERE id_us_registro = ?
            GROUP BY id_proyecto
            ORDER BY fecha DESC
            LIMIT ?, ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $id_usuario, $inicio, $por_pagina);
    $stmt->execute();
    return $stmt->get_result();
}

function acc_ContarTotalProyectos() {
    global $conn;
    $res = $conn->query('SELECT COUNT(DISTINCT id_proyecto) as total FROM cola_procesamiento');
    return $res->fetch_assoc()['total'];
}

function acc_ObtenerDetalleProyecto($datos) {
    global $conn;
    $proyecto = $datos['id_proyecto'] ?? '';
    $stmt = $conn->prepare('SELECT * FROM cola_procesamiento WHERE id_proyecto = ? ORDER BY id ASC');
    $stmt->bind_param('s', $proyecto);
    $stmt->execute();
    return $stmt->get_result();
}

function acc_GuardarAprobacionHumana($datos) {
    global $conn;
    $id = intval($datos['id'] ?? 0);
    $respuesta = $datos['respuesta'] ?? '';
    $precio_user = floatval($datos['precio_usuario'] ?? 0);
    $categoria = $datos['categoria_rechazo'] ?? '';
    $id_user = intval($_SESSION['usuario_id'] ?? 0);

    if ($id <= 0) {
        return false;
    }

    $sql = "UPDATE cola_procesamiento
            SET respuesta = ?,
                precio_usuario = ?,
                id_usuario = ?,
                estatus = 'completado',
                categoria_rechazo = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sdisi', $respuesta, $precio_user, $id_user, $categoria, $id);
    return $stmt->execute();
}

function acc_ObtenerDesviaciones($datos) {
    global $conn;
    $proyecto = $datos['id_proyecto'] ?? '';
    // Filtramos donde el precio de la IA difiere > 15% del precio promedio
    $sql = "SELECT * FROM cola_procesamiento
            WHERE id_proyecto = ?
            AND estatus = 'completado'
            AND JSON_EXTRACT(propuesta_ia, '$.precio_ia') > 0
            AND (ABS(JSON_EXTRACT(propuesta_ia, '$.precio_ia') - JSON_EXTRACT(propuesta_ia, '$.precio_promedio')) / JSON_EXTRACT(propuesta_ia, '$.precio_promedio')) > 0.15
            ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $proyecto);
    $stmt->execute();
    return $stmt->get_result();
}

// ==========================================
// PROCESADOR DE PETICIONES AJAX
// ==========================================
if (isset($_POST['accion'])) {
    require_once __DIR__ . '/core/json_api.php';
    iniciarRespuestaJSON();
    // header ya lo puso iniciarRespuestaJSON()

    // Antes este endpoint no validaba sesion en absoluto: cualquiera podia
    // llamarlo directo (sin pasar por index.php/monitor_precios_v2.php) y
    // leer o incluso aprobar precios de cotizaciones ajenas. Se agrega el
    // mismo candado que ya usan cargador_masivo.php / procesar_carga.php.
    if (empty($_SESSION['usuario_id'])) {
        echo json_encode(['status' => 'error', 'error' => 'Sesion expirada o no valida.']);
        exit;
    }

    $res = ejecutarAccion($_POST['accion'], $_POST);

    // Si $res es un objeto de mysqli_result, no lo podemos encodear directo
    if ($res instanceof mysqli_result) {
        $data = [];
        while ($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
    } else {
        // Si es un booleano (como en el UPDATE)
        echo json_encode(['status' => $res ? 'success' : 'error']);
    }
    exit;
}
