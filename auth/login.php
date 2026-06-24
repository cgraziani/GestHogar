<?php require_once '../config/db.php'; require_once '../config/app.php'; session_start();
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
 $st=$pdo->prepare("SELECT * FROM usuarios WHERE email=? AND estado='activo' AND eliminado=0"); $st->execute([$email]); $u=$st->fetch();
 if($u && password_verify($pass,$u['password'])){ $_SESSION['usuario']=$u; header('Location: '.BASE_URL.'/index.php'); exit; }
 else $error='Usuario o contraseña incorrectos, o usuario pausado/de baja.';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css?v=10"><title>Login</title></head><body class="login-bg"><div class="login-card"><h1>Hogar Nongo</h1><p>Ingreso al sistema</p><?php if($error):?><div class="alert"><?=$error?></div><?php endif;?><form method="post"><label>Email</label><input name="email" type="email" required><label>Contraseña</label><input name="password" type="password" required><button>Entrar</button></form><p><a href="recuperar.php">Recuperar contraseña</a></p></div></body></html>
