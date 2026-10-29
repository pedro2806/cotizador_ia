<?php
/**
 * Importador Visual con Consola de Diagnóstico de Errores
 * CORRECCIÓN: bind_param restaurado en $stmtMaster
 */
ini_set('memory_limit','512M');
set_time_limit(0); 

if (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

require_once __DIR__ . '/conexion.php';
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
<body class="d-flex align-items-center justify-content-center min-vh-100 py-4">
    
    <div class="container" style="max-width: 700px;">
        <div class="card card-import p-4 p-md-5">
            <div class="text-center mb-4">
                <h4 class="fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Procesando Archivo CSV</h4>
                <p class="text-muted small mb-0" id="status-text"><?php echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Preparando importación...' : 'Selecciona el archivo CSV que deseas importar.'; ?></p>
            </div>

            <form method="post" enctype="multipart/form-data" class="mb-4">
                <label for="csv_file" class="form-label fw-semibold">Archivo CSV</label>
                <input class="form-control mb-3" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                <div class="form-text mb-3">Tamaño máximo permitido: 100 MB.</div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-upload me-1"></i>Importar archivo
                </button>
            </form>
            
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '</body></html>';
    exit;
}

if (
    (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && empty($_POST)
    && empty($_FILES)
) {
    $tamanoSolicitud = round((int) $_SERVER['CONTENT_LENGTH'] / 1048576, 2);
    $limitePost = ini_get('post_max_size');
    $limiteArchivo = ini_get('upload_max_filesize');
    $mensaje = "La solicitud de {$tamanoSolicitud} MB excede el límite POST efectivo de {$limitePost}. Límite de archivo: {$limiteArchivo}. Reinicia Apache desde WAMP después de cambiar la configuración.";
    echo '<script>document.getElementById("console-log").textContent = ' .
        json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
        ';</script>';
    echo '<script>document.getElementById("status-text").textContent = "No se pudo iniciar la importación.";</script></body></html>';
    exit;
}

// ==========================================
// 2. VALIDAR Y LEER EL ARCHIVO SUBIDO
// ==========================================
$errorCarga = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
$nombreArchivo = $_FILES['csv_file']['name'] ?? '';
$archivo = $_FILES['csv_file']['tmp_name'] ?? '';

$mensajesErrorCarga = [
    UPLOAD_ERR_INI_SIZE => 'El archivo supera el tamaño máximo permitido por el servidor.',
    UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido por el formulario.',
    UPLOAD_ERR_PARTIAL => 'El archivo se recibió parcialmente. Intenta subirlo de nuevo.',
    UPLOAD_ERR_NO_FILE => 'Selecciona un archivo CSV antes de iniciar la importación.',
    UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene configurada una carpeta temporal para las cargas.',
    UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar temporalmente el archivo.',
    UPLOAD_ERR_EXTENSION => 'Una extensión del servidor impidió recibir el archivo.'
];

if ($errorCarga !== UPLOAD_ERR_OK) {
    $mensaje = $mensajesErrorCarga[$errorCarga] ?? 'Ocurrió un error al recibir el archivo.';
    echo '<script>document.getElementById("console-log").textContent = ' .
        json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
        ';</script>';
    echo '<script>document.getElementById("status-text").textContent = "No se pudo iniciar la importación.";</script></body></html>';
    exit;
}

if (strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION)) !== 'csv' || !is_uploaded_file($archivo) || !is_readable($archivo)) {
    $mensaje = 'El archivo seleccionado no es un CSV válido o no se pudo leer.';
    echo '<script>document.getElementById("console-log").textContent = ' .
        json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
        ';</script></body></html>';
    exit;
}

$gestor = fopen($archivo, "r");
if ($gestor === false) {
    die("<script>document.getElementById('console-log').innerHTML += '<div class=\"text-danger\">No fue posible abrir $nombreArchivo.</div>';</script></body></html>");
}

