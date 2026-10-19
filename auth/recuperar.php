<?php
require_once '../config/app.php';
require_once '../config/db.php';
require_once '../config/email.php';

$enviado = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá un email válido.';
    } else {
        $u = $pdo->prepare("SELECT id_usuario, nombre FROM usuarios WHERE email=? AND estado='activo'");
        $u->execute([$email]);
        $usuario = $u->fetch();

        if ($usuario) {
            $token   = bin2hex(random_bytes(32));
            $expira  = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("UPDATE usuarios SET token_recuperacion=?, token_expira=? WHERE id_usuario=?")
                ->execute([$token, $expira, $usuario['id_usuario']]);

            email_recuperar_password($email, $usuario['nombre'], $token);
        }

        $enviado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña de GestHogar</title>
    <style>
        body { font-family:Arial; background:#f0f4f8; min-height:100vh; display:flex; align-items:center; justify-content:center; margin:0 }
        .card { background:#fff; border-radius:12px; padding:36px 32px; width:100%; max-width:400px; box-shadow:0 4px 20px rgba(0,0,0,.1) }
        h1 { color:#1F4E79; font-size:22px; text-align:center; margin-bottom:20px }
        label { font-size:13px; font-weight:600; display:block; margin-bottom:4px }
        input { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:16px }
        button { width:100%; padding:12px; background:#1F4E79; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer }
        .error { background:#fdecea; color:#c0392b; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px }
        .ok { text-align:center; padding:20px 0 }
        a { color:#2E75B6; font-size:13px; text-align:center; display:block; margin-top:14px }
    </style>
</head>
<body>
<div class="card">
    <h1>Recuperar contraseña</h1>
    <?php if ($enviado): ?>
        <div class="ok">
            <p>Si el email existe en el sistema, te enviamos un link para restablecer tu contraseña.
               Revisá tu casilla (y la carpeta de spam).</p>
        </div>
    <?php else: ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <label>Email de tu cuenta</label>
            <input type="email" name="email" required placeholder="tu@email.com">
            <button type="submit">Enviar link de recuperación</button>
        </form>
    <?php endif; ?>
    <a href="login.php">← Volver al login</a>
</div>
</body>
</html>
