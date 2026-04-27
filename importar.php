<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "cotizador_ia";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);

$archivo = 'historico.csv'; // Nombre de tu archivo exportado
$gestor = fopen($archivo, "r");

// Omitir la primera línea si tiene encabezados
fgetcsv($gestor);

while (($datos = fgetcsv($gestor, 1000, ",")) !== FALSE) {
    // Mapeo según tu imagen (ajusta los índices si varían)
    $id_cotiza = $datos[0]; // IDCOTIZA
    $id_clte   = $datos[1]; // IDCLTE
    $fecha     = $datos[2]; // FECHA
    $status    = $datos[3]; // STATUS
    $valor_mxp = $datos[4]; // VALOR_MXP
    $id_vend   = $datos[6]; // IDUSRVEND
    $cdmess    = $datos[8]; // CDMESS (el ID del producto)
    $cant      = $datos[18]; // CANT
    $desc      = $conn->real_escape_string($datos[11]); // DESCRIPCION

    // 1. Insertar en 'cotizaciones' (Cabecera) 
    // Usamos INSERT IGNORE para que no marque error si el folio ya existe
    $sql_master = "INSERT IGNORE INTO cotizaciones (IDCOTIZA, IDCLTE, FECHA, STATUS, VALOR_USD, IDUSRVEND) 
                   VALUES ('$id_cotiza', '$id_clte', '$fecha', '$status', '$valor_mxp', '$id_vend')";
    $conn->query($sql_master);

    // 2. Insertar en 'cotizaciones_items' (Detalle)
    $sql_item = "INSERT INTO cotizaciones_items (IDCOTIZA, CDMESS, CANT, PRECIO_VENTA, DESCRIPCION) 
                 VALUES ('$id_cotiza', '$cdmess', '$cant', '$valor_mxp', '$desc')";
    $conn->query($sql_item);
}

fclose($gestor);
echo "Importación completada con éxito.";
$conn->close();
?>