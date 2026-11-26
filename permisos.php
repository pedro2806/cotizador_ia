<?php
/**
 * ========================================================================
 * PERMISOS DE VISTAS — quién puede abrir 3 vistas del cotizador
 * ========================================================================
 * Solo la ven los administradores de permisos (noEmpleado 523
 * Sebastián, 276 Pedro, 183 Amram; ver core/permisos_vistas.php).
 * Cualquier otro usuario logueado se redirige a index.php. El acceso se
 * administra por noEmpleado (cookie), no por id_usuario de sesión.
 *
 * Por cada vista se muestra quién tiene acceso (los administradores van
 * marcados y no se les puede quitar: siempre entran), se agrega acceso
 * con el select de usuarios activos y se quita con el botón Quitar.
 * La tabla permisos_vistas se crea sola si no existe.
 * ========================================================================
 */
session_start();
if (empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/core/permisos_vistas.php';

asegurarTablaPermisosVistas($conn);
if (!esAdminPermisos((int)($_COOKIE['noEmpleado'] ?? 0))) {
    header('Location: index.php');
    exit;
}

// Acciones por POST (con redirección para no reenviar al recargar).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $vista = $_POST['vista'] ?? '';
    $noEmpleado = (int) ($_POST['noEmpleado'] ?? 0);
    if ($accion === 'agregar') {
        permisosAgregarAcceso($conn, $vista, $noEmpleado);
    } elseif ($accion === 'quitar') {
        permisosQuitarAcceso($conn, $vista, $noEmpleado);
    }
    header('Location: permisos.php');
    exit;
}

$vistas = [];
$disponibles = [];
$nombresVista = [];
foreach (PERMISOS_VISTAS as $clave => $nombre) {
    $vistas[$clave] = [
        'nombre' => $nombre,
        'con_acceso' => permisosUsuariosConAcceso($conn, $clave),
        'disponibles' => permisosUsuariosDisponibles($conn, $clave),
    ];
    // Mapas para el modal de agregar (un solo modal reutilizable).
    $disponibles[$clave] = $vistas[$clave]['disponibles'];
    $nombresVista[$clave] = $nombre;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title>Permisos de vistas | MessIAs</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link rel="stylesheet" href="css/estilos.css">
    <link rel="stylesheet" href="css/marco_messias.css">
    <link rel="stylesheet" href="css/select2.min.css">
    <style>
        /* Mismo marco messIAs que monitor_precios_v2.php e
           historial_precios.php. Solo van aquí reglas propias de esta
           página; los colores usan los tokens del marco. Sin rojo vivo. */
        .font-small { font-size: 0.85rem; }
        .x-small { font-size: 0.75rem; }
        .text-primary { color: var(--accent) !important; }
        .perm-card { border: 1px solid #e9ecef; border-radius: 10px; }
    </style>
</head>
<body class="mx-marco">

<header class="mx-header">
    <a class="mx-volver" href="index.php" title="Volver a Inicio" aria-label="Volver a Inicio"><img src="img/messias/volver.png" alt="Volver"></a>
    <a class="mx-logo" href="index.php"><img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI"></a>
</header>

<main class="mx-main">
<div class="mx-hoja">
    <h1 class="mx-hoja-titulo">Permisos de vistas</h1>
    <p class="text-muted font-small">Cada vista solo la abre quien tenga acceso. Los administradores de permisos (Sebastián, Pedro y Amram) siempre entran y no se les puede quitar el acceso.</p>

    <div class="row g-3">
        <?php foreach ($vistas as $clave => $v): ?>
        <div class="col-md-4">
            <div class="card perm-card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($v['nombre'], ENT_QUOTES, 'UTF-8'); ?></h6>
                    <div class="x-small text-muted mb-3"><?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?></div>

                    <div class="x-small text-uppercase text-muted fw-bold mb-1">Con acceso</div>
                    <?php if (empty($v['con_acceso'])): ?>
                        <p class="x-small text-muted">Nadie tiene acceso todavía.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($v['con_acceso'] as $u): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 x-small">
                                <span><?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">(<?php echo (int) $u['noEmpleado']; ?>)</span>
                                    <?php if (esAdminPermisos($u['noEmpleado'])): ?>
                                        <span class="badge bg-secondary ms-1">Administrador</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!esAdminPermisos($u['noEmpleado'])): ?>
                                <form method="post" class="m-0" onsubmit="return confirm('¿Quitar el acceso a <?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="noEmpleado" value="<?php echo (int) $u['noEmpleado']; ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Quitar</button>
                                </form>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($v['disponibles'])): ?>
                        <button type="button" class="btn btn-warning btn-sm fw-bold" onclick="abrirModalAgregar('<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>')">Agregar</button>
                    <?php else: ?>
                        <p class="x-small text-muted mb-0">No hay más usuarios activos para agregar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</main>

<!-- Modal único para agregar acceso (Bootstrap, no SweetAlert2: con Swal,
     Select2 pelea por el foco y no deja teclear en el buscador). -->
<div class="modal fade" id="modalPermiso" tabindex="-1" aria-labelledby="modalPermisoTitulo" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="permisos.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPermisoTitulo">Agregar acceso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar">
                    <input type="hidden" name="vista" id="modalVista" value="">
                    <label class="form-label x-small fw-bold mb-1" for="modalUsuario">Usuario</label>
                    <select name="noEmpleado" id="modalUsuario" class="form-select select-usuario" style="width: 100%" required></select>
                    <div class="form-text x-small">Busca por nombre; solo se muestran los usuarios activos sin acceso a esta vista.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm fw-bold">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script>
<script src="js/select2.min.js"></script>
<script>
    // Usuarios disponibles por vista, para el modal de agregar (un solo
    // modal reutilizable). El JSON se escapa para incrustarlo seguro.
    const disponiblesPorVista = <?php echo json_encode($disponibles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nombresVista = <?php echo json_encode($nombresVista, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Select2 con buscador: el dropdownParent al modal es obligatorio o
    // el desplegable se corta (el modal tiene overflow propio).
    $(function () {
        $('.select-usuario').select2({dropdownParent: $('#modalPermiso'), width: '100%', placeholder: 'Buscar usuario...'});
    });

    // El botón Agregar de cada vista abre el modal con esa vista en el
    // campo oculto y el select con solo sus usuarios disponibles
    // (nombre + id). Al guardar, el form hace el POST de siempre y la
    // página recarga la lista por la redirección.
    function abrirModalAgregar(vista) {
        $('#modalVista').val(vista);
        $('#modalPermisoTitulo').text('Agregar acceso a ' + (nombresVista[vista] || vista));
        const sel = $('.select-usuario');
        sel.empty();
        (disponiblesPorVista[vista] || []).forEach(u => {
            sel.append(new Option(u.nombre + ' (' + u.noEmpleado + ')', String(u.noEmpleado)));
        });
        sel.val(null).trigger('change');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPermiso')).show();
    }
</script>

</body>
</html>
