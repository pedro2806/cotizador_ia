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
 * Normaliza un texto para comparar acciones/descripciones sin importar
 * acentos ni mayúsculas: 'Calibración' -> 'calibracion'.
 */
function normalizarTextoPlano($texto) {
    $texto = (string) $texto;
    $texto = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
        ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
        $texto
    );
    return strtolower(trim($texto));
}

/**
 * R1 — Centraliza la lógica para detectar si es SERVICIO o EQUIPO y limpia
 * las cadenas.
 *
 * Antes esta función borraba la acción ('calibración de', 'servicio ', ...)
 * como si fuera ruido, así que 'calibración de cmm' quedaba limpia solo
 * como 'cmm' y la búsqueda devolvía cualquier cosa de CMM (revisión,
 * instalación, movimiento...) ordenada por frecuencia. Ahora la acción se
 * conserva como token principal y se devuelve normalizada en 'accion'
 * (o null si la entrada no trae ninguna acción conocida). Solo se quitan
 * palabras de relleno real: de, del, la, el, los, las, con, por, para,
 * en, un, una.
 */
function procesarContextoBusqueda($busqueda) {
    $busqueda_original = trim($busqueda);
    $texto_plano = normalizarTextoPlano($busqueda_original);

    // Acciones conocidas, en su forma normalizada (sin acentos). La primera
    // que aparezca en el texto, en orden, es la acción principal.
    $acciones = [
        'calibracion', 'revision', 'instalacion', 'movimiento',
        'mantenimiento', 'verificacion', 'servicio', 'reparacion',
        'ajuste', 'diagnostico', 'inspeccion', 'prueba', 'venta',
    ];
    $accion = null;
    $tokens_planos = preg_split('/[^\p{L}\p{N}]+/u', $texto_plano, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($tokens_planos)) {
        // 'servicio' es genérico: solo manda si no hay una acción específica
        // (p. ej. en 'servicio de calibración' la acción es calibración).
        foreach ($tokens_planos as $token) {
            if ($token !== 'servicio' && in_array($token, $acciones, true)) {
                $accion = $token;
                break;
            }
        }
        if ($accion === null && in_array('servicio', $tokens_planos, true)) {
            $accion = 'servicio';
        }
    }

    // 1. Detectamos el tipo (SERVICIO o EQUIPO)
    $busca_servicio = ($accion !== null ||
                       preg_match('/^[SL]\d+/i', $busqueda_original));

    $tipo_val = $busca_servicio ? 'SERVICIO' : 'EQUIPO';

    // 2. Quitamos solo relleno real. La acción (calibración, revisión, ...)
    // y el equipo (cmm, durómetro, ...) se conservan: son el criterio
    // principal de la búsqueda, no ruido.
    $palabras_quitar = [' de ', ' del ', ' la ', ' el ', ' los ', ' las ',
                        ' con ', ' por ', ' para ', ' en ', ' un ', ' una '];
    $busqueda_limpia = ' ' . $busqueda_original . ' ';
    $busqueda_limpia = str_ireplace($palabras_quitar, ' ', $busqueda_limpia);

    // 3. Limpiamos caracteres extraños (respetando el guion, ej. S8-5)
    $busqueda_limpia = str_replace([',', ';', ':', '(', ')', '.'], ' ', $busqueda_limpia);

    // 4. Quitamos espacios en blanco sobrantes
    $busqueda_limpia = preg_replace('/\s+/', ' ', trim($busqueda_limpia));

    return [
        'busqueda_limpia' => $busqueda_limpia,
        'termino' => '%' . str_replace(' ', '%', $busqueda_limpia) . '%',
        'tipo' => $tipo_val,
        'es_cdmess' => (bool) preg_match('/^[SL]\d+/i', $busqueda_original),
        'accion' => $accion,
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
