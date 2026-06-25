<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit = null;
if($id){
    $st=$pdo->prepare('SELECT * FROM cuidadoras WHERE id_cuidadora=?');
    $st->execute([$id]);
    $edit=$st->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!empty($_POST['id_cuidadora'])){
        $pdo->prepare('UPDATE cuidadoras SET id_usuario=?, nombre=?, apellido=?, dni=?, telefono=?, tipo_turno=?, estado=? WHERE id_cuidadora=?')
            ->execute([$_POST['id_usuario'],$_POST['nombre'],$_POST['apellido'],$_POST['dni'],$_POST['telefono'],$_POST['tipo_turno'],$_POST['estado'],$_POST['id_cuidadora']]);
    } else {
        $pdo->prepare('INSERT INTO cuidadoras(id_usuario,nombre,apellido,dni,telefono,tipo_turno,estado) VALUES(?,?,?,?,?,?,?)')
            ->execute([$_POST['id_usuario'],$_POST['nombre'],$_POST['apellido'],$_POST['dni'],$_POST['telefono'],$_POST['tipo_turno'],$_POST['estado']]);
    }
    header('Location: listar.php'); exit;
}
$usuarios=$pdo->query("SELECT * FROM usuarios WHERE rol IN ('cuidadora','asistente') AND eliminado=0 ORDER BY rol,nombre")->fetchAll();
include '../config/menu.php';
?>
<h1><?= $edit ? 'Modificar cuidadora' : 'Crear cuidadora' ?></h1>
<div class="card">
<form method="post">
<input type="hidden" name="id_cuidadora" value="<?=$edit['id_cuidadora'] ?? ''?>">
<label>Usuario del sistema</label>
<select name="id_usuario" required>
<?php foreach($usuarios as $u):?>
<option value="<?=$u['id_usuario']?>" <?=($edit && $edit['id_usuario']==$u['id_usuario'])?'selected':''?>><?=$u['nombre']?> - <?=$u['email']?> (<?=$u['rol']?>)</option>
<?php endforeach;?>
</select>
<label>Nombre</label><input name="nombre" value="<?=htmlspecialchars($edit['nombre'] ?? '')?>" placeholder="Nombre" required>
<label>Apellido</label><input name="apellido" value="<?=htmlspecialchars($edit['apellido'] ?? '')?>" placeholder="Apellido" required>
<label>DNI</label><input name="dni" value="<?=htmlspecialchars($edit['dni'] ?? '')?>" placeholder="DNI">
<label>Teléfono</label><input name="telefono" value="<?=htmlspecialchars($edit['telefono'] ?? '')?>" placeholder="Teléfono">
<label>Tipo de turno</label>
<!-- Dropdown con opciones predefinidas en vez de campo libre.
     El Hogar Ñongo maneja turnos de 24/48 horas principalmente.
     Se mantiene la opción "Otro" para casos especiales. -->
<select name="tipo_turno">
    <option value="" <?= (!$edit || $edit['tipo_turno']=='')?'selected':'' ?>>Sin especificar</option>
    <option value="24/48" <?= ($edit && $edit['tipo_turno']==='24/48')?'selected':'' ?>>24/48 horas</option>
    <option value="12hs Diurno" <?= ($edit && $edit['tipo_turno']==='12hs Diurno')?'selected':'' ?>>12hs Diurno</option>
    <option value="24hs" <?= ($edit && $edit['tipo_turno']==='24hs')?'selected':'' ?>>24hs</option>
    <option value="Refuerzo mañana" <?= ($edit && $edit['tipo_turno']==='Refuerzo mañana')?'selected':'' ?>>Refuerzo mañana</option>
    <option value="Refuerzo tarde" <?= ($edit && $edit['tipo_turno']==='Refuerzo tarde')?'selected':'' ?>>Refuerzo tarde</option>
</select>
<label>Estado</label><select name="estado"><option value="activo" <?=($edit && $edit['estado']=='activo')?'selected':''?>>Activo</option><option value="pausado" <?=($edit && $edit['estado']=='pausado')?'selected':''?>>Pausado</option><option value="baja" <?=($edit && $edit['estado']=='baja')?'selected':''?>>Baja</option></select>
<button>Guardar</button> <a class="btn gray" href="listar.php">Volver</a>
</form>
</div>
<?php include '../config/footer.php'; ?>
