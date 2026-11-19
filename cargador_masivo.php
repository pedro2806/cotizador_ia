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
        /* Diseño messIAs tomado de index.php (diseño aprobado "MessIAs Login.pptx").
           Solo presentación: no se toca lógica PHP ni JavaScript. */
        :root {
            --mx-navy: #002060;        /* encabezado y pie */
            --mx-oro: #FFC911;         /* filetes bajo el encabezado y sobre el pie */
            --mx-azul: #2E75B6;        /* banda central */
            --mx-azul-btn: #013569;    /* botones */
            --mx-azul-btn-hover: #02478c;
            --mx-rojo: #C00000;        /* botón Salir / cancelar */
            --mx-rojo-hover: #9a0000;
            --mx-borde-tarjeta: #172C51;
            --mx-texto-titulo: #3B3838;
            --mx-texto-desc: #767171;
            --mx-gris-bienvenido: #D9D6D6;
            --mx-fondo: #F8F9FA;
        }

        html, body { height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            /* Banda azul con la red de nodos de index.php (.mx-main). */
            background: var(--mx-azul) url("img/messias/red-fondo.png") no-repeat right bottom / cover fixed;
            font-family: Calibri, Carlito, "Segoe UI", system-ui, -apple-system, sans-serif;
            font-size: 16px;
            color: var(--mx-texto-titulo);
        }
        body > .container { flex: 1 0 auto; }

        /* Encabezado: mismo marco que index.php (.mx-header). */
        .navbar-custom {
            background-color: var(--mx-navy);
            border-bottom: 3px solid var(--mx-oro);
        }
        /* Pie: mismo marco que index.php (.mx-footer). */
        .mx-footer {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 8px 16px;
            background: var(--mx-navy);
            border-top: 3px solid var(--mx-oro);
            color: var(--mx-gris-bienvenido, #D9D6D6);
        }
        .mx-footer img { height: 56px; width: auto; max-width: 100%; display: block; }
        .mx-footer p, .mx-footer small { color: #D9D6D6; }

        .card-upload {
            border: 1px solid var(--mx-borde-tarjeta);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        /* Tarjeta principal del formulario: blanca como .mx-card. */
        .mx-card-form {
            border: 1px solid var(--mx-borde-tarjeta);
            border-radius: 12px;
        }
        .card-header-mess {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1.5rem 2rem;
        }
        .textarea-console {
            background-color: #fafafa;
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            font-family: 'Fira Code', monospace;
            font-size: 0.9rem;
            resize: none;
            transition: all 0.3s ease;
        }
        .textarea-console:focus {
            background-color: #fff;
            border-color: var(--mx-azul-btn);
            box-shadow: 0 0 0 4px rgba(1, 53, 105, 0.08);
        }
        .step-badge {
            width: 35px;
            height: 35px;
            background: var(--mx-azul-btn);
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
            background: var(--mx-azul-btn);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .btn-upload:hover {
            background: var(--mx-azul-btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(1, 53, 105, 0.2);
            color: white;
        }
        /* Selects del formulario en azul messIAs. */
        .form-select:focus {
            border-color: var(--mx-azul-btn);
            box-shadow: 0 0 0 3px rgba(1, 53, 105, 0.12);
        }

        /* Modal de SweetAlert2: se ajusta por CSS, sin tocar el
           JavaScript que lo dispara ni su contenido. */
        .swal2-popup {
            font-family: Calibri, Carlito, "Segoe UI", system-ui, -apple-system, sans-serif;
            border-radius: 12px;
            border: 1px solid var(--mx-borde-tarjeta);
        }
        .swal2-title { color: var(--mx-texto-titulo); }
        .swal2-confirm { background-color: var(--mx-azul-btn) !important; }
        .swal2-cancel { background-color: var(--mx-rojo) !important; }
        .swal2-confirm:focus, .swal2-cancel:focus {
            box-shadow: 0 0 0 3px rgba(255, 201, 17, .55) !important;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-2 shadow">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI" style="height: 64px;">            
        </a>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill">
            <i class="bi bi-arrow-left"></i> Volver a Inicio
        </a>
    </div>
</nav>

<div class="container mb-0">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card shadow-sm mb-0 mx-card-form">
                
                <!-- Header -->
                <div class="card-header bg-white border-bottom-0">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h5 class="fw-bold mb-1 text-dark">Nueva Carga de Proyecto (Cotización)</h5>                            
                        </div>
                        <!--<a href="monitor_precios_v2.php" class="btn btn-light btn-sm text-secondary fw-bold border">
                            <i class="bi bi-view-list me-1"></i> Ir al monitor de proyectos activos
                        </a>-->
                    </div>
                </div>

                <!-- Formulario -->
                <form id="formCargaMasiva">
                    <div class="card-body px-2 px-md-4 pb-4 pt-2">
                        <div class="row gy-5 gx-lg-5">
                            
                            <!-- Columna Izquierda: Instrucciones -->
                            <div class="col-lg-3 order-2 order-lg-1">
                                <div class="bg-light p-3 rounded-4 h-100 border border-light-subtle">
                                    <p class="text-muted mb-4 small">Carga descripciones o códigos CDMESS para análisis con referencias en el histórico de cotizaciones y sugerencias de MessIAs.</p>
                                    
                                    <h6 class="fw-bold text-uppercase small text-muted mb-4">Instrucciones</h6>
                                    
                                    <div class="mb-2 d-flex align-items-start">
                                        <span class="badge bg-secondary rounded-circle me-3 mt-2">1</span>
                                        <p class="small text-secondary mb-2">Copia las *descripciones* o *claves mess* para el análisis.</p>
                                    </div>
                                    
                                    <div class="mb-2 d-flex align-items-start">
                                        <span class="badge bg-secondary rounded-circle me-3 mt-2">2</span>
                                        <p class="small text-secondary mb-2">Pega el contenido en el recuadro de la derecha; un registro por línea.</p>
                                    </div>
                                    
                                    <div class="mb-2 d-flex align-items-start">
                                        <span class="badge bg-secondary rounded-circle me-3 mt-2">3</span>
                                        <p class="small text-secondary mb-2">Haz clic en iniciar análisis con MessIAs</p>
                                    </div>
                                    
                                    <div class="alert alert-warning border-0 rounded-3 p-3 mt-4 mb-0 shadow-sm">
                                        <div class="d-flex align-items-start">
                                            <i class="bi bi-info-circle-fill text-warning fs-5 me-2" style="line-height: 1;"></i>
                                            <small class="text-dark"><strong>Tip:</strong><br> Puedes mezclar códigos como <code>S8-5</code> con descripciones como <code>Calibración de vernier</code>.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Columna Derecha: Inputs y Acción -->
                            <div class="col-lg-9 order-1 order-lg-2">    
                                <!-- Fila para alinear Tipo de Búsqueda y Cliente -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tipoBusqueda" class="form-label fw-bold small text-dark">Tipo de búsqueda</label>
                                            <select name="tipoBusqueda" id="tipoBusqueda" class="form-select border-secondary-subtle shadow-sm">
                                                <option value="todas" selected>Todas los filtros</option>
                                                <option value="descripciones">Descripciones</option>
                                                <option value="codigos">Clave MESS</option>
                                                <option value="modelo">Modelo</option>
                                                <option value="noSerie">Número de Serie</option>
                                                <option value="messTag">Mess Tag</option>
                                                <option value="IdEquipoCliente">ID Equipo Cliente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="cliente" class="form-label fw-bold small text-dark">Cliente</label>
                                            <select name="cliente" id="cliente" class="form-select border-secondary-subtle shadow-sm" style="width: 100%;">
                                                <option value="todos" selected>Todos los clientes</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Área de Items -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-dark" for="lista_excel">Items de la cotización</label>
                                    <textarea name="lista_excel" id="lista_excel" class="form-control border-secondary-subtle shadow-sm textarea-console font-monospace bg-light" rows="12" required placeholder="S8-5&#10;Calibración de CMM con acreditación EMA&#10;Durómetro Vickers HV5..."></textarea>
                                </div>
                                
                                <!-- Botón y pie de nota -->
                                <div class="d-grid gap-2 mt-2">
                                    <button type="submit" id="btnSubmit" class="btn btn-primary btn-lg py-3 fw-bold rounded-3 shadow-sm btn-upload">
                                        <i class="bi bi-cpu-fill me-2 fs-5"></i> Iniciar Análisis con MessIAs
                                    </button>
                                    <p class="text-center text-muted small mt-2 mb-0">
                                        <i class="bi bi-shield-check me-1"></i> Al procesar, se generará un ID de proyecto automático para seguimiento.
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

<footer class="mx-footer mt-auto">
    <img src="img/messias/logo-mess-b1.png" alt="Grupo mess, all about metrology · B1 Business Intelligence">
    <p class="small mb-0">Business intelligence | MessIAs&copy;</p>
    <small class="opacity-75">2026</small>
</footer>
<!-- CSS de Select2 y su adaptador Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- JS de jQuery y Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#cliente').select2({
        theme: 'bootstrap-5', // Si usas el tema de Bootstrap 5 para Select2
        placeholder: 'Todos los clientes',
        allowClear: true,
        language: {
            inputTooShort: function() {
                return "Escribe para buscar un cliente...";
            },
            noResults: function() {
                return "No se encontraron clientes";
            },
            searching: function() {
                return "Buscando...";
            }
        },
        ajax: {
            url: 'buscar_clientes.php', // El archivo PHP que hace la consulta a la BD
            dataType: 'json',
            delay: 500, // Espera 500ms después de que el usuario deje de escribir para hacer la petición
            data: function (params) {
                return {
                    q: params.term || '' // Término que el usuario está escribiendo
                };
            },
            processResults: function (data) {
                return {
                    ///pintar id en el value y text, estado y ciudad en el objeto adicional para mostrar en la interfaz, estos 2 ultimos entre parentesis
                    results: data.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text + (item.estado ? ' (' + item.estado + (item.ciudad ? ', ' + item.ciudad : '') + ')' : ''),
                            estado: item.estado,
                            ciudad: item.ciudad
                        };
                    })
                };
            },
            cache: true
        }
    });
});

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