<?php
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title>Cotizador | MessIAs</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <script src="js/sweetalert2.all.min.js"></script>
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
            width: 35px;
            height: 35px;
            background: var(--mess-blue);
            color: white;
            border-radius: 50%;                         
            flex-shrink: 0;             
            box-sizing: border-box;             
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 5px;
            font-size: 14px; 
            line-height: 1;
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
            <img src="img/desarrollo-tecnologia.png" alt="Logo MESS" style="height: 55px; background: white; padding: 5px; border-radius: 8px;">            
        </a>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill">
            <i class="bi bi-arrow-left"></i> Volver a Inicio
        </a>
    </div>
</nav>

<div class="container mb-2">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            
            <div class="card card-upload">
                <div class="card-header-mess">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Nueva Carga de Proyecto</h5>
                            <p class="text-muted mb-0 small">Carga descripciones o códigos CDMESS para análisis con referencias en el histórico de cotizaciones y sugerencias de MessIAs.</p>
                        </div>
                        <a href="monitor_precios_v2.php" class="text-decoration-none text-secondary small fw-bold">
                            <i class="bi bi-view-list me-1"></i> Ir al monitor de proyectos activos
                        </a>                        
                    </div>
                </div>

                <form id="formCargaMasiva">                
                    <div class="card-body p-4 p-md-4">
                        <div class="row mb-3">
                            <div class="col-md-4"></div>
                            <div class="col-md-8">
                                <label for="tipoBusqueda" class="form-label fw-bold small">Tipo de búsqueda</label>
                                <select name="tipoBusqueda" id="tipoBusqueda" class="form-select">
                                    <option value="todas" selected>Mixto (Todas las opciones)</option>
                                    <option value="descripciones">Descripciones</option>
                                    <option value="codigos">Clave MESS</option>
                                    <option value="modelo">Modelo</option>
                                    <option value="noSerie">Número de Serie</option>
                                    <option value="messTag">Mess Tag</option>
                                    <option value="IdEquipoCliente"> ID Equipo Cliente</option>
                                </select>
                            </div>
                        </div> 
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
                                <div class="mb-4">
                                    <label class="form-label fw-bold small">Items de la cotización</label>
                                    <textarea name="lista_excel" id="lista_excel" class="form-control textarea-console" rows="12" required
                                        placeholder="S8-5&#10;Calibración de CMM con acreditación EMA&#10;Durómetro Vickers HV5..."></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" id="btnSubmit" class="btn btn-upload btn-lg mb-3">
                                        <i class="bi bi-cpu-fill me-2"></i> Iniciar Análisis con MessIAs
                                    </button>
                                    <p class="text-center text-muted x-small">
                                        Al procesar, se generará un ID de proyecto automático para seguimiento.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>            
        </div>
    </div>
</div>

<footer class="py-2 text-center text-muted border-top bg-white mt-auto">
    <div class="container">       
        <p class="mb-1 fw-bold">Mess Servicios Metrológicos, S. de R.L. de C.V.</p>
        <p class="small mb-0">Business intelligence | MessIAs&copy;</p>
        <small class="opacity-50">2026</small>
    </div>
</footer>

<script>
document.getElementById('formCargaMasiva').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const btn = document.getElementById('btnSubmit');
    const formData = new FormData(form);

    // Deshabilitar interfaz mientras procesa
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Enviando datos a MessIAs...';

    // Petición dirigida al nuevo archivo en la misma raíz
    fetch('procesar_carga.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Error en la comunicación con el servidor.');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Manejo de alertas parciales
            if (data.reporte_errores && data.reporte_errores.length > 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Proceso con observaciones',
                    html: `Se registraron <b>${data.insertados}</b> filas correctamente.<br><br><div style="text-align:left; font-size:0.85em; max-height:180px; overflow-y:auto; background:#f7f9fa; padding:10px; border-radius:8px;">${data.reporte_errores.join('<br>')}</div>`,
                    confirmButtonText: 'Ir al Monitor',
                    confirmButtonColor: '#002d5a'
                }).then(() => { 
                    window.location.href = `monitor_precios_v2.php?proyecto=${encodeURIComponent(data.id_proyecto)}&tipoBusqueda=${encodeURIComponent(data.tipoBusqueda)}`; 
                });
            } else {
                // Éxito total sin advertencias: Redirección instantánea sin interrumpir
                window.location.href = `monitor_precios_v2.php?proyecto=${encodeURIComponent(data.id_proyecto)}&tipoBusqueda=${encodeURIComponent(data.tipoBusqueda)}`;
            }
        } else {
            // Error controlado retornado desde PHP
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: data.error || 'No se pudo procesar ninguna línea.',
                confirmButtonColor: '#ffc107'
            });
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cpu-fill me-2"></i> Iniciar Análisis con MessIAs';
        }
    })
    .catch(error => {
        // Error grave de red o parseo JSON
        Swal.fire({
            icon: 'error',
            title: 'Falla en la Solicitud',
            text: error.message || 'No se pudo completar la operación.',
            confirmButtonColor: '#d33'
        });
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cpu-fill me-2"></i> Iniciar Análisis con MessIAs';
    });
});
</script>

</body>
</html>