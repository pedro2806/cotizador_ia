<?php
/**
 * ========================================================================
 * PERMISOS DE VISTAS — quién puede abrir 3 vistas del cotizador
 * ========================================================================
 * Solo la ven los administradores de permisos (523 Sebastián, 276 Pedro,
 * 183 Amram; ver core/permisos_vistas.php). Cualquier otro usuario
 * logueado se redirige a index.php.
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
if (!esAdminPermisos($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Acciones por POST (con redirección para no reenviar al recargar).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $vista = $_POST['vista'] ?? '';
    $id_usuario = (int) ($_POST['id_usuario'] ?? 0);
    if ($accion === 'agregar') {
        permisosAgregarAcceso($conn, $vista, $id_usuario);
    } elseif ($accion === 'quitar') {
        permisosQuitarAcceso($conn, $vista, $id_usuario);
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
                                <span><?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">(<?php echo (int) $u['id_usuario']; ?>)</span>
                                    <?php if (esAdminPermisos($u['id_usuario'])): ?>
                                        <span class="badge bg-secondary ms-1">Administrador</span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!esAdminPermisos($u['id_usuario'])): ?>
                                <form method="post" class="m-0" onsubmit="return confirm('¿Quitar el acceso a <?php echo htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="vista" value="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_usuario" value="<?php echo (int) $u['id_usuario']; ?>">
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

<script src="js/sweetalert2.all.min.js"></script>
<script>
    // Usuarios disponibles por vista, para el modal de agregar (un solo
    // modal reutilizable). El JSON se escapa para incrustarlo seguro.
    const disponiblesPorVista = <?php echo json_encode($disponibles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const nombresVista = <?php echo json_encode($nombresVista, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    // Modal (SweetAlert2, el mismo que usa el monitor; js/ no trae el
    // bundle JS de Bootstrap) para agregar acceso a una vista: el botón
    // Agregar de cada vista lo abre con esa vista en el campo oculto y
    // el select con solo sus usuarios disponibles.
    function abrirModalAgregar(vista) {
        const lista = disponiblesPorVista[vista] || [];
        const opciones = {};
        lista.forEach(u => { opciones[String(u.id_usuario)] = u.nombre + ' (' + u.id_usuario + ')'; });
        Swal.fire({
            title: 'Agregar acceso a ' + (nombresVista[vista] || vista),
            input: 'select',
            inputOptions: opciones,
            inputPlaceholder: 'Elegir usuario...',
            showCancelButton: true,
            confirmButtonText: 'Agregar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#002d5a',
            inputValidator: v => !v ? 'Elige un usuario' : null
        }).then(result => {
            if (result && result.isConfirmed && result.value) enviarAgregar(vista, result.value);
        });
    }

    // El guardado es el mismo POST de siempre (accion=agregar, vista,
    // id_usuario); la página recarga la lista por la redirección.
    function enviarAgregar(vista, idUsuario) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'permisos.php';
        [['accion', 'agregar'], ['vista', vista], ['id_usuario', idUsuario]].forEach(([k, v]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = k;
            input.value = v;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
</script>

</body>
</html>
