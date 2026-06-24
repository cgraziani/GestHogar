<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

function estado_label($e){
    $map=[
        'administrado'=>'Administrado',
        'realizada'=>'Realizada',
        'no_pudo'=>'No se pudo',
        'no_dado'=>'No administrado',
        'no_realizada'=>'No realizada',
        'pendiente'=>'Pendiente',
        ''=>'Pendiente',
        null=>'Pendiente'
    ];
    return $map[$e] ?? $e;
}
function estado_class($e){
    if($e==='administrado' || $e==='realizada') return 'estado-ok';
    if($e==='no_pudo') return 'estado-alerta';
    if($e==='no_dado' || $e==='no_realizada') return 'estado-problema';
    return 'estado-pendiente';
}

function subir_receta(){
    if(empty($_FILES['foto_receta']['name'])) return null;
    if(!is_dir('../uploads/recetas')) mkdir('../uploads/recetas',0777,true);
    $ext = pathinfo($_FILES['foto_receta']['name'], PATHINFO_EXTENSION);
    $name = 'receta_'.time().'_'.rand(1000,9999).'.'.$ext;
    $dest = '../uploads/recetas/'.$name;
    if(move_uploaded_file($_FILES['foto_receta']['tmp_name'],$dest)) return 'uploads/recetas/'.$name;
    return null;
}
function volver_residente($id){ header('Location: listar.php?residente='.(int)$id); exit; }

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion_medicacion'])){
    $estado = $_POST['accion_medicacion'];
    $motivo = trim($_POST['motivo'] ?? '');
    if(($estado==='no_pudo' || $estado==='no_dado') && $motivo===''){
        $_SESSION['msg']='Para marcar NO PUDE o NO SE DIO, tenés que escribir el motivo.';
        volver_residente($_POST['id_residente'] ?? 0);
    }
    $stmt=$pdo->prepare("INSERT INTO administracion_medicacion(id_plan,id_residente,id_medicamento,id_cuidadora_usuario,fecha_hora,estado_adm,observaciones) VALUES(?,?,?,?,NOW(),?,?)");
    $stmt->execute([$_POST['id_plan'],$_POST['id_residente'],$_POST['id_medicamento'],uid(),$estado,$motivo]);
    $_SESSION['msg']='Registro guardado correctamente. Quedó guardado quién lo registró, fecha y hora.';
    volver_residente($_POST['id_residente']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion_tarea'])){
    $estado = $_POST['accion_tarea'];
    $motivo = trim($_POST['motivo'] ?? '');
    if(($estado==='no_realizada' || $estado==='no_pudo') && $motivo===''){
        $_SESSION['msg']='Para marcar NO PUDE o NO REALIZADA, tenés que escribir el motivo.';
        volver_residente($_POST['id_residente'] ?? 0);
    }
    $stmt=$pdo->prepare("INSERT INTO administracion_tareas(id_tarea,id_residente,id_cuidadora_usuario,fecha_hora,estado_tarea,motivo) VALUES(?,?,?,NOW(),?,?)");
    $stmt->execute([$_POST['id_tarea'],$_POST['id_residente'],uid(),$estado,$motivo]);
    $_SESSION['msg']='Tarea registrada correctamente.';
    volver_residente($_POST['id_residente']);
}
if($_SERVER['REQUEST_METHOD']==='POST' && es_admin()){
    if(isset($_POST['nuevo_medicamento'])){
        $pdo->prepare('INSERT INTO medicamentos(nombre,presentacion,descripcion) VALUES(?,?,?)')->execute([$_POST['nombre'],$_POST['presentacion'],$_POST['descripcion']]);
    }
    if(isset($_POST['nueva_receta'])){
        $foto = subir_receta();
        $fecha_fin = trim($_POST['fecha_fin'] ?? '') ?: null;
        $pdo->prepare('INSERT INTO recetas_medicas(id_residente,diagnostico,indicaciones_generales,fecha_inicio,fecha_fin,foto_receta,creado_por) VALUES(?,?,?,?,?,?,?)')
            ->execute([$_POST['id_residente'],$_POST['diagnostico'],$_POST['indicaciones_generales'],$_POST['fecha_inicio'],$fecha_fin,$foto,uid()]);
        $id_receta = $pdo->lastInsertId();
        $horarios=preg_split('/[,;\n]+/', $_POST['horarios']);
        foreach($horarios as $h){
            $h=trim($h); if($h==='') continue;
            $pdo->prepare('INSERT INTO medicacion_residente(id_receta,id_residente,id_medicamento,dosis,horario,indicacion,fecha_inicio,fecha_fin,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$id_receta,$_POST['id_residente'],$_POST['id_medicamento'],$_POST['dosis'],$h,$_POST['indicacion'],$_POST['fecha_inicio'],$fecha_fin,uid()]);
        }
    }
    if(isset($_POST['nueva_tarea'])){
        $pdo->prepare('INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,created_by) VALUES(?,?,?,?,?)')
            ->execute([$_POST['id_residente'],$_POST['titulo'],$_POST['descripcion'],$_POST['horario'],uid()]);
    }
    header('Location: listar.php'); exit;
}
if(isset($_GET['eliminar']) && es_admin()){ $pdo->prepare('UPDATE medicacion_residente SET eliminado=1 WHERE id_plan=?')->execute([$_GET['eliminar']]); $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('medicacion_residente',?,'eliminado',?)")->execute([$_GET['eliminar'],$_SESSION['usuario']['email']]); header('Location: listar.php'); exit; }
if(isset($_GET['eliminar_receta']) && es_admin()){ $pdo->prepare('UPDATE recetas_medicas SET eliminado=1 WHERE id_receta=?')->execute([$_GET['eliminar_receta']]); $pdo->prepare('UPDATE medicacion_residente SET eliminado=1 WHERE id_receta=?')->execute([$_GET['eliminar_receta']]); $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('recetas_medicas',?,'eliminado',?)")->execute([$_GET['eliminar_receta'],$_SESSION['usuario']['email']]); header('Location: listar.php'); exit; }
if(isset($_GET['eliminar_tarea']) && es_admin()){ $pdo->prepare('UPDATE tareas_residente SET eliminado=1 WHERE id_tarea=?')->execute([$_GET['eliminar_tarea']]); $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('tareas_residente',?,'eliminado',?)")->execute([$_GET['eliminar_tarea'],$_SESSION['usuario']['email']]); header('Location: listar.php'); exit; }

include '../config/menu.php';
$res=$pdo->query("SELECT * FROM residentes WHERE eliminado=0 AND estado='activo' ORDER BY apellido,nombre")->fetchAll();
$meds=$pdo->query("SELECT * FROM medicamentos WHERE eliminado=0 AND estado='activo' ORDER BY nombre")->fetchAll();
$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$debe_elegir = (!es_admin() && $id_residente<=0);
$whereRes = $id_residente ? ' AND mr.id_residente='.$id_residente : '';
$whereRec = $id_residente ? ' AND rec.id_residente='.$id_residente : '';
$whereHist = $id_residente ? ' AND am.id_residente='.$id_residente : '';
$whereTar = $id_residente ? ' AND tr.id_residente='.$id_residente : '';

$rows=[]; $recetas=[]; $hist=[]; $tareas=[];
if(!$debe_elegir){
$rows=$pdo->query("SELECT mr.*,r.nombre rn,r.apellido ra,m.nombre med,m.presentacion,rec.diagnostico,rec.fecha_inicio r_inicio,rec.fecha_fin r_fin,rec.foto_receta,
(SELECT am.estado_adm FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_estado,
(SELECT am.observaciones FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_motivo,
(SELECT am.fecha_hora FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_horario,
(SELECT u.nombre FROM administracion_medicacion am LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_usuario
FROM medicacion_residente mr JOIN residentes r ON r.id_residente=mr.id_residente JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
WHERE mr.eliminado=0 AND mr.estado='activo' AND (mr.fecha_inicio IS NULL OR CURDATE()>=mr.fecha_inicio) AND (mr.fecha_fin IS NULL OR CURDATE()<=mr.fecha_fin) $whereRes ORDER BY r.apellido,mr.horario")->fetchAll();
$recetas=$pdo->query("SELECT rec.*,r.nombre rn,r.apellido ra,u.nombre creador FROM recetas_medicas rec JOIN residentes r ON r.id_residente=rec.id_residente LEFT JOIN usuarios u ON u.id_usuario=rec.creado_por WHERE rec.eliminado=0 $whereRec ORDER BY rec.fecha_inicio DESC")->fetchAll();
$hist=$pdo->query("SELECT am.*,r.nombre rn,r.apellido ra,m.nombre med,m.presentacion,u.nombre cuidadora,mr.dosis,mr.horario,rec.diagnostico FROM administracion_medicacion am JOIN residentes r ON r.id_residente=am.id_residente JOIN medicamentos m ON m.id_medicamento=am.id_medicamento LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario LEFT JOIN medicacion_residente mr ON mr.id_plan=am.id_plan LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta WHERE am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY) $whereHist ORDER BY am.fecha_hora DESC")->fetchAll();
$tareas=$pdo->query("SELECT tr.*,r.nombre rn,r.apellido ra,(SELECT at.estado_tarea FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_estado,(SELECT at.motivo FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_motivo,(SELECT at.fecha_hora FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_horario FROM tareas_residente tr JOIN residentes r ON r.id_residente=tr.id_residente WHERE tr.eliminado=0 AND tr.estado='activo' $whereTar ORDER BY tr.horario,r.apellido")->fetchAll();
}
?>
<h1>Medicación, recetas y tareas</h1>
<?php if(!empty($_SESSION['msg'])): ?><div class="alert"><?=htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']);?></div><?php endif; ?>

<div class="card">
    <h2>1) Seleccionar residente</h2>
    <div class="resident-selector">
        <?php if(es_admin()): ?><a class="resident-option <?=$id_residente==0?'selected':''?>" href="listar.php">Todos</a><?php endif; ?>
        <?php foreach($res as $x):?>
            <a class="resident-option <?=$id_residente==$x['id_residente']?'selected':''?>" href="?residente=<?=$x['id_residente']?>">
                <span class="fake-check"><?=$id_residente==$x['id_residente']?'':''?></span>
                <?=htmlspecialchars($x['nombre'].' '.$x['apellido'])?>
            </a>
        <?php endforeach;?>
    </div>
</div>

<?php if($debe_elegir): ?>
    <div class="card bloqueo-med"><h2>Medicación cerrada</h2></div>
    <?php include '../config/footer.php'; exit; ?>
<?php endif; ?>

<?php if(es_admin()): ?>
<div class="grid"><div class="card"><h3>Crear medicamento <span class="small">solo admin</span></h3><form method="post"><input type="hidden" name="nuevo_medicamento" value="1"><input name="nombre" placeholder="Nombre del medicamento" required><input name="presentacion" placeholder="Presentación: comprimido 50mg"><textarea name="descripcion" placeholder="Descripción"></textarea><button>Guardar medicamento</button></form></div>
<div class="card"><h3>Crear receta / tratamiento con vencimiento</h3><form method="post" enctype="multipart/form-data"><input type="hidden" name="nueva_receta" value="1"><select name="id_residente"><?php foreach($res as $x):?><option value="<?=$x['id_residente']?>"><?=$x['nombre'].' '.$x['apellido']?></option><?php endforeach;?></select><select name="id_medicamento"><?php foreach($meds as $m):?><option value="<?=$m['id_medicamento']?>"><?=$m['nombre']?> - <?=$m['presentacion']?></option><?php endforeach;?></select><input name="diagnostico" placeholder="Motivo/diagnóstico" required><input name="dosis" placeholder="Dosis" required><textarea name="horarios" placeholder="Horarios" required></textarea><textarea name="indicacion" placeholder="Indicación específica del medicamento"></textarea><textarea name="indicaciones_generales" placeholder="Indicaciones generales de la receta"></textarea><label>Inicio</label><input type="date" name="fecha_inicio" value="<?=date('Y-m-d')?>" required><label>Fin / vencimiento (vacío si es permanente)</label><input type="date" name="fecha_fin"><label>Foto de receta</label><input type="file" name="foto_receta" accept="image/*"><button>Guardar receta y horarios</button></form></div>
<div class="card"><h3>Crear tarea para cuidadora</h3><form method="post"><input type="hidden" name="nueva_tarea" value="1"><select name="id_residente"><?php foreach($res as $x):?><option value="<?=$x['id_residente']?>"><?=$x['nombre'].' '.$x['apellido']?></option><?php endforeach;?></select><input name="titulo" placeholder="Tarea: controlar presión, baño, caminata..." required><input type="time" name="horario"><textarea name="descripcion" placeholder="Detalle de la tarea"></textarea><button>Guardar tarea</button></form></div></div>
<?php else: ?><div class="card"><b>Modo cuidadora/asistente:</b> solo podés ver lo asignado y confirmar. El sistema guarda automáticamente tu nombre, fecha y hora.</div><?php endif; ?>

<h2>Recetas / tratamientos</h2><table><tr><th>Residente</th><th>Diagnóstico</th><th>Inicio</th><th>Vencimiento</th><th>Foto</th><th>Estado</th><th>Acciones</th></tr><?php foreach($recetas as $r): $vencida=($r['fecha_fin'] && $r['fecha_fin']<date('Y-m-d'));?><tr><td><?=$r['rn'].' '.$r['ra']?></td><td><?=htmlspecialchars($r['diagnostico'])?><br><span class="small"><?=htmlspecialchars($r['indicaciones_generales'])?></span></td><td><?=$r['fecha_inicio']?></td><td><?=$r['fecha_fin']?:'Permanente'?></td><td><?php if($r['foto_receta']):?><a class="btn blue" href="<?=BASE_URL.'/'.$r['foto_receta']?>" target="_blank">Ver receta</a><?php else:?>-<?php endif;?></td><td><span class="pill estado-<?=$vencida?'baja':'activo'?>"><?=$vencida?'vencida':'vigente'?></span></td><td><?php if(es_admin()):?><a class="btn red" href="?eliminar_receta=<?=$r['id_receta']?>">Papelera</a><?php endif;?></td></tr><?php endforeach;?></table>

<h2>Medicamentos vigentes por horario</h2>
<table><tr><th>Residente</th><th>Tratamiento</th><th>Medicamento</th><th>Dosis</th><th>Horario</th><th>Vence</th><th>Último registro</th><th class="actions-col">Registrar</th></tr><?php foreach($rows as $r):?><tr><td><?=$r['rn'].' '.$r['ra']?></td><td><?=htmlspecialchars($r['diagnostico']?:'-')?></td><td><?=$r['med']?> <span class="small"><?=$r['presentacion']?></span></td><td><?=$r['dosis']?></td><td><b><?=$r['horario']?></b></td><td><?=$r['fecha_fin']?:'Permanente'?></td><td><div class="estado-cuadro <?=estado_class($r['ultimo_estado'])?>"><?=estado_label($r['ultimo_estado'])?></div><span class="small"><?=$r['ultimo_horario']?> <?=$r['ultimo_usuario']?(' · '.$r['ultimo_usuario']):''?><br><?=$r['ultimo_motivo']?></span></td><td>
<form method="post" class="accion-unica">
<input type="hidden" name="id_plan" value="<?=$r['id_plan']?>"><input type="hidden" name="id_residente" value="<?=$r['id_residente']?>"><input type="hidden" name="id_medicamento" value="<?=$r['id_medicamento']?>">
<select name="accion_medicacion" class="selector-accion" required><option value="">Elegir estado</option><option value="administrado">Administrado / entregado</option><option value="no_pudo">No pude administrar</option><option value="no_dado">No administrado</option></select>
<textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio si no se administró"></textarea>
<button>Guardar</button>
</form>
<?php if(es_admin()):?><a class="btn gray" href="?eliminar=<?=$r['id_plan']?>">Papelera</a><?php endif;?></td></tr><?php endforeach;?></table>

<h2>Historial de medicación de los últimos 7 días</h2><table><tr><th>Fecha y hora</th><th>Residente</th><th>Medicamento</th><th>Dosis</th><th>Tratamiento</th><th>Estado</th><th>Administró / registró</th><th>Motivo</th></tr><?php foreach($hist as $h):?><tr><td><?=$h['fecha_hora']?></td><td><?=$h['rn'].' '.$h['ra']?></td><td><?=$h['med']?> <?=$h['presentacion']?></td><td><?=$h['dosis']?></td><td><?=htmlspecialchars($h['diagnostico']?:'-')?></td><td><div class="estado-cuadro <?=estado_class($h['estado_adm'])?>"><?=estado_label($h['estado_adm'])?></div></td><td><?=$h['cuidadora']?:'-'?></td><td><?=htmlspecialchars($h['observaciones'])?></td></tr><?php endforeach;?></table>

<h2>Tareas de cuidado</h2><table><tr><th>Residente</th><th>Tarea</th><th>Horario</th><th>Detalle</th><th>Último registro</th><th class="actions-col">Registrar</th></tr><?php foreach($tareas as $t):?><tr><td><?=$t['rn'].' '.$t['ra']?></td><td><?=$t['titulo']?></td><td><b><?=$t['horario']?></b></td><td><?=$t['descripcion']?></td><td><div class="estado-cuadro <?=estado_class($t['ultimo_estado'])?>"><?=estado_label($t['ultimo_estado'])?></div><span class="small"><?=$t['ultimo_horario']?> <?=$t['ultimo_motivo']?></span></td><td>
<form method="post" class="accion-unica"><input type="hidden" name="id_tarea" value="<?=$t['id_tarea']?>"><input type="hidden" name="id_residente" value="<?=$t['id_residente']?>">
<select name="accion_tarea" class="selector-accion" required><option value="">Elegir estado</option><option value="realizada">Realizada</option><option value="no_pudo">No pude realizarla</option><option value="no_realizada">No realizada</option></select>
<textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio si no se realizó"></textarea>
<button>Guardar</button>
</form>
<?php if(es_admin()):?><a class="btn gray" href="?eliminar_tarea=<?=$t['id_tarea']?>">Papelera</a><?php endif;?></td></tr><?php endforeach;?></table>

<script>
document.querySelectorAll('.selector-accion').forEach(function(sel){
    function revisar(){
        var form = sel.closest('form');
        var motivo = form.querySelector('.motivo-js');
        var v = sel.value;
        if(v==='no_pudo' || v==='no_dado' || v==='no_realizada'){
            motivo.style.display='block'; motivo.required=true;
        } else { motivo.style.display='none'; motivo.required=false; motivo.value=''; }
    }
    sel.addEventListener('change', revisar); revisar();
});
</script>
<?php include '../config/footer.php'; ?>