$columnasRequeridas = [
    'IDCOTIZA', 'IDCLTE', 'FECHA', 'STATUS', 'VALOR_USD', 'VALOR_MXP',
    'CANT', 'CDMESS', 'DESCRIPCION', 'IDEQUIPOCLIENTE', 'MESS_TAG',
    'MARCA', 'MODELO', 'NOSERIE', 'IDUSRVEND'
];
$normalizarEncabezados = static function (array $valores) {
    $valores = array_map(static function ($valor) {
        return strtoupper(trim((string) $valor));
    }, $valores);
    if (isset($valores[0])) {
        $valores[0] = preg_replace('/^\xEF\xBB\xBF/', '', $valores[0]);
    }
    return $valores;
};

$encabezados = false;
while (($filaEncabezado = fgetcsv($gestor, 0, ",", '"', "")) !== false) {
    $candidato = $normalizarEncabezados($filaEncabezado);
    if (count(array_diff($columnasRequeridas, $candidato)) === 0) {
        $encabezados = $candidato;
        break;
    }
}

if ($encabezados === false) {
    fclose($gestor);
    $mensaje = 'No se encontró el encabezado esperado. Verifica que el CSV contenga las columnas requeridas.';
    echo '<script>document.getElementById("console-log").textContent = ' .
        json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
        ';</script></body></html>';
    exit;
}

$inicioDatos = ftell($gestor);
$totalLineas = 0;
while (fgetcsv($gestor, 0, ",", '"', "") !== false) {
    $totalLineas++;
}
if (fseek($gestor, $inicioDatos) !== 0) {
    fclose($gestor);
    $mensaje = 'No se pudo volver al inicio de los registros del CSV.';
    echo '<script>document.getElementById("console-log").textContent = ' .
        json_encode($mensaje, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) .
        ';</script></body></html>';
    exit;
}

// ==========================================
// 3. PREPARAR SENTENCIAS
// ==========================================
$stmtMaster = $conn->prepare("INSERT IGNORE INTO cotizaciones (IDCOTIZA, IDCLTE, FECHA, STATUS, VALOR_USD, VALOR_MXN, IDUSRVEND) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmtItem   = $conn->prepare("INSERT INTO cotizaciones_items (IDCOTIZA, CDMESS, CANT, PRECIO_VENTA, PRECIO_VENTA_MXN, DESCRIPCION, TIPO, MARCA, MODELO, SERIE, ID_EQ_CLIENTE, MESSTAG) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$procesados = 0;
$errores = 0;
$ignorados = 0;
$lineasLeidas = 0; 

$normalizar = static function ($valor) {
    $valor = trim((string) $valor);
    return $valor === "'-" ? '' : $valor;
};

$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->begin_transaction(); 

try {
    while (($datos = fgetcsv($gestor, 0, ",", '"', "")) !== false) {
        $lineasLeidas++; 
        
        if (empty($datos) || count($datos) !== count($encabezados)) {
            $ignorados++;
            continue;
        }

        $fila = array_combine($encabezados, $datos);
        if ($fila === false || trim($fila['IDCOTIZA']) === '') {
            $ignorados++;
            continue;
        }

        // ==========================================
        // 4. MAPEO DE DATOS
        // ==========================================
        $id_cotiza  = $normalizar($fila['IDCOTIZA']);
        $id_clte    = $normalizar($fila['IDCLTE']);
        $fecha      = $normalizar($fila['FECHA']);
        $status     = $normalizar($fila['STATUS']);
        $valor_usd  = (float) $normalizar($fila['VALOR_USD']);
        $valor_mxn  = (float) $normalizar($fila['VALOR_MXP']);
        $cant       = (int) $normalizar($fila['CANT']);
        $cdmess     = $normalizar($fila['CDMESS']);
        $desc       = $normalizar($fila['DESCRIPCION']);
        $id_eq_clte = $normalizar($fila['IDEQUIPOCLIENTE']);
        $messtag    = $normalizar($fila['MESS_TAG']);
        $marca      = $normalizar($fila['MARCA']);
        $modelo     = $normalizar($fila['MODELO']);
        $serie      = $normalizar($fila['NOSERIE']);
        $id_vend    = $normalizar($fila['IDUSRVEND']);

        if ($id_cotiza === '') {
            $ignorados++;
            continue;
        }

        if ($cant <= 0) $cant = 1;

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
            $porcentaje = $totalLineas > 0 ? min(100, round(($lineasLeidas / $totalLineas) * 100)) : 100;
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