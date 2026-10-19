<?php
require_once '../config/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$ok    = false;

if (!$token) {
    die('<p style="font-family:Arial;text-align:center;padding:60px">Link inválido.</p>');
}

$u = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE token_recuperacion=? AND token_expira > NOW()");
$u->execute([$token]);
$usuario = $u->fetch();

if (!$usuario) {
    die('<p style="font-family:Arial;text-align:center;padding:60px">Este link expiró o ya fue usado. <a href="recuperar.php">Solicitá uno nuevo</a>.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($pass1, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE usuarios SET password=?, token_recuperacion=NULL, token_expira=NULL WHERE id_usuario=?")
            ->execute([$hash, $usuario['id_usuario']]);
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva contraseña de GestHogar</title>
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
    <h1>Nueva contraseña</h1>
    <?php if ($ok): ?>
        <div class="ok">
            <p>Tu contraseña fue actualizada correctamente.</p>
        </div>
        <a href="login.php">Ir al login →</a>
    <?php else: ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <label>Nueva contraseña</label>
            <input type="password" name="password" required minlength="6" placeholder="Mínimo 6 caracteres">
            <label>Repetir contraseña</label>
            <input type="password" name="password2" required minlength="6">
            <button type="submit">Guardar contraseña</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
