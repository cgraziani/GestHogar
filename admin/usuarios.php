<?php require_once '../config/session.php'; solo_admin(); require_once '../config/db.php';
if(isset($_GET['accion'])){ $pdo->prepare('UPDATE usuarios SET estado=? WHERE id_usuario=?')->execute([$_GET['accion'],$_GET['id']]); header('Location: usuarios.php'); exit; }
if(isset($_GET['eliminar'])){ $pdo->prepare('UPDATE usuarios SET eliminado=1 WHERE id_usuario=?')->execute([$_GET['eliminar']]); $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('usuarios',?,'eliminado',?)")->execute([$_GET['eliminar'],$_SESSION['usuario']['email']]); header('Location: usuarios.php'); exit; }
$edit=['id_usuario'=>'','nombre'=>'','email'=>'','rol'=>'cuidadora','estado'=>'activo','color'=>'#087f7a'];
if(isset($_GET['editar'])){ $st=$pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?'); $st->execute([$_GET['editar']]); $edit=$st->fetch(); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 $color=$_POST['color'] ?: '#087f7a';
 if(!empty($_POST['id_usuario'])){
    if(!empty($_POST['password'])){
        $hash=password_hash($_POST['password'],PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,password=?,rol=?,estado=?,color=? WHERE id_usuario=?')->execute([$_POST['nombre'],$_POST['email'],$hash,$_POST['rol'],$_POST['estado'],$color,$_POST['id_usuario']]);
    } else {
        $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,rol=?,estado=?,color=? WHERE id_usuario=?')->execute([$_POST['nombre'],$_POST['email'],$_POST['rol'],$_POST['estado'],$color,$_POST['id_usuario']]);
    }
 } else {
    $hash=password_hash($_POST['password'],PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO usuarios(nombre,email,password,rol,estado,color) VALUES(?,?,?,?,?,?)')->execute([$_POST['nombre'],$_POST['email'],$hash,$_POST['rol'],$_POST['estado'],$color]);
 }
 header('Location: usuarios.php'); exit;
}
include '../config/menu.php';
if(isset($_GET['ver'])){ $u=$pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?'); $u->execute([$_GET['ver']]); $u=$u->fetch();
 $turnos=$pdo->prepare('SELECT * FROM turnos_programados WHERE id_cuidadora_usuario=? AND eliminado=0 ORDER BY fecha,hora_inicio LIMIT 20'); $turnos->execute([$u['id_usuario']]); $turnos=$turnos->fetchAll(); ?>
<h1>Detalle de usuario</h1>
<div class="card detail-grid">
 <div class="info-box"><b>Nombre</b><br><?=htmlspecialchars($u['nombre'])?></div>
 <div class="info-box"><b>Email</b><br><?=htmlspecialchars($u['email'])?></div>
 <div class="info-box"><b>Rol</b><br><?=htmlspecialchars($u['rol'])?></div>
 <div class="info-box"><b>Estado</b><br><span class="pill estado-<?=$u['estado']?>"><?=$u['estado']?></span></div>
 <div class="info-box"><b>Color calendario</b><br><span style="display:inline-block;width:35px;height:20px;background:<?=$u['color']?>;border-radius:5px"></span> <?=$u['color']?></div>
 <div class="info-box"><b>Creado</b><br><?=$u['created_at']?></div>
</div>
<h2>Próximos turnos / horarios cargados</h2><table><tr><th>Fecha</th><th>Horario</th><th>Nota</th></tr><?php foreach($turnos as $t):?><tr><td><?=date('d/m/Y',strtotime($t['fecha']))?></td><td><?=$t['hora_inicio']?> a <?=$t['hora_fin']?></td><td><?=htmlspecialchars($t['nota'])?></td></tr><?php endforeach;?></table><br>
<a class="btn" href="?editar=<?=$u['id_usuario']?>">Modificar usuario / color</a> <a class="btn gray" href="usuarios.php">Volver</a>
<?php include '../config/footer.php'; exit; }
$rows=$pdo->query('SELECT * FROM usuarios WHERE eliminado=0 ORDER BY rol,nombre')->fetchAll(); ?>
<h1>Usuarios</h1>
<div class="card"><h3><?= $edit['id_usuario']?'Modificar usuario':'Crear usuario' ?></h3><form method="post" class="form-row"><input type="hidden" name="id_usuario" value="<?=$edit['id_usuario']?>"><input name="nombre" placeholder="Nombre" value="<?=htmlspecialchars($edit['nombre'])?>" required><input name="email" type="email" placeholder="Email" value="<?=htmlspecialchars($edit['email'])?>" required><input name="password" type="password" placeholder="Contraseña <?= $edit['id_usuario']?'(dejar vacía para no cambiar)':'' ?>" <?= $edit['id_usuario']?'':'required' ?>><select name="rol"><option value="cuidadora" <?=$edit['rol']=='cuidadora'?'selected':''?>>Cuidadora</option><option value="cocinera" <?=$edit['rol']=='cocinera'?'selected':''?>>Cocinera</option><option value="asistente" <?=$edit['rol']=='asistente'?'selected':''?>>Asistente</option><option value="admin" <?=$edit['rol']=='admin'?'selected':''?>>Administradora</option></select><select name="estado"><option value="activo" <?=$edit['estado']=='activo'?'selected':''?>>activo</option><option value="pausado" <?=$edit['estado']=='pausado'?'selected':''?>>pausado</option><option value="baja" <?=$edit['estado']=='baja'?'selected':''?>>baja</option></select><label>Color para calendario <input name="color" type="color" value="<?=$edit['color']?>"></label><button>Guardar</button><?php if($edit['id_usuario']):?><a class="btn gray" href="usuarios.php">Cancelar</a><?php endif;?></form></div>
<table><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Color</th><th>Estado</th><th>Acciones</th></tr><?php foreach($rows as $r):?><tr><td><?=htmlspecialchars($r['nombre'])?></td><td><?=htmlspecialchars($r['email'])?></td><td><?=$r['rol']?></td><td><span style="display:inline-block;width:35px;height:20px;background:<?=$r['color']?>;border-radius:5px"></span></td><td><span class="pill estado-<?=$r['estado']?>"><?=$r['estado']?></span></td><td><a class="btn blue" href="?ver=<?=$r['id_usuario']?>">Ver todo</a><a class="btn" href="?editar=<?=$r['id_usuario']?>">Modificar</a><a class="btn green" href="?accion=activo&id=<?=$r['id_usuario']?>">Activar</a><a class="btn orange" href="?accion=pausado&id=<?=$r['id_usuario']?>">Pausar</a><a class="btn gray" href="?accion=baja&id=<?=$r['id_usuario']?>">Baja</a><a class="btn red" href="?eliminar=<?=$r['id_usuario']?>">Papelera</a></td></tr><?php endforeach;?></table>
<?php include '../config/footer.php'; ?>
