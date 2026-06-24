<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if(isset($_GET['accion']) && isset($_GET['id'])){
    $estado = $_GET['accion'];
    if(in_array($estado, ['activo','pausado','baja'])){
        $pdo->prepare('UPDATE cuidadoras SET estado=? WHERE id_cuidadora=?')->execute([$estado, $_GET['id']]);
        $pdo->prepare('UPDATE usuarios u JOIN cuidadoras c ON c.id_usuario=u.id_usuario SET u.estado=? WHERE c.id_cuidadora=?')->execute([$estado, $_GET['id']]);
    }
    header('Location: listar.php'); exit;
}
if(isset($_GET['eliminar'])){
    $pdo->prepare('UPDATE cuidadoras SET eliminado=1 WHERE id_cuidadora=?')->execute([$_GET['eliminar']]);
    $pdo->prepare('UPDATE usuarios u JOIN cuidadoras c ON c.id_usuario=u.id_usuario SET u.eliminado=1 WHERE c.id_cuidadora=?')->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('cuidadoras',?,'eliminado',?)")->execute([$_GET['eliminar'], $_SESSION['usuario']['email']]);
    header('Location: listar.php'); exit;
}

include '../config/menu.php';
$rows=$pdo->query('SELECT c.*,u.email,u.rol,u.color,u.estado estado_usuario FROM cuidadoras c LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.eliminado=0 ORDER BY c.apellido,c.nombre')->fetchAll();
?>
<h1>Cuidadoras / Personal de cuidado</h1>
<div class="card">
    <a class="btn" href="crear.php">Crear datos de cuidadora</a>
</div>
<table>
<tr><th>Color</th><th>Nombre</th><th>DNI</th><th>Teléfono</th><th>Turno</th><th>Email usuario</th><th>Estado</th><th>Acciones</th></tr>
<?php foreach($rows as $r):?>
<tr>
<td><span class="color-dot" style="--c:<?=$r['color']?:'#087f7a'?>"></span></td>
<td><b><?=htmlspecialchars($r['nombre'].' '.$r['apellido'])?></b><br><span class="small">Rol: <?=htmlspecialchars($r['rol']?:'cuidadora')?></span></td>
<td><?=htmlspecialchars($r['dni'])?></td>
<td><?=htmlspecialchars($r['telefono'])?></td>
<td><?=htmlspecialchars($r['tipo_turno'])?></td>
<td><?=htmlspecialchars($r['email'])?></td>
<td><span class="pill estado-<?=$r['estado']?>"><?=$r['estado']?></span></td>
<td>
    <a class="btn blue" href="crear.php?id=<?=$r['id_cuidadora']?>">Modificar</a>
    <a class="btn green" href="?accion=activo&id=<?=$r['id_cuidadora']?>">Activar</a>
    <a class="btn orange" href="?accion=pausado&id=<?=$r['id_cuidadora']?>">Pausar</a>
    <a class="btn gray" href="?accion=baja&id=<?=$r['id_cuidadora']?>">Baja</a>
    <a class="btn red" href="?eliminar=<?=$r['id_cuidadora']?>" onclick="return confirm('¿Enviar cuidadora a papelera?')">Papelera</a>
</td>
</tr>
<?php endforeach;?>
</table>
<?php include '../config/footer.php'; ?>
