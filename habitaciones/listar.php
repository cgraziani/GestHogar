<?php require_once '../config/session.php'; solo_admin(); require_once '../config/db.php';
$id=$_GET['id']??0;
$h=['numero'=>'','sector'=>'','piso'=>'','cama'=>'','estado'=>'disponible','observaciones'=>''];
if($id){$st=$pdo->prepare('SELECT * FROM habitaciones WHERE id_habitacion=?');$st->execute([$id]);$h=$st->fetch();}
if(isset($_GET['eliminar'])){ $pdo->prepare('UPDATE habitaciones SET eliminado=1 WHERE id_habitacion=?')->execute([$_GET['eliminar']]); header('Location: listar.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){
    $data=[$_POST['numero'],$_POST['sector'],$_POST['piso'],$_POST['cama'],$_POST['estado'],$_POST['observaciones']];
    if($_POST['id']) $pdo->prepare('UPDATE habitaciones SET numero=?,sector=?,piso=?,cama=?,estado=?,observaciones=? WHERE id_habitacion=?')->execute([...$data,$_POST['id']]);
    else $pdo->prepare('INSERT INTO habitaciones(numero,sector,piso,cama,estado,observaciones) VALUES(?,?,?,?,?,?)')->execute($data);
    header('Location: listar.php'); exit;
}
include '../config/menu.php';
$rows=$pdo->query("SELECT h.*, CONCAT(r.nombre,' ',r.apellido) residente FROM habitaciones h LEFT JOIN residentes r ON r.id_habitacion=h.id_habitacion AND r.eliminado=0 WHERE h.eliminado=0 ORDER BY h.numero")->fetchAll();
?>
<h1>Habitaciones</h1>
<div class="card"><h2><?= $id?'Modificar habitación':'Crear habitación' ?></h2><form method="post" class="form-row"><input type="hidden" name="id" value="<?=$id?>"><input name="numero" placeholder="Número" value="<?=htmlspecialchars($h['numero'])?>" required><input name="sector" placeholder="Sector" value="<?=htmlspecialchars($h['sector'])?>"><input name="piso" placeholder="Piso" value="<?=htmlspecialchars($h['piso'])?>"><input name="cama" placeholder="Cama" value="<?=htmlspecialchars($h['cama'])?>"><select name="estado"><option value="disponible" <?=$h['estado']=='disponible'?'selected':''?>>disponible</option><option value="ocupada" <?=$h['estado']=='ocupada'?'selected':''?>>ocupada</option><option value="mantenimiento" <?=$h['estado']=='mantenimiento'?'selected':''?>>mantenimiento</option></select><input name="observaciones" placeholder="Observaciones" value="<?=htmlspecialchars($h['observaciones'])?>"><button>Guardar</button></form></div>
<table><tr><th>Habitación</th><th>Sector/Piso/Cama</th><th>Residente asignado</th><th>Estado</th><th>Acciones</th></tr><?php foreach($rows as $r):?><tr><td><b><?=$r['numero']?></b></td><td><?=$r['sector']?> · <?=$r['piso']?> · <?=$r['cama']?><br><span class="small"><?=htmlspecialchars($r['observaciones'])?></span></td><td><?=htmlspecialchars($r['residente'] ?: 'Sin residente')?></td><td><span class="pill estado-<?=$r['estado']=='disponible'?'activo':($r['estado']=='mantenimiento'?'pausado':'baja')?>"><?=$r['estado']?></span></td><td><a class="btn" href="?id=<?=$r['id_habitacion']?>">Modificar</a><a class="btn red" href="?eliminar=<?=$r['id_habitacion']?>">Papelera</a></td></tr><?php endforeach;?></table>
<?php include '../config/footer.php'; ?>
