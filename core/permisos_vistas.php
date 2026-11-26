<?php
/**
 * ========================================================================
 * PERMISOS POR VISTA (2026-11-26)
 * ========================================================================
 * Administra quién puede abrir 3 vistas del cotizador:
 *
 *   actualizar_tarifario_validado -> "Actualizar tarifario validado"
 *   historial_precios             -> "Historial de cotizaciones"
 *   importar_v2                   -> "Importar cotizaciones (carga masiva)"
 *
 * La tabla permisos_vistas (vista, id_usuario) guarda los accesos. Se
 * crea sola si no existe y se siembra con los 3 administradores de
 * permisos (mismo patrón de auto-creación que config_tipos_cambio y
 * areas_validadas en actualizar_tarifario_validado.php).
 *
 * Administradores de permisos (ids de usuarios.id_usuario, fijos en el
 * código, no en BD): 523 Sebastián, 276 Pedro, 183 Amram. Solo ellos
 * ven la página permisos.php, y siempre tienen acceso a las 3 vistas
 * aunque se borre su fila: así nadie se bloquea a sí mismo.
 *
 * Uso en las vistas (después del chequeo de sesión, antes de cualquier
 * salida; $conn viene de conexion.php):
 *
 *   require_once __DIR__ . '/core/permisos_vistas.php';
 *   if (!tieneAccesoVista($conn, 'historial_precios', $_SESSION['usuario_id'])) {
 *       header('Location: index.php');
 *       exit;
 *   }
 * ========================================================================
 */

/** Administradores de permisos (usuarios.id_usuario). */
const PERMISOS_ADMIN_IDS = [523, 276, 183];

/** Vistas administrables: clave interna -> nombre visible. */
const PERMISOS_VISTAS = [
    'actualizar_tarifario_validado' => 'Actualizar tarifario validado',
    'historial_precios' => 'Historial de cotizaciones',
    'importar_v2' => 'Importar cotizaciones (carga masiva)',
];

/** ¿El usuario es administrador de permisos? Sin BD (sirve en index.php). */
function esAdminPermisos($id_usuario)
{
    return in_array((int) $id_usuario, PERMISOS_ADMIN_IDS, true);
}

/**
 * Crea permisos_vistas si no existe y siembra los 3 admins x las 3
 * vistas (INSERT IGNORE: si ya están, no duplica nada).
 */
function asegurarTablaPermisosVistas($conn)
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS permisos_vistas (' .
        'vista VARCHAR(50) NOT NULL, ' .
        'id_usuario INT NOT NULL, ' .
        'PRIMARY KEY (vista, id_usuario)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $stmt = $conn->prepare('INSERT IGNORE INTO permisos_vistas (vista, id_usuario) VALUES (?, ?)');
    foreach (PERMISOS_VISTAS as $vista => $nombre) {
        foreach (PERMISOS_ADMIN_IDS as $admin) {
            $stmt->bind_param('si', $vista, $admin);
            $stmt->execute();
        }
    }
    $stmt->close();
}

/**
 * ¿El usuario puede abrir la vista? True si es admin de permisos o si
 * tiene fila en permisos_vistas. Vista desconocida: false. La tabla se
 * crea sola si no existe (así el candado funciona aunque nadie haya
 * abierto todavía permisos.php).
 */
function tieneAccesoVista($conn, $vista, $id_usuario)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    if (esAdminPermisos($id_usuario)) {
        return true;
    }
    asegurarTablaPermisosVistas($conn);
    $id = (int) $id_usuario;
    $stmt = $conn->prepare('SELECT 1 FROM permisos_vistas WHERE vista = ? AND id_usuario = ? LIMIT 1');
    $stmt->bind_param('si', $vista, $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

/** Usuarios con acceso a una vista: [{id_usuario, nombre}], por nombre. */
function permisosUsuariosConAcceso($conn, $vista)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT u.id_usuario, u.nombre FROM permisos_vistas pv ' .
        'JOIN usuarios u ON u.id_usuario = pv.id_usuario ' .
        'WHERE pv.vista = ? ORDER BY u.nombre'
    );
    $stmt->bind_param('s', $vista);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuarios = [];
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
        $usuarios[] = [
            'id_usuario' => (int) $fila['id_usuario'],
            'nombre' => (string) $fila['nombre'],
        ];
    }
    $stmt->close();
    return $usuarios;
}

/** Usuarios activos (estatus=1) que aún no tienen acceso a una vista. */
function permisosUsuariosDisponibles($conn, $vista)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT id_usuario, nombre FROM usuarios WHERE estatus = 1 ' .
        'AND id_usuario NOT IN (SELECT id_usuario FROM permisos_vistas WHERE vista = ?) ' .
        'ORDER BY nombre'
    );
    $stmt->bind_param('s', $vista);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuarios = [];
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
        $usuarios[] = [
            'id_usuario' => (int) $fila['id_usuario'],
            'nombre' => (string) $fila['nombre'],
        ];
    }
    $stmt->close();
    return $usuarios;
}

/** Da acceso a una vista. False si la vista es desconocida o el id no vale. */
function permisosAgregarAcceso($conn, $vista, $id_usuario)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    $id = (int) $id_usuario;
    if ($id <= 0) {
        return false;
    }
    $stmt = $conn->prepare('INSERT IGNORE INTO permisos_vistas (vista, id_usuario) VALUES (?, ?)');
    $stmt->bind_param('si', $vista, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

/**
 * Quita el acceso a una vista. False si la vista es desconocida, el id
 * no vale, o si es un administrador de permisos (ellos siempre entran).
 */
function permisosQuitarAcceso($conn, $vista, $id_usuario)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    $id = (int) $id_usuario;
    if ($id <= 0 || esAdminPermisos($id)) {
        return false;
    }
    $stmt = $conn->prepare('DELETE FROM permisos_vistas WHERE vista = ? AND id_usuario = ?');
    $stmt->bind_param('si', $vista, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}
