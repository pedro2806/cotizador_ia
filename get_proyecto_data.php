<?php
// get_proyecto_data.php
include 'conexion.php';
include 'funcionesWorker.php';


// Establecemos la cabecera para que el navegador sepa que recibe JSON
header('Content-Type: application/json');

// Desactivamos el reporte de errores en pantalla para no ensuciar el JSON
error_reporting(0); 

$id_proyecto = $_GET['proyecto'] ?? '';

if (empty($id_proyecto)) {
    echo json_encode(["error" => "No se especificó un ID de proyecto"]);
    exit;
}

// 1. Limpiamos el parámetro para seguridad
$id_proyecto_safe = $conn->real_escape_string($id_proyecto);

// 2. Ejecutamos la consulta para traer los ítems de ese proyecto
// Traemos la entrada original, el estatus y el JSON de la propuesta
$sql = "SELECT id, entrada_usuario, estatus, propuesta_ia 
        FROM cola_procesamiento 
        WHERE id_proyecto = '$id_proyecto_safe' 
        ORDER BY id ASC";

$res = $conn->query($sql);

$resultado = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Decodificamos el JSON de la IA para que el frontend pueda leer los campos Min/Max
        $propuesta = json_decode($row['propuesta_ia'], true);
        
        $resultado[] = [
            "id" => $row['id'],
            "entrada_usuario" => $row['entrada_usuario'],
            "estatus" => $row['estatus'],
            "propuesta_ia" => $propuesta // Enviamos el objeto ya parseado
        ];
    }
}

// 3. Enviamos la respuesta final
echo json_encode($resultado);