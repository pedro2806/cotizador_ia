<?php
session_start();
require_once 'conexion.php';
require_once 'funcionesWorker.php';

// Manejar POST de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($correo === '' || $password === '') {
        $_SESSION['login_error'] = 'Completa todos los campos.';
        header('Location: index.php');
        exit;
    }

    // Traemos solo por correo + estatus; la contraseña se valida aparte con
    // password_verify(). Antes se comparaba "password = ?" en texto plano
    // directo contra la BD, lo cual expone las contraseñas de todos los
    // usuarios ante cualquier fuga o acceso a la base de datos.
    $stmt = $conn->prepare(
        'SELECT id_usuario, nombre, correo, password FROM usuarios WHERE correo = ? AND estatus = 1 LIMIT 1'
    );
    $stmt->bind_param('s', $correo);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $password_ok = false;
    if ($row) {
        $hash_almacenado = $row['password'];

        $algo_detectado = password_get_info($hash_almacenado)['algo'];
        if (!empty($algo_detectado)) {
            // Ya es un hash moderno (bcrypt/argon2): verificación normal.
            $password_ok = password_verify($password, $hash_almacenado);
        } elseif (hash_equals($hash_almacenado, $password)) {
            // Migración: la cuenta todavía tiene la contraseña en texto plano
            // (esquema anterior). Si coincide, la aceptamos esta única vez y
            // de inmediato la reescribimos como hash seguro para que a partir
            // de ahora ya no quede en texto plano en la base de datos.
            $password_ok = true;
            $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE usuarios SET password = ? WHERE id_usuario = ?');
            $upd->bind_param('si', $nuevo_hash, $row['id_usuario']);
            $upd->execute();
            $upd->close();
        }
    }

    if ($password_ok) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $row['id_usuario'];
        $_SESSION['nombre']     = $row['nombre'];
        $_SESSION['correo']     = $row['correo'];
        $_SESSION['login_success'] = true;
        header('Location: index.php');
    } else {
        $_SESSION['login_error'] = 'Correo o contraseña incorrectos, o cuenta inactiva.';
        header('Location: index.php');
    }

    exit;
}

// Variables para la vista (cuando index.php incluye este archivo)
$logueado = isset($_SESSION['usuario_id']);

$login_error = '';
if (isset($_GET['login_error'])) {
    $login_error = $_GET['login_error'] === 'campos'
        ? 'Completa todos los campos.'
        : 'Correo o contraseña incorrectos, o cuenta inactiva.';
}

$total_proyectos = $conn->query("SELECT COUNT(DISTINCT id_proyecto) as total FROM cola_procesamiento")->fetch_assoc()['total'];
$total_items     = $conn->query("SELECT COUNT(*) as total FROM cola_procesamiento")->fetch_assoc()['total'];
