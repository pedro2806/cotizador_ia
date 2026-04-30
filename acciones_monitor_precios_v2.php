<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'funcionesWorker.php';


function ejecutarAccion($accion, $datos = []) {
    global $conn;

    switch ($accion) {
        case 'OBTENER_RESUMEN_PAGINADO':
            $por_pagina = $datos['por_pagina'] ?? 10;
            $inicio = ($datos['pagina'] - 1) * $por_pagina;

            $sql = "SELECT id_proyecto, MAX(fecha_registro) as fecha,
                    SUM(CASE WHEN es_sugerencia = 0 THEN 1 ELSE 0 END) as total,
                    SUM(CASE WHEN es_sugerencia = 0 AND estatus = 'completado' THEN 1 ELSE 0 END) as listos
                    FROM cola_procesamiento
                    WHERE id_us_registro = " . intval($_SESSION['usuario_id'] ?? $datos['id_usuario'] ?? 0) . "
                    GROUP BY id_proyecto
                    ORDER BY fecha DESC
                    LIMIT $inicio, $por_pagina";
            return $conn->query($sql);

        case 'CONTAR_TOTAL_PROYECTOS':
            $res = $conn->query("SELECT COUNT(DISTINCT id_proyecto) as total FROM cola_procesamiento");
            return $res->fetch_assoc()['total'];

        case 'OBTENER_DETALLE_PROYECTO':
            $proyecto = $conn->real_escape_string($datos['id_proyecto']);
            return $conn->query("SELECT * FROM cola_procesamiento WHERE id_proyecto = '$proyecto' ORDER BY id ASC");

        default:
            return null;

        case 'GUARDAR_APROBACION_HUMANA':
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
}

// Lógica para procesar peticiones AJAX directas
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