<?php
include 'conexion.php';
include 'funcionesWorker.php';


echo verificarWorker() ? '<span class="text-success">● Worker Activo</span>' : '<span class="text-danger">○ Worker Apagado (Ejecuta el .bat)</span>';

// 1. Obtener el resumen de proyectos activos para el selector
$proyectos_query = $conn->query("SELECT id_proyecto, COUNT(*) as total, 
                                SUM(CASE WHEN estatus = 'completado' THEN 1 ELSE 0 END) as listos 
                                FROM cola_procesamiento 
                                GROUP BY id_proyecto ORDER BY fecha_registro DESC");

$proyecto_seleccionado = isset($_GET['proyecto']) ? $_GET['proyecto'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Monitor de Progreso - MESS AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($proyecto_seleccionado): ?>
    <meta http-equiv="refresh" content="10">
    <?php endif; ?>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Monitor de Procesamiento Masivo</h2>
        <a href="cargador_masivo.php" class="btn btn-primary">+ Nueva Carga Masiva</a>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="list-group shadow-sm">
                <div class="list-group-item bg-dark text-white">Proyectos Recientes</div>
                <?php while($p = $proyectos_query->fetch_assoc()): 
                    $porcentaje = ($p['listos'] / $p['total']) * 100;
                    $active = ($proyecto_seleccionado == $p['id_proyecto']) ? 'active' : '';
                ?>
                <a href="?proyecto=<?php echo $p['id_proyecto']; ?>" class="list-group-item list-group-item-action <?php echo $active; ?>">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1"><?php echo $p['id_proyecto']; ?></h6>
                        <small><?php echo round($porcentaje); ?>%</small>
                    </div>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $porcentaje; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $p['listos']; ?> de <?php echo $p['total']; ?> ítems</small>
                </a>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="col-md-8">
            <?php if ($proyecto_seleccionado): 
                $res_items = $conn->query("SELECT * FROM cola_procesamiento WHERE id_proyecto = '$proyecto_seleccionado' ORDER BY id ASC");
            ?>
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Resultados: <?php echo $proyecto_seleccionado; ?></h5>
                        <span class="badge bg-info">Auto-actualizado cada 10s</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 600px;">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Entrada (Excel)</th>
                                        <th>Estatus</th>
                                        <th>Propuesta Generada</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($item = $res_items->fetch_assoc()): ?>
                                    <tr>
                                        <td style="width: 30%;"><small><?php echo htmlspecialchars($item['entrada_usuario']); ?></small></td>
                                        <td>
                                            <?php 
                                            $color = ['pendiente'=>'secondary', 'procesando'=>'warning', 'completado'=>'success', 'error'=>'danger'];
                                            echo "<span class='badge bg-{$color[$item['estatus']]}'>{$item['estatus']}</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php if($item['estatus'] == 'completado'): ?>
                                                <div style="font-size: 0.85rem; max-height: 100px; overflow-y: auto; background: #f9f9f9; padding: 5px; border: 1px solid #eee;">
                                                    <?php echo nl2br(htmlspecialchars($item['propuesta_ia'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Esperando...</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center">Selecciona un proyecto de la izquierda para ver los avances.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>