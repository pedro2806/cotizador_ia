<?php
session_start();
setcookie('noEmpleado', '', time() - 3600, '/');
session_destroy();
header('Location: index.php');
exit;
