<?php
/**
 * ========================================================================
 * MÓDULO: HELPERS Y LIMPIEZA DE DATOS
 * ========================================================================
 * Funciones de utilidad puras: no tocan la BD ni la IA, solo transforman
 * texto de entrada del usuario.
 */

function limpiarEntrada($texto) {
    $limpio = str_replace([',', '.', ';', ':', '(', ')'], ' ', $texto);
    return trim($limpio);
}

/**
 * Centraliza la lógica para detectar si es SERVICIO o EQUIPO y limpia las cadenas.
 */
function procesarContextoBusqueda($busqueda) {
    $busqueda_original = trim($busqueda);

    // 1. Detectamos el tipo (SERVICIO o EQUIPO)
    $busca_servicio = (stripos($busqueda_original, 'servicio') !== false ||
                       stripos($busqueda_original, 'calibracion') !== false ||
                       stripos($busqueda_original, 'calibración') !== false ||
                       stripos($busqueda_original, 'mantenimiento') !== false ||
                       preg_match('/^[SL]\d+/i', $busqueda_original));

    $tipo_val = $busca_servicio ? 'SERVICIO' : 'EQUIPO';

    // 2. Quitamos ruido (palabras de relleno que no aportan a la búsqueda)
    $palabras_quitar = [
        'calibracion de', 'calibración de', 'calibracion ', 'calibración ',
        'servicio de', 'servicio ', 'mantenimiento de', 'mantenimiento '
    ];
    $busqueda_limpia = str_ireplace($palabras_quitar, '', $busqueda_original);

    // 3. Limpiamos caracteres extraños (respetando el guion, ej. S8-5)
    $busqueda_limpia = str_replace([',', ';', ':', '(', ')', '.'], ' ', $busqueda_limpia);

    // 4. Quitamos espacios en blanco sobrantes
    $busqueda_limpia = preg_replace('/\s+/', ' ', trim($busqueda_limpia));

    return [
        'busqueda_limpia' => $busqueda_limpia,
        'termino' => '%' . str_replace(' ', '%', $busqueda_limpia) . '%',
        'tipo' => $tipo_val,
        'es_cdmess' => (bool) preg_match('/^[SL]\d+/i', $busqueda_original)
    ];
}

/**
 * Verifica si una clave CDMESS existe y está activa en el tarifario.
 */
function validaCDMESS($cdmess, $conn) {
    $sql = "SELECT COUNT(*) as count FROM tarifario WHERE STATUS = 'ACTIVE' AND CDMESS = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $cdmess);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && $row['count'] > 0;
}
