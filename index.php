<?php include 'acciones_login.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title>MessIAs | Dashboard</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    
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

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .login-card { animation: fadeInUp 0.4s ease both; }

        .user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--mess-gold);
            color: var(--mess-blue);
            font-weight: 800;
            font-size: 0.8rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .caps-warning {
            font-size: 0.72rem;
            color: #e65100;
            display: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="logo.png" alt="Grupo MESS Logo" height="45">
        </a>
        <div class="d-flex text-white align-items-center">
            <?php if ($logueado): ?>
                <div class="text-end me-3 d-none d-sm-block">
                    <small class="d-block opacity-75">Bienvenido,</small>
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                </div>
                <button onclick="confirmarSalida()" class="btn btn-sm btn-outline-light rounded-pill px-3">Salir</button>
            <?php else: ?>
                <span class="opacity-50 small me-3 d-none d-sm-block">No has iniciado sesión</span>
            <?php endif; ?>
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
                    <div class="card card-menu h-100 p-4<?php echo $logueado ? '' : ' opacity-50 pe-none'; ?>"
                         <?php if ($logueado): ?>onclick="location.href='cargador_masivo.php'"<?php endif; ?>>
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
                    <div class="card card-menu h-100 p-4<?php echo $logueado ? '' : ' opacity-50 pe-none'; ?>"
                         <?php if ($logueado): ?>onclick="location.href='monitor_precios_v2.php'"<?php endif; ?>>
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

                <?php if (!$logueado): ?>
                <div class="auth-section mt-4 bg-light p-3 rounded-4">
                    <h6 class="small fw-bold text-uppercase text-muted mb-3">Autenticación</h6>

                    <?php if ($login_error): ?>
                        <div class="alert alert-danger border-0 py-2 mb-3 small">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?php echo htmlspecialchars($login_error); ?>
                        </div>
                    <?php endif; ?>
                    <form action="acciones_login.php" method="POST" id="formLogin">
                        <div class="mb-2">
                            <div class="input-group input-group-sm">
                                <input type="text" id="inputCorreo" name="correo" class="form-control bg-white border-0"
                                       placeholder="usuario"
                                       value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                                       required>
                                <span class="input-group-text bg-white border-0 text-muted" style="font-size:0.75rem;">@mess.com.mx</span>
                            </div>
                        </div>
                        <div class="mb-1 input-group input-group-sm">
                            <input type="password" id="inputPassword" name="password" class="form-control bg-white border-0"
                                   placeholder="Contraseña" required>
                            <button type="button" class="btn btn-light border-0" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="caps-warning mb-2" id="capsWarning">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Mayúsculas activadas
                        </div>
                        <button type="submit" id="btnLogin" class="btn btn-dark btn-sm w-100 rounded-pill py-2 shadow-sm">
                            <i class="bi bi-lock-fill me-1"></i> Entrar
                        </button>
                    </form>
                    <p class="mt-3 text-center text-muted" style="font-size: 0.7rem;">
                        <i class="bi bi-shield-lock me-1"></i> Acceso restringido &middot; Grupo MESS
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer class="mt-5 py-4 text-center">
    <div class="container">
        <hr class="opacity-10 mb-4">
        <p class="mb-1 fw-bold">Mess Servicios Metrológicos, S. de R.L. de C.V.</p>
        <p class="small mb-0">Desarrollo y Sistematización | MessIAs&copy;</p>
        <small class="opacity-50">Versión 2.1 - <?php echo date('Y'); ?></small>
    </div>
</footer>
<script src="js/sweetalert2.all.min.js"></script>
<script>
    // Auto-dominio y bloqueo de submit
    const formLogin = document.getElementById('formLogin');
    if (formLogin) {
        formLogin.addEventListener('submit', function(e) {
            const correoInput = document.getElementById('inputCorreo');
            if (!correoInput.value.includes('@')) {
                correoInput.value = correoInput.value.trim() + '@mess.com.mx';
            }
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Entrando...';
        });
    }

    // Caps Lock detector
    const pwdInput = document.getElementById('inputPassword');
    if (pwdInput) {
        pwdInput.addEventListener('keyup', e => {
            document.getElementById('capsWarning').style.display =
                e.getModifierState('CapsLock') ? 'block' : 'none';
        });
    }

    // Placeholder animado en el campo de correo
    const correoInput = document.querySelector('input[name="correo"]');
    if (correoInput) {
        const hints = ['usuario@mess.com.mx', 'nombre.apellido@mess.com.mx', 'tu correo corporativo'];
        let i = 0;
        setInterval(() => {
            if (document.activeElement !== correoInput) {
                correoInput.placeholder = hints[i++ % hints.length];
            }
        }, 2500);
    }

    function togglePassword() {
        const input = document.getElementById('inputPassword');
        const icon  = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }

    function confirmarSalida() {
        Swal.fire({
            toast: true,
            position: 'top-center',
            icon: 'question',
            title: '¿Seguro que quieres salir?',
            showConfirmButton: true,
            confirmButtonText: 'Salir',
            showCancelButton: true,
            cancelButtonText: 'No',
            timer: 2500
        }).then(result => {
            if (result.isConfirmed) window.location.href = 'logout.php';
        });
    }

    <?php if (!empty($_SESSION['login_error'])): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error al iniciar sesión',
        text: '<?php echo addslashes($_SESSION['login_error']); unset($_SESSION['login_error']); ?>',
        confirmButtonColor: '#013569'
    });
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_success'])): unset($_SESSION['login_success']); ?>
    Swal.fire({
        icon: 'success',
        title: '¡Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?>!',
        text: 'Has iniciado sesión correctamente.',
        timer: 2500,
        timerProgressBar: true,
        showConfirmButton: false
    });
    
    <?php endif; ?>
</script>

</body>
</html>