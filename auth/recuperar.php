<?php require_once '../config/db.php'; require_once '../config/app.php'; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=trim($_POST['email']); $st=$pdo->prepare('SELECT id_usuario FROM usuarios WHERE email=? AND eliminado=0'); $st->execute([$email]);
 if($u=$st->fetch()){ $token=bin2hex(random_bytes(16)); $pdo->prepare('UPDATE usuarios SET token_recuperacion=? WHERE id_usuario=?')->execute([$token,$u['id_usuario']]); $msg="Link local: <a href='nueva_password.php?token=$token'>Crear nueva contraseña</a>"; }
 else $msg='No se encontró ese email.';
}
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css?v=10"></head><body class="login-bg"><div class="login-card"><h1>Recuperar contraseña</h1><form method="post"><input name="email" type="email" placeholder="Email" required><button>Generar link</button></form><p><?=$msg?></p><a href="login.php">Volver</a></div></body></html>
