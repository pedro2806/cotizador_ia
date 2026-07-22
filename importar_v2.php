Tienes toda la razón en reclamarme, te ofrezco una sincera disculpa. Fue un error completamente mío al transcribir.

Al agregar el bloque de la consola de diagnóstico, **borré accidentalmente la línea que amarra los datos (`bind_param`) para la cabecera**. Por eso MySQL detiene todo y se queja diciendo *"No data supplied..."* (No me enviaste los datos para ejecutar la inserción).

Aquí tienes el código completo con esa línea restaurada en el paso 6. Solo cópialo y pégalo, ahora sí va a pasar directo al análisis de las filas.

```php
<?php
/**
 * Importador Visual con Consola de Diagnóstico de Errores
 * CORRECCIÓN: bind_param restaurado en $stmtMaster
 */
ini_set('memory_limit','512M');
set_time_limit(0); 

if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

// ==========================================
// 1. CREDENCIALES DE BASE DE DATOS
// ==========================================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "cotizador_ia";

$conn = new mysqli($host, $user, $pass, $db);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Monitor de Importación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .card-import { border: none; border-radius: 1.2rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08); }
        .progress { height: 28px; border-radius: 1rem; background-color: #e9ecef; }
        .progress-bar { font-weight: bold; font-size: 0.9rem; line-height: 28px; }
        .stat-box { background: #fff; border: 1px solid #dee2e6; border-radius: 0.8rem; padding: 1rem; }
        #console-log { height: 180px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; background: #212529; color: #00ff00; }
        #console-log div { border-bottom: 1px solid #343a40; padding: 4px 0; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    
    <div class="container" style="max-width: 700px;">
        <div class="card card-import p-4 p-md-5">
            <div class="text-center mb-4">
                <h4 class="fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Procesando Archivo CSV</h4>
                <p class="text-muted small mb-0" id="status-text">Iniciando lectura...</p>
            </div>
            
            <div class="progress mb-4 shadow-sm">
                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
            
            <div class="row g-3 text-center mb-4">
                <div class="col-4">
                    <div class="stat-box shadow-sm">
                        <div class="fs-4 fw-bold text-success" id="counter-procesados">0</div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Insertados</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-box shadow-sm">
                        <div class="fs-4 fw-bold text-warning" id="counter-ignorados">0</div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Ignorados</small>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-box shadow-sm">
                        <div class="fs-4 fw-bold text-danger" id="counter-errores">0</div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Errores BD</small>
                    </div>
                </div>
            </div>

            <!-- Consola de Diagnóstico -->
            <h6 class="fw-bold text-muted small text-uppercase mb-2"><i class="bi bi-terminal-fill me-1"></i> Log de Diagnóstico</h6>
            <div id="console-log" class="rounded p-3 shadow-inner">
                <div class="text-secondary">Esperando eventos...</div>
            </div>
        </div>
    </div>

<?php
if ($conn->connect_error) {
    die("<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger\">Error conexión: " . $conn->connect_error . "</div>';</script></body></html>");
}

// ==========================================
// 2. ARCHIVO A LEER
// ==========================================
$archivo = 'Export_TabCotizaciones 2023.csv'; 

if (!file_exists($archivo)) {
    die("<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger\">Archivo $archivo no existe.</div>';</script></body></html>");
}

$lineasArchivo = file($archivo);
$totalLineas = count($lineasArchivo) - 1; 
unset($lineasArchivo); 
$gestor = fopen($archivo, "r");
fgetcsv($gestor); 

// ==========================================
// 3. PREPARAR SENTENCIAS
// ==========================================
$stmtMaster = $conn->prepare("INSERT IGNORE INTO cotizaciones (IDCOTIZA, IDCLTE, FECHA, STATUS, VALOR_USD, VALOR_MXN, IDUSRVEND) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtItem   = $conn->prepare("INSERT INTO cotizaciones_items (IDCOTIZA, CDMESS, CANT, PRECIO_VENTA, PRECIO_VENTA_MXN, DESCRIPCION, TIPO, MARCA, MODELO, SERIE, ID_EQ_CLIENTE, MESSTAG) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$procesados = 0;
$errores = 0;
$ignorados = 0;
$lineasLeidas = 0; 

$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->begin_transaction(); 

try {
    while (($datos = fgetcsv($gestor, 1000, ",")) !== FALSE) {
        $lineasLeidas++; 
        
        if (empty($datos) || !isset($datos[0]) || trim($datos[0]) === '') {
            $ignorados++;
            continue;
        }

        // ==========================================
        // 4. MAPEO DE DATOS
        // ==========================================
        $id_cotiza  = trim($datos[0] ?? '');
        $id_clte    = trim($datos[1] ?? '');
        $fecha      = trim($datos[2] ?? '');
        $status     = trim($datos[3] ?? '');
        
        $valor_usd  = floatval($datos[4] ?? 0); 
        $valor_mxn  = isset($datos[5]) ? floatval($datos[5]) : 0; 
        
        $id_vend    = trim($datos[6] ?? '');
        $cdmess     = trim($datos[8] ?? '');
        $desc       = trim($datos[11] ?? '');
        
        $cant       = isset($datos[18]) ? intval($datos[18]) : 1;
        if ($cant <= 0) $cant = 1;
        
        $marca      = trim($datos[20] ?? '');
        $modelo     = trim($datos[21] ?? '');
        $serie      = trim($datos[22] ?? '');
        $id_eq_clte = trim($datos[23] ?? ''); 
        $messtag    = trim($datos[24] ?? ''); 

        $tipo_defecto = 'EQUIPO';

        // ==========================================
        // 5. FILTROS Y EXCEPCIONES
        // ==========================================
        if ($cdmess === 'Sin registro' || stripos($desc, 'mensajeria') !== false || stripos($desc, 'mensajería') !== false || stripos($desc, 'viaticos') !== false || stripos($desc, 'viáticos') !== false) {
            
            $ignorados++;
            
            if ($id_cotiza === '7281-2026') {
                echo "<script>document.getElementById('console-log').innerHTML += '<div class=\"text-warning\">⚠️ 7281-2026 IGNORADA: El texto contenía mensajería o viáticos.</div>';</script>";
                flush();
            }
            continue; 
        }

        // ==========================================
        // 6. Insertar Cabecera (AQUÍ ESTABA EL ERROR)
        // ==========================================
        // ¡Restaurada la línea que envía las variables a la consulta!
        $stmtMaster->bind_param("ssssdds", $id_cotiza, $id_clte, $fecha, $status, $valor_usd, $valor_mxn, $id_vend);
        
        if (!$stmtMaster->execute()) {
            $err = addslashes($stmtMaster->error);
            if (!empty($err)) {
                echo "<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger\">❌ SQL ERROR Cabecera ({$id_cotiza}): {$err}</div>';</script>";
                flush();
            }
        }

        // 7. Insertar Detalle
        $stmtItem->bind_param("ssiddsssssss", $id_cotiza, $cdmess, $cant, $valor_usd, $valor_mxn, $desc, $tipo_defecto, $marca, $modelo, $serie, $id_eq_clte, $messtag);
        if ($stmtItem->execute()) {
            $procesados++;
        } else {
            $errores++;
            $err = addslashes($stmtItem->error);
            
            echo "<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger\">❌ SQL ERROR Detalle ({$id_cotiza} | {$cdmess}): {$err}</div>';
            var objDiv = document.getElementById('console-log');
            objDiv.scrollTop = objDiv.scrollHeight;
            </script>";
            flush();
        }

        // ==========================================
        // 8. ACTUALIZACIÓN VISUAL (TIEMPO REAL)
        // ==========================================
        if ($lineasLeidas % 50 === 0 || $lineasLeidas === $totalLineas) {
            $porcentaje = min(100, round(($lineasLeidas / $totalLineas) * 100)); 
            echo "<script>
                document.getElementById('progress-bar').style.width = '{$porcentaje}%';
                document.getElementById('progress-bar').innerText = '{$porcentaje}%';
                document.getElementById('counter-procesados').innerText = '{$procesados}';
                document.getElementById('counter-ignorados').innerText = '{$ignorados}';
                document.getElementById('counter-errores').innerText = '{$errores}';
                document.getElementById('status-text').innerHTML = 'Analizando fila <strong>{$lineasLeidas}</strong> de {$totalLineas}...';
            </script>";
            flush(); 
        }
    }

    $conn->commit(); 
    echo "<script>
        document.getElementById('status-text').innerHTML = '<span class=\"text-success fw-bold\"><i class=\"bi bi-check-circle-fill me-1\"></i>¡Importación finalizada con éxito!</span>';
        document.getElementById('progress-bar').classList.remove('progress-bar-animated', 'progress-bar-striped');
        document.getElementById('progress-bar').classList.add('bg-success');
    </script>";

} catch (Exception $e) {
    $conn->rollback(); 
    echo "<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger fw-bold\">Error Crítico: " . addslashes($e->getMessage()) . "</div>';</script>";
}

$conn->query("SET FOREIGN_KEY_CHECKS = 1");
fclose($gestor);
$stmtMaster->close();
$stmtItem->close();
$conn->close();
?>
</body>
</html>

```