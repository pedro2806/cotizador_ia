<?php
/**
 * ========================================================================
 * PERMISOS POR VISTA (2026-11-26, corregido a noEmpleado)
 * ========================================================================
 * Administra quién puede abrir 3 vistas del cotizador:
 *
 *   actualizar_tarifario_validado -> "Actualizar tarifario validado"
 *   historial_precios             -> "Historial de cotizaciones"
 *   importar_v2                   -> "Importar cotizaciones (carga masiva)"
 *
 * El acceso se administra por noEmpleado (cookie $_COOKIE['noEmpleado']),
 * NO por id_usuario de sesión: son números distintos (Pedro: noEmpleado
 * 276, id_usuario 312; Sebastián: 523 / 495; Amram: 183 / 308 —
 * verificado contra el dump mess_rrhh.usuarios). Con el diseño anterior
 * (id_usuario) la opción no le salía a nadie.
 *
 * La tabla permisos_vistas (vista, noEmpleado) guarda los accesos. Se
 * crea sola si no existe y se siembra con los 3 administradores de
 * permisos (mismo patrón de auto-creación que config_tipos_cambio y
 * areas_validadas en actualizar_tarifario_validado.php). Si la tabla ya
 * existe con la columna vieja id_usuario (del diseño anterior, que
 * nunca llegó a producción), se borra y se recrea con el esquema nuevo:
 * no hay permisos reales que perder.
 *
 * Administradores de permisos (noEmpleado, fijos en el código, no en
 * BD): 523 Sebastián, 276 Pedro, 183 Amram. Solo ellos ven la página
 * permisos.php, y siempre tienen acceso a las 3 vistas aunque se borre
 * su fila: así nadie se bloquea a sí mismo.
 *
 * Uso en las vistas (después del chequeo de sesión, antes de cualquier
 * salida; $conn viene de conexion.php):
 *
 *   require_once __DIR__ . '/core/permisos_vistas.php';
 *   if (!tieneAccesoVista($conn, 'historial_precios', (int)($_COOKIE['noEmpleado'] ?? 0))) {
 *       header('Location: index.php');
 *       exit;
 *   }
 *
 * El nombre del empleado se resuelve en la tabla `usuarios` (campo
 * `nombre`), por `usuarios.noEmpleado`.
 * ========================================================================
 */

/** Administradores de permisos (noEmpleado de la cookie). */
const PERMISOS_ADMIN_NOEMPLEADO = [523, 276, 183];

/** Vistas administrables: clave interna -> nombre visible. */
const PERMISOS_VISTAS = [
    'actualizar_tarifario_validado' => 'Actualizar tarifario validado',
    'historial_precios' => 'Historial de cotizaciones',
    'importar_v2' => 'Importar cotizaciones (carga masiva)',
];

/** ¿El noEmpleado es administrador de permisos? Sin BD (sirve en index.php). */
function esAdminPermisos($noEmpleado)
{
    return in_array((int) $noEmpleado, PERMISOS_ADMIN_NOEMPLEADO, true);
}

/**
 * Crea permisos_vistas si no existe y siembra los 3 admins x las 3
 * vistas (INSERT IGNORE: si ya están, no duplica nada). Si la tabla ya
 * existe con la columna vieja id_usuario, se borra y se recrea con el
 * esquema nuevo (el diseño anterior nunca llegó a producción).
 */
function asegurarTablaPermisosVistas($conn)
{
    $cols = $conn->query("SHOW COLUMNS FROM permisos_vistas LIKE 'id_usuario'");
    if ($cols && $cols->num_rows > 0) {
        $conn->query('DROP TABLE permisos_vistas');
    }
    $conn->query(
        'CREATE TABLE IF NOT EXISTS permisos_vistas (' .
        'vista VARCHAR(50) NOT NULL, ' .
        'noEmpleado INT NOT NULL, ' .
        'PRIMARY KEY (vista, noEmpleado)' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $stmt = $conn->prepare('INSERT IGNORE INTO permisos_vistas (vista, noEmpleado) VALUES (?, ?)');
    foreach (PERMISOS_VISTAS as $vista => $nombre) {
        foreach (PERMISOS_ADMIN_NOEMPLEADO as $admin) {
            $stmt->bind_param('si', $vista, $admin);
            $stmt->execute();
        }
    }
    $stmt->close();
}

/**
 * ¿El noEmpleado puede abrir la vista? True si es admin de permisos o
 * si tiene fila en permisos_vistas. Vista desconocida: false. Sin
 * cookie o con 0: false. La tabla se crea sola si no existe (así el
 * candado funciona aunque nadie haya abierto todavía permisos.php).
 */
function tieneAccesoVista($conn, $vista, $noEmpleado)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    if (esAdminPermisos($noEmpleado)) {
        return true;
    }
    asegurarTablaPermisosVistas($conn);
    $id = (int) $noEmpleado;
    $stmt = $conn->prepare('SELECT 1 FROM permisos_vistas WHERE vista = ? AND noEmpleado = ? LIMIT 1');
    $stmt->bind_param('si', $vista, $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res && $res->num_rows > 0;
    $stmt->close();
    return $ok;
}

/** Usuarios con acceso a una vista: [{noEmpleado, nombre}], por nombre. */
function permisosUsuariosConAcceso($conn, $vista)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return [];
    }
    $stmt = $conn->prepare(
        'SELECT u.noEmpleado, u.nombre FROM permisos_vistas pv ' .
        'JOIN usuarios u ON u.noEmpleado = pv.noEmpleado ' .
        'WHERE pv.vista = ? ORDER BY u.nombre'
    );
    $stmt->bind_param('s', $vista);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuarios = [];
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
        $usuarios[] = [
            'noEmpleado' => (int) $fila['noEmpleado'],
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
        'SELECT noEmpleado, nombre FROM usuarios WHERE estatus = 1 ' .
        "AND noEmpleado IS NOT NULL AND noEmpleado <> '' " .
        'AND noEmpleado NOT IN (SELECT noEmpleado FROM permisos_vistas WHERE vista = ?) ' .
        'ORDER BY nombre'
    );
    $stmt->bind_param('s', $vista);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuarios = [];
    foreach ($res ? $res->fetch_all(MYSQLI_ASSOC) : [] as $fila) {
        $usuarios[] = [
            'noEmpleado' => (int) $fila['noEmpleado'],
            'nombre' => (string) $fila['nombre'],
        ];
    }
    $stmt->close();
    return $usuarios;
}

/** Da acceso a una vista. False si la vista es desconocida o el noEmpleado no vale. */
function permisosAgregarAcceso($conn, $vista, $noEmpleado)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    $id = (int) $noEmpleado;
    if ($id <= 0) {
        return false;
    }
    $stmt = $conn->prepare('INSERT IGNORE INTO permisos_vistas (vista, noEmpleado) VALUES (?, ?)');
    $stmt->bind_param('si', $vista, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}

/**
 * Quita el acceso a una vista. False si la vista es desconocida, el
 * noEmpleado no vale, o si es un administrador de permisos (ellos
 * siempre entran).
 */
function permisosQuitarAcceso($conn, $vista, $noEmpleado)
{
    if (!isset(PERMISOS_VISTAS[$vista])) {
        return false;
    }
    $id = (int) $noEmpleado;
    if ($id <= 0 || esAdminPermisos($id)) {
        return false;
    }
    $stmt = $conn->prepare('DELETE FROM permisos_vistas WHERE vista = ? AND noEmpleado = ?');
    $stmt->bind_param('si', $vista, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return (bool) $ok;
}
