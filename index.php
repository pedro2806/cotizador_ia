<?php include 'acciones_login.php'; ?>
<?php require_once __DIR__ . '/core/permisos_vistas.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <title><?php echo $logueado ? 'MessIAs | Inicio' : 'MessIAs | Iniciar sesión'; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">

    <style>
        /* Colores tomados de "MessIAs Login.pptx" (diseño aprobado). */
        :root {
            --mx-navy: #002060;        /* encabezado y pie */
            --mx-oro: #FFC911;         /* filetes bajo el encabezado y sobre el pie */
            --mx-azul: #2E75B6;        /* banda central (Azul énfasis 5, -25 %) */
            --mx-azul-btn: #013569;    /* botones "Consultar mis cotizaciones" */
            --mx-azul-btn-hover: #02478c;
            --mx-rojo: #C00000;        /* botón Salir */
            --mx-rojo-hover: #9a0000;
            --mx-borde-tarjeta: #172C51;
            --mx-texto-titulo: #3B3838;
            --mx-texto-desc: #767171;
            --mx-gris-bienvenido: #D9D6D6;
            --mx-fondo-login: #F8F9FA;
            --mx-entrar: #212529;
            --mx-gutter: clamp(16px, 2vw, 32px);
        }

        html, body { height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--mx-navy);
            font-family: Calibri, Carlito, "Segoe UI", system-ui, -apple-system, sans-serif;
            font-size: 16px;
            color: var(--mx-texto-titulo);
        }

        /* ---------- Marco: encabezado / banda / pie ---------- */
        .mx-header {
            flex: 0 0 auto;
            height: clamp(88px, 19vh, 156px);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 clamp(12px, 1.2vw, 24px);
            background: var(--mx-navy);
            border-bottom: 3px solid var(--mx-oro);
        }
        .mx-header a { display: block; height: 92%; }
        .mx-header img { height: 100%; width: auto; display: block; }

        .mx-main {
            flex: 1 0 auto;
            position: relative;
            overflow: hidden;
            background: var(--mx-azul);
        }
        /* Red de nodos: ocupa los 2/3 derechos de la banda, como en la diapositiva. */
        .mx-main::before {
            content: "";
            position: absolute;
            top: 0; right: 0; bottom: 0;
            width: 67%;
            background: url("img/messias/red-fondo.png") no-repeat right bottom / cover;
            pointer-events: none;
        }
        .mx-main > * { position: relative; z-index: 1; }

        .mx-footer {
            flex: 0 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 8px 16px;
            background: var(--mx-navy);
            border-top: 3px solid var(--mx-oro);
        }
        .mx-footer img { height: clamp(52px, 8.5vh, 80px); width: auto; max-width: 100%; display: block; }

        @keyframes mxEntrada {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---------- Login ---------- */
        .mx-login-wrap {
            min-height: 100%;
            display: grid;
            place-items: center;
            padding: 40px 16px;
        }
        .mx-login {
            width: min(470px, 100%);
            background: var(--mx-fondo-login);
            border-radius: 14px;
            padding: 26px clamp(20px, 4vw, 52px) 22px;
            box-shadow: 0 18px 40px rgba(0, 20, 60, .22);
            animation: mxEntrada .4s ease both;
        }
        .mx-login-titulo {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
            color: #555;
            margin: 0 0 14px;
        }
        .mx-campo {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }
        .mx-input-grupo {
            flex: 1;
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 5px;
            min-width: 0;
        }
        .mx-input-grupo:focus-within { box-shadow: 0 0 0 3px rgba(46, 117, 182, .35); }
        .mx-input-grupo input {
            flex: 1;
            min-width: 0;
            border: 0;
            background: transparent;
            padding: 9px 10px;
            font: inherit;
            font-size: 1rem;
            color: #333;
            outline: none;
        }
        .mx-sufijo { padding-right: 10px; font-size: .85rem; color: #666; white-space: nowrap; }
        .mx-ojo {
            flex: 0 0 auto;
            border: 0;
            background: transparent;
            color: #333;
            padding: 6px;
            line-height: 1;
            border-radius: 5px;
            font-size: 1.05rem;
        }
        .mx-ojo:hover { background: #e9ecef; }
        .mx-caps { display: none; font-size: .8rem; color: #b54700; margin: -4px 0 8px; }
        .mx-error {
            background: #f8d7da;
            color: #58151c;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: .9rem;
            margin-bottom: 12px;
        }
        .mx-btn-entrar {
            width: 100%;
            border: 0;
            border-radius: 999px;
            background: var(--mx-entrar);
            color: #fff;
            font: inherit;
            font-size: 1.05rem;
            padding: 10px 16px;
            margin-top: 2px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .18);
            transition: background-color .2s ease;
        }
        .mx-btn-entrar:hover:not(:disabled) { background: #000; }
        .mx-btn-entrar:disabled { opacity: .75; }
        .mx-restringido {
            margin: 16px 0 0;
            text-align: center;
            font-size: .85rem;
            color: #555;
        }

        /* ---------- Panel de inicio (sesión iniciada) ---------- */
        .mx-panel {
            display: grid;
            grid-template-columns: minmax(300px, min(36vw, 620px)) 1fr;
            gap: 24px 40px;
            align-items: start;
            padding: var(--mx-gutter);
            animation: mxEntrada .4s ease both;
        }
        .mx-opciones { display: flex; flex-direction: column; gap: 16px; }

        .mx-card {
            display: flex;
            align-items: stretch;
            gap: 8px;
            min-height: 210px;
            background: #fff;
            border: 1px solid var(--mx-borde-tarjeta);
            border-radius: 12px;
            overflow: hidden;
            color: inherit;
            text-decoration: none;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .mx-card:hover {
            color: inherit;
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 20, 60, .28);
        }
        .mx-card:focus-visible,
        .mx-btn-consulta:focus-visible,
        .mx-btn-salir:focus-visible,
        .mx-btn-entrar:focus-visible,
        .mx-ojo:focus-visible {
            outline: 3px solid var(--mx-oro);
            outline-offset: 2px;
        }
        .mx-card-texto { flex: 1; min-width: 0; padding: 18px 0 18px 22px; }
        .mx-card h2 {
            font-size: clamp(1.15rem, 1.4vw, 1.35rem);
            font-weight: 700;
            line-height: 1.2;
            color: var(--mx-texto-titulo);
            margin: 0 0 10px;
        }
        .mx-card p {
            font-size: 1rem;
            line-height: 1.35;
            color: var(--mx-texto-desc);
            margin: 0;
        }
        .mx-card p small { font-size: .82em; white-space: nowrap; }
        .mx-card-img { align-self: flex-end; flex: 0 0 auto; display: block; height: auto; }
        .mx-card-img.ia { width: clamp(84px, 9vw, 124px); margin: 0 14px 12px 0; }
        /* El robot viene sobre fondo casi blanco opaco: "multiply" lo funde con la tarjeta. */
        .mx-card-img.robot { width: clamp(110px, 11vw, 150px); mix-blend-mode: multiply; }

        .mx-motor {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: .9rem;
            font-weight: 700;
        }
        .mx-motor .dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
        .mx-motor.ok { background: #d1e7dd; color: #0a3622; }
        .mx-motor.ok .dot { background: #198754; }
        .mx-motor.off { background: #f8d7da; color: #58151c; }
        .mx-motor.off .dot { background: #dc3545; }

        .mx-consultas { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 4px; }
        .mx-btn-consulta {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 56px;
            padding: 8px 14px;
            border-radius: 12px;
            background: var(--mx-azul-btn);
            color: #fff;
            text-align: center;
            text-decoration: none;
            font-size: .95rem;
            line-height: 1.25;
            transition: background-color .2s ease;
        }
        .mx-btn-consulta:hover { background: var(--mx-azul-btn-hover); color: #fff; }

        .mx-bienvenida { text-align: right; padding-top: clamp(8px, 3vh, 36px); }
        .mx-bienvenido {
            margin: 0;
            font-size: clamp(1.05rem, 1.4vw, 1.4rem);
            font-weight: 700;
            text-transform: uppercase;
            color: var(--mx-gris-bienvenido);
        }
        .mx-nombre {
            margin: 0 0 14px;
            font-size: clamp(1.5rem, 2.5vw, 2.4rem);
            font-weight: 700;
            line-height: 1.15;
            color: #fff;
            overflow-wrap: anywhere;
        }
        .mx-btn-salir {
            border: 2px solid #fff;
            border-radius: 999px;
            background: var(--mx-rojo);
            color: #fff;
            font: inherit;
            font-size: 1.2rem;
            line-height: 1.2;
            padding: 3px 28px;
            transition: background-color .2s ease;
        }
        .mx-btn-salir:hover { background: var(--mx-rojo-hover); }
        /* Enlace discreto a Permisos: solo lo ven los administradores
           de permisos (ver el aside); texto chico y tenue, sin tarjeta
           ni icono llamativo. */
        .mx-link-permisos {
            display: block;
            margin-top: 10px;
            font-size: 0.8rem;
            color: rgba(255,255,255,.55);
            text-decoration: none;
        }
        .mx-link-permisos:hover { color: rgba(255,255,255,.85); text-decoration: underline; }

        @media (max-width: 899px) {
            .mx-panel { grid-template-columns: 1fr; }
            .mx-bienvenida { order: -1; padding-top: 0; }
            .mx-main::before { width: 100%; opacity: .7; }
        }
        @media (max-width: 479px) {
            .mx-consultas { grid-template-columns: 1fr; }
            .mx-card { min-height: 0; }
            .mx-card-texto { padding: 16px 0 16px 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .mx-login, .mx-panel { animation: none; }
            .mx-card, .mx-card:hover { transition: none; transform: none; }
        }
    </style>
</head>
<body>

<header class="mx-header">
    <a href="index.php" aria-label="messIAs, inicio">
        <img src="img/messias/logo-messias.png" alt="messIAs, all about metrology at the speed of AI">
    </a>
</header>

<main class="mx-main">
<?php if (!$logueado): ?>

    <div class="mx-login-wrap">
        <section class="mx-login" aria-labelledby="tituloAuth">
            <h1 class="mx-login-titulo" id="tituloAuth">Autenticación</h1>

            <?php if ($login_error): ?>
                <div class="mx-error" role="alert">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>

            <form action="acciones_login.php" method="POST" id="formLogin">
                <div class="mx-campo">
                    <label class="visually-hidden" for="inputCorreo">Correo corporativo</label>
                    <div class="mx-input-grupo">
                        <input type="text" id="inputCorreo" name="correo"
                               placeholder="usuario@mess.com.mx" autocomplete="username"
                               value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>"
                               required>
                        <span class="mx-sufijo" aria-hidden="true">@mess.com.mx</span>
                    </div>
                </div>

                <div class="mx-campo">
                    <label class="visually-hidden" for="inputPassword">Contraseña</label>
                    <div class="mx-input-grupo">
                        <input type="password" id="inputPassword" name="password"
                               placeholder="Contraseña" autocomplete="current-password" required>
                    </div>
                    <button type="button" class="mx-ojo" id="btnOjo" onclick="togglePassword()"
                            aria-label="Mostrar contraseña" aria-controls="inputPassword">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="mx-caps" id="capsWarning" role="status">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Mayúsculas activadas
                </div>

                <button type="submit" id="btnLogin" class="mx-btn-entrar">
                    <i class="bi bi-lock-fill me-1"></i> Entrar
                </button>
            </form>

            <p class="mx-restringido">
                <i class="bi bi-shield-lock me-1"></i> Acceso restringido &middot; Grupo MESS
            </p>
        </section>
    </div>

<?php else: ?>

    <div class="mx-panel">
        <div class="mx-opciones">
            <a class="mx-card" href="cargador_masivo.php">
                <div class="mx-card-texto">
                    <h2>Nueva Cotización<br>con modelo messIAs</h2>
                    <p>Análisis con referencias en el histórico de cotizaciones al instante mediante el modelo de lenguaje propio de Grupo mess.</p>
                    <?php if (function_exists('verificarWorker') && verificarWorker()): ?>
                        <div class="mx-motor ok"><span class="dot" aria-hidden="true"></span> Motor Activo</div>
                    <?php else: ?>
                        <!-- worker_ia.php no está corriendo: las cotizaciones se encolan
                             pero no se procesan hasta que el motor vuelva. -->
                        <div class="mx-motor off"><span class="dot" aria-hidden="true"></span> Motor Inactivo</div>
                    <?php endif; ?>
                </div>
                <img class="mx-card-img ia" src="img/messias/ia-red.png" alt="" width="363" height="341">
            </a>

            <!-- Agentes messbook (proyecto agentesIA, mismo servidor): el usuario levanta
                 la solicitud y un agente la cotiza; la respuesta llega a "Mis solicitudes". -->
            <a class="mx-card" href="../agentesIA/embed_nueva_solicitud.php">
                <div class="mx-card-texto">
                    <h2>Nueva Cotización<br>con Agentes messbook</h2>
                    <p>Envía tus ítems y un agente IA de messbook te devuelve la cotización, puede consultar manuales y listas de precios además del histórico de cotizaciones. <small>(puede tomar de 1 a 6 min.)</small></p>
                </div>
                <img class="mx-card-img robot" src="img/messias/robot-messbook.jpg" alt="" width="420" height="420">
            </a>

            <div class="mx-consultas">
                <a class="mx-btn-consulta" href="monitor_precios_v2.php">Consultar mis Cotizaciones con el modelo messIAs</a>
                <a class="mx-btn-consulta" href="../agentesIA/embed_mis_solicitudes.php">Consultar mis Cotizaciones con Agentes messbook</a>
            </div>
        </div>

        <aside class="mx-bienvenida" aria-label="Sesión">
            <p class="mx-bienvenido">Bienvenido</p>
            <p class="mx-nombre"><?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
            <button type="button" class="mx-btn-salir" onclick="confirmarSalida()">Salir</button>
            <?php if (esAdminPermisos($_SESSION['usuario_id'] ?? 0)): ?>
                <a class="mx-link-permisos" href="permisos.php">Permisos</a>
            <?php endif; ?>
        </aside>
    </div>

<?php endif; ?>
</main>

<footer class="mx-footer">
    <img src="img/messias/logo-mess-b1.png" alt="Grupo mess, all about metrology · B1 Business Intelligence">
</footer>

<script src="js/sweetalert2.all.min.js"></script>
<script>
    // Auto-dominio y bloqueo de submit
    const formLogin = document.getElementById('formLogin');
    if (formLogin) {
        formLogin.addEventListener('submit', function () {
            const correoInput = document.getElementById('inputCorreo');
            if (!correoInput.value.includes('@')) {
                correoInput.value = correoInput.value.trim() + '@mess.com.mx';
            }
            const btn = document.getElementById('btnLogin');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Entrando...';
        });
    }

    // Detector de Bloq Mayús
    const pwdInput = document.getElementById('inputPassword');
    if (pwdInput) {
        pwdInput.addEventListener('keyup', e => {
            document.getElementById('capsWarning').style.display =
                e.getModifierState('CapsLock') ? 'block' : 'none';
        });
    }

    // Placeholder animado en el campo de correo
    const correoInput = document.getElementById('inputCorreo');
    if (correoInput) {
        const hints = ['usuario@mess.com.mx', 'nombre.apellido@mess.com.mx', 'tu correo corporativo'];
        let i = 1;
        setInterval(() => {
            if (document.activeElement !== correoInput) {
                correoInput.placeholder = hints[i++ % hints.length];
            }
        }, 2500);
    }

    function togglePassword() {
        const input = document.getElementById('inputPassword');
        const icon  = document.getElementById('toggleIcon');
        const btn   = document.getElementById('btnOjo');
        const mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        icon.classList.toggle('bi-eye', !mostrar);
        icon.classList.toggle('bi-eye-slash', mostrar);
        btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    }

    function confirmarSalida() {
        Swal.fire({
            toast: true,
            position: 'top',
            icon: 'question',
            title: '¿Seguro que quieres salir?',
            showConfirmButton: true,
            confirmButtonText: 'Salir',
            confirmButtonColor: '#C00000',
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
        text: <?php echo json_encode($_SESSION['login_error'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); unset($_SESSION['login_error']); ?>,
        confirmButtonColor: '#013569'
    });
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_success'])): unset($_SESSION['login_success']); ?>
    Swal.fire({
        icon: 'success',
        titleText: <?php echo json_encode('¡Bienvenido, ' . $_SESSION['nombre'] . '!', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
        text: 'Has iniciado sesión correctamente.',
        timer: 2500,
        timerProgressBar: true,
        showConfirmButton: false
    });
    <?php endif; ?>
</script>

</body>
</html>
