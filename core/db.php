<?php
/**
 * ========================================================================
 * CONEXIÓN A BASE DE DATOS
 * ========================================================================
 * Antes vivía directo en conexion.php con las credenciales hardcodeadas
 * ahí mismo. Ahora conexion.php solo incluye este archivo, que a su vez
 * toma los valores de core/config.php.
 */

require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // No exponemos el detalle completo del error de conexión al público;
    // solo lo registramos en el log del servidor.
    error_log('Error de conexión a BD: ' . $conn->connect_error);
    die('No fue posible conectar con la base de datos. Contacta al administrador del sistema.');
}

// Asegurar que soporte tildes y eñes
$conn->set_charset(DB_CHARSET);
