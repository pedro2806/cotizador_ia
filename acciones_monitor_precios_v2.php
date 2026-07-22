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
// ==========================================

function acc_ObtenerResumenPaginado($datos) {
    global $conn;
    $por_pagina = $datos['por_pagina'] ?? 10;
    $inicio = ($datos['pagina'] - 1) * $por_pagina;
    $id_usuario = intval($_SESSION['usuario_id'] ?? $datos['id_usuario'] ?? 0);

    $sql = "SELECT id_proyecto, MAX(fecha_registro) as fecha,
            SUM(CASE WHEN es_sugerencia = 0 THEN 1 ELSE 0 END) as total,
            SUM(CASE WHEN es_sugerencia = 0 AND estatus = 'completado' THEN 1 ELSE 0 END) as listos
            FROM cola_procesamiento
            WHERE id_us_registro = $id_usuario
            GROUP BY id_proyecto
            ORDER BY fecha DESC
            LIMIT $inicio, $por_pagina";
            
    return $conn->query($sql);
}

function acc_ContarTotalProyectos() {
    global $conn;
    $res = $conn->query("SELECT COUNT(DISTINCT id_proyecto) as total FROM cola_procesamiento");
    return $res->fetch_assoc()['total'];
}

function acc_ObtenerDetalleProyecto($datos) {
    global $conn;
    $proyecto = $conn->real_escape_string($datos['id_proyecto']);
    return $conn->query("SELECT * FROM cola_procesamiento WHERE id_proyecto = '$proyecto' ORDER BY id ASC");
}

function acc_GuardarAprobacionHumana($datos) {
    global $conn;
    $id = intval($datos['id']);
    $respuesta = $conn->real_escape_string($datos['respuesta']);
    $precio_user = floatval($datos['precio_usuario']);
    $categoria = $conn->real_escape_string($datos['categoria_rechazo']);
    $id_user = intval($_SESSION['usuario_id'] ?? $datos['id_usuario'] ?? 0);

    $sql = "UPDATE cola_procesamiento 
            SET respuesta = '$respuesta', 
                precio_usuario = $precio_user, 
                id_usuario = $id_user, 
                estatus = 'completado', 
                categoria_rechazo = '$categoria'
            WHERE id = $id";
            
    return $conn->query($sql);
}

function acc_ObtenerDesviaciones($datos) {
    global $conn;
    $proyecto = $conn->real_escape_string($datos['id_proyecto']);
    // Filtramos donde el precio de la IA difiere > 15% del precio promedio
    $sql = "SELECT * FROM cola_procesamiento 
            WHERE id_proyecto = '$proyecto' 
            AND estatus = 'completado'
            AND JSON_EXTRACT(propuesta_ia, '$.precio_ia') > 0
            AND (ABS(JSON_EXTRACT(propuesta_ia, '$.precio_ia') - JSON_EXTRACT(propuesta_ia, '$.precio_promedio')) / JSON_EXTRACT(propuesta_ia, '$.precio_promedio')) > 0.15
            ORDER BY id ASC";
    return $conn->query($sql);
}

// ==========================================
// PROCESADOR DE PETICIONES AJAX
// ==========================================
if (isset($_POST['accion'])) {
    $res = ejecutarAccion($_POST['accion'], $_POST);
    
    // Si $res es un objeto de mysqli_result, no lo podemos encodear directo
    if ($res instanceof mysqli_result) {
        $data = [];
        while($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
    } else {
        // Si es un booleano (como en el UPDATE)
        echo json_encode(['status' => $res ? 'success' : 'error']);
    }
    exit;
}