<?php
include 'conexion.php';
include 'funcionesWorker.php';


// Estadísticas rápidas para el Dashboard
$total_proyectos = $conn->query("SELECT COUNT(DISTINCT id_proyecto) as total FROM cola_procesamiento")->fetch_assoc()['total'];
$total_items = $conn->query("SELECT COUNT(*) as total FROM cola_procesamiento")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.png">
    <title>MessIAs | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mess-blue: #013569;
            --mess-gold: #ffc107;
            --bg-light: #f8f9fc;
        }

        body { 
            background-color: var(--bg-light); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Navbar elegante */
        .navbar {
            background-color: var(--mess-blue) !important;
            border-bottom: 3px solid var(--mess-gold);
            padding: 0.8rem 2rem;
        }

        /* Hero Header */
        .hero-welcome {
            background: linear-gradient(135deg, var(--mess-blue) 0%, #004a94 100%);
            color: white;
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,45,90,0.15);
        }

        /* Tarjetas de menú mejoradas */
        .card-menu { 
            transition: all 0.3s cubic-bezier(.25,.8,.25,1);
            border: none; 
            border-radius: 18px; 
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .card-menu:hover { 
            transform: translateY(-10px); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .icon-box {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        .bg-soft-success { background-color: #e8f5e9; color: #2e7d32; }
        .bg-soft-primary { background-color: #e3f2fd; color: #1976d2; }

        /* Sección de Login como Sidecard */
        .login-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            padding: 2rem;
        }

        .status-dot {
            height: 10px;
            width: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--mess-blue);
        }

        footer { font-size: 0.85rem; color: #888; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="logo.png" alt="Grupo MESS Logo" height="45">
        </a>
        <div class="d-flex text-white align-items-center">
            <div class="text-end me-3 d-none d-sm-block">
                <small class="d-block opacity-75">Bienvenido,</small>
                <span class="fw-bold">Ing. Pedro Martínez</span>
            </div>
            <a href="#" class="btn btn-sm btn-outline-light rounded-pill px-3">Salir</a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row g-4">
        
        <div class="col-lg-8">
            <div class="hero-welcome">
                <h1 class="display-6 fw-bold">MessIAs <span class="fw-light">| Smart Pricing</span></h1>
                <p class="opacity-75">Optimización de cotizaciones basada en inteligencia analítica y registros históricos.</p>
                <div class="d-flex gap-4 mt-4">
                    <div>
                        <div class="text-uppercase small opacity-50">Proyectos</div>
                        <div class="h3 mb-0"><?php echo $total_proyectos; ?></div>
                    </div>
                    <div style="width: 1px; background: rgba(255,255,255,0.2);"></div>
                    <div>
                        <div class="text-uppercase small opacity-50">Items Procesados</div>
                        <div class="h3 mb-0"><?php echo $total_items; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card card-menu h-100 p-4" onclick="location.href='cargador_masivo.php'">
                        <div class="card-body text-center">
                            <div class="icon-box bg-soft-success">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                            </div>
                            <h4 class="fw-bold">Nueva Cotizaci&oacute;n con IA</h4>
                            <p class="text-muted small">Lectura linea a linea con análisis automático del modelo MessIAs.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-menu h-100 p-4" onclick="location.href='monitor_precios_v2.php'">
                        <div class="card-body text-center">
                            <div class="icon-box bg-soft-primary">
                                <i class="bi bi-speedometer2"></i>
                            </div>
                            <h4 class="fw-bold">Entrenamiento del Modelo</h4>
                            <p class="text-muted small">Auditoría, variaciones de margen y ajustes para el entrenamiento del modelo MessIAs.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="login-card mb-4">
                <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Sistema de Control</h5>
                
                <div class="mb-4">
                    <label class="small text-muted d-block mb-2">Estatus del Motor</label>
                    <?php if(function_exists('verificarWorker') && verificarWorker()): ?>
                        <div class="alert alert-success border-0 py-2 d-flex align-items-center">
                            <span class="status-dot bg-success"></span>
                            <small class="fw-bold">Worker Activo</small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger border-0 py-2 d-flex align-items-center">
                            <span class="status-dot bg-danger pulse"></span>
                            <small class="fw-bold">Motor Offline</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="auth-section mt-4 bg-light p-3 rounded-4">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Autenticación</h6>
                    <form action="#" method="POST">
                        <div class="mb-3">
                            <input type="text" class="form-control form-control-sm bg-white border-0" placeholder="Usuario" disabled>
                        </div>
                        <div class="mb-3">
                            <input type="password" class="form-control form-control-sm bg-white border-0" placeholder="Contraseña" disabled>
                        </div>
                        <button type="button" class="btn btn-dark btn-sm w-100 rounded-pill py-2 shadow-sm" disabled>
                            <i class="bi bi-lock-fill me-1"></i> Entrar
                        </button>
                    </form>
                    <p class="mt-3 text-center text-muted" style="font-size: 0.7rem;">
                        <i class="bi bi-shield-lock me-1"></i> Integración con login-master de Messbook habilitada próximamente.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="mt-5 py-4 text-center">
    <div class="container">
        <hr class="opacity-10 mb-4">
        <p class="mb-1 fw-bold">Mess Servicios Metrológicos, S. de R.L. de C.V.</p>
        <p class="small mb-0">Desarrollo y Sistematización | MessIAs&copy;</p>
        <small class="opacity-50">Versión 2.1 - 2026</small>
    </div>
</footer>

</body>
</html>