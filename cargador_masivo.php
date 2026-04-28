<?php
include 'conexion.php';
include 'funcionesWorker.php';


$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['lista_excel'])) {
    $id_proyecto = "PROY-" . date("Ymd") . "-" . rand(100, 999);
    $lineas = explode("\n", $_POST['lista_excel']);
    $insertados = 0;

    $stmt = $conn->prepare("INSERT INTO cola_procesamiento (id_proyecto, entrada_usuario, estatus) VALUES (?, ?, 'pendiente')");

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (!empty($linea)) {
            $stmt->bind_param("ss", $id_proyecto, $linea);
            $stmt->execute();
            $insertados++;
        }
    }
    $stmt->close();
    
    // Redirección inmediata al monitor para ver la magia en tiempo real
    header("Location: monitor_precios_v2.php?proyecto=" . urlencode($id_proyecto));
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizador | MessIAs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mess-blue: #002d5a;
            --mess-gold: #ffc107;
        }

        body { 
            background-color: #f0f2f5; 
            font-family: 'Inter', sans-serif;
        }

        .navbar-custom {
            background-color: var(--mess-blue);
            border-bottom: 3px solid var(--mess-gold);
        }

        .card-upload {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header-mess {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1.5rem 2rem;
        }

        .textarea-console {
            background-color: #fafafa;
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            resize: none;
            transition: all 0.3s ease;
        }

        .textarea-console:focus {
            background-color: #fff;
            border-color: var(--mess-blue);
            box-shadow: 0 0 0 4px rgba(0,45,90,0.05);
        }

        .step-badge {
            width: 30px;
            height: 30px;
            background: var(--mess-blue);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .btn-upload {
            background: var(--mess-blue);
            color: white;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-upload:hover {
            background: #004080;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,45,90,0.2);
            color: white;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-5 shadow">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="logo.png" alt="MESS" height="40" class="me-3">
            <span class="fw-bold border-start ps-3">MessIAs | Smart Pricing</span>
        </a>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill">
            <i class="bi bi-arrow-left"></i> Volver a Inicio
        </a>
    </div>
</nav>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="card card-upload">
                <div class="card-header-mess">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="fw-bold mb-1 text-dark">Nueva Carga de Proyecto</h4>
                            <p class="text-muted mb-0 small">Carga descripciones o códigos CDMESS para análisis con referencias en el histórico de cotizaciones y sugerencias de MessIAs.</p>
                        </div>
                        <i class="bi bi-cloud-arrow-up text-primary fs-1 opacity-25"></i>
                    </div>
                </div>
                
                <div class="card-body p-4 p-md-5">
                    <div class="row g-5">
                        <div class="col-md-4">
                            <h6 class="fw-bold text-uppercase small text-muted mb-4">Instrucciones</h6>
                            <div class="mb-4 d-flex align-items-start">
                                <span class="step-badge">1</span>
                                <p class="small text-secondary">Copia las **descripciones** o **claves mess** para el an&aacute;lisis.</p>
                            </div>
                            <div class="mb-4 d-flex align-items-start">
                                <span class="step-badge">2</span>
                                <p class="small text-secondary">Pega el contenido en el recuadro de la derecha. Debes agregar un registro por linea</p>
                            </div>
                            <div class="mb-4 d-flex align-items-start">
                                <span class="step-badge">3</span>
                                <p class="small text-secondary">Haz clic en procesar para que MessIAs inicie el an&aacute;lisis.</p>
                            </div>
                            
                            <div class="alert alert-warning border-0 rounded-4 p-3 mt-5">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <small><strong>Tip:</strong> Puedes mezclar códigos como `S8-5` con descripciones como `Calibración de vernier`.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <form method="POST">
                                <div class="mb-4">
                                    <label class="form-label fw-bold small">Items de la cotización</label>
                                    <textarea name="lista_excel" class="form-control textarea-console" rows="12" 
                                        placeholder="S8-5&#10;Calibración de CMM con acreditación EMA&#10;Durómetro Vickers HV5..."></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-upload btn-lg mb-3">
                                        <i class="bi bi-cpu-fill me-2"></i> Iniciar Análisis con MessIAs
                                    </button>
                                    <p class="text-center text-muted x-small">
                                        Al procesar, se generará un ID de proyecto automático para seguimiento.
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-5 text-center">
                <div class="col-md-6 mx-auto">
                    <a href="monitor_precios_v2.php" class="text-decoration-none text-secondary small fw-bold">
                        <i class="bi bi-view-list me-1"></i> Ir al monitor de proyectos activos
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="py-4 text-center text-muted border-top bg-white mt-auto">
      <div class="container">
        <hr class="opacity-10 mb-4">
        <p class="mb-1 fw-bold">GRUPO MESS</p>
        <p class="small mb-0">Desarrollo y Sistematización | MessIAs&copy; IA</p>
        <small class="opacity-50">Versión 2.1 - 2026</small>
    </div>
</footer>

</body>
</html>