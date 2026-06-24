<?php require_once 'config/session.php'; require_login(); require_once 'config/db.php'; include 'config/menu.php';
$usuarios=$pdo->query("SELECT id_usuario,nombre,email,rol,estado,color FROM usuarios WHERE eliminado=0 ORDER BY rol,nombre")->fetchAll();
$residentes=$pdo->query("SELECT id_residente,nombre,apellido,dni,contacto_familiar,telefono_familiar,estado,foto FROM residentes WHERE eliminado=0 ORDER BY apellido,nombre")->fetchAll();
$cuidadoras=$pdo->query("SELECT c.*,u.email,u.estado estado_usuario,u.color FROM cuidadoras c LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.eliminado=0 ORDER BY c.apellido,c.nombre")->fetchAll();
$planes=$pdo->query("SELECT mr.*,r.nombre rn,r.apellido ra,m.nombre med,m.presentacion FROM medicacion_residente mr JOIN residentes r ON r.id_residente=mr.id_residente JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento WHERE mr.eliminado=0 ORDER BY mr.horario")->fetchAll();
$hoy=date('Y-m-d');
$turnos=$pdo->prepare("SELECT tp.*,u.nombre cuidadora,u.color,u.rol FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario WHERE tp.eliminado=0 AND tp.fecha>=? ORDER BY tp.fecha,tp.hora_inicio LIMIT 12");
$turnos->execute([$hoy]); $turnos=$turnos->fetchAll();
?>
<h1>Panel principal</h1>
<?php if(es_admin()): ?>
<div class="grid"><div class="card"><div class="stat"><?=count($residentes)?></div><b>Residentes</b></div><div class="card"><div class="stat"><?=count($usuarios)?></div><b>Usuarios</b></div><div class="card"><div class="stat"><?=count($cuidadoras)?></div><b>Cuidadoras</b></div><div class="card"><div class="stat"><?=count($planes)?></div><b>Horarios de medicación</b></div></div>
<div class="admin-columns">
 <div class="card mini-list"><h2>Residentes</h2><?php foreach($residentes as $r): ?><div class="rowline"><?php if($r['foto']):?><img class="mini" src="<?=BASE_URL.'/'.$r['foto']?>"><?php endif;?><div><b><?=$r['nombre'].' '.$r['apellido']?></b><br><span class="small">DNI <?=$r['dni']?> · <?=$r['estado']?></span><br><span class="small">Familiar: <?=$r['contacto_familiar']?> <?=$r['telefono_familiar']?></span><br><a href="residentes/listar.php?ver=<?=$r['id_residente']?>">Ver ficha completa</a></div></div><?php endforeach;?></div>
 <div class="card mini-list"><h2>Usuarios / Cuidadoras</h2><?php foreach($usuarios as $u): ?><div class="rowline"><span style="width:18px;height:18px;background:<?=$u['color']?>;border-radius:50%;display:inline-block"></span><div><b><?=$u['nombre']?></b> <span class="badge"><?=$u['rol']?></span><br><span class="small"><?=$u['email']?> · <?=$u['estado']?></span><br><a href="admin/usuarios.php?ver=<?=$u['id_usuario']?>">Ver datos</a></div></div><?php endforeach;?></div>
 <div class="card mini-list"><h2>Medicación por horario</h2><?php foreach($planes as $p): ?><div class="rowline"><div><b><?=$p['rn'].' '.$p['ra']?></b><br><span class="small"><?=$p['med']?> <?=$p['presentacion']?> · <?=$p['dosis']?> · <?=$p['horario']?></span></div></div><?php endforeach;?></div>
</div>
<div class="card"><h2>Próximos turnos</h2><table><tr><th>Fecha</th><th>Personal</th><th>Horario</th><th>Nota</th></tr><?php foreach($turnos as $t):?><tr><td><?=$t['fecha']?></td><td><span style="display:inline-block;width:18px;height:18px;background:<?=$t['color']?>;border-radius:50%"></span> <?=$t['cuidadora']?> <span class="badge"><?=$t['rol']?></span></td><td><?=$t['hora_inicio']?> a <?=$t['hora_fin']?></td><td><?=$t['nota']?></td></tr><?php endforeach;?></table><p><a class="btn" href="turnos/calendario.php">Abrir calendario grande</a></p></div>
<?php elseif(es_cuidadora()): ?>
<div class="card"><h2>Panel de cuidadora</h2><p><a class="btn" href="medicacion/listar.php">Ver medicación y tareas</a> <a class="btn gray" href="turnos/calendario.php">Ver calendario</a></p></div>
<?php elseif(es_cocinera()): ?>
<div class="card"><h2>Panel de cocina</h2><p><a class="btn" href="cocina/listar.php">Cargar recetas y gastos</a> <a class="btn gray" href="turnos/calendario.php">Ver calendario</a></p></div>
<?php elseif(es_asistente()): ?>
<div class="card"><h2>Panel de asistente</h2><p><a class="btn gray" href="turnos/calendario.php">Ver calendario</a></p></div>
<?php endif; ?>
<?php include 'config/footer.php'; ?>
