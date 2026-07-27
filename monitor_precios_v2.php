<?php
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
include 'acciones_monitor_precios_v2.php';

// Parámetros para el Sidebar (Carga inicial)
$id_proyecto_activo = $_GET['proyecto'] ?? '';
$tipoBusqueda = $_GET['tipoBusqueda'] ?? 'todo';
$p_pag = isset($_GET['p_pag']) ? (int)$_GET['p_pag'] : 1;
$por_pagina = 12;

$total_registros = ejecutarAccion('CONTAR_TOTAL_PROYECTOS');
$total_paginas = ceil($total_registros / $por_pagina);
$proyectos_query = ejecutarAccion('OBTENER_RESUMEN_PAGINADO', ['pagina' => $p_pag, 'por_pagina' => $por_pagina]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title>Monitor MessIAs | Auditoría de Precios</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="css/sweetalert2.min.css" rel="stylesheet">
    <style>
        :root { --mess-blue: #002d5a; --mess-gold: #ffc107; }
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        
        .navbar-custom { background: var(--mess-blue); border-bottom: 3px solid var(--mess-gold); }
        
        /* Sidebar Estilizado */
        .sidebar-card { border: none; border-radius: 15px; height: calc(100vh - 120px); display: flex; flex-direction: column; background: white; }
        .sidebar-scroll { flex-grow: 1; overflow-y: auto; }
        .list-group-item { border-left: none; border-right: none; padding: 1rem; border-bottom: 1px solid #f0f0f0; transition: 0.2s; }
        .list-group-item.active { background-color: #eef4ff; border-color: transparent; color: var(--mess-blue); border-right: 4px solid var(--mess-blue); }
        
        /* Contenedor de Tabla */
        .table-container { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); min-height: 600px; }
        .table thead { background-color: var(--mess-blue); color: white; }
        
        .font-small { font-size: 0.85rem; }
        .x-small { font-size: 0.75rem; }
        .bg-sugerido { background-color: #f0f7ff !important; }
        .badge-range { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; font-weight: normal; }

        .bg-sugerido {
            background-color: #f8f9fa;
        }
        .bg-sugerido input {
            border: 1px solid #002d5a;
            background-color: #fffceb;
        }
        textarea.x-small {
            font-size: 0.75rem !important;
            resize: none;
        }
        .text-muted { color: #6c757d !important; }

        /* Mejora general de la tabla */
        .table-container {
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            padding: 20px;
        }

        #tabla-precios {
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Encabezados con mejor contraste y espaciado */
        #tabla-precios thead th {
            background-color: var(--mess-blue) !important;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 12px 10px;
            border-top: none;
        }

        /* Filas con efecto de tarjeta limpia */
        .font-small { font-size: 0.85rem; }
        .table-hover tbody tr:hover { background-color: #f8fbff; }

        /* Inputs y Selects integrados (sin bordes pesados) */
        .form-control-sm, .form-select-sm {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .form-control-sm:focus { border-color: var(--mess-blue); box-shadow: none; }

        /* Etiquetas de rango más sutiles */
        .badge-range {
            background: #f1f3f5;
            color: #495057;
            font-weight: 500;
            font-size: 0.70rem;
            display: block;
            margin-bottom: 2px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="img/desarrollo-tecnologia.png" alt="Logo MESS" style="height: 55px; background: white; padding: 5px; border-radius: 8px;">
            <span class="border-start ps-3">Monitor de cotizaciones realizadas por el modelo MessIAs</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white x-small" id="last-update">Sincronizando...</span>
            <a href="cargador_masivo.php" class="btn btn-warning btn-sm fw-bold px-3">Nueva Carga</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-md-2">
            <div class="card sidebar-card shadow-sm">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-collection-fill me-2"></i>Proyectos</h6>
                    <span class="badge bg-light text-dark border"><?php echo $total_registros; ?></span>
                </div>
                <div class="sidebar-scroll">
                    <div class="list-group list-group-flush">
                        <?php if ($proyectos_query): while($p = $proyectos_query->fetch_assoc()): 
                            $listos = (int)$p['listos'];
                            $total = (int)$p['total'];
                            $porcentaje = ($total > 0) ? round(($listos / $total) * 100) : 0;
                            $es_activo = ($id_proyecto_activo == $p['id_proyecto']) ? 'active' : '';
                        ?>
                        <a href="?proyecto=<?php echo urlencode($p['id_proyecto']); ?>&p_pag=<?php echo $p_pag; ?>" class="list-group-item list-group-item-action <?php echo $es_activo; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold x-small text-truncate" style="max-width: 150px;"><?php echo $p['id_proyecto']; ?></span>
                                <span class="fw-bold x-small"><?php echo $porcentaje; ?>%</span>
                            </div>
                            <div class="progress mb-2" style="height: 4px;">
                                <div class="progress-bar <?php echo $es_activo ? 'bg-primary' : 'bg-info'; ?>" style="width: <?php echo $porcentaje; ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between x-small opacity-75">
                                <span><i class="bi bi-layers"></i> <?php echo $listos; ?> / <?php echo $total; ?> ítems</span>
                                <span><?php echo date('d/m/y', strtotime($p['fecha'])); ?></span>
                            </div>
                        </a>
                        <?php endwhile; endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 d-flex justify-content-between p-2">
                    <a href="?p_pag=<?php echo max(1, $p_pag - 1); ?>" class="btn btn-sm btn-outline-secondary <?php echo ($p_pag <= 1) ? 'disabled' : ''; ?>"><i class="bi bi-chevron-left"></i></a>
                    <span class="x-small align-self-center fw-bold">Pág <?php echo $p_pag; ?></span>
                    <a href="?p_pag=<?php echo min($total_paginas, $p_pag + 1); ?>" class="btn btn-sm btn-outline-secondary <?php echo ($p_pag >= $total_paginas) ? 'disabled' : ''; ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
        </div>

        <div class="col-md-10">
            <?php if ($id_proyecto_activo): ?>
            <div class="table-container p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">Detalle del Proyecto</h4>
                        <span class="text-primary fw-bold small"><?php echo htmlspecialchars($id_proyecto_activo); ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-success btn-sm fw-bold" onclick="exportarCSV()">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Exportar seleccionados
                        </button>
                        <!-- <button class="btn btn-outline-danger btn-sm fw-bold" onclick="cargarDatos(true)">
                            <i class="bi bi-exclamation-triangle"></i> Ver solo desviaciones > 15%
                        </button> -->
                        <div id="progreso-header" class="text-end"></div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-precios">
                        <thead>
                            <tr class="x-small text-uppercase">
                                <th style="width: 3%;" class="text-center"><input type="checkbox" id="sel-all" class="form-check-input" onclick="toggleTodos(this)"></th>
                                <th style="width: 10%;">CDMESS</th>
                                <th style="width: 20%;">Descripción</th>
                                <th style="width: 10%;">Última Cot.</th>
                                <th style="width: 13%;">Rango (Min - Max)</th>
                                <th style="width: 10%;">Hist. Promedio</th>
                                <th style="width: 14%;">Sugerido MessIAs</th>
                                <th style="width: 20%;">Entrenamiento</th>
                            </tr>
                        </thead>
                        <tbody id="contenedor-items">
                            </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="table-container d-flex flex-column align-items-center justify-content-center text-center p-5">
                <i class="bi bi-clipboard2-check text-light" style="font-size: 5rem;"></i>
                <h4 class="text-muted mt-3">Panel de Auditoría de Cotizaciones</h4>
                <p class="text-secondary">Selecciona un proyecto del listado para ver el análisis de los precios dados por el modelo MessIAs.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="js/sweetalert2.all.min.js"></script>

<script>
/**
 * ========================================================================
 * ESTADO GLOBAL Y CONFIGURACIÓN
 * ========================================================================
 */
const proyectoActual = "<?php echo $id_proyecto_activo; ?>";
let datosProyecto = [];

/**
 * ========================================================================
 * MOTOR DE RENDERIZADO (UI)
 * Separado del ciclo de carga para optimizar memoria
 * ========================================================================
 */
const fmt = v => parseFloat(v || 0).toFixed(2);

const renderFila = (item, esSugerencia = false) => {
    if (item.estatus !== 'completado') return `<tr><td colspan="8" class="text-center py-3 text-muted x-small italic">Analizando...</td></tr>`;

    const ia = item.propuesta_ia || {};
    const rowClass = esSugerencia ? 'table-warning font-small' : 'font-small';
    const fmt = v => parseFloat(v || 0).toFixed(2);

    return `
        <tr class="${rowClass}">
            <td class="text-center align-middle"><input type="checkbox" class="form-check-input item-check" value="${item.id}"></td>
            <td class="fw-bold text-primary align-middle">
                ${ia.cdmess || 'S/C'}                
                ${item.entrada_usuario ? `<br><span class="x-small text-muted">Sugerencia para: ${item.entrada_usuario}</span>` : ''}
            </td>
            <td class="align-middle">
                <div class="fw-bold">${ia.desc || ''}</div>
                ${ia.detalle_calculo ? `<div class="x-small text-muted">${ia.detalle_calculo}</div>` : ''}
            </td>
            <td class="align-middle x-small">
                ${ia.folio ? `<b>${ia.folio}</b><br>${ia.fecha?.substring(0,10) || ''}` : '-'}
            </td>
            <td class="align-middle text-center x-small">
                <div class="text-muted">USD: $${fmt(ia.precio_min)} - $${fmt(ia.precio_max)}</div>
                <div class="text-muted">MXN: $${fmt(ia.precio_min_mxn)} - $${fmt(ia.precio_max_mxn)}</div>
            </td>
            <td class="align-middle text-center x-small">
                <div><b>USD:</b> $${fmt(ia.precio_promedio)}</div>
                <div><b>MXN:</b> $${fmt(ia.precio_promedio_mxn)}</div>
            </td>
            <td class="align-middle bg-light">
                <div class="x-small text-primary mb-1">Sugerido: $${fmt(ia.precio_ia)}</div>
                <input type="number" id="precio_u_${item.id}" class="form-control form-control-sm fw-bold" value="${parseFloat(item.precio_usuario) > 0 ? fmt(item.precio_usuario) : fmt(ia.precio_ia)}">
            </td>
            <td class="align-middle">
                <select id="cat_u_${item.id}" class="form-select form-select-sm mb-1 x-small">
                    ${['Acepta precio IA','Precio muy bajo','Precio muy alto','Descripción incorrecta'].map(op =>
                        `<option value="${op}" ${item.categoria_rechazo === op ? 'selected' : ''}>${op}</option>`
                    ).join('')}
                </select>
                <button class="btn btn-sm w-100 ${item.estatus === 'completado' ? 'btn-success' : 'btn-outline-primary'}" onclick="validarRegistro(${item.id})">
                    <i class="bi ${item.estatus === 'completado' ? 'bi-check-all' : 'bi-send'}"></i> Validar
                </button>
            </td>
        </tr>
    `;
};

/**
 * ========================================================================
 * CONTROLADORES LÓGICOS (API y Eventos)
 * ========================================================================
 */
async function cargarDatos(soloDesviaciones = false) {
    if (!proyectoActual) return;
    
    // Cambiamos la URL de fetch dinámicamente
    const endpoint = soloDesviaciones ? 'acciones_monitor_precios_v2.php' : `get_proyecto_data.php?proyecto=${encodeURIComponent(proyectoActual)}`;
    
    // Si es desviación, preparamos el FormData
    let options = { method: 'GET' };
    if (soloDesviaciones) {
        const formData = new FormData();
        formData.append('accion', 'OBTENER_DESVIACIONES');
        formData.append('id_proyecto', proyectoActual);
        options = { method: 'POST', body: formData };
    }

    try {
        const response = await fetch(endpoint, options);
        const data = await response.json();
        datosProyecto = data;
        
        const contenedor = document.getElementById('contenedor-items');
        const prevSel = new Set([...document.querySelectorAll('.item-check:checked')].map(cb => cb.value));
        
        document.getElementById('last-update').innerHTML = `<i class="bi bi-clock-history me-1"></i> Sync: ${new Date().toLocaleTimeString()}`;

        const originales = data.filter(i => !i.es_sugerencia);
        const sugerencias = data.filter(i => i.es_sugerencia);
        
        const total = originales.length;
        const listos = originales.filter(i => i.estatus === 'completado').length;
        const pct = total > 0 ? Math.round((listos/total)*100) : 0;
        
        document.getElementById('progreso-header').innerHTML = `
            <div class="fw-bold small mb-1">Avance del Análisis: ${listos} de ${total}</div>
            <div class="progress" style="height: 6px; width: 200px; float: right;">
                <div class="progress-bar bg-success" style="width: ${pct}%"></div>
            </div>
        `;

        const filaDivisora = sugerencias.length > 0 ? `
            <tr class="table-secondary">
                <td colspan="9" class="text-center py-2 x-small fw-bold text-uppercase text-muted">
                    <i class="bi bi-stars me-1"></i> Sugerencias de MessIAs
                </td>
            </tr>
        ` : '';

        contenedor.innerHTML =
            originales.map(i => renderFila(i, false)).join('') +
            filaDivisora +
            sugerencias.map(i => renderFila(i, true)).join('');

        // Restaurar checks previos
        document.querySelectorAll('.item-check').forEach(cb => {
            if (prevSel.has(cb.value)) cb.checked = true;
        });
        const allChecks = document.querySelectorAll('.item-check');
        const selAll = document.getElementById('sel-all');
        if (selAll) selAll.checked = allChecks.length > 0 && [...allChecks].every(cb => cb.checked);

    } catch (e) {
        console.error("Error en la carga AJAX:", e);
    }
}

async function validarRegistro(id) {
    const precio = document.getElementById(`precio_u_${id}`).value;
    const respuesta = document.getElementById(`resp_u_${id}`).value;
    const categoria = document.getElementById(`cat_u_${id}`).value;
    const idUsuario = <?php echo intval($_SESSION['usuario_id'] ?? 0); ?>;

    Swal.fire({
        title: '¿Confirmar validación?',
        text: "El precio y nota se guardarán como aprendizaje para la IA.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#002d5a', 
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="bi bi-check-lg"></i> Sí, validar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Procesando...',
                    didOpen: () => { Swal.showLoading() },
                    allowOutsideClick: false
                });

                const formData = new FormData();
                formData.append('accion', 'GUARDAR_APROBACION_HUMANA');
                formData.append('id', id);
                formData.append('precio_usuario', precio);
                formData.append('respuesta', respuesta);
                formData.append('id_usuario', idUsuario);
                formData.append('categoria_rechazo', categoria);

                const response = await fetch('acciones_monitor_precios_v2.php', {
                    method: 'POST',
                    body: formData
                });

                const res = await response.json();

                if (res.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'El precio ha sido validado y la IA ha aprendido de esta corrección.',
                        icon: 'success',
                        confirmButtonColor: '#002d5a'
                    }).then(() => {
                        const urlParams = new URLSearchParams(window.location.search);
                        if (urlParams.has('proyecto')) {
                            cargarDatos();
                        }
                    });
                } else {
                    throw new Error('Error en la respuesta del servidor');
                }
            } catch (error) {
                console.error("Error al validar:", error);
                Swal.fire('Error', 'No se pudo conectar con el servidor físico.', 'error');
            }
        }
    });
}

/**
 * ========================================================================
 * UTILIDADES (Exportación y UI)
 * ========================================================================
 */
function toggleTodos(cb) {
    document.querySelectorAll('.item-check').forEach(el => el.checked = cb.checked);
}

function exportarCSV() {
    const seleccionados = new Set([...document.querySelectorAll('.item-check:checked')].map(cb => cb.value));
    if (seleccionados.size === 0) {
        Swal.fire({ title: 'Sin selección', text: 'Selecciona al menos un ítem para exportar.', icon: 'warning', confirmButtonColor: '#002d5a' });
        return;
    }
    
    const items = datosProyecto.filter(i => seleccionados.has(String(i.id)));
    const fmt = v => parseFloat(v || 0).toFixed(2);
    
    const areaCode = cdmess => cdmess ? cdmess.split('-')[0] : '';
    const entityType = cdmess => cdmess && cdmess.toUpperCase().startsWith('S') ? 'SERVICE' : 'PRODUCT';

    const headers = [
        'orderCode', 'service_product_mess_code', 'cant', 'item_description',
        'MARCA', 'MODELO', 'NO_SERIE',
        'entityType', 'mess_precio_min', 'mess_precio_max', 'mess_precio_promedio',
        'precio_us', 'mess_precio_ia',
        'notes', 'observations',
        'status', 'area_mess_code', 'created', 'elaboratedby_id'
    ];

    const rows = items.map(item => {
        const ia = item.propuesta_ia || {};
        const cdmess = ia.cdmess || '';
        const fecha = item.fecha_registro ? item.fecha_registro.split(' ')[0] : '';

        return [
            proyectoActual,
            cdmess || 'N/A',
            '1',
            ia.desc || item.entrada_usuario || '',
            '', '', '',
            entityType(cdmess),
            fmt(ia.precio_min),
            fmt(ia.precio_max),
            fmt(ia.precio_promedio),
            fmt(item.precio_usuario),
            fmt(ia.precio_ia),
            item.entrada_usuario || '',
            item.respuesta || '',
            item.estatus || '',
            areaCode(cdmess),
            fecha,
            item.id_us_registro || ''
        ].map(v => `"${String(v).replace(/"/g, '""')}"`).join(',');
    });

    const csvContent = '\uFEFF' + [headers.join(','), ...rows].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${proyectoActual}_cotizacion.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// Inicialización del proceso
if (proyectoActual) {
    cargarDatos();
    setInterval(cargarDatos, 10000); // Refresco asíncrono
}
</script>

</body>
</html>