<?php
include 'conexion.php';  
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

$sql = "SELECT idclte, cliente_largo, estado, ciudad FROM clientes 
        WHERE cliente_largo LIKE '%$busqueda%' or cliente_corto LIKE '%$busqueda%'
        ORDER BY cliente_largo ASC 
        LIMIT 80";

$res = $conn->query($sql);
$resultado = [];

if (empty($busqueda)) {
    $resultado[] = ['id' => 'todos', 'text' => 'Todos los clientes'];
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $resultado[] = [
            'id' => $row['idclte'],
            'text' => $row['cliente_largo'],
            'estado' => $row['estado'],
            'ciudad' => $row['ciudad']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($resultado);