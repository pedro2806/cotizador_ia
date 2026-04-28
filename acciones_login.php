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

    $stmt = $conn->prepare(
        "SELECT id_usuario, nombre, correo FROM usuarios WHERE correo = ? AND password = ? AND estatus = 1 LIMIT 1"
    );
    $stmt->bind_param('ss', $correo, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
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

    $stmt->close();
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
