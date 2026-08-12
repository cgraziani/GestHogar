<?php
// ============================================================
// LOGIN
// Cambios v2:
// - Eliminado: AND eliminado=0 en la query de login
// - Sin cambios en la lógica de estados (activo/pausado/baja)
// ============================================================
require_once '../config/db.php';
require_once '../config/app.php';
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Corregido: sin AND eliminado=0
    $st = $pdo->prepare("SELECT * FROM usuarios WHERE email=?");
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($pass, $u['password'])) {
        if ($u['estado'] === 'activo') {
            $_SESSION['usuario'] = $u;
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } elseif ($u['estado'] === 'pausado') {
            $error = 'Tu usuario está pausado temporalmente. Contactá a la administradora.';
        } elseif ($u['estado'] === 'baja') {
            $error = 'Tu usuario fue dado de baja del sistema. Contactá a la administradora.';
        }
    } elseif ($u && !password_verify($pass, $u['password'])) {
        $error = 'La contraseña es incorrecta. Verificá que no tenga mayúsculas o espacios de más.';
    } else {
        $error = 'No existe ningún usuario con ese email. Verificá que esté bien escrito.';
    }
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSETS_VER ?>">
    <title>Login</title>
</head>
<body class="login-bg">
    <div class="login-card">
        <h1 class="title" style="align-items:center">Hogar Nongo</h1>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" id="form-login" novalidate>
            <label class="textlogin">Email</label>
            <input name="email" type="email" id="input-email" required
                   autocomplete="email" placeholder="tu@email.com">
            <span id="err-email" style="color:red;font-size:12px;display:none">Ingresá un email válido.</span>

            <label class="textlogin">Contraseña</label>
            <input name="password" type="password" id="input-pass" required
                   autocomplete="current-password" placeholder="Tu contraseña">
            <span id="err-pass" style="color:red;font-size:12px;display:none">La contraseña no puede estar vacía.</span>

            <a href="recuperar.php" class="link">Recuperar contraseña</a>
            <button type="submit" id="btn-login">Entrar</button>
        </form>

        <script>
        var form  = document.getElementById('form-login');
        var email = document.getElementById('input-email');
        var pass  = document.getElementById('input-pass');
        var btn   = document.getElementById('btn-login');
        form.addEventListener('submit', function(e) {
            var ok = true;
            if (!email.value.trim() || !/\S+@\S+\.\S+/.test(email.value)) {
                document.getElementById('err-email').style.display = 'block';
                email.style.borderColor = 'red'; ok = false;
            } else {
                document.getElementById('err-email').style.display = 'none';
                email.style.borderColor = '';
            }
            if (!pass.value) {
                document.getElementById('err-pass').style.display = 'block';
                pass.style.borderColor = 'red'; ok = false;
            } else {
                document.getElementById('err-pass').style.display = 'none';
                pass.style.borderColor = '';
            }
            if (!ok) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.textContent = 'Ingresando...';
        });
        email.addEventListener('input', function() {
            document.getElementById('err-email').style.display = 'none';
            email.style.borderColor = '';
        });
        pass.addEventListener('input', function() {
            document.getElementById('err-pass').style.display = 'none';
            pass.style.borderColor = '';
        });
        </script>
    </div>
</body>
</html>
