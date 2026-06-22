<?php
/**
 * Importador optimizado para cotizador_ia (Estructura Exacta)
 */
ini_set('memory_limit','512M');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "cotizador_ia";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);

$archivo = 'Export_TabCotizaciones 2023.csv';
if (!file_exists($archivo)) die("El archivo $archivo no existe.");

$totalLineas = count(file($archivo)) - 1;
$gestor = fopen($archivo, "r");

$encabezado = fgetcsv($gestor, 0, ",");
if (!$encabezado) die("El archivo CSV está vacío.");

$encabezado = array_map(function($col) {
    return trim(str_replace("\xEF\xBB\xBF", "", $col));
}, $encabezado);

$columnas = array_flip($encabezado);

// 1. Preparar las sentencias con las columnas EXACTAS de la imagen
$stmtMaster = $conn->prepare("INSERT IGNORE INTO cotizaciones (IDCOTIZA, IDCLTE, FECHA, STATUS, VALOR_USD, VALOR_MXN, IDUSRVEND) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtItem   = $conn->prepare("INSERT INTO cotizaciones_items (IDCOTIZA, CDMESS, CANT, PRECIO_VENTA, PRECIO_VENTA_MXN, DESCRIPCION, TIPO, MARCA, MODELO, SERIE, ID_EQ_CLIENTE, MESSTAG) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmtMaster || !$stmtItem) {
    die("Error al preparar las sentencias: " . $conn->error);
}

$procesados = 0;
$errores = 0;

echo "Iniciando carga de $totalLineas registros...\n";

$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->begin_transaction();

try {
    while (($datos = fgetcsv($gestor, 0, ",")) !== FALSE) {
        // Mapeo dinámico desde el CSV
        $id_cotiza   = $datos[$columnas['IDCOTIZA']];
        $id_clte     = $datos[$columnas['IDCLTE']];
        $fecha       = $datos[$columnas['FECHA']];
        $status      = $datos[$columnas['STATUS']];
        $valor_usd   = $datos[$columnas['VALOR_USD']];
        $valor_mxn   = $datos[$columnas['VALOR_MXP']]; // Viene como VALOR_MXP en el CSV
        $id_vend     = $datos[$columnas['IDUSRVEND']];
        
        $cdmess      = $datos[$columnas['CDMESS']];
        $cant        = $datos[$columnas['CANT']];
        $desc        = $datos[$columnas['DESCRIPCION']];
        $marca       = $datos[$columnas['marca']];
        $modelo      = $datos[$columnas['modelo']];
        $serie       = $datos[$columnas['noSerie']];
        $id_eq_clte  = $datos[$columnas['IdEquipoCliente']];
        $messtag     = $datos[$columnas['mess_tag']];

        // Nota: Como 'TIPO' es un ENUM en tu BD y no viene explícito en este CSV,
        // le asignamos 'EQUIPO' por defecto (o puedes cambiarlo según corresponda).
        $tipo_defecto = 'EQUIPO'; 

        // Ejecutar Cabecera (cotizaciones) -> 7 campos
        $stmtMaster->bind_param("ssssdds", $id_cotiza, $id_clte, $fecha, $status, $valor_usd, $valor_mxn, $id_vend);
        $stmtMaster->execute();

        // Ejecutar Detalle (cotizaciones_items) -> 12 campos (id_item es AUTO_INCREMENT)
        $stmtItem->bind_param("ssiddsssssss", $id_cotiza, $cdmess, $cant, $valor_usd, $valor_mxn, $desc, $tipo_defecto, $marca, $modelo, $serie, $id_eq_clte, $messtag);
        
        if ($stmtItem->execute()) {
            $procesados++;
        } else {
            $errores++;
        }

        $porcentaje = round(($procesados + $errores) / $totalLineas * 100);
        echo "\rProgreso: [$porcentaje%] - Procesados: $procesados - Errores: $errores";
    }

    $conn->commit();
    echo "\n\nImportación finalizada con éxito.\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\nError crítico: " . $e->getMessage();
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");
fclose($gestor);
$stmtMaster->close();
$stmtItem->close();
$conn->close();
?>