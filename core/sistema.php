<?php
/**
 * ========================================================================
 * MÓDULO: SISTEMA Y DEPURACIÓN
 * ========================================================================
 */

/**
 * Antes esta función ejecutaba `wmic process where "name='php.exe'"...`
 * para ver si worker_ia.php estaba corriendo. Eso solo funciona en
 * Windows (y "wmic" está deprecado desde Windows 11), así que en
 * cualquier otro entorno siempre fallaba en silencio. Además index.php
 * mostraba "Motor Activo" en AMBAS ramas del if/else, así que el
 * resultado nunca importaba de verdad.
 *
 * Ahora worker_ia.php escribe un archivo de "latido" (timestamp) en
 * cada vuelta de su bucle. Aquí solo comprobamos que ese archivo se
 * haya actualizado hace poco. Es multiplataforma y realmente refleja
 * si el proceso sigue vivo y procesando.
 */
function verificarWorker() {
    if (!file_exists(WORKER_HEARTBEAT_FILE)) {
        return false;
    }
    $ultimo_latido = (int) @file_get_contents(WORKER_HEARTBEAT_FILE);
    return $ultimo_latido > 0 && (time() - $ultimo_latido) <= WORKER_HEARTBEAT_TTL;
}

/**
 * Trae el detalle de un proyecto (items + propuesta de la IA ya decodificada)
 * para uso interno vía AJAX.
 */
function obtenerDetalleProyectoAJAX($id_proyecto) {
    global $conn;

    $sql = "SELECT id, entrada_usuario, estatus, propuesta_ia FROM cola_procesamiento
            WHERE id_proyecto = ? ORDER BY id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $id_proyecto);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['propuesta_ia'] = $row['propuesta_ia'] !== null ? json_decode($row['propuesta_ia'], true) : null;
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}
