<?php
require_once '../config/session.php';
require_login();
require_once '../config/db.php';

function back_cal(){ header('Location: calendario.php'); exit; }
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

if($_SERVER['REQUEST_METHOD']==='POST' && es_admin()){
    $accion=$_POST['accion'] ?? 'crear';
    if($accion==='crear'){
        $fechas=$_POST['fechas'] ?? [];
        if(empty($fechas) && !empty($_POST['fecha'])) $fechas=[$_POST['fecha']];
        foreach($fechas as $f){
            if(!$f) continue;
            $pdo->prepare('INSERT INTO turnos_programados(id_cuidadora_usuario,fecha,hora_inicio,hora_fin,nota,created_by) VALUES(?,?,?,?,?,?)')
                ->execute([$_POST['id_personal'],$f,$_POST['hora_inicio'],$_POST['hora_fin'],$_POST['nota'],uid()]);
        }
        back_cal();
    }
    if($accion==='editar'){
        $pdo->prepare('UPDATE turnos_programados SET id_cuidadora_usuario=?, fecha=?, hora_inicio=?, hora_fin=?, nota=? WHERE id_turno_prog=?')
            ->execute([$_POST['id_personal'],$_POST['fecha'],$_POST['hora_inicio'],$_POST['hora_fin'],$_POST['nota'],$_POST['id_turno_prog']]);
        back_cal();
    }
}
if(isset($_GET['eliminar']) && es_admin()){
    $pdo->prepare('UPDATE turnos_programados SET eliminado=1 WHERE id_turno_prog=?')->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('turnos_programados',?,'eliminado',?)")
        ->execute([$_GET['eliminar'],$_SESSION['usuario']['email']]);
    back_cal();
}

include '../config/menu.php';

if(es_admin()){
    $personal=$pdo->query("SELECT id_usuario,nombre,email,rol,color FROM usuarios WHERE rol IN ('cuidadora','cocinera','asistente') AND eliminado=0 AND estado='activo' ORDER BY FIELD(rol,'cuidadora','asistente','cocinera'),nombre")->fetchAll();
} else {
    $stPersonal=$pdo->prepare("SELECT id_usuario,nombre,email,rol,color FROM usuarios WHERE id_usuario=? AND eliminado=0 AND estado='activo'");
    $stPersonal->execute([uid()]);
    $personal=$stPersonal->fetchAll();
}
$base = new DateTime(date('Y-m-d'));
$inicio = clone $base; $inicio->modify('monday this week')->modify('-7 days');
$fin = clone $inicio; $fin->modify('+20 days');
$inicioConsulta = clone $inicio; $inicioConsulta->modify('-1 day');

$filtroPersonal = es_admin() ? '' : ' AND tp.id_cuidadora_usuario='.intval(uid());
$stmt=$pdo->prepare("SELECT tp.*,u.nombre personal,u.email,u.color,u.rol FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario WHERE tp.eliminado=0 AND tp.fecha BETWEEN ? AND ? $filtroPersonal ORDER BY tp.fecha,tp.hora_inicio,u.nombre");
$stmt->execute([$inicioConsulta->format('Y-m-d'),$fin->format('Y-m-d')]);
$rows=$stmt->fetchAll();

$by=[];
foreach($rows as $r){
    $startMin = intval(substr($r['hora_inicio'],0,2))*60 + intval(substr($r['hora_inicio'],3,2));
    $endMin = intval(substr($r['hora_fin'],0,2))*60 + intval(substr($r['hora_fin'],3,2));
    if($endMin == $startMin){ $endMin = $startMin + 1440; }
    elseif($endMin < $startMin){ $endMin += 1440; }

    $startDate = new DateTime($r['fecha']);
    for($m=$startMin; $m<$endMin; $m+=60){
        $dayOffset = intdiv($m,1440);
        $hour = intdiv($m % 1440,60);
        $dateObj = clone $startDate; $dateObj->modify("+$dayOffset days");
        $dateKey = $dateObj->format('Y-m-d');
        if($dateKey < $inicio->format('Y-m-d') || $dateKey > $fin->format('Y-m-d')) continue;
        $r['bloque_hora'] = $hour;
        $r['continua'] = ($m > $startMin);
        $by[$dateKey][$hour][] = $r;
    }
}

