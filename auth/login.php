<?php
require_once '../config/app.php';
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$error  = '';
$motivo = $_GET['motivo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Ingresá tu email y contraseña.';
    } else {
        $u = $pdo->prepare("SELECT * FROM usuarios WHERE email=?");
        $u->execute([$email]);
        $usuario = $u->fetch();

        if (!$usuario || !password_verify($pass, $usuario['password'])) {
            $error = 'Email o contraseña incorrectos.';
        } elseif ($usuario['estado'] === 'pausado') {
            $error = 'Tu cuenta está inhabilitada. Contactá a la administradora.';
        } elseif ($usuario['estado'] === 'baja') {
            $error = 'Tu cuenta fue dada de baja. Contactá a la administradora.';
        } else {
            $_SESSION['usuario'] = [
                'id_usuario' => $usuario['id_usuario'],
                'nombre'     => $usuario['nombre'],
                'rol'        => $usuario['rol'],
            ];
            $_SESSION['ultima_actividad'] = time();
            if ($usuario['rol'] === 'profesional') {
                $st_tipo = $pdo->prepare("SELECT tipo_profesional FROM medicos WHERE id_usuario=? AND estado='activo'");
                $st_tipo->execute([$usuario['id_usuario']]);
                $_SESSION['_tipo_prof'] = $st_tipo->fetchColumn() ?: '';
            }
            $destino = $usuario['rol'] === 'profesional'
                ? BASE_URL . '/profesional/index.php'
                : BASE_URL . '/index.php';
            header('Location: ' . $destino);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión en GestHogar</title>
    <script>
        sessionStorage.setItem('gh-theme', 'light');
        document.documentElement.setAttribute('data-theme', 'light');
    </script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f0f4f8;
            padding: 20px;
        }
        .wrap {
            display: flex; width: 100%; max-width: 860px; border-radius: 16px; overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,.08); border: 1px solid #e5e7eb;
        }
        .panel-info {
            flex: 1; background: #eaf1fb;
            padding: 48px 40px; display: flex; flex-direction: column; justify-content: center; gap: 10px;
        }
        .panel-info .marca { display: flex; align-items: center; gap: 12px; margin-bottom: 4px }
        .panel-info .logo-redondo {
            width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
            border: 2px solid #fff; box-shadow: 0 2px 8px rgba(31,78,121,.2);
        }
        .panel-info h1 { font-size: 24px; font-weight: 600; color: #1F4E79; margin: 0 }
        .panel-info p { font-size: 14px; color: #5a6b7d; line-height: 1.6; margin: 0 }
        .panel-form {
            flex: 1; background: #fff; padding: 48px 40px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .panel-form h2 { color: #1F4E79; font-size: 22px; font-weight: 600; margin-bottom: 4px }
        .panel-form .sub { color: #888; font-size: 13px; margin-bottom: 24px }
        label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 5px; margin-top: 14px }
        .input-wrap { position: relative }
        input {
            width: 100%; padding: 11px 14px; border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; outline: none; transition: border .2s;
        }
        input:focus { border-color: #1F4E79 }
        .toggle-pass {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: #999; font-size: 13px; user-select: none;
        }
        .btn-login {
            width: 100%; margin-top: 22px; padding: 12px; background: #1F4E79; color: #fff;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: background .2s;
        }
        .btn-login:hover { background: #163a5c }
        .error, .warn { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 8px }
        .error { background: #fdecea; border-left: 4px solid #c0392b; color: #c0392b }
        .warn  { background: #fff3cd; border-left: 4px solid #e67e22; color: #856404 }
        .links { display: flex; justify-content: flex-start; margin-top: 16px; font-size: 13px }
        .links a { color: #2E75B6; text-decoration: none }
        .links a:hover { text-decoration: underline }
        @media (max-width: 700px) {
            .wrap { flex-direction: column }
            .panel-info { display: none }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="panel-info">
        <div class="marca">
            <img class="logo-redondo" src="<?= BASE_URL ?>/assets/img/logo_nongo.png" alt="Hogar Ñongo Ñongo">
            <h1>GestHogar</h1>
        </div>
    </div>
    <div class="panel-form">
        <h2>Bienvenido</h2>
        <p class="sub">Ingresá tus credenciales para continuar</p>

        <?php if ($motivo === 'inactividad'): ?>
            <div class="warn">Tu sesión se cerró por inactividad. Volvé a iniciar sesión.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <label>Email</label>
            <input type="email" name="email" required autofocus placeholder="nombre@ejemplo.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label>Contraseña</label>
            <div class="input-wrap">
                <input type="password" name="password" id="pass" required placeholder="Tu contraseña">
                <span class="toggle-pass" onclick="togglePass()"></span>
            </div>

            <button type="submit" class="btn-login">Iniciar sesión</button>
        </form>

        <div class="links">
            <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
        </div>
    </div>
</div>
<script>
function togglePass() {
    var p = document.getElementById('pass');
    p.type = p.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
