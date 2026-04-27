<?php
include 'conexion.php';
$proyecto = $_GET['proyecto'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Análisis de Precios IA - MESS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
    <h3>Análisis de Precios por Proyecto: <?php echo htmlspecialchars($proyecto); ?></h3>
    
    <div class="card shadow">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>CDMESS</th>
                        <th>Descripción Técnica (IA)</th>
                        <th>Precio Hist. Promedio</th>
                        <th class="table-primary text-dark">Precio Sugerido IA</th>
                        <th>Variación %</th>
                        <th>Notas y Justificación</th>
                        <th>Estatus</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $items = $conn->query("SELECT * FROM cola_procesamiento WHERE id_proyecto = '$proyecto'");
                    while($row = $items->fetch_assoc()):
                        // Decodificamos el JSON que guardó el Worker
                        $ia = json_decode($row['propuesta_ia'], true);
                        
                        // Cálculo de variaciones (asumiendo que extraemos un promedio del historial)
                        $precio_base = 1000; // Esto debería venir de una consulta AVG() en tu historial
                        $sugerido = $ia['precio_ia'] ?? 0;
                        $diff = ($precio_base > 0) ? (($sugerido - $precio_base) / $precio_base) * 100 : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo $ia['cdmess'] ?? 'S/C'; ?></strong></td>
                        <td><small><?php echo $ia['descripcion'] ?? $row['entrada_usuario']; ?></small></td>
                        <td>$<?php echo number_format($precio_base, 2); ?> <small class="text-muted">USD</small></td>
                        <td class="fw-bold text-primary">$<?php echo number_format($sugerido, 2); ?></td>
                        <td>
                            <span class="badge <?php echo $diff > 0 ? 'bg-danger' : 'bg-success'; ?>">
                                <?php echo ($diff > 0 ? '+' : '') . round($diff, 1); ?>%
                            </span>
                        </td>
                        <td><i style="font-size: 0.85rem;"><?php echo $ia['notas'] ?? 'Esperando análisis...'; ?></i></td>
                        <td>
                            <?php if($row['estatus'] == 'completado'): ?>
                                <span class="badge bg-success">✓ Analizado</span>
                            <?php else: ?>
                                <div class="spinner-border spinner-border-sm text-warning"></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>