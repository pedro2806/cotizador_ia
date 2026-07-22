<?php
session_start();

// 1. SI ES UNA PETICIÓN AJAX / POST (Procesar el archivo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Validar sesión activa
    if (empty($_SESSION['usuario_id'])) {
        echo json_encode(['success' => false, 'error' => 'Sesión expirada o no válida.']);
        exit;
    }

    // Incluir conexión (ajusta el nombre si tu archivo se llama diferente)
    include 'conexion.php';

    if (empty($_FILES['csv_file'])) {
        echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo CSV válido para procesar.']);
        exit;
    }

    try {
        $conn->begin_transaction();

        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($fileTmpPath, "r");

        if ($handle === FALSE) {
            throw new Exception("No se pudo abrir el archivo CSV proporcionado.");
        }

        // Omitir cabecera (cambia la coma ',' por ';' si tu Excel usa punto y coma)
        fgetcsv($handle, 2000, ",");

        $query_insert = "INSERT IGNORE INTO clientes (
            idclte, region, estado, municipio, parque_ind, zona, 
            id_vendedor, ranking, cliente_largo, cliente_corto, cliente, 
            codigo_postal, calle, ciudad, numero, fecha_creacion, 
            credit_hold, pago_anticipado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query_insert);

        $insertados = 0;
        $duplicados = 0;
        $filas_procesadas = 0;
        $reporte_errores = [];

        while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
            $idclte = trim($data[0] ?? '');
            if ($idclte === '') {
                continue; 
            }

            $filas_procesadas++;

            $val_idclte        = $idclte;
            $val_region        = $data[1] ?? '';
            $val_estado        = $data[2] ?? '';
            $val_municipio     = $data[3] ?? '';
            $val_parque_ind    = ($data[4] !== '') ? $data[4] : null;
            $val_zona          = ($data[5] !== '') ? $data[5] : null;
            $val_id_vendedor   = ($data[6] !== '') ? (int)$data[6] : null;
            $val_ranking       = ($data[7] !== '') ? $data[7] : null;
            $val_cliente_largo = $data[8] ?? '';
            $val_cliente_corto = ($data[9] !== '') ? $data[9] : null;
            $val_cliente       = $data[10] ?? '';
            $val_codigo_postal = ($data[11] !== '') ? $data[11] : null;
            $val_calle         = ($data[12] !== '') ? $data[12] : null;
            $val_ciudad        = ($data[13] !== '') ? $data[13] : null;
            $val_numero        = ($data[14] !== '') ? $data[14] : null;
            $val_fecha_cre     = ($data[15] !== '') ? date('Y-m-d', strtotime($data[15])) : null;
            $val_credit_hold   = isset($data[16]) ? (int)$data[16] : 0;
            $val_pago_anticip  = isset($data[17]) ? (int)$data[17] : 0;

            $stmt->bind_param("ssssssisssssssssii",
                $val_idclte, $val_region, $val_estado, $val_municipio, $val_parque_ind, $val_zona, 
                $val_id_vendedor, $val_ranking, $val_cliente_largo, $val_cliente_corto, $val_cliente, 
                $val_codigo_postal, $val_calle, $val_ciudad, $val_numero, $val_fecha_cre, 
                $val_credit_hold, $val_pago_anticip
            );

            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $insertados++;
            } else {
                $duplicados++;
                $reporte_errores[] = "⚠️ Cliente duplicado omitido (ID Clte: <b>{$val_idclte}</b>)";
            }
        }

        fclose($handle);
        if ($stmt) $stmt->close();

        $conn->commit();

        echo json_encode([
            'success'          => true,
            'insertados'       => $insertados,
            'duplicados'       => $duplicados,
            'procesados'       => $filas_procesadas,
            'reporte_errores'  => $reporte_errores
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->ping()) {
            $conn->rollback();
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Error interno en el procesamiento: ' . $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Clientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0">Importar Catálogo de Clientes</h4>
                    </div>
                    <div class="card-body">
                        <form id="formImportar" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="csv_file" class="form-label">Selecciona tu archivo CSV</label>
                                <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                <div class="form-text">Los registros con el mismo <code>idclte</code> se omitirán automáticamente.</div>
                            </div>
                            <button type="submit" id="btnSubmit" class="btn btn-primary w-100">Subir e Importar</button>
                        </form>

                        <div id="resultado" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('formImportar').addEventListener('submit', function(e) {
        e.preventDefault();
        
        let formData = new FormData(this);
        let btn = document.getElementById('btnSubmit');
        let divResultado = document.getElementById('resultado');

        btn.disabled = true;
        btn.textContent = 'Procesando...';
        divResultado.innerHTML = '';

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Subir e Importar';

            if (data.success) {
                let html = `<div class="alert alert-success">
                    <b>¡Importación exitosa!</b><br>
                    Nuevos insertados: ${data.insertados}<br>
                    Duplicados omitidos: ${data.duplicados}<br>
                    Total procesados: ${data.procesados}
                </div>`;
                
                if (data.reporte_errores && data.reporte_errores.length > 0) {
                    html += `<div class="alert alert-warning small" style="max-height: 150px; overflow-y: auto;">
                        ${data.reporte_errores.join('<br>')}
                    </div>`;
                }
                divResultado.innerHTML = html;
            } else {
                divResultado.innerHTML = `<div class="alert alert-danger"><b>Error:</b> ${data.error}</div>`;
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.textContent = 'Subir e Importar';
            divResultado.innerHTML = `<div class="alert alert-danger">Error de red o conexión con el servidor.</div>`;
        });
    });
    </script>
</body>
</html>