<?php
/**
 * Importador optimizado para cotizador_ia
 * Mejora: Transacciones, Sentencias Preparadas y Monitor de Carga.
 */
ini_set('memory_limit','512M');


$host = "localhost";
$user = "messias_admin";
$pass = "Pipmytrade123";
$db   = "cotizador_ia";

// 1. Conexión optimizada
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Error de conexión: " . $conn->connect_error);

$archivo = 'COT.csv';
if (!file_exists($archivo)) die("El archivo $archivo no existe.");

$totalLineas = count(file($archivo)) - 1; // Menos el encabezado
$gestor = fopen($archivo, "r");
fgetcsv($gestor); // Omitir encabezado

// 2. Preparar sentencias (Más rápido y seguro contra SQL Injection)
$stmtMaster = $conn->prepare("INSERT IGNORE INTO cotizaciones (IDCOTIZA, IDCLTE, FECHA, STATUS, VALOR_USD, IDUSRVEND) VALUES (?, ?, ?, ?, ?, ?)");
$stmtItem   = $conn->prepare("INSERT INTO cotizaciones_items (IDCOTIZA, CDMESS, CANT, PRECIO_VENTA, DESCRIPCION, MARCA, MODELO, SERIE) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$procesados = 0;
$errores = 0;

echo "Iniciando carga de $totalLineas registros...\n";

// 3. Desactivar llaves foráneas temporalmente para asegurar la inserción masiva
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->begin_transaction(); // Iniciar transacción para velocidad

try {
    while (($datos = fgetcsv($gestor, 1000, ",")) !== FALSE) {
        // Mapeo de datos
        $id_cotiza = $datos[0];
        $id_clte   = $datos[1];
        $fecha     = $datos[2];
        $status    = $datos[3];
        $valor_mxp = $datos[4];
        $id_vend   = $datos[6];
        $cdmess    = $datos[8];
        $cant      = $datos[18];
        $desc      = $datos[11];
        $marca     = $datos[20];
        $modelo    = $datos[21];
        $serie     = $datos[22];

        // Ejecutar Cabecera
        $stmtMaster->bind_param("ssssds", $id_cotiza, $id_clte, $fecha, $status, $valor_mxp, $id_vend);
        $stmtMaster->execute();

        // Ejecutar Detalle
        $stmtItem->bind_param("ssisssss", $id_cotiza, $cdmess, $cant, $valor_mxp, $desc, $marca, $modelo, $serie);
        
        if ($stmtItem->execute()) {
            $procesados++;
        } else {
            $errores++;
        }

        // 4. Monitor de progreso (Visual en consola)
        $porcentaje = round(($procesados + $errores) / $totalLineas * 100);
        echo "\rProgreso: [$porcentaje%] - Procesados: $procesados - Errores: $errores";
    }

    $conn->commit(); // Guardar cambios de forma definitiva
    echo "\n\nImportación finalizada con éxito.\n";

} catch (Exception $e) {
    $conn->rollback(); // Si algo falla críticamente, deshace los cambios
    echo "\nError crítico: " . $e->getMessage();
}

// 5. Limpieza y restauración
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
fclose($gestor);
$stmtMaster->close();
$stmtItem->close();
$conn->close();
?>