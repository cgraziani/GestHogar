<?php
$sql = file_get_contents(__DIR__.'/database/gesthogar_db.sql');
try{
 $pdo = new PDO('mysql:host=localhost;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $pdo->exec($sql);
 echo '<h1>Base instalada correctamente</h1><p>Base: <b>gesthogar_db</b></p><p><a href="auth/login.php">Ir al login</a></p><p>Usuarios: admin@gesthogar.com / ana@gesthogar.com / sofia@gesthogar.com / laura@gesthogar.com / daniela@gesthogar.com / marta@gesthogar.com</p><p>Contraseña para todos: <b>Admin123</b></p>';
}catch(Exception $e){ echo '<h1>Error instalando</h1><pre>'.$e->getMessage().'</pre>'; }
?>
