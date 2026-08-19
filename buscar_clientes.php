<?php
require_once __DIR__ . '/core/json_api.php';
iniciarRespuestaJSON();

session_start();
include 'conexion.php';

// header ya lo puso iniciarRespuestaJSON()

// Antes cualquiera (sin sesion) podia listar el catalogo completo de
// clientes de la empresa desde este endpoint.
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesion expirada o no valida']);
    exit;
}

$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$like = '%' . $busqueda . '%';

$sql = "SELECT idclte, cliente_largo, estado, ciudad FROM clientes
        WHERE cliente_largo LIKE ? OR cliente_corto LIKE ?
        ORDER BY cliente_largo ASC
        LIMIT 80";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$resultado = [];

if (empty($busqueda)) {
    $resultado[] = ['id' => 'todos', 'text' => 'Todos los clientes'];
}

while ($row = $res->fetch_assoc()) {
    $resultado[] = [
        'id' => $row['idclte'],
        'text' => $row['cliente_largo'],
        'estado' => $row['estado'],
        'ciudad' => $row['ciudad']
    ];
}

echo json_encode($resultado);
