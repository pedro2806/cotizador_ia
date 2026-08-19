<?php
/**
 * ========================================================================
 * BOOTSTRAP PARA ENDPOINTS JSON
 * ========================================================================
 * Cualquier endpoint AJAX (procesar_carga.php, buscar_clientes.php,
 * get_proyecto_data.php, acciones_monitor_precios_v2.php) DEBE devolver
 * JSON puro y nada más. El problema real que se vio en pantalla
 * ("Unexpected token '<', "<br /> <fo"... is not valid JSON") es que un
 * warning/notice de PHP (probablemente de Xdebug, que WampServer trae
 * activado por defecto) se imprime ANTES del json_encode() y rompe el
 * parseo en el navegador — aunque el resto del script haya funcionado bien.
 *
 * Esta función:
 *   1. Apaga display_errors (nada de HTML de error se imprime nunca).
 *   2. Deja log_errors encendido (todo se sigue registrando en el log de
 *      PHP, así que un warning real no desaparece, solo deja de romper
 *      la respuesta — se puede diagnosticar después leyendo el log).
 *   3. Si ocurre un error FATAL (el único caso que sí detiene el script),
 *      el shutdown handler limpia cualquier salida parcial y responde con
 *      un JSON de error limpio en vez de dejar pasar el volcado de PHP.
 *
 * Debe llamarse ANTES de cualquier otra cosa en el archivo (antes incluso
 * de session_start()).
 */
function iniciarRespuestaJSON() {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');

    register_shutdown_function(function () {
        $error = error_get_last();
        $buffer = ob_get_clean();

        $es_fatal = $error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);

        if ($es_fatal) {
            error_log('Fatal en endpoint JSON: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor. Se registró el detalle en el log de PHP.'
            ]);
            return;
        }

        // Sin fatal: lo que se generó en el buffer es la respuesta real
        // (normalmente solo el JSON, porque los warnings ya no se imprimen).
        echo $buffer;
    });
}
