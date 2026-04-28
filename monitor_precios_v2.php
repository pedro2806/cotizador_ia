<?php
include 'acciones_monitor_precios_v2.php';

// Parámetros para el Sidebar (Carga inicial)
$id_proyecto_activo = $_GET['proyecto'] ?? '';
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
    <title>Monitor MessIAs | Auditoría de Precios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <img src="logo.png" alt="MESS" height="35" class="me-3">
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
        <div class="col-md-3">
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
                        <a href="?proyecto=<?php echo urlencode($p['id_proyecto']); ?>&p_pag=<?php echo $p_pag; ?>" 
                           class="list-group-item list-group-item-action <?php echo $es_activo; ?>">
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

        <div class="col-md-9">
            <?php if ($id_proyecto_activo): ?>
            <div class="table-container p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h4 class="fw-bold mb-0 text-dark">Detalle del Proyecto</h4>
                        <span class="text-primary fw-bold small"><?php echo htmlspecialchars($id_proyecto_activo); ?></span>
                    </div>
                    <div id="progreso-header" class="text-end">
                        </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="tabla-precios">
                        <thead>
                            <tr class="x-small text-uppercase">
                                <th style="width: 12%;">CDMESS</th>
                                <th style="width: 30%;">Descripción Técnica (MessIAs))</th>
                                <th style="width: 15%;" class="text-center">Rango (Min - Max)</th>
                                <th style="width: 12%;" class="text-center">Hist. Promedio</th>
                                <th style="width: 12%;" class="text-center bg-primary text-white">Sugerido por MessIAs</th>
                                <th style="width: 19%;">Notas / Justificación</th>
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

<script>
const proyectoActual = "<?php echo $id_proyecto_activo; ?>";

async function cargarDatos() {
    if (!proyectoActual) return;
    try {
        const response = await fetch(`get_proyecto_data.php?proyecto=${encodeURIComponent(proyectoActual)}`);
        const data = await response.json();
        const contenedor = document.getElementById('contenedor-items');
        
        // Actualizar hora de sincronización
        document.getElementById('last-update').innerHTML = `<i class="bi bi-clock-history me-1"></i> Sync: ${new Date().toLocaleTimeString()}`;

        // Actualizar contadores de cabecera
        const total = data.length;
        const listos = data.filter(i => i.estatus === 'completado').length;
        const pct = total > 0 ? Math.round((listos/total)*100) : 0;
        
        document.getElementById('progreso-header').innerHTML = `
            <div class="fw-bold small mb-1">Avance del Análisis: ${listos} de ${total}</div>
            <div class="progress" style="height: 6px; width: 200px; float: right;">
                <div class="progress-bar bg-success" style="width: ${pct}%"></div>
            </div>
        `;

        contenedor.innerHTML = data.map(item => {
            if (item.estatus !== 'completado') {
                return `<tr><td colspan="6" class="text-center py-4 bg-light border-0">
                    <div class="spinner-grow spinner-grow-sm text-primary me-2"></div>
                    <span class="text-muted font-small fw-bold italic">Analizando historial para: "${item.entrada_usuario}"</span>
                </td></tr>`;
            }

            const ia = item.propuesta_ia || {};
            return `
                <tr class="font-small shadow-sm">
                    <td class="fw-bold text-primary"><i class="bi bi-hash"></i> ${ia.cdmess || 'S/C'}</td>
                    <td>
                        <div class="fw-bold text-dark">${ia.desc || ''}</div>
                        ${ia.coincidencias ? `<div class="mt-2 p-2 bg-light border-start border-warning border-3 x-small text-muted">${ia.coincidencias}</div>` : ''}
                    </td>
                    <td class="text-center">
                        <span class="badge badge-range">$${ia.precio_min || 0} - $${ia.precio_max || 0}</span>
                    </td>
                    <td class="text-center text-secondary">$${ia.precio_promedio || 0}</td>
                    <td class="text-center fw-bold bg-sugerido text-primary border-start border-end" style="font-size: 1rem;">
                        $${ia.precio_ia || 0}
                    </td>
                    <td class="x-small fst-italic text-secondary line-height-sm">${ia.notas || 'Sin observaciones.'}</td>
                </tr>
            `;
        }).join('');

    } catch (e) {
        console.error("Error en la carga AJAX:", e);
    }
}

if (proyectoActual) {
    cargarDatos();
    setInterval(cargarDatos, 10000); // Refresco cada 5 segundos
}
</script>

</body>
</html>