$dias=['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$editar=null;
if(isset($_GET['editar']) && es_admin()){
    $st=$pdo->prepare('SELECT * FROM turnos_programados WHERE id_turno_prog=?');
    $st->execute([$_GET['editar']]);
    $editar=$st->fetch();
}
$diaSel=$_GET['dia'] ?? date('Y-m-d');
$stDia=$pdo->prepare("SELECT tp.*,u.nombre personal,u.color,u.rol FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario WHERE tp.eliminado=0 AND (tp.fecha=? OR (tp.fecha=DATE_SUB(?, INTERVAL 1 DAY) AND tp.hora_fin <= tp.hora_inicio)) $filtroPersonal ORDER BY tp.fecha,tp.hora_inicio");
$stDia->execute([$diaSel,$diaSel]); $turnosDia=$stDia->fetchAll();
?>
<h1>Calendario de turnos</h1>
<div class="legend">
<?php foreach($personal as $p):?>
    <span style="--c:<?=h($p['color'])?>"><?=h($p['nombre'])?> <small>(<?=h($p['rol'])?>)</small></span>
<?php endforeach;?>
</div>
<br>

<?php if(es_admin()): ?>
<div class="card">
    <h2><?= $editar ? 'Modificar turno' : 'Cargar turno en uno o varios días' ?></h2>
    <form method="post">
        <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
        <?php if($editar): ?><input type="hidden" name="id_turno_prog" value="<?=$editar['id_turno_prog']?>"><?php endif; ?>
        <div class="form-row">
            <select name="id_personal" required>
                <option value="">Seleccionar personal</option>
                <?php foreach($personal as $p):?>
                    <option value="<?=$p['id_usuario']?>" <?=($editar && $editar['id_cuidadora_usuario']==$p['id_usuario'])?'selected':''?>><?=h($p['nombre'])?> - <?=h($p['rol'])?> - <?=h($p['email'])?></option>
                <?php endforeach;?>
            </select>
            <input type="time" name="hora_inicio" value="<?=$editar['hora_inicio']??''?>" required>
            <input type="time" name="hora_fin" value="<?=$editar['hora_fin']??''?>" required>
            <input name="nota" placeholder="Nota del turno" value="<?=h($editar['nota']??'')?>">
        </div>
        <?php if($editar): ?>
            <label>Fecha</label><input type="date" name="fecha" value="<?=$editar['fecha']?>" required>
        <?php else: ?>
            <label><b>Seleccionar días</b></label>
            <div class="multi-days">
                <?php for($i=0;$i<21;$i++): $d=(clone $inicio)->modify("+$i days"); ?>
                    <label><input type="checkbox" name="fechas[]" value="<?=$d->format('Y-m-d')?>"> <?=$dias[$i%7]?><br><small><?=$d->format('d/m')?></small></label>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <button><?= $editar ? 'Guardar modificación' : 'Guardar turnos' ?></button>
        <?php if($editar): ?><a class="btn gray" href="calendario.php">Cancelar</a><?php endif; ?>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Detalle del día <?=date('d/m/Y', strtotime($diaSel))?></h2>
    <div class="day-detail-list">
    <?php foreach($turnosDia as $t): ?>
        <div class="turno-chip" style="--c:<?=h($t['color'])?>">
            <b><?=h($t['personal'])?></b> <span class="badge"><?=h($t['rol'])?></span><br>
            <?=h($t['hora_inicio'])?> a <?=h($t['hora_fin'])?> · <?=h($t['nota'])?><br>
            <?php if(es_admin()): ?>
                <a class="btn blue" href="?editar=<?=$t['id_turno_prog']?>&dia=<?=$diaSel?>">Modificar</a>
                <a class="btn red" href="?eliminar=<?=$t['id_turno_prog']?>" onclick="return confirm('¿Quitar este turno?')">Quitar</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<?php for($w=0;$w<3;$w++): $weekStart=(clone $inicio)->modify('+'.($w*7).' days'); $weekEnd=(clone $weekStart)->modify('+6 days'); ?>
<div class="card calendar-wrap">
    <div class="calendar-head">
        <h2><?= $w==0?'Semana anterior':($w==1?'Semana actual':'Semana próxima') ?>: <?=$weekStart->format('d/m/Y')?> al <?=$weekEnd->format('d/m/Y')?></h2>
    </div>
    <div class="schedule-grid">
        <div class="schedule-cell schedule-time schedule-title">Hora</div>
        <?php for($i=0;$i<7;$i++): $d=(clone $weekStart)->modify("+$i days"); ?>
            <a class="schedule-cell schedule-day-title day-click" href="?dia=<?=$d->format('Y-m-d')?>">
                <?=$dias[$i]?><br><span class="small-white"><?=$d->format('d/m/Y')?></span>
            </a>
        <?php endfor; ?>
        <?php for($h=0;$h<24;$h++): ?>
            <div class="schedule-cell schedule-time"><?=str_pad($h,2,'0',STR_PAD_LEFT)?>:00</div>
            <?php for($i=0;$i<7;$i++): $d=(clone $weekStart)->modify("+$i days"); $key=$d->format('Y-m-d'); ?>
                <div class="schedule-cell schedule-slot <?=!empty($by[$key][$h])?'slot-ocupado':''?>">
                    <?php foreach(($by[$key][$h]??[]) as $r):?>
                        <div class="turno-chip turno-mini turno-hour" style="--c:<?=h($r['color'])?>; background:<?=h($r['color'])?>1a;">
                            <b><?=h($r['personal'])?></b> <span class="badge"><?=h($r['rol'])?></span><br>
                            <span class="small"><?=h($r['hora_inicio'])?> a <?=h($r['hora_fin'])?></span>
                            <?php if(!empty($r['nota']) && !$r['continua']):?><br><span class="small"><?=h($r['nota'])?></span><?php endif;?>
                            <?php if(es_admin() && !$r['continua']):?><br><a class="btn blue" href="?editar=<?=$r['id_turno_prog']?>&dia=<?=$key?>">Editar</a><?php endif;?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        <?php endfor; ?>
    </div>
</div>
<?php endfor; ?>
<?php include '../config/footer.php'; ?>
