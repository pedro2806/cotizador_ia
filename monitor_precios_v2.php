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
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/marco_messias.css">
    <style>
        /* Diseño marco messIAs real (css/estilos.css + css/marco_messias.css,
           de agentesIA). Solo presentación: no se toca lógica PHP ni JavaScript.
           Aquí solo van reglas propias de esta página que los CSS del marco
           no cubren; los colores usan sus tokens (--mx-*, --accent). */

        /* Sidebar: altura ajustada al encabezado alto del marco (88-156 px)
           más el pie, para no desbordar la hoja blanca. */
        .sidebar-card { height: calc(100vh - 280px); display: flex; flex-direction: column; }
        .sidebar-scroll { flex-grow: 1; overflow-y: auto; }
        .list-group-item { border-left: none; border-right: none; padding: 1rem; border-bottom: 1px solid #f0f0f0; transition: 0.2s; }
        .list-group-item.active { background-color: var(--accent-soft); border-color: transparent; color: var(--accent); border-right: 4px solid var(--accent); }
        .sidebar-card .progress-bar { background-color: var(--mx-azul); }
        .sidebar-card .btn-outline-secondary { color: var(--accent); border-color: var(--accent); }
        .sidebar-card .btn-outline-secondary:hover { background-color: var(--accent); color: #fff; }

        /* Contenedor de tabla: la hoja blanca del marco ya es la tarjeta;
           este div queda como envoltorio sin caja propia. */
        .table-container { background: transparent; border: 0; box-shadow: none; min-height: 0; }

        .font-small { font-size: 0.85rem; }
        .x-small { font-size: 0.75rem; }
        .bg-sugerido { background-color: #f8f9fa; }
        .badge-range {
            background: #f1f3f5;
            color: #495057;
            font-weight: 500;
            font-size: 0.70rem;
            display: block;
            margin-bottom: 2px;
            border-radius: 4px;
        }

        .bg-sugerido input {
            border: 1px solid var(--mx-navy);
            background-color: #fffceb;
        }
        textarea.x-small {
            font-size: 0.75rem !important;
            resize: none;
        }
        /* Acentos de texto en el color primario del sistema (código CDMESS, "Sugerido", folio). */
        .text-primary { color: var(--accent) !important; }

        /* Mejora general de la tabla */
        #tabla-precios {
            border-collapse: separate;
            border-spacing: 0;
        }

        /* Encabezados navy del marco, con mejor contraste y espaciado */
        #tabla-precios thead th {
            background-color: var(--mx-navy) !important;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 12px 10px;
            border-top: none;
        }

        /* Inputs y Selects integrados (sin bordes pesados) */
        .form-control-sm, .form-select-sm {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .form-control-sm:focus { border-color: var(--accent); box-shadow: none; }

        /* Botones en el color primario del sistema (Exportar, Validar):
           se reestilan por CSS para no tocar el JavaScript que genera
           las filas. El outline-primary ya lo cubre estilos.css. */
        .table-container .btn-success,
        #tabla-precios .btn-success {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        .table-container .btn-success:hover,
        #tabla-precios .btn-success:hover {
            background-color: var(--accent-dark);
            border-color: var(--accent-dark);
        }

        /* Estado vacío: el icono claro no se veía sobre la hoja blanca. */
        .table-container .bi-clipboard2-check { color: var(--mx-azul) !important; }

        /* Modal de veredicto (SweetAlert2): se ajusta por CSS, sin tocar el
           JavaScript que lo dispara ni su contenido. Los colores de los
           badges (Directa/Parcial/Sin ejemplo, alerta, principal) se
           conservan tal cual para seguir distinguibles. */
        .swal2-popup {
            font-family: var(--mx-font);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .swal2-title { color: var(--text); }
        .swal2-confirm { background-color: var(--accent) !important; }
        .swal2-cancel { background-color: #fff !important; color: #212529 !important; border: 1px solid #6c757d !important; }
        .swal2-confirm:focus, .swal2-cancel:focus {
            box-shadow: 0 0 0 3px rgba(255, 201, 17, .55) !important;
        }
    </style>
</head>
<body class="mx-marco">

<header class="mx-header">
    <a class="mx-volver" href="index.php" title="Volver a Inicio" aria-label="Volver a Inicio"><img src="img/messias/volver.png" alt="Volver"></a>
    <a class="mx-logo" href="index.php"><img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI"></a>
</header>

<main class="mx-main">
<div class="mx-hoja">
    <h1 class="mx-hoja-titulo">Monitor de cotizaciones realizadas por el modelo MessIAs</h1>
    <div class="d-flex justify-content-end align-items-center gap-3 mb-3">
        <span class="text-muted x-small" id="last-update">Sincronizando...</span>
        <a href="cargador_masivo.php" class="btn btn-warning btn-sm fw-bold px-3">Nueva Carga</a>
    </div>
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
</main>
<footer class="mx-footer">
    <img src="img/messias/logo-mess-b1.png" alt="Grupo mess, all about metrology · B1 Business Intelligence">
</footer>
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

const renderFila = (item, modo = false) => {
    if (item.estatus !== 'completado') return `<tr><td colspan="8" class="text-center py-3 text-muted x-small italic">Analizando...</td></tr>`;

    const ia = item.propuesta_ia || {};
    // Fase 4: modo false = original; true = sugerencia de la carga masiva;
    // 'alternativa' = clave alternativa validada (hija); 'sugerencia' = clave
    // sugerida por similitud con clave muerta (hija). Las hijas van en
    // segundo plano bajo su padre (ver cargarDatos).
    const esAlt = modo === 'alternativa';
    const esSugHija = modo === 'sugerencia';
    const esHermana = esAlt || esSugHija;
    const rowClass = modo === true ? 'table-warning font-small' : (esHermana ? 'table-light font-small' : 'font-small');
    const fmt = v => parseFloat(v || 0).toFixed(2);
    // A7: escapa la nota guardada para poder ponerla en el value del input
    // resp_u_ sin romper el atributo si trae comillas o < >.
    const escAttr = v => String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    return `
        <tr class="${rowClass}">
            <td class="text-center align-middle"><input type="checkbox" class="form-check-input item-check" value="${item.id}"></td>
            <td class="fw-bold text-primary align-middle">
                ${esHermana ? '↳ ' : ''}${ia.cdmess || 'S/C'}                
                ${ia.atlas && ia.atlas.origen === 'similar' ? `<br><span class="badge bg-secondary x-small">Ya no activa — validación de experto</span>` : ''}
                ${ia.atlas && ia.atlas.codigo_principal && String(ia.atlas.codigo_principal) === String(ia.cdmess) && ia.atlas.codigo_encontrado && String(ia.atlas.codigo_encontrado) !== String(ia.cdmess) ? `<br><span class="x-small text-muted">Principal de ${escAttr(ia.atlas.codigo_encontrado)}</span>` : ''}
                ${!esHermana && item.entrada_usuario ? `<br><span class="x-small text-muted">Sugerencia para: ${item.entrada_usuario}</span>` : ''}
            </td>
            <td class="align-middle">
                <div class="fw-bold">${ia.desc || ''}</div>
                ${ia.detalle_calculo ? `<div class="x-small text-muted">${ia.detalle_calculo}</div>` : ''}
            </td>
            <td class="align-middle x-small">
                ${ia.folio ? `<b>${ia.folio}</b><br>${ia.fecha?.substring(0,10) || ''}` : '-'}
            </td>
            <td class="align-middle text-center x-small">
                ${esHermana
                    ? `<div class="text-muted">Histórico: ${ia.precio_promedio != null ? '$' + fmt(ia.precio_promedio) : 'sin historial'}</div>${ia.precio_promedio_mxn != null ? `<div class="text-muted">MXN: $${fmt(ia.precio_promedio_mxn)}</div>` : ''}`
                    : `<div class="text-muted">USD: $${fmt(ia.precio_min)} - $${fmt(ia.precio_max)}</div>
                <div class="text-muted">MXN: $${fmt(ia.precio_min_mxn)} - $${fmt(ia.precio_max_mxn)}</div>`}
            </td>
            <td class="align-middle text-center x-small">
                <div><b>USD:</b> $${fmt(ia.precio_promedio)}</div>
                <div><b>MXN:</b> $${fmt(ia.precio_promedio_mxn)}</div>
            </td>
            <td class="align-middle bg-light">
                <div class="x-small text-primary mb-1 d-flex align-items-center justify-content-between">
                    <span>${esHermana ? 'Histórico' : 'Sugerido'}: $${esHermana ? (ia.precio_promedio != null ? fmt(ia.precio_promedio) : '—') : fmt(ia.precio_ia)}</span>
                    ${ia.veredicto_ia ? `<button type="button" class="btn btn-link btn-sm p-0 ms-1" title="Ver veredicto de MessIAs" onclick='mostrarVeredicto(${JSON.stringify(item.id)})'><i class="bi bi-chat-square-quote-fill"></i></button>` : ''}
                </div>
                ${(() => {
                    // A5: distintivo de origen del precio y de revisión. El
                    // origen viene en propuesta_ia (get_proyecto_data.php ya
                    // pasa el JSON completo): 'ia' = propuesto por Ollama,
                    // 'promedio' = respaldo del promedio porque Ollama no
                    // devolvió propuesta válida (se marca en ámbar para que
                    // no se vea como sugerencia de la IA), 'humano'/'mixto'
                    // = con aprendizaje humano (A4). El veredicto va como
                    // tooltip (escapado con escAttr). En ítems viejos sin
                    // origen_precio no se muestra nada. 'Revisar' sale solo
                    // si requiere_revision es true.
                    const etiquetas = {ia: 'IA', promedio: 'Promedio (respaldo)', humano: 'Humano', mixto: 'Mixto'};
                    const etiqueta = ia.origen_precio ? (etiquetas[ia.origen_precio] || ia.origen_precio) : '';
                    let badges = '';
                    // Fase 4: en las hijas no se pinta el badge de origen de
                    // precio (siempre es histórico); el badge de clase ya
                    // dice si es alternativa o sugerencia.
                    if (etiqueta && !esHermana) {
                        const clase = ia.origen_precio === 'promedio' ? 'bg-warning text-dark' : 'bg-secondary';
                        badges += `<span class="badge ${clase} x-small me-1" title="${escAttr(ia.veredicto_ia || '')}">${etiqueta}</span>`;
                    }
                    if (ia.requiere_revision) {
                        badges += `<span class="badge bg-danger x-small">Revisar</span>`;
                    }
                    // Fase 1 (Atlas): badge de coincidencia y alerta de
                    // validación de experto. En el caso similar nunca se
                    // pinta como validado: "Sugerencia por similitud (sin validar)".
                    // Fase 4: las hijas alternativa llevan su propio badge;
                    // las hijas sugerencia usan el de similitud.
                    if (ia.atlas) {
                        if (esAlt) {
                            badges += `<span class="badge bg-info text-dark x-small me-1">Alternativa — validación de experto</span>`;
                        }
                        if (ia.atlas.es_sugerencia || ia.atlas.origen === 'similar') {
                            badges += `<span class="badge bg-warning text-dark x-small me-1">Sugerencia por similitud (sin validar)</span>`;
                        } else {
                            const claseAtlas = ia.atlas.tipo_coincidencia === 'Directa' ? 'bg-success' : (ia.atlas.tipo_coincidencia === 'Parcial' ? 'bg-warning text-dark' : 'bg-secondary');
                            badges += `<span class="badge ${claseAtlas} x-small me-1">Validación de experto técnico: ${escAttr(ia.atlas.tipo_coincidencia || 's/d')}</span>`;
                        }
                        if (ia.atlas.requiere_validacion_experto) {
                            badges += `<span class="badge bg-danger x-small me-1" title="${escAttr('Requiere validación de experto, acércate al área correspondiente')}">Requiere validación de experto</span>`;
                        }
                    }
                    return badges ? `<div class="mb-1">${badges}</div>` : '';
                })()}
                <input type="number" id="precio_u_${item.id}" class="form-control form-control-sm fw-bold" value="${parseFloat(item.precio_usuario) > 0 ? fmt(item.precio_usuario) : (esHermana ? (ia.precio_promedio != null ? fmt(ia.precio_promedio) : '') : fmt(ia.precio_ia))}">
            </td>
            <td class="align-middle">
                <select id="cat_u_${item.id}" class="form-select form-select-sm mb-1 x-small">
                    ${['Acepta precio IA','Precio muy bajo','Precio muy alto','Descripción incorrecta'].map(op =>
                        `<option value="${op}" ${item.categoria_rechazo === op ? 'selected' : ''}>${op}</option>`
                    ).join('')}
                </select>
                <!-- A7: campo de nota/respuesta que validarRegistro() ya leía
                     (resp_u_<id>) pero que renderFila nunca generaba: sin él,
                     validar rompía con TypeError y la validación humana no se
                     guardaba. Se prellena con item.respuesta si ya existe. -->
                <input type="text" id="resp_u_${item.id}" class="form-control form-control-sm x-small mb-1" placeholder="Nota para la IA (opcional)" value="${escAttr(item.respuesta)}">
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
        // Fase 4: los renglones hermanos (es_sugerencia=1 con atlas.alternativa_de)
        // se agrupan inmediatamente debajo de su padre; las sugerencias de la
        // carga masiva (es_sugerencia=1 sin alternativa_de) siguen al final
        // bajo el divisor "Sugerencias de MessIAs". Los hermanos huérfanos
        // (padre no en este proyecto) también van al final, con su badge.
        const hermanas = data.filter(i => i.es_sugerencia && i.propuesta_ia && i.propuesta_ia.atlas && i.propuesta_ia.atlas.alternativa_de != null);
        const sugerencias = data.filter(i => i.es_sugerencia && !(i.propuesta_ia && i.propuesta_ia.atlas && i.propuesta_ia.atlas.alternativa_de != null));
        const porPadre = {};
        hermanas.forEach(h => {
            const p = h.propuesta_ia.atlas.alternativa_de;
            (porPadre[p] = porPadre[p] || []).push(h);
        });
        const esHuerfana = h => !originales.some(o => String(o.id) === String(h.propuesta_ia.atlas.alternativa_de));
        const huerfanas = hermanas.filter(esHuerfana);
        const modoHermana = h => (h.propuesta_ia.atlas.es_sugerencia ? 'sugerencia' : 'alternativa');
        
        const total = originales.length;
        const listos = originales.filter(i => i.estatus === 'completado').length;
        const pct = total > 0 ? Math.round((listos/total)*100) : 0;
        
        document.getElementById('progreso-header').innerHTML = `
            <div class="fw-bold small mb-1">Avance del Análisis: ${listos} de ${total}</div>
            <div class="progress" style="height: 6px; width: 200px; float: right;">
                <div class="progress-bar bg-success" style="width: ${pct}%"></div>
            </div>
        `;

        const filaDivisora = (sugerencias.length + huerfanas.length > 0) ? `
            <tr class="table-secondary">
                <td colspan="9" class="text-center py-2 x-small fw-bold text-uppercase text-muted">
                    <i class="bi bi-stars me-1"></i> Sugerencias de MessIAs
                </td>
            </tr>
        ` : '';

        contenedor.innerHTML =
            originales.map(i => renderFila(i, false) + (porPadre[i.id] || []).filter(h => !esHuerfana(h)).map(h => renderFila(h, modoHermana(h))).join('')).join('') +
            filaDivisora +
            sugerencias.map(i => renderFila(i, true)).join('') +
            huerfanas.map(h => renderFila(h, modoHermana(h))).join('');

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

/**
 * Muestra en un modal el "veredicto" que la IA justificó para un ítem:
 * su recomendación de precio/descripción explicada en palabras, comparando
 * contra las coincidencias históricas que encontró (si las hubo). El texto
 * viene de propuesta_ia.veredicto_ia, generado por preguntarOllamaConPrecios()
 * en el worker.
 */
function mostrarVeredicto(id) {
    const item = datosProyecto.find(i => String(i.id) === String(id));
    if (!item) return;

    const ia = item.propuesta_ia || {};
    const veredicto = ia.veredicto_ia || 'Sin veredicto disponible para este ítem.';

    const coincidenciasHtml = ia.coincidencias
        ? `<div class="text-start mt-3">
             <div class="x-small text-uppercase text-muted fw-bold mb-1">Otras coincidencias históricas consideradas</div>
             <div class="x-small text-secondary">${ia.coincidencias.replace(/</g, '&lt;')}</div>
           </div>`
        : '';

    const rangoHtml = (ia.precio_ia_min !== undefined && ia.precio_ia_max !== undefined && parseFloat(ia.precio_ia_max) > parseFloat(ia.precio_ia_min))
        ? `<div class="text-center x-small text-muted mt-1">Rango sugerido: $${parseFloat(ia.precio_ia_min).toFixed(2)} &ndash; $${parseFloat(ia.precio_ia_max).toFixed(2)}</div>`
        : '';
    // A6: línea corta de trazabilidad en el modal (modelo, intentos y
    // latencia de la IA, fecha de procesamiento). Solo si la propuesta
    // trae trazabilidad; ítems viejos sin ella no muestran nada.
    const traza = ia.trazabilidad || {};
    const trazaHtml = (traza.modelo || traza.fecha_hora_procesamiento)
        ? `<div class="x-small text-muted mt-2">Modelo: ${String(traza.modelo || 's/d').replace(/</g, '&lt;')} &middot; Intentos IA: ${ia.ia_intentos ?? 's/d'} &middot; Latencia IA: ${traza.latencia_ia_ms != null ? traza.latencia_ia_ms + ' ms' : 's/d'} &middot; Procesado: ${String(traza.fecha_hora_procesamiento || '').replace(/</g, '&lt;')}</div>`
        : '';

    // Fase 1 (Atlas): sección Atlas en el modal. Solo si la propuesta trae
    // atlas; ítems viejos sin él no muestran nada. Todo texto de Atlas se
    // escapa antes de interpolarlo en innerHTML (R5).
    const esc = v => String(v ?? '').replace(/</g, '&lt;');
    const fmtPrecioAtlas = v => (v === null || v === undefined || v === '') ? 'sin historial' : '$' + parseFloat(v).toFixed(2);
    const atlas = ia.atlas || null;
    let atlasHtml = '';
    if (atlas) {
        const alertaHtml = atlas.requiere_validacion_experto
            ? `<div class="alert alert-warning x-small mt-2 mb-0">Requiere validación de experto, acércate al área correspondiente${atlas.motivo_validacion && atlas.motivo_validacion.length ? ` (${atlas.motivo_validacion.map(m => esc(m)).join(', ')})` : ''}</div>`
            : '';
        if (atlas.origen === 'similar') {
            // §5.7: se muestran AMBAS claves, cada una con su propio bloque.
            // La cotizada es la original (su precio es precio_ia); la
            // sugerencia lleva solo su precio histórico como referencia.
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
            // Fase 2 (D1): si el principal es el código cotizado (re-base),
            // va primero con su precio de IA; las demás claves con su
            // promedio histórico. El servidor ya ordena principal primero.
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
                    ${atlas.es_sugerencia ? `<div class="x-small mt-1">Similitud de ${esc(atlas.sugerencia_de || atlas.codigo_buscado || '')}, ya no activa.</div><div class="mt-1"><span class="badge bg-warning text-dark x-small">Sugerencia por similitud (sin validar)</span></div>${atlas.puntaje_similitud != null ? `<div class="x-small text-muted mt-1">Similitud: puntaje ${atlas.puntaje_similitud}${atlas.tokens_comunes && atlas.tokens_comunes.length ? ` (coincide en: ${atlas.tokens_comunes.map(t => esc(t)).join(', ')})` : ''}</div>` : ''}` : ''}
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

    // Fase 4: las hijas (alternativas y sugerencias) no tienen precio de IA:
    // el precio que se muestra es el histórico.
    const esHermana = !!(atlas && (atlas.es_alternativa || atlas.es_sugerencia));
    const precioLinea = esHermana
        ? `<span>Precio histórico: <b class="text-dark">${ia.precio_promedio != null ? '$' + parseFloat(ia.precio_promedio).toFixed(2) : 'sin historial'}</b></span><span>Sin precio de IA (se cotiza por histórico)</span>`
        : `<span>Precio sugerido: <b class="text-dark">$${parseFloat(ia.precio_ia || 0).toFixed(2)}</b></span><span>Promedio histórico: $${parseFloat(ia.precio_promedio || 0).toFixed(2)}</span>`;

    Swal.fire({
        title: 'Veredicto de MessIAs',
        confirmButtonColor: '#002d5a',
        confirmButtonText: 'Entendido',
        html: `
            <div class="text-start">
                <div class="fw-bold text-primary mb-1">${String(ia.cdmess || 'S/C').replace(/</g, '&lt;')} &mdash; ${String(ia.desc || item.entrada_usuario || '').replace(/</g, '&lt;')}</div>
                <div class="alert alert-light border mt-2 mb-2 text-start" style="white-space: pre-line;">${veredicto.replace(/</g, '&lt;')}</div>
                <div class="d-flex justify-content-between x-small text-muted">
                    ${precioLinea}
                </div>
                ${rangoHtml}
                ${trazaHtml}
                ${atlasHtml}
            </div>
            ${coincidenciasHtml}
        `
    });
}

async function validarRegistro(id) {
    // A7: lecturas a prueba de nulos: si algún campo no existe, se usa ''
    // en vez de romper con TypeError (antes resp_u_ no existía y validar
    // nunca llegaba a enviar nada al servidor).
    const precioEl = document.getElementById(`precio_u_${id}`);
    const respEl = document.getElementById(`resp_u_${id}`);
    const catEl = document.getElementById(`cat_u_${id}`);
    const precio = precioEl ? precioEl.value : '';
    const respuesta = respEl ? respEl.value : '';
    const categoria = catEl ? catEl.value : '';
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
