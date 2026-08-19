<?php
// get_proyecto_data.php
require_once __DIR__ . '/core/json_api.php';
iniciarRespuestaJSON();

session_start();
include 'conexion.php';
include 'funcionesWorker.php';

// header ya lo puso iniciarRespuestaJSON(); antes aquí había
// error_reporting(0), que además de ocultar errores en pantalla los
// ocultaba también del log — con el bootstrap compartido siguen quedando
// registrados, solo que ya no rompen el JSON de salida.

// Antes este endpoint no verificaba sesion: cualquiera podia consultar el
// detalle de cualquier proyecto (incluyendo precios ya aprobados) solo
// adivinando/probando el id_proyecto en la URL.
if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Sesion expirada o no valida"]);
    exit;
}

$id_proyecto = $_GET['proyecto'] ?? '';

if (empty($id_proyecto)) {
    echo json_encode(["error" => "No se especifico un ID de proyecto"]);
    exit;
}

// Consulta con sentencia preparada (antes se armaba con real_escape_string
// concatenado directo en el SQL)
$sql = "SELECT id, entrada_usuario, estatus, propuesta_ia, es_sugerencia, precio_usuario, respuesta, categoria_rechazo, fecha_registro, id_us_registro
        FROM cola_procesamiento
        WHERE id_proyecto = ?
        ORDER BY es_sugerencia ASC, id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $id_proyecto);
$stmt->execute();
$res = $stmt->get_result();

$resultado = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Decodificamos el JSON de la IA para que el frontend pueda leer los campos Min/Max.
        // Si el worker todavía no procesó este ítem, propuesta_ia es NULL en la BD;
        // json_decode(null) genera un "Deprecated" en PHP 8.1+ (se veía en el log
        // en cada refresco del monitor), así que lo evitamos con el guard.
        $propuesta = $row['propuesta_ia'] !== null ? json_decode($row['propuesta_ia'], true) : null;

        $resultado[] = [
            "id"                => $row['id'],
            "entrada_usuario"   => $row['entrada_usuario'],
            "estatus"           => $row['estatus'],
            "es_sugerencia"     => (int)$row['es_sugerencia'],
            "precio_usuario"    => $row['precio_usuario'],
            "respuesta"         => $row['respuesta'],
            "categoria_rechazo" => $row['categoria_rechazo'],
            "fecha_registro"    => $row['fecha_registro'],
            "id_us_registro"    => $row['id_us_registro'],
            "propuesta_ia"      => $propuesta
        ];
    }
}

// Enviamos la respuesta final
echo json_encode($resultado);
