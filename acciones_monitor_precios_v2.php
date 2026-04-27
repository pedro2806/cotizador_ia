<?php
include 'conexion.php';

function ejecutarAccion($accion, $datos = []) {
    global $conn;

    switch ($accion) {
        case 'OBTENER_RESUMEN_PAGINADO':
            $por_pagina = $datos['por_pagina'] ?? 10;
            $inicio = ($datos['pagina'] - 1) * $por_pagina;

            $sql = "SELECT id_proyecto, MAX(fecha_registro) as fecha, COUNT(*) as total, 
                    SUM(CASE WHEN estatus = 'completado' THEN 1 ELSE 0 END) as listos 
                    FROM cola_procesamiento 
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
    }
}