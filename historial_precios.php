<?php
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Valores iniciales de los filtros desde la URL (para poder compartir
// el enlace con filtros puestos). El JavaScript los lee del formulario.
$flt = function ($k) { return htmlspecialchars($_GET[$k] ?? '', ENT_QUOTES, 'UTF-8'); };
$v_proyecto = $flt('proyecto');
$v_cdmess = $flt('cdmess');
$v_desde = $flt('desde');
$v_hasta = $flt('hasta');
$v_origen = $flt('origen');
$v_estatus = $flt('estatus');
$v_q = $flt('q');
$chk_revision = !empty($_GET['solo_revision']) ? 'checked' : '';
$chk_validacion = !empty($_GET['solo_validacion']) ? 'checked' : '';
$sel = function ($campo, $valor) use ($flt) { return ($flt($campo) === $valor) ? 'selected' : ''; };
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title>Historial de cotizaciones | MessIAs</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="css/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/marco_messias.css">
    <style>
        /* Mismo marco messIAs que monitor_precios_v2.php. Solo van aquí
           reglas propias de esta página; los colores usan los tokens del
           marco (--mx-*, --accent). Nada de rojo vivo: los avisos van en
           ámbar (bg-warning text-dark) y el validado en bg-info. */
        .sidebar-card { height: calc(100vh - 280px); display: flex; flex-direction: column; }
        .sidebar-scroll { flex-grow: 1; overflow-y: auto; }
        .table-container { background: transparent; border: 0; box-shadow: none; min-height: 0; }
        .font-small { font-size: 0.85rem; }
        .x-small { font-size: 0.75rem; }
        /* Hijas (es_sugerencia=1): mismo tab que el monitor en la celda
           del código; el resto de las celdas no se indenta. */
        #tabla-historial td.hija-indent { padding-left: 2rem; }
        /* Encabezado de grupo por proyecto: banda suave como el ejemplo
           ("Vendedor: ..."), sin rojo vivo; no es clicable. */
        #tabla-historial tr.fila-grupo td {
            background-color: #dbe7f5;
            color: #1f3a5f;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 6px 10px;
            cursor: default;
        }
        .text-primary { color: var(--accent) !important; }
        #tabla-historial { border-collapse: separate; border-spacing: 0; }
        #tabla-historial thead th {
            background-color: var(--mx-navy) !important;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 12px 10px;
            border-top: none;
        }
        #tabla-historial tbody tr { cursor: pointer; }
        .form-control-sm, .form-select-sm {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .form-control-sm:focus, .form-select-sm:focus { border-color: var(--accent); box-shadow: none; }
        /* Tarjetas del dashboard: número grande en azul del marco, etiqueta
           sobria. Sin colores de alerta en las tarjetas (el aviso vive en
           los badges de la tabla). */
        .stat-card { border: 1px solid #e9ecef; border-radius: 10px; }
        .stat-num { font-size: 1.6rem; font-weight: 700; color: var(--mx-navy); line-height: 1.1; }
        .stat-label { font-size: 0.72rem; text-transform: uppercase; color: #6c757d; font-weight: 600; }
        .swal2-popup { font-family: var(--mx-font); border-radius: 12px; border: 1px solid var(--border); }
        .swal-veredicto { width: 60rem; max-width: 95vw; }
        .swal2-title { color: var(--text); }
        .swal2-confirm { background-color: var(--accent) !important; }
        .swal2-confirm:focus { box-shadow: 0 0 0 3px rgba(255, 201, 17, .55) !important; }
    </style>
</head>
<body class="mx-marco">

<header class="mx-header">
    <a class="mx-volver" href="index.php" title="Volver a Inicio" aria-label="Volver a Inicio"><img src="img/messias/volver.png" alt="Volver"></a>
    <a class="mx-logo" href="index.php"><img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI"></a>
</header>

<main class="mx-main">
<div class="mx-hoja">
    <h1 class="mx-hoja-titulo">Historial de cotizaciones MessIAs</h1>
    <div class="d-flex justify-content-end align-items-center gap-3 mb-3">
        <span class="text-muted x-small" id="last-update">Sincronizando...</span>
        <a href="monitor_precios_v2.php" class="btn btn-outline-secondary btn-sm fw-bold px-3">Ir al monitor</a>
        <a href="cargador_masivo.php" class="btn btn-warning btn-sm fw-bold px-3">Nueva Carga</a>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="card sidebar-card shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-funnel-fill me-2"></i>Filtros</h6>
                </div>
                <div class="sidebar-scroll p-3">
                    <form id="form-filtros" onsubmit="return false;">
                        <div class="mb-2">
                            <label class="form-label x-small fw-bold mb-1" for="f-proyecto">Proyecto</label>
                            <select id="f-proyecto" class="form-select form-select-sm">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label x-small fw-bold mb-1" for="f-cdmess">Código CDMESS</label>
                            <input type="text" id="f-cdmess" class="form-control form-control-sm" placeholder="p. ej. L7-8" value="<?php echo $v_cdmess; ?>">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label x-small fw-bold mb-1" for="f-desde">Desde</label>
                                <input type="date" id="f-desde" class="form-control form-control-sm" value="<?php echo $v_desde; ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label x-small fw-bold mb-1" for="f-hasta">Hasta</label>
                                <input type="date" id="f-hasta" class="form-control form-control-sm" value="<?php echo $v_hasta; ?>">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label x-small fw-bold mb-1" for="f-origen">Origen del precio</label>
                            <select id="f-origen" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="ia" <?php echo $sel('origen', 'ia'); ?>>IA</option>
                                <option value="promedio" <?php echo $sel('origen', 'promedio'); ?>>Promedio (respaldo)</option>
                                <option value="validado" <?php echo $sel('origen', 'validado'); ?>>Precio validado</option>
                                <option value="humano" <?php echo $sel('origen', 'humano'); ?>>Humano</option>
                                <option value="mixto" <?php echo $sel('origen', 'mixto'); ?>>Mixto</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label x-small fw-bold mb-1" for="f-estatus">Estatus</label>
                            <select id="f-estatus" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="completado" <?php echo $sel('estatus', 'completado'); ?>>Completado</option>
                                <option value="procesando" <?php echo $sel('estatus', 'procesando'); ?>>Procesando</option>
                                <option value="pendiente" <?php echo $sel('estatus', 'pendiente'); ?>>Pendiente</option>
                                <option value="error" <?php echo $sel('estatus', 'error'); ?>>Error</option>
                            </select>
                        </div>
                        <div class="form-check mb-1">
                            <input type="checkbox" id="f-revision" class="form-check-input" <?php echo $chk_revision; ?>>
                            <label class="form-check-label x-small" for="f-revision">Solo requieren revisión</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" id="f-validacion" class="form-check-input" <?php echo $chk_validacion; ?>>
                            <label class="form-check-label x-small" for="f-validacion">Solo validación de experto pendiente</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label x-small fw-bold mb-1" for="f-q">Buscar en descripción</label>
                            <input type="text" id="f-q" class="form-control form-control-sm" placeholder="Texto de la línea..." value="<?php echo $v_q; ?>">
                        </div>
                        <input type="hidden" id="f-proyecto-inicial" value="<?php echo $v_proyecto; ?>">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success btn-sm fw-bold" onclick="aplicarFiltros()">Aplicar filtros</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarFiltros()">Limpiar filtros</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-white border-0 p-2">
                    <span class="x-small text-muted" id="resumen-filtros"></span>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="row g-2 mb-3" id="dashboard-stats">
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-total">—</div><div class="stat-label">Total de ítems</div></div></div>
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-completados">—</div><div class="stat-label">Completados</div></div></div>
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-pendientes">—</div><div class="stat-label">Pendientes / en proceso</div></div></div>
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-revision">—</div><div class="stat-label">Requieren revisión</div></div></div>
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-validacion">—</div><div class="stat-label">Validación de experto pendiente</div></div></div>
                <div class="col-6 col-lg-2"><div class="card stat-card shadow-sm p-2 text-center"><div class="stat-num" id="d-validado">—</div><div class="stat-label">Con precio validado</div></div></div>
            </div>
            <div class="x-small text-muted mb-3" id="d-sin-propuesta"></div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold text-dark x-small text-uppercase">Por origen del precio</h6></div>
                        <div class="card-body p-2" id="d-origen"></div>
                        <div class="x-small text-muted px-2 pb-2" id="d-origen-nota"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold text-dark x-small text-uppercase">Top 10 códigos más cotizados</h6></div>
                        <div class="card-body p-2">
                            <table class="table table-sm font-small mb-0">
                                <thead><tr class="x-small text-uppercase text-muted"><th>Código</th><th class="text-end">Ítems</th><th class="text-end">Precio sugerido prom.</th></tr></thead>
                                <tbody id="d-top"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-container p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">Histórico de ítems</h4>
                        <span class="text-muted small" id="resumen-tabla">Todos los proyectos</span>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm fw-bold" onclick="cargarDatos()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-historial">
                        <thead>
                            <tr class="x-small text-uppercase">
                                <th style="width: 11%;">Proyecto</th>
                                <th style="width: 9%;">Fecha</th>
                                <th style="width: 9%;">Código</th>
                                <th style="width: 22%;">Descripción</th>
                                <th style="width: 12%;" class="text-center">Rango USD</th>
                                <th style="width: 10%;" class="text-center">Sugerido USD</th>
                                <th style="width: 11%;">Origen</th>
                                <th style="width: 11%;">Alertas</th>
                                <th style="width: 5%;">Estatus</th>
                            </tr>
                        </thead>
                        <tbody id="contenedor-historial"></tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="x-small text-muted" id="paginacion-info"></span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="x-small text-muted mb-0" for="sel-por-pagina">Por página</label>
                        <select id="sel-por-pagina" class="form-select form-select-sm w-auto" onchange="cambiarPorPagina(this.value)" title="Ítems por página">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary" id="btn-ant" onclick="irPagina(paginaActual - 1)"><i class="bi bi-chevron-left"></i> Anterior</button>
                            <button class="btn btn-outline-secondary" id="btn-sig" onclick="irPagina(paginaActual + 1)">Siguiente <i class="bi bi-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-2"><h6 class="mb-0 fw-bold text-dark x-small text-uppercase">Últimos 10 procesados</h6></div>
                        <div class="card-body p-2">
                            <table class="table table-sm font-small mb-0">
                                <thead><tr class="x-small text-uppercase text-muted"><th>Fecha</th><th>Proyecto</th><th>Código</th><th class="text-end">Precio sugerido</th></tr></thead>
                                <tbody id="d-ultimos"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</main>
<footer class="mx-footer">
    <img src="img/messias/logo-mess-b1.png" alt="Grupo mess, all about metrology · B1 Business Intelligence">
</footer>
<script src="js/sweetalert2.all.min.js"></script>

<script>
/**
 * ========================================================================
 * HISTORIAL DE COTIZACIONES — vista de solo lectura.
 * Lee de get_historial_data.php (todos los proyectos + dashboard).
 * El render de badges y el modal de veredicto reutilizan el patrón de
 * monitor_precios_v2.php; aquí no hay validación humana (sin formulario).
 * ========================================================================
 */
let datosHistorial = [];
let paginaActual = 1;
let totalPaginas = 1;
let porPagina = 10;
let proyectoInicial = null;

const fmt = v => parseFloat(v || 0).toFixed(2);
// R5: todo texto que viene de la BD o del JSON se escapa antes de
// interpolarlo en innerHTML (aquí sí se escapa también la descripción
// y la entrada del usuario, que en el monitor van sin escapar).
const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
const escAttr = v => esc(v);

function leerFiltros() {
    const val = id => { const e = document.getElementById(id); return e ? e.value : ''; };
    const chk = id => { const e = document.getElementById(id); return e ? e.checked : false; };
    return {
        proyecto: val('f-proyecto'),
        cdmess: val('f-cdmess').trim(),
        desde: val('f-desde'),
        hasta: val('f-hasta'),
        origen: val('f-origen'),
        estatus: val('f-estatus'),
        solo_revision: chk('f-revision') ? '1' : '',
        solo_validacion: chk('f-validacion') ? '1' : '',
        q: val('f-q').trim(),
    };
}

function aplicarFiltros() {
    paginaActual = 1;
    cargarDatos();
}

function limpiarFiltros() {
    ['f-proyecto', 'f-cdmess', 'f-desde', 'f-hasta', 'f-origen', 'f-estatus', 'f-q'].forEach(id => {
        const e = document.getElementById(id);
        if (e) e.value = '';
    });
    ['f-revision', 'f-validacion'].forEach(id => {
        const e = document.getElementById(id);
        if (e) e.checked = false;
    });
    paginaActual = 1;
    cargarDatos();
}

function irPagina(n) {
    if (n < 1 || n > totalPaginas || n === paginaActual) return;
    paginaActual = n;
    cargarDatos();
}

// Tamaño de página (10 / 25 / 50 / 100): al cambiarlo se recargan los
// datos desde la página 1. Solo se aceptan los valores del selector.
function cambiarPorPagina(v) {
    const n = parseInt(v, 10);
    if (![10, 25, 50, 100].includes(n)) return;
    porPagina = n;
    paginaActual = 1;
    cargarDatos();
}

/**
 * Badges de un ítem, mismo patrón que el monitor (renderFila):
 * origen del precio (IA / Promedio (respaldo) / Precio validado /
 * Humano / Mixto), insignia "Precio validado" cuando el ítem tiene
 * validado pero la IA ganó, "Revisar" en ámbar y "Requiere validación
 * de experto" en ámbar. En las hijas no se pinta el badge de origen
 * (siempre es histórico).
 */
function badgesHistorial(ia, esHermana) {
    const etiquetas = {ia: 'IA', promedio: 'Promedio (respaldo)', validado: 'Precio validado', humano: 'Humano', mixto: 'Mixto'};
    const etiqueta = ia.origen_precio ? (etiquetas[ia.origen_precio] || ia.origen_precio) : '';
    let origenHtml = '';
    if (etiqueta && !esHermana) {
        const clase = ia.origen_precio === 'promedio' ? 'bg-warning text-dark' : (ia.origen_precio === 'validado' ? 'bg-info text-dark' : 'bg-secondary');
        origenHtml = `<span class="badge ${clase} x-small me-1" title="${escAttr(ia.veredicto_ia || '')}">${etiqueta}</span>`;
    }
    let alertasHtml = '';
    if (ia.tiene_precio_validado && ia.origen_precio !== 'validado' && !esHermana) {
        alertasHtml += `<span class="badge bg-info text-dark x-small me-1" title="Precio validado por el área responsable (tarifario)">Precio validado</span>`;
    }
    if (ia.requiere_revision) {
        alertasHtml += `<span class="badge bg-warning text-dark x-small me-1">Revisar</span>`;
    }
    if (ia.atlas) {
        if (ia.atlas.es_sugerencia || ia.atlas.origen === 'similar') {
            alertasHtml += `<span class="badge bg-warning text-dark x-small me-1">Sugerencia por similitud (sin validar)</span>`;
        } else if (ia.atlas.requiere_validacion_experto) {
            alertasHtml += `<span class="badge bg-warning text-dark x-small me-1" title="Requiere validación de experto, acércate al área correspondiente">Requiere validación de experto</span>`;
        }
    }
    return { origenHtml, alertasHtml };
}

/**
 * Encabezado de grupo por proyecto (una fila de ancho completo, estilo
 * sobrio como el ejemplo: banda suave, sin rojo vivo): proyecto,
 * solicitante, total de ítems y fecha más reciente. Sin solicitante
 * se muestra la etiqueta "Sin registro" en ámbar (nada de rojo).
 * El solicitante y el total se escapan igual que el resto (R5).
 */
function renderEncabezadoProyecto(proy) {
    const sol = (proy.solicitante !== null && proy.solicitante !== undefined && String(proy.solicitante) !== '')
        ? esc(proy.solicitante)
        : '<span class="badge bg-warning text-dark x-small">Sin registro</span>';
    const totalTxt = (proy.total !== null && proy.total !== undefined) ? ` · ${proy.total} ítems` : '';
    const fechaTxt = proy.fecha_reciente ? ` · ${esc(String(proy.fecha_reciente).substring(0, 10))}` : '';
    return `<tr class="fila-grupo"><td colspan="9">Proyecto: ${esc(proy.id_proyecto)} · Solicitante: ${sol}${totalTxt}${fechaTxt}</td></tr>`;
}

/**
 * Tabla agrupada por proyecto: inserta el encabezado de grupo cada vez
 * que cambia el proyecto dentro de la página. Como cada página empieza
 * sin proyecto actual, un proyecto partido entre dos páginas vuelve a
 * mostrar su encabezado arriba en la siguiente.
 */
function renderTablaHistorial(items, proyectos) {
    const porId = {};
    (proyectos || []).forEach(p => { porId[p.id_proyecto] = p; });
    let html = '';
    let actual = null;
    items.forEach(item => {
        if (item.id_proyecto !== actual) {
            actual = item.id_proyecto;
            const meta = porId[actual] || {};
            html += renderEncabezadoProyecto({
                id_proyecto: actual,
                solicitante: meta.solicitante !== undefined ? meta.solicitante : (item.solicitante ?? null),
                total: meta.total !== undefined ? meta.total : null,
                fecha_reciente: meta.fecha_reciente !== undefined ? meta.fecha_reciente : null,
            });
        }
        html += renderFilaHistorial(item);
    });
    return html;
}

const renderFilaHistorial = (item) => {
    const ia = item.propuesta_ia || {};
    const esHermana = !!item.es_sugerencia;
    const { origenHtml, alertasHtml } = badgesHistorial(ia, esHermana);
    const codigo = ia.cdmess || item.cdmess_historico || 'S/C';
    const descripcion = ia.desc || item.descripcion_historica || item.entrada_usuario || '';
    const fecha = item.fecha_registro ? String(item.fecha_registro).substring(0, 10) : '';
    // Rango: min/max limpios del histórico; si no los hay, el rango
    // sugerido por la IA; si tampoco, '—'.
    const rango = (ia.precio_min != null && ia.precio_max != null)
        ? `$${fmt(ia.precio_min)} - $${fmt(ia.precio_max)}`
        : ((ia.precio_ia_min != null && ia.precio_ia_max != null)
            ? `$${fmt(ia.precio_ia_min)} - $${fmt(ia.precio_ia_max)}`
            : '—');
    // Sugerido: en las hijas el precio es el histórico; en el resto el
    // precio de la IA (o el respaldo si no hubo IA).
    const sugerido = esHermana
        ? (ia.precio_promedio != null ? '$' + fmt(ia.precio_promedio) : 'sin historial')
        : (ia.precio_ia != null ? '$' + fmt(ia.precio_ia) : '—');
    const estatusBadge = item.estatus === 'completado'
        ? '<span class="badge bg-success x-small">Completado</span>'
        : (item.estatus === 'error'
            ? '<span class="badge bg-secondary x-small">Error</span>'
            : '<span class="badge bg-warning text-dark x-small">' + esc(item.estatus) + '</span>');
    const sinPropuesta = !item.propuesta_ia
        ? '<div class="x-small text-muted">' + (item.propuesta_valida === false && item.estatus === 'completado' ? 'Sin propuesta válida' : 'Aún no procesado') + '</div>'
        : '';
    return `
        <tr class="font-small${esHermana ? ' table-light' : ''}" onclick="mostrarVeredictoHistorial(${item.id})" title="Ver veredicto">
            <td class="align-middle x-small">${esc(item.id_proyecto)}</td>
            <td class="align-middle x-small">${esc(fecha)}</td>
            <td class="fw-bold text-primary align-middle${esHermana ? ' hija-indent' : ''}">${esHermana ? '↳ ' : ''}${esc(codigo)}</td>
            <td class="align-middle"><div class="fw-bold">${esc(descripcion)}</div>${sinPropuesta}</td>
            <td class="align-middle text-center x-small">${rango}</td>
            <td class="align-middle text-center fw-bold">${sugerido}</td>
            <td class="align-middle">${origenHtml || '<span class="text-muted x-small">—</span>'}</td>
            <td class="align-middle">${alertasHtml || '<span class="text-muted x-small">—</span>'}</td>
            <td class="align-middle">${estatusBadge}</td>
        </tr>
    `;
};

function renderDashboard(d) {
    const num = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    num('d-total', d.total);
    num('d-completados', d.completados);
    num('d-pendientes', d.pendientes);
    num('d-revision', d.requieren_revision);
    num('d-validacion', d.validacion_pendiente);
    num('d-validado', d.con_precio_validado);
    const sinP = document.getElementById('d-sin-propuesta');
    if (sinP) {
        sinP.textContent = d.propuesta_invalida > 0
            ? `Sin propuesta válida: ${d.total - d.propuesta_valida} ítems (${d.propuesta_invalida} con JSON inválido).`
            : (d.total - d.propuesta_valida > 0 ? `Sin propuesta todavía: ${d.total - d.propuesta_valida} ítems pendientes de procesar.` : '');
    }
    const etiquetasOrigen = {ia: 'IA', promedio: 'Promedio (respaldo)', validado: 'Precio validado', humano: 'Humano', mixto: 'Mixto', sin_origen: 'Sin origen', sin_propuesta: 'Sin propuesta válida'};
    const claseOrigen = {ia: 'bg-primary', promedio: 'bg-warning', validado: 'bg-info', humano: 'bg-secondary', mixto: 'bg-secondary', sin_origen: 'bg-secondary', sin_propuesta: 'bg-light text-dark border'};
    const origenEl = document.getElementById('d-origen');
    if (origenEl) {
        origenEl.innerHTML = (d.por_origen || []).map(o => `
            <div class="mb-2">
                <div class="d-flex justify-content-between x-small"><span>${esc(etiquetasOrigen[o.origen] || o.origen)}</span><span class="fw-bold">${o.n} (${o.pct}%)</span></div>
                <div class="progress" style="height: 6px;"><div class="progress-bar ${claseOrigen[o.origen] || 'bg-secondary'}" style="width: ${o.pct}%"></div></div>
            </div>`).join('') || '<div class="x-small text-muted">Sin datos.</div>';
    }
    // Nota bajo el desglose: explica las dos etiquetas que suelen
    // preguntar. Solo aparece cuando al menos una de las dos tiene
    // conteo > 0; si no hay ninguna, queda vacía (sin texto).
    const notaOrigen = document.getElementById('d-origen-nota');
    if (notaOrigen) {
        const por = {};
        (d.por_origen || []).forEach(o => { por[o.origen] = o.n; });
        notaOrigen.textContent = ((por['sin_origen'] || 0) > 0 || (por['sin_propuesta'] || 0) > 0)
            ? 'Sin origen: ítems con propuesta válida pero sin origen registrado (procesados antes de que se guardara ese dato). Sin propuesta válida: aún no procesados o con propuesta inválida.'
            : '';
    }
    const topEl = document.getElementById('d-top');
    if (topEl) {
        topEl.innerHTML = (d.top_codigos || []).map(t => `
            <tr><td class="fw-bold text-primary">${esc(t.codigo)}</td><td class="text-end">${t.n}</td><td class="text-end">${t.precio_promedio_sugerido != null ? '$' + fmt(t.precio_promedio_sugerido) : '—'}</td></tr>`).join('')
            || '<tr><td colspan="3" class="text-muted x-small">Sin datos.</td></tr>';
    }
    const ultEl = document.getElementById('d-ultimos');
    if (ultEl) {
        ultEl.innerHTML = (d.ultimos || []).map(u => `
            <tr><td class="x-small">${esc(String(u.fecha_registro || '').substring(0, 10))}</td><td>${esc(u.id_proyecto)}</td><td class="fw-bold text-primary">${esc(u.codigo || 'S/C')}</td><td class="text-end">${u.precio != null ? '$' + fmt(u.precio) : '—'}</td></tr>`).join('')
            || '<tr><td colspan="4" class="text-muted x-small">Sin datos.</td></tr>';
    }
}

async function cargarDatos() {
    const f = leerFiltros();
    const params = new URLSearchParams();
    Object.keys(f).forEach(k => { if (f[k] !== '' && f[k] != null) params.append(k, f[k]); });
    params.append('pagina', paginaActual);
    params.append('por_pagina', porPagina);
    try {
        const response = await fetch('get_historial_data.php?' + params.toString());
        const data = await response.json();
        datosHistorial = data.items || [];
        paginaActual = data.paginacion ? data.paginacion.pagina : 1;
        totalPaginas = data.paginacion ? data.paginacion.total_paginas : 1;
        // El servidor es la fuente de verdad del tamaño de página
        // (acota lo inválido al valor por defecto); el selector refleja
        // lo que realmente se está usando.
        if (data.paginacion && data.paginacion.por_pagina) porPagina = data.paginacion.por_pagina;
        const selPP = document.getElementById('sel-por-pagina');
        if (selPP) selPP.value = String(porPagina);

        document.getElementById('last-update').innerHTML = `<i class="bi bi-clock-history me-1"></i> Sync: ${new Date().toLocaleTimeString()}`;

        // Lista de proyectos del filtro (se rellena del endpoint; se
        // conserva la selección actual y el valor inicial de la URL).
        // La opción muestra "PROY-... — Nombre del solicitante" (o
        // "— Sin registro" cuando el proyecto no tiene usuario).
        const selProy = document.getElementById('f-proyecto');
        if (selProy) {
            const actual = selProy.value || proyectoInicial || '';
            selProy.innerHTML = '<option value="">Todos</option>' + (data.proyectos || []).map(p => {
                const sol = (p.solicitante !== null && p.solicitante !== undefined && String(p.solicitante) !== '') ? p.solicitante : 'Sin registro';
                return `<option value="${escAttr(p.id_proyecto)}">${esc(p.id_proyecto)} — ${esc(sol)}</option>`;
            }).join('');
            if (actual && (data.proyectos || []).some(p => p.id_proyecto === actual)) selProy.value = actual;
            if (proyectoInicial) { selProy.value = proyectoInicial; proyectoInicial = null; }
        }

        if (data.dashboard) renderDashboard(data.dashboard);

        const contenedor = document.getElementById('contenedor-historial');
        contenedor.innerHTML = datosHistorial.length
            ? renderTablaHistorial(datosHistorial, data.proyectos)
            : '<tr><td colspan="9" class="text-center py-4 text-muted">Sin ítems para estos filtros.</td></tr>';

        const pag = data.paginacion || {};
        document.getElementById('paginacion-info').textContent =
            `Pág ${pag.pagina || 1} de ${pag.total_paginas || 1} (${pag.total || 0} ítems)`;
        document.getElementById('btn-ant').disabled = (paginaActual <= 1);
        document.getElementById('btn-sig').disabled = (paginaActual >= totalPaginas);
        const fResumen = document.getElementById('resumen-filtros');
        if (fResumen) {
            const activos = Object.keys(f).filter(k => f[k] !== '' && f[k] != null).length;
            fResumen.textContent = activos > 0 ? `${activos} filtro(s) activo(s)` : 'Sin filtros (todo el historial)';
        }
        const resumenTabla = document.getElementById('resumen-tabla');
        if (resumenTabla) {
            resumenTabla.textContent = f.proyecto ? `Proyecto ${f.proyecto}` : 'Todos los proyectos';
        }
    } catch (e) {
        console.error('Error en la carga del historial:', e);
    }
}

/**
 * Modal de veredicto (solo lectura): mismo patrón que mostrarVeredicto
 * del monitor — veredicto, rango, trazabilidad corta, sección de
 * validación de experto técnico y línea de precio validado. Sin el
 * formulario de validación humana del monitor.
 */
function mostrarVeredictoHistorial(id) {
    const item = datosHistorial.find(i => String(i.id) === String(id));
    if (!item) return;

    const ia = item.propuesta_ia || {};
    const veredicto = ia.veredicto_ia || 'Sin veredicto disponible para este ítem.';
    const fmtPrecioAtlas = v => (v === null || v === undefined || v === '') ? 'sin historial' : '$' + parseFloat(v).toFixed(2);

    const coincidenciasHtml = ia.coincidencias
        ? `<div class="text-start mt-3">
             <div class="x-small text-uppercase text-muted fw-bold mb-1">Otras coincidencias históricas consideradas</div>
             <div class="x-small text-secondary">${esc(ia.coincidencias)}</div>
           </div>`
        : '';

    const rangoHtml = (ia.precio_ia_min !== undefined && ia.precio_ia_max !== undefined && parseFloat(ia.precio_ia_max) > parseFloat(ia.precio_ia_min))
        ? `<div class="text-center x-small text-muted mt-1">Rango sugerido: $${parseFloat(ia.precio_ia_min).toFixed(2)} &ndash; $${parseFloat(ia.precio_ia_max).toFixed(2)}</div>`
        : '';
    const traza = ia.trazabilidad || {};
    const trazaHtml = (traza.modelo || traza.fecha_hora_procesamiento)
        ? `<div class="x-small text-muted mt-2">Modelo: ${esc(traza.modelo || 's/d')} &middot; Intentos IA: ${ia.ia_intentos ?? 's/d'} &middot; Latencia IA: ${traza.latencia_ia_ms != null ? traza.latencia_ia_ms + ' ms' : 's/d'} &middot; Procesado: ${esc(traza.fecha_hora_procesamiento || '')}</div>`
        : '';

    // Precio validado (tarifario): línea en el modal cuando el ítem tiene
    // validado, igual que en el monitor.
    const validadoHtml = (ia.tiene_precio_validado && ia.precio_validado_usd != null)
        ? `<div class="x-small mt-1">Precio validado (tarifario): <b>$${parseFloat(ia.precio_validado_usd).toFixed(2)} USD</b>${ia.precio_validado_mxn != null ? ` / MXN $${parseFloat(ia.precio_validado_mxn).toFixed(2)}` : ''}</div>`
        : '';

    // Validación de experto técnico: mismo patrón que el monitor (ambas
    // claves cuando la encontrada ya no está activa; alternativas con su
    // precio histórico; alerta ámbar cuando requiere validación).
    const atlas = ia.atlas || null;
    let atlasHtml = '';
    if (atlas) {
        const alertaHtml = atlas.requiere_validacion_experto
            ? `<div class="alert alert-warning x-small mt-2 mb-0">Requiere validación de experto, acércate al área correspondiente${atlas.motivo_validacion && atlas.motivo_validacion.length ? ` (${atlas.motivo_validacion.map(m => esc(m)).join(', ')})` : ''}</div>`
            : '';
        if (atlas.origen === 'similar') {
            const sugs = Array.isArray(atlas.sugerencias) ? atlas.sugerencias : [];
            const codigosVivos = sugs.map(s => esc(s.codigo_scot));
            const encabezado = sugs.length === 0
                ? `La clave ${esc(atlas.codigo_buscado)} ya no está activa en la validación de experto técnico.`
                : (sugs.length === 1
                    ? `La clave ${esc(atlas.codigo_buscado)} ya no está activa en la validación de experto técnico; se sugiere el código vivo ${codigosVivos[0]} por similitud.`
                    : `La clave ${esc(atlas.codigo_buscado)} ya no está activa en la validación de experto técnico; se sugieren los códigos vivos ${codigosVivos.slice(0, -1).join(', ')} y ${codigosVivos[codigosVivos.length - 1]} por similitud.`);
            const tarjetasSug = sugs.map(s => `
                <div class="card mt-2">
                    <div class="card-body p-2 text-start">
                        <div class="fw-bold">${esc(s.codigo_scot)} <span class="badge bg-success x-small">Clave viva (validación de experto)</span> <span class="badge bg-warning text-dark x-small">Sugerencia por similitud</span></div>
                        ${s.servicio ? `<div class="x-small mt-1">${esc(s.servicio)}</div>` : ''}
                        ${s.tipo_coincidencia ? `<div class="x-small text-muted">Coincidencia: ${esc(s.tipo_coincidencia)}</div>` : ''}
                        <div class="x-small mt-1">Precio histórico (referencial, para comparar): <b>${fmtPrecioAtlas(s.precio_promedio)}</b>${s.precio_promedio_mxn != null ? ` / MXN ${fmtPrecioAtlas(s.precio_promedio_mxn)}` : ''}${s.n_muestras != null ? ` (${s.n_muestras} muestras)` : ''}</div>
                        ${s.tokens_comunes && s.tokens_comunes.length ? `<div class="x-small text-muted">Coincide en: ${s.tokens_comunes.map(t => esc(t)).join(', ')} (puntaje ${s.puntaje_similitud})</div>` : ''}
                        ${s.ejemplos ? `<div class="x-small text-muted">Ejemplos: ${esc(s.ejemplos)}</div>` : ''}
                        <div class="alert alert-warning x-small mt-2 mb-0">Requiere validación de experto, acércate al área correspondiente</div>
                    </div>
                </div>`).join('');
            atlasHtml = `
                <div class="mt-3 text-start">
                    <div class="x-small text-uppercase text-muted fw-bold mb-1">Validación de experto técnico — clave no activa</div>
                    <div class="x-small">${encabezado}</div>
                    <div class="x-small text-muted">Código no cubierto por la validación de experto técnico. Sugerencia por similitud, no validada por experto.</div>
                    <div class="card mt-2">
                        <div class="card-body p-2 text-start">
                            <div class="fw-bold">${esc(atlas.codigo_buscado)} <span class="badge bg-secondary x-small">Ya no activa — validación de experto</span></div>
                            ${ia.desc ? `<div class="x-small mt-1">${esc(ia.desc)}</div>` : ''}
                            <div class="x-small mt-1">Precio sugerido (cotizado): <b>$${parseFloat(ia.precio_ia || 0).toFixed(2)}</b> &middot; Promedio histórico: $${parseFloat(ia.precio_promedio || 0).toFixed(2)}</div>
                        </div>
                    </div>
                    ${tarjetasSug}
                </div>`;
        } else {
            const modelos = Array.isArray(atlas.modelos) ? atlas.modelos : [];
            const modelosHtml = modelos.length
                ? `<div class="x-small mt-1"><b>Modelos (${modelos.length}):</b> ${modelos.slice(0, 10).map(m => `${esc(m.fabricante || '')} ${esc(m.modelo || '')}${m.confianza ? ` (${esc(m.confianza)})` : ''}`).join(' &middot; ')}${modelos.length > 10 ? ` &middot; y ${modelos.length - 10} más` : ''}</div>`
                : '';
            const alts = Array.isArray(atlas.alternativas) ? atlas.alternativas : [];
            const principalCotizado = (atlas.codigo_principal && String(atlas.codigo_principal) === String(ia.cdmess)) ? String(atlas.codigo_principal) : null;
            const altsHtml = alts.length
                ? `<div class="x-small mt-1"><b>Otras claves que pueden aplicar:</b><ul class="mb-0 ps-3">${alts.map(a => {
                    const esCotizado = principalCotizado !== null && String(a.codigo_scot) === principalCotizado;
                    const precio = esCotizado
                        ? `Precio IA: <b>$${parseFloat(ia.precio_ia || 0).toFixed(2)}</b> (prom. hist. ${fmtPrecioAtlas(a.precio_promedio)})`
                        : fmtPrecioAtlas(a.precio_promedio);
                    return `<li><b>${esc(a.codigo_scot)}</b>${a.es_principal ? ' <span class="badge bg-primary x-small">Principal</span>' : ''}${esCotizado ? ' <span class="badge bg-success x-small">Cotizado</span>' : ''}${a.servicio ? ` — ${esc(a.servicio)}` : ''} — ${precio}${a.n_muestras != null ? ` (${a.n_muestras} muestras)` : ''}</li>`;
                }).join('')}</ul></div>`
                : '';
            const rebaseHtml = (principalCotizado !== null && atlas.codigo_encontrado && String(atlas.codigo_encontrado) !== principalCotizado)
                ? `<div class="x-small mt-1">Código cotizado: el principal <b>${esc(principalCotizado)}</b> (la clave encontrada fue ${esc(atlas.codigo_encontrado)}).</div>`
                : '';
            atlasHtml = `
                <div class="mt-3 text-start">
                    <div class="x-small text-uppercase text-muted fw-bold mb-1">Validación de experto técnico — ${esc(atlas.codigo_scot)} ${atlas.codigo_principal ? `<span class="badge bg-primary x-small">Principal: ${esc(atlas.codigo_principal)}</span>` : ''}</div>
                    ${atlas.es_sugerencia ? `<div class="x-small mt-1">Sugerencia para ${esc(atlas.sugerencia_de || atlas.codigo_buscado || '')}, ya no activa.</div><div class="mt-1"><span class="badge bg-warning text-dark x-small">Sugerencia por similitud (sin validar)</span></div>${atlas.puntaje_similitud != null ? `<div class="x-small text-muted mt-1">Similitud: puntaje ${atlas.puntaje_similitud}${atlas.tokens_comunes && atlas.tokens_comunes.length ? ` (coincide en: ${atlas.tokens_comunes.map(t => esc(t)).join(', ')})` : ''}</div>` : ''}` : ''}
                    <div><span class="badge ${atlas.tipo_coincidencia === 'Directa' ? 'bg-success' : (atlas.tipo_coincidencia === 'Parcial' ? 'bg-warning text-dark' : 'bg-secondary')} x-small">Validación de experto técnico: ${esc(atlas.tipo_coincidencia || 's/d')}</span></div>
                    ${atlas.servicio ? `<div class="x-small mt-1"><b>Servicio:</b> ${esc(atlas.servicio)}</div>` : ''}
                    ${(atlas.usar_cuando || atlas.precaucion || atlas.preguntas) ? `<div class="alert alert-info mt-2 mb-1 text-start">
                        <div class="x-small fw-bold text-uppercase mb-1"><i class="bi bi-info-circle me-1"></i>Para cotizar este código</div>
                        ${atlas.usar_cuando ? `<div class="x-small mt-1"><b>Cuándo usar:</b> ${esc(atlas.usar_cuando)}</div>` : ''}
                        ${atlas.precaucion ? `<div class="x-small mt-1"><b>Precaución:</b> ${esc(atlas.precaucion)}</div>` : ''}
                        ${atlas.preguntas ? `<div class="x-small mt-1"><b>Preguntas:</b> ${esc(atlas.preguntas)}</div>` : ''}
                    </div>` : ''}
                    ${modelosHtml}
                    ${altsHtml}
                    ${rebaseHtml}
                    ${alertaHtml}
                </div>`;
        }
    }

    const esHermana = !!(atlas && (atlas.es_alternativa || atlas.es_sugerencia));
    const precioLinea = !item.propuesta_ia
        ? `<span class="text-muted">Este ítem aún no tiene propuesta de precio.</span>`
        : (esHermana
            ? `<span>Precio histórico: <b class="text-dark">${ia.precio_promedio != null ? '$' + parseFloat(ia.precio_promedio).toFixed(2) : 'sin historial'}</b></span><span>Sin precio de IA (se cotiza por histórico)</span>`
            : `<span>Precio sugerido: <b class="text-dark">$${parseFloat(ia.precio_ia || 0).toFixed(2)}</b></span><span>Promedio histórico: $${parseFloat(ia.precio_promedio || 0).toFixed(2)}</span>`);

    Swal.fire({
        title: 'Veredicto de MessIAs',
        customClass: { popup: 'swal-veredicto' },
        confirmButtonColor: '#002d5a',
        confirmButtonText: 'Entendido',
        html: `
            <div class="text-start">
                <div class="fw-bold text-primary mb-1">${esc(ia.cdmess || item.cdmess_historico || 'S/C')} &mdash; ${esc(ia.desc || item.descripcion_historica || item.entrada_usuario || '')}</div>
                <div class="x-small text-muted mb-1">Proyecto ${esc(item.id_proyecto)} &middot; ${esc(String(item.fecha_registro || '').substring(0, 10))} &middot; Estatus: ${esc(item.estatus)}</div>
                <div class="alert alert-light border mt-2 mb-2 text-start" style="white-space: pre-line;">${esc(veredicto)}</div>
                <div class="d-flex justify-content-between x-small text-muted">
                    ${precioLinea}
                </div>
                ${rangoHtml}
                ${validadoHtml}
                ${trazaHtml}
                ${atlasHtml}
            </div>
            ${coincidenciasHtml}
        `
    });
}

// Inicialización: el proyecto inicial viene del input oculto (URL).
(function () {
    const ini = document.getElementById('f-proyecto-inicial');
    if (ini && ini.value) proyectoInicial = ini.value;
    cargarDatos();
})();
</script>

</body>
</html>
