<?php
require_once '../config/session.php';
require_login();
require_once '../config/db.php';

$DIAS_CARGA = max(1, ajuste_num('turnos_dias_carga', 14));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_cronograma']) && es_admin()) {
    $desde = trim($_POST['fecha_desde'] ?? date('Y-m-d'));
    $dias  = max(1, ajuste_num('turnos_dias_cronograma', 30));

    $cuidadoras = $pdo->query("SELECT id_usuario, nombre, color FROM usuarios WHERE rol='cuidadora' AND estado='activo' ORDER BY id_usuario")->fetchAll();
    $asistentes = $pdo->query("SELECT id_usuario, nombre, color FROM usuarios WHERE rol='asistente' AND estado='activo' ORDER BY id_usuario")->fetchAll();

    if (empty($cuidadoras)) {
        header('Location: calendario.php?toast=' . urlencode('No hay cuidadoras activas para generar el cronograma') . '&tipo=error');
        exit;
    }

    $pdo->prepare("DELETE FROM turnos_programados WHERE fecha >= ? AND generado_auto = 1 AND estado='activo'")
        ->execute([$desde]);

    $stmt = $pdo->prepare("INSERT INTO turnos_programados
        (id_cuidadora_usuario, fecha, hora_inicio, hora_fin, estado, generado_auto)
        VALUES (?,?,?,?,'activo',1)");

    $idx_cuid = 0; $idx_asis = 0; $creados = 0;

    for ($i = 0; $i < $dias; $i++) {
        $fecha = date('Y-m-d', strtotime($desde . " +$i days"));

        if (!empty($cuidadoras)) {
            $c = $cuidadoras[$idx_cuid % count($cuidadoras)];
            $stmt->execute([$c['id_usuario'], $fecha, '08:00:00', '08:00:00']);
            $idx_cuid++; $creados++;
        }
        if (!empty($asistentes)) {
            $a = $asistentes[$idx_asis % count($asistentes)];
            $stmt->execute([$a['id_usuario'], $fecha, '08:00:00', '20:00:00']);
            $idx_asis++; $creados++;
        }
    }

    header('Location: calendario.php?toast=' . urlencode("Cronograma generado: $creados turnos para $dias días") . '&tipo=ok');
    exit;
}

function back_cal(){ header('Location: calendario.php'); exit; }

if($_SERVER['REQUEST_METHOD']==='POST' && es_admin() && !isset($_POST['reemplazar'])){
    $accion = $_POST['accion'] ?? 'crear';
    if($accion === 'crear'){
        $fechas = $_POST['fechas'] ?? [];
        if(empty($fechas) && !empty($_POST['fecha'])) $fechas = [$_POST['fecha']];
        $minCargaPost = new DateTime(date('Y-m-d'));
        $maxCargaPost = (clone $minCargaPost)->modify('+' . $DIAS_CARGA . ' days');
        if (empty($_POST['id_personal'])) {
            header('Location: calendario.php?toast='.urlencode('Seleccioná el personal para el turno.').'&tipo=error'); exit;
        }
        try {
            foreach($fechas as $f){
                if(!$f) continue;
                $fechaObj = DateTime::createFromFormat('Y-m-d', $f);
                if(!$fechaObj || $fechaObj < $minCargaPost || $fechaObj > $maxCargaPost) continue;
                $pdo->prepare('INSERT INTO turnos_programados(id_cuidadora_usuario,fecha,hora_inicio,hora_fin,nota,created_by) VALUES(?,?,?,?,?,?)')
                    ->execute([$_POST['id_personal'],$f,$_POST['hora_inicio'],$_POST['hora_fin'],$_POST['nota'],uid()]);
            }
        } catch (PDOException $e) {
            header('Location: calendario.php?toast='.urlencode('Error al guardar el turno. Intentá de nuevo.').'&tipo=error'); exit;
        }
        back_cal();
    }
    if($accion === 'editar'){
        $minCargaPost = new DateTime(date('Y-m-d'));
        $maxCargaPost = (clone $minCargaPost)->modify('+' . $DIAS_CARGA . ' days');
        $fechaObj = DateTime::createFromFormat('Y-m-d', $_POST['fecha']);
        if(!$fechaObj || $fechaObj < $minCargaPost || $fechaObj > $maxCargaPost){
            header('Location: calendario.php?toast=' . urlencode('Solo se pueden modificar turnos dentro de los próximos ' . $DIAS_CARGA . ' días') . '&tipo=warn');
            exit;
        }
        $pdo->prepare('UPDATE turnos_programados SET id_cuidadora_usuario=?,fecha=?,hora_inicio=?,hora_fin=?,nota=?,updated_by=? WHERE id_turno_prog=?')
            ->execute([$_POST['id_personal'],$_POST['fecha'],$_POST['hora_inicio'],$_POST['hora_fin'],$_POST['nota'],uid(),$_POST['id_turno_prog']]);
        back_cal();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reemplazar']) && es_admin()) {
    $id_turno  = (int)$_POST['id_turno_prog'];
    $id_nuevo  = (int)$_POST['id_reemplazante'];
    $motivo    = trim($_POST['motivo_reemplazo'] ?? '');

    if (!$id_turno || !$id_nuevo) {
        header('Location: calendario.php?toast=' . urlencode('Faltan datos para el reemplazo') . '&tipo=error');
        exit;
    }
    if (mb_strlen($motivo) < 4) {
        header('Location: calendario.php?toast=' . urlencode('Indicá el motivo del reemplazo') . '&tipo=error');
        exit;
    }

    $act = $pdo->prepare('SELECT id_cuidadora_usuario, id_titular, fecha FROM turnos_programados WHERE id_turno_prog=?');
    $act->execute([$id_turno]);
    $turno = $act->fetch();

    if (!$turno) {
        header('Location: calendario.php?toast=' . urlencode('El turno no existe') . '&tipo=error');
        exit;
    }
    if ($turno['id_cuidadora_usuario'] == $id_nuevo) {
        header('Location: calendario.php?dia=' . $turno['fecha'] . '&toast=' . urlencode('Esa persona ya tiene ese turno') . '&tipo=warn');
        exit;
    }

    $titular = $turno['id_titular'] ?: $turno['id_cuidadora_usuario'];

    try {
        $pdo->prepare('UPDATE turnos_programados SET id_cuidadora_usuario=?, id_titular=?, motivo_reemplazo=?, updated_by=? WHERE id_turno_prog=?')
            ->execute([$id_nuevo, $titular, $motivo, uid(), $id_turno]);
        header('Location: calendario.php?dia='.$turno['fecha'].'&toast='.urlencode('Reemplazo registrado correctamente').'&tipo=ok');
    } catch (PDOException $e) {
        header('Location: calendario.php?toast='.urlencode('Error al registrar el reemplazo. Intentá de nuevo.').'&tipo=error');
    }
    exit;
}

if(isset($_GET['eliminar']) && es_admin()){
    $pdo->prepare("UPDATE turnos_programados SET estado='baja', updated_by=? WHERE id_turno_prog=?")
        ->execute([uid(), $_GET['eliminar']]);
    back_cal();
}

$filtro_desde     = $_GET['filtro_desde'] ?? '';
$filtro_hasta     = $_GET['filtro_hasta'] ?? '';
$tiene_filtro     = $filtro_desde !== '' && $filtro_hasta !== '';
$filtro_mes_inicio = $tiene_filtro ? substr($filtro_desde, 0, 7) : '';
$filtro_mes_fin    = $tiene_filtro ? substr($filtro_hasta, 0, 7) : '';

include '../config/menu.php';

if(es_admin()){
    $personal = $pdo->query("SELECT id_usuario,CONCAT(nombre,' ',COALESCE(apellido,'')) nombre,email,rol,color FROM usuarios
                              WHERE rol IN ('cuidadora','cocinera','asistente') AND estado='activo'
                              ORDER BY FIELD(rol,'cuidadora','asistente','cocinera'),nombre")->fetchAll();
} else {
    $stPersonal = $pdo->prepare("SELECT id_usuario,CONCAT(nombre,' ',COALESCE(apellido,'')) nombre,email,rol,color FROM usuarios WHERE id_usuario=? AND estado='activo'");
    $stPersonal->execute([uid()]);
    $personal = $stPersonal->fetchAll();
}

$base = new DateTime(date('Y-m-d'));
$minCarga = new DateTime(date('Y-m-d'));
$maxCarga = (clone $minCarga)->modify('+' . $DIAS_CARGA . ' days');
$inicio = clone $base; $inicio->modify('monday this week')->modify('-7 days');
$fin = clone $inicio; $fin->modify('+20 days');
$inicioConsulta = clone $inicio; $inicioConsulta->modify('-1 day');

$filtroPersonal = es_admin() ? '' : ' AND tp.id_cuidadora_usuario='.intval(uid());

$stmt = $pdo->prepare("SELECT tp.*,CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) personal,u.email,u.color,u.rol
                       FROM turnos_programados tp
                       JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario
                       WHERE tp.estado='activo' AND tp.fecha BETWEEN ? AND ?
                       $filtroPersonal
                       ORDER BY tp.fecha,tp.hora_inicio,u.nombre");
$stmt->execute([$inicioConsulta->format('Y-m-d'), $fin->format('Y-m-d')]);
$rows = $stmt->fetchAll();

$by = [];
foreach($rows as $r){
    $startMin = intval(substr($r['hora_inicio'],0,2))*60 + intval(substr($r['hora_inicio'],3,2));
    $endMin   = intval(substr($r['hora_fin'],0,2))*60   + intval(substr($r['hora_fin'],3,2));
    if($endMin == $startMin){ $endMin = $startMin + 1440; }
    elseif($endMin < $startMin){ $endMin += 1440; }
    $startDate = new DateTime($r['fecha']);
    for($m=$startMin; $m<$endMin; $m+=60){
        $dayOffset = intdiv($m,1440);
        $hour      = intdiv($m % 1440,60);
        $dateObj   = clone $startDate; $dateObj->modify("+$dayOffset days");
        $dateKey   = $dateObj->format('Y-m-d');
        if($dateKey < $inicio->format('Y-m-d') || $dateKey > $fin->format('Y-m-d')) continue;
        $r['bloque_hora'] = $hour;
        $r['continua']    = ($m > $startMin);
        $by[$dateKey][$hour][] = $r;
    }
}

$dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
$editar = null;
if(isset($_GET['editar']) && es_admin()){
    $st = $pdo->prepare('SELECT * FROM turnos_programados WHERE id_turno_prog=?');
    $st->execute([$_GET['editar']]);
    $editar = $st->fetch();
}
$diaSel = $_GET['dia'] ?? date('Y-m-d');

$stDia = $pdo->prepare("SELECT tp.*,CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) personal,u.color,u.rol,
                        ut.nombre titular_nombre, ut.apellido titular_apellido
                        FROM turnos_programados tp
                        JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario
                        LEFT JOIN usuarios ut ON ut.id_usuario=tp.id_titular
                        WHERE tp.estado='activo'
                        AND (tp.fecha=? OR (tp.fecha=DATE_SUB(?, INTERVAL 1 DAY) AND tp.hora_fin <= tp.hora_inicio))
                        $filtroPersonal
                        ORDER BY tp.fecha,tp.hora_inicio");
$stDia->execute([$diaSel,$diaSel]);
$turnosDia = $stDia->fetchAll();
?>

<h1>Cronograma de turnos</h1>

<?php if (es_admin()): ?>
<div class="card">
    <h2 style="margin-top:0">Cronograma automático</h2>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="generar_cronograma" value="1">
        <div>
            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">Generar desde</label>
            <input type="date" name="fecha_desde" value="<?= date('Y-m-d') ?>" required>
        </div>
        <button type="submit" class="btn"
            onclick="return confirm('¿Generar el cronograma? Esto reemplazará los turnos automáticos futuros existentes desde esa fecha.')">
            Generar cronograma (30 días)
        </button>
    </form>
</div>
<?php endif; ?>

<div class="legend">
<?php foreach($personal as $p):?>
    <span style="--c:<?=h($p['color'])?>">
        <?=h($p['nombre'])?> <small>(<?=h($p['rol'])?>)</small>
    </span>
<?php endforeach;?>
</div>
<br>

<?php if(es_admin()): ?>
<div class="card no-imprimir">
    <h2 style="margin-top:0"><?= $editar ? 'Modificar turno' : 'Registrar turno' ?></h2>
    <form method="post">
        <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
        <?php if($editar): ?>
            <input type="hidden" name="id_turno_prog" value="<?=$editar['id_turno_prog']?>">
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 110px 110px 1fr;gap:10px;align-items:end;margin-bottom:8px">
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Personal *</label>
                <select name="id_personal" class="buscador" required style="width:100%">
                    <option value="">Seleccionar personal</option>
                    <?php foreach($personal as $p):?>
                        <option value="<?=$p['id_usuario']?>"
                            <?=($editar && $editar['id_cuidadora_usuario']==$p['id_usuario'])?'selected':''?>>
                            <?=h($p['nombre'])?> (<?=h($p['rol'])?>)
                        </option>
                    <?php endforeach;?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Desde</label>
                <input type="time" name="hora_inicio" value="<?=$editar['hora_inicio']??'08:00'?>" required style="width:100%">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Hasta</label>
                <input type="time" name="hora_fin" value="<?=$editar['hora_fin']??'20:00'?>" required style="width:100%">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Nota</label>
                <input name="nota" placeholder="Opcional" value="<?=h($editar['nota']??'')?>" style="width:100%">
            </div>
        </div>

        <?php if($editar): ?>
            <div style="margin-bottom:10px">
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Fecha</label>
                <input type="date" name="fecha" value="<?=$editar['fecha']?>"
                       min="<?=$minCarga->format('Y-m-d')?>" max="<?=$maxCarga->format('Y-m-d')?>" required>
            </div>
        <?php else: ?>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px">Seleccioná los días</label>
            <div class="multi-days">
                <?php
                $hoyStr = date('Y-m-d');
                for($i=0;$i<=14;$i++):
                    $d      = (clone $minCarga)->modify("+$i days");
                    $iso    = $d->format('Y-m-d');
                    $numDia = (int)$d->format('N');
                    $clases = 'dia-chip';
                    if($numDia >= 6)     $clases .= ' es-finde';
                    if($iso === $hoyStr) $clases .= ' es-hoy';
                ?>
                    <div class="dia-item">
                        <input type="checkbox" id="fecha-<?=$iso?>" name="fechas[]" value="<?=$iso?>">
                        <label class="<?=$clases?>" for="fecha-<?=$iso?>">
                            <span class="dia-chip__nombre"><?=$dias[$numDia-1]?></span>
                            <span class="dia-chip__fecha"><?=$d->format('d/m')?></span>
                            <?php if($iso === $hoyStr): ?><span class="dia-chip__hoy">hoy</span><?php endif; ?>
                        </label>
                    </div>
                <?php endfor; ?>
            </div>
            <p class="small" style="margin:6px 0 10px;color:#aaa">Solo se pueden cargar turnos hasta <?= $DIAS_CARGA ?> días adelante.</p>
        <?php endif; ?>

        <div style="display:flex;gap:8px">
            <button class="btn">Guardar turno</button>
            <?php if($editar): ?><a class="btn gray" href="calendario.php">Cancelar</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<?php
$vista      = $_GET['vista'] ?? 'mes';
$hoyStr     = date('Y-m-d');

$mesSel = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
if ($tiene_filtro && !isset($_GET['mes'])) {
    $mesSel = substr($filtro_desde, 0, 7);
}
$mesObj    = DateTime::createFromFormat('Y-m', $mesSel) ?: new DateTime();
$mesPrev   = (clone $mesObj)->modify('-1 month')->format('Y-m');
$mesSig    = (clone $mesObj)->modify('+1 month')->format('Y-m');
$primerDia = (clone $mesObj)->modify('first day of this month');
$ultimoDia = (clone $mesObj)->modify('last day of this month');

$stMes = $pdo->prepare("SELECT tp.*,CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) personal,u.color,u.rol
    FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario
    WHERE tp.estado='activo' AND tp.fecha BETWEEN ? AND ? $filtroPersonal ORDER BY tp.hora_inicio");
$stMes->execute([$primerDia->format('Y-m-d'), $ultimoDia->format('Y-m-d')]);
$turnosMes = [];
foreach ($stMes->fetchAll() as $t) $turnosMes[$t['fecha']][] = $t;

$diaSelObj  = new DateTime($diaSel);
$lunesSem   = (clone $diaSelObj)->modify('monday this week');
$domSem     = (clone $lunesSem)->modify('+6 days');
$semPrev    = (clone $lunesSem)->modify('-7 days')->format('Y-m-d');
$semSig     = (clone $lunesSem)->modify('+7 days')->format('Y-m-d');

$stSem = $pdo->prepare("SELECT tp.*,CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) personal,u.color,u.rol
    FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario
    WHERE tp.estado='activo' AND tp.fecha BETWEEN ? AND ? $filtroPersonal ORDER BY tp.hora_inicio");
$stSem->execute([$lunesSem->format('Y-m-d'), $domSem->format('Y-m-d')]);
$turnosSem = [];
foreach ($stSem->fetchAll() as $t) {
    $startMin = intval(substr($t['hora_inicio'],0,2))*60 + intval(substr($t['hora_inicio'],3,2));
    $endMin   = intval(substr($t['hora_fin'],0,2))*60   + intval(substr($t['hora_fin'],3,2));
    if ($endMin <= $startMin) $endMin = $startMin + 1440;
    for ($m = $startMin; $m < min($endMin, $startMin+1440); $m += 60) {
        $h = intdiv($m % 1440, 60);
        $turnosSem[$t['fecha']][$h][] = $t;
    }
}

$turnosDiaHora = [];
foreach ($turnosDia as $t) {
    $startMin = intval(substr($t['hora_inicio'],0,2))*60 + intval(substr($t['hora_inicio'],3,2));
    $endMin   = intval(substr($t['hora_fin'],0,2))*60   + intval(substr($t['hora_fin'],3,2));
    if ($endMin <= $startMin) $endMin = $startMin + 1440;
    for ($m = $startMin; $m < min($endMin, $startMin+1440); $m += 60) {
        $h = intdiv($m % 1440, 60);
        $turnosDiaHora[$h][] = $t;
    }
}

$MESES_ES = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril',
    'May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto',
    'September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];

function url_cal($params = []) {
    $base = array_merge([
        'vista'         => $_GET['vista']         ?? 'mes',
        'dia'           => $_GET['dia']            ?? date('Y-m-d'),
        'mes'           => $_GET['mes']            ?? date('Y-m'),
        'filtro_desde'  => $_GET['filtro_desde']   ?? '',
        'filtro_hasta'  => $_GET['filtro_hasta']   ?? '',
    ], $params);
    $base = array_filter($base, fn($v) => $v !== '');
    return 'calendario.php?' . http_build_query($base) . '#cal';
}
?>

<style>
.cal-vista-btns{display:flex;gap:6px;margin-bottom:12px}
.cal-vista-btns a{padding:6px 16px;border:2px solid var(--color-primary,#2D9B8A);border-radius:6px;
    color:var(--color-primary,#2D9B8A);font-size:13px;font-weight:600;text-decoration:none;transition:.15s}
.cal-vista-btns a.active{background:var(--color-primary,#2D9B8A);color:#fff}
.cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.cal-nav h2{margin:0;font-size:17px;font-weight:700}
.cal-mes{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
.cal-mes th{background:var(--color-primary,#2D9B8A);color:#fff;padding:7px 4px;text-align:center;font-weight:600}
.cal-mes td{border:1px solid var(--border-color,#e5e7eb);vertical-align:top;padding:4px;height:80px}
.cal-dia-num{font-weight:700;font-size:11px;margin-bottom:3px;display:inline-block;
    padding:1px 4px;line-height:18px;text-align:center;border-radius:4px}
.cal-dia-num.hoy{background:var(--color-primary,#2D9B8A);color:#fff}
.cal-dia-vacio{background:rgba(0,0,0,.025)}
.cal-chip{display:block;border-radius:3px;padding:1px 4px;margin-bottom:1px;font-size:10px;
    font-weight:600;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cal-mas{font-size:10px;color:#aaa}
td.cal-hoy{background:rgba(45,155,138,.06)}
td.cal-sel{outline:2px solid var(--color-primary,#2D9B8A);outline-offset:-2px}
.cal-grid{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
.cal-grid th{background:var(--color-primary,#2D9B8A);color:#fff;padding:6px 4px;
    text-align:center;font-weight:600;font-size:12px;border:1px solid rgba(255,255,255,.2)}
.cal-grid td{border:1px solid var(--border-color,#e5e7eb);vertical-align:top;padding:3px;height:36px}
.cal-grid td.hora-label{background:var(--bg-page,#f5f6fa);font-size:11px;font-weight:600;
    color:var(--text-muted);text-align:right;padding-right:6px;width:46px;white-space:nowrap}
.cal-grid td.sin-turno{background:rgba(0,0,0,.015)}
.cal-grid td.con-turno{}
.hora-chip{display:block;border-radius:3px;padding:1px 4px;margin-bottom:1px;
    font-size:10px;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cal-grid th.dia-hoy-th{background:#1e7a6a}
td.col-hoy{background:rgba(45,155,138,.04)}
@media print {
    .no-imprimir,nav,aside,.legend,button,a.btn{display:none!important}
    body{font-size:11px}
    .cal-mes td{height:50px!important}
    .card{box-shadow:none!important;border:1px solid #ddd}
}
</style>

<?php
?>

<div id="cal" class="no-imprimir card" style="padding:12px 14px;margin-bottom:10px">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div class="cal-vista-btns" style="margin-bottom:0">
            <a href="<?= url_cal(['vista'=>'mes']) ?>"    class="<?= $vista==='mes'?'active':'' ?>">Mes</a>
            <a href="<?= url_cal(['vista'=>'semana']) ?>" class="<?= $vista==='semana'?'active':'' ?>">Semana</a>
            <a href="<?= url_cal(['vista'=>'dia']) ?>"    class="<?= $vista==='dia'?'active':'' ?>">Día</a>
        </div>
        <form method="get" action="calendario.php#cal" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap" id="form-filtro-cal">
            <input type="hidden" name="vista" value="<?= h($vista) ?>">
            <label style="font-size:12px;color:var(--text-muted)">Período:</label>
            <input type="date" name="filtro_desde" value="<?= h($filtro_desde) ?>"
                   style="font-size:12px;padding:4px 6px;border-radius:6px;border:1.5px solid var(--border-color,#ddd)">
            <span style="font-size:12px;color:#aaa"></span>
            <input type="date" name="filtro_hasta" value="<?= h($filtro_hasta) ?>"
                   style="font-size:12px;padding:4px 6px;border-radius:6px;border:1.5px solid var(--border-color,#ddd)">
            <button type="submit" class="btn gray" style="font-size:12px;padding:4px 10px">Filtrar</button>
            <?php if ($tiene_filtro): ?>
                <a href="<?= url_cal() ?>" class="btn gray" style="font-size:12px;padding:4px 8px">Limpiar</a>
            <?php endif; ?>
            <a class="btn gray" style="font-size:12px;padding:4px 10px"
               href="<?= url_cal(['dia'=>date('Y-m-d'),'mes'=>date('Y-m'),'filtro_desde'=>$filtro_desde,'filtro_hasta'=>$filtro_hasta]) ?>">Hoy</a>
            <button type="button" onclick="window.print()" class="btn gray" style="font-size:12px;padding:4px 10px">Imprimir</button>
        </form>
    </div>
    <?php if ($tiene_filtro): ?>
    <div style="margin-top:8px;font-size:12px;color:#2980b9">
        Período activo: <b><?= date('d/m/Y', strtotime($filtro_desde)) ?></b> al <b><?= date('d/m/Y', strtotime($filtro_hasta)) ?></b>. Navegación limitada a este rango
    </div>
    <?php endif; ?>
</div>



<?php
if ($vista === 'mes'): ?>
<div class="card" style="overflow-x:auto;padding:14px">
    <div class="cal-nav">
        <?php $puede_prev_mes = !$tiene_filtro || $mesPrev >= $filtro_mes_inicio; ?>
        <?php $puede_sig_mes  = !$tiene_filtro || $mesSig  <= $filtro_mes_fin; ?>
        <?php if ($puede_prev_mes): ?>
            <a class="btn gray" style="font-size:12px" href="<?= url_cal(['mes'=>$mesPrev,'dia'=>$primerDia->format('Y-m-d')]) ?>">◀</a>
        <?php else: ?>
            <span class="btn gray" style="font-size:12px;opacity:.3;cursor:default">◀</span>
        <?php endif; ?>
        <h2><?= strtr($mesObj->format('F Y'), $MESES_ES) ?></h2>
        <?php if ($puede_sig_mes): ?>
            <a class="btn gray" style="font-size:12px" href="<?= url_cal(['mes'=>$mesSig,'dia'=>(clone $mesObj)->modify('first day of next month')->format('Y-m-d')]) ?>">▶</a>
        <?php else: ?>
            <span class="btn gray" style="font-size:12px;opacity:.3;cursor:default">▶</span>
        <?php endif; ?>
    </div>
    <table class="cal-mes">
        <thead><tr>
            <?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dn): echo "<th>$dn</th>"; endforeach; ?>
        </tr></thead>
        <tbody>
        <?php
        $inicioSem = (int)$primerDia->format('N') - 1;
        $totalD    = (int)$ultimoDia->format('j');
        $cel       = 0;
        echo '<tr>';
        for ($v=0; $v<$inicioSem; $v++) { echo '<td class="cal-dia-vacio"></td>'; $cel++; }
        for ($d=1; $d<=$totalD; $d++) {
            $iso     = $mesObj->format('Y-m').'-'.str_pad($d,2,'0',STR_PAD_LEFT);
            $esHoy   = $iso === $hoyStr;
            $esSel   = $iso === $diaSel;
            $ts      = $turnosMes[$iso] ?? [];
            $clsTd   = $esHoy ? 'cal-hoy' : ($esSel ? 'cal-sel' : '');
            echo '<td class="'.$clsTd.'" onclick="irDia(\''.h($iso).'\')" style="cursor:pointer">';
            $clsNum  = $esHoy ? 'hoy' : '';
            $diaSemNom = ['1'=>'Lun','2'=>'Mar','3'=>'Mié','4'=>'Jue','5'=>'Vie','6'=>'Sáb','7'=>'Dom'];
            $nomDiaSem = $diaSemNom[(new DateTime($iso))->format('N')] ?? '';
            echo '<span class="cal-dia-num '.$clsNum.'" style="width:auto;padding:0 2px;border-radius:4px">'
                .$nomDiaSem.' '.str_pad($d,2,'0',STR_PAD_LEFT).'</span>';
            foreach (array_slice($ts,0,3) as $t)
                echo '<span class="cal-chip" style="background:'.h($t['color'] ?: '#888').'"
                    title="'.h($t['personal']).' '.substr($t['hora_inicio'],0,5).' a '.substr($t['hora_fin'],0,5).'">'.h($t['personal']).'</span>';
            if (count($ts)>3) echo '<span class="cal-mas">+'.(count($ts)-3).' más</span>';
            echo '</td>';
            $cel++;
            if ($cel%7===0 && $d<$totalD) echo '</tr><tr>';
        }
        while ($cel%7!==0) { echo '<td class="cal-dia-vacio"></td>'; $cel++; }
        echo '</tr>';
        ?>
        </tbody>
    </table>
</div>

<?php
elseif ($vista === 'semana'): ?>
<div class="card" style="overflow-x:auto;padding:14px">
    <div class="cal-nav">
        <?php $puede_prev_sem = !$tiene_filtro || $semPrev >= $filtro_desde; ?>
        <?php $puede_sig_sem  = !$tiene_filtro || $semSig  <= $filtro_hasta; ?>
        <?php if ($puede_prev_sem): ?>
        <a class="btn gray" style="font-size:12px"
           href="<?= url_cal(['vista'=>'semana','dia'=>$semPrev,'mes'=>substr($semPrev,0,7)]) ?>">◀</a>
        <?php else: ?>
        <span class="btn gray" style="font-size:12px;opacity:.3;cursor:default">◀</span>
        <?php endif; ?>
        <h2><?= $lunesSem->format('d/m') ?> al <?= $domSem->format('d/m/Y') ?></h2>
        <?php if ($puede_sig_sem): ?>
        <a class="btn gray" style="font-size:12px"
           href="<?= url_cal(['vista'=>'semana','dia'=>$semSig,'mes'=>substr($semSig,0,7)]) ?>">▶</a>
        <?php else: ?>
        <span class="btn gray" style="font-size:12px;opacity:.3;cursor:default">▶</span>
        <?php endif; ?>
    </div>
    <table class="cal-grid">
        <thead><tr>
            <th style="width:46px">Hora</th>
            <?php
            for ($di=0; $di<7; $di++) {
                $dObj = (clone $lunesSem)->modify("+$di days");
                $iso  = $dObj->format('Y-m-d');
                $cls  = $iso===$hoyStr ? 'dia-hoy-th' : '';
                $link = url_cal(['vista'=>'dia','dia'=>$iso]);
                echo '<th class="'.$cls.'"><a href="'.h($link).'" style="color:inherit;text-decoration:none">'
                    .$dObj->format('D d/m').'</a></th>';
            }
            ?>
        </tr></thead>
        <tbody>
        <?php for ($hora=0; $hora<24; $hora++): ?>
        <tr>
            <td class="hora-label"><?= str_pad($hora,2,'0',STR_PAD_LEFT) ?>:00</td>
            <?php for ($di=0; $di<7; $di++):
                $dObj = (clone $lunesSem)->modify("+$di days");
                $iso  = $dObj->format('Y-m-d');
                $ts   = $turnosSem[$iso][$hora] ?? [];
                $cls  = $iso===$hoyStr ? 'col-hoy' : ($ts ? 'con-turno' : 'sin-turno');
            ?>
            <td class="<?= $cls ?>">
                <?php foreach ($ts as $t): ?>
                    <span class="hora-chip" style="background:<?= h($t['color'] ?: '#888') ?>"
                          title="<?= h($t['personal']) ?> · <?= substr($t['hora_inicio'],0,5) ?> a <?= substr($t['hora_fin'],0,5) ?>">
                        <?= h($t['personal']) ?>
                    </span>
                <?php endforeach; ?>
            </td>
            <?php endfor; ?>
        </tr>
        <?php endfor; ?>
        </tbody>
    </table>
</div>

<?php
else: ?>
<div class="card" style="padding:14px">
    <div class="cal-nav">
        <?php
        $antDia = date('Y-m-d', strtotime($diaSel.' -1 day'));
        $sigDia = date('Y-m-d', strtotime($diaSel.' +1 day'));
        ?>
        <a class="btn gray" style="font-size:12px"
           href="<?= url_cal(['vista'=>'dia','dia'=>$antDia,'mes'=>substr($antDia,0,7)]) ?>">◀</a>
        <h2><?= strtr($diaSelObj->format('l d \d\e F Y'),
            array_merge($MESES_ES,['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles',
            'Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'])) ?></h2>
        <a class="btn gray" style="font-size:12px"
           href="<?= url_cal(['vista'=>'dia','dia'=>$sigDia,'mes'=>substr($sigDia,0,7)]) ?>">▶</a>
    </div>
    <?php if (!empty($turnosDiaHora)): ?>
    <table class="cal-grid" style="max-width:420px">
        <thead><tr><th style="width:46px">Hora</th><th>Personal de turno</th></tr></thead>
        <tbody>
        <?php for ($hora=0; $hora<24; $hora++):
            $ts = $turnosDiaHora[$hora] ?? [];
        ?>
        <tr style="<?= $hora === (int)date('H') && $diaSel===$hoyStr ? 'background:rgba(45,155,138,.08)' : '' ?>">
            <td class="hora-label">
                <?= str_pad($hora,2,'0',STR_PAD_LEFT) ?>:00
                <?php if ($hora === (int)date('H') && $diaSel===$hoyStr): ?>
                    <span style="color:var(--color-primary,#2D9B8A);font-size:9px">▶</span>
                <?php endif; ?>
            </td>
            <td style="<?= $ts ? '' : 'background:rgba(0,0,0,.015)' ?>">
                <?php foreach ($ts as $t): ?>
                    <span class="hora-chip" style="background:<?= h($t['color'] ?: '#888') ?>">
                        <?= h($t['personal']) ?>
                        <span style="opacity:.8;font-weight:400">(<?= h($t['rol']) ?>)</span>
                    </span>
                <?php endforeach; ?>
                <?php if (empty($ts)): ?>
                    <span style="color:#ddd;font-size:10px"></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endfor; ?>
        </tbody>
    </table>

    <h3 style="margin:16px 0 8px;font-size:13px">Turnos del día</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($turnosDia as $t):
        $puedeEditar = es_admin() && $t['fecha'] >= $minCarga->format('Y-m-d');
    ?>
        <div style="border-left:4px solid <?= h($t['color'] ?: '#888') ?>;
                    background:var(--bg-surface);border-radius:6px;padding:8px 12px;min-width:200px">
            <b style="font-size:13px"><?= h($t['personal']) ?></b>
            <span class="badge" style="font-size:10px"><?= h($t['rol']) ?></span><br>
            <span class="small"><?= substr($t['hora_inicio'],0,5) ?> a <?= substr($t['hora_fin'],0,5) ?></span>
            <?php if ($t['nota']): ?><br><span class="small"><?= h($t['nota']) ?></span><?php endif; ?>
            <?php if (!empty($t['id_titular'])): ?>
                <br><span class="pill pill-aviso" style="font-size:10px">Cubre a <?= h(trim($t['titular_nombre'].' '.$t['titular_apellido'])) ?></span>
            <?php endif; ?>
            <?php if ($puedeEditar): ?>
            <div style="margin-top:6px;display:flex;gap:4px">
                <a class="btn blue" style="font-size:10px;padding:2px 6px"
                   href="?editar=<?= $t['id_turno_prog'] ?>&dia=<?= $diaSel ?>&vista=dia">Editar</a>
                <button class="btn orange" style="font-size:10px;padding:2px 6px"
                        onclick="abrirModalReemplazo(<?=$t['id_turno_prog']?>,'<?=h($t['personal'])?>',<?=(int)$t['id_cuidadora_usuario']?>)">
                    Reemplazar
                </button>
                <a class="btn red" style="font-size:10px;padding:2px 6px"
                   href="?eliminar=<?= $t['id_turno_prog'] ?>&vista=dia"
                   onclick="return confirmarLink(this,'¿Quitar este turno?','Quitar','red')">Quitar</a>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function irDia(iso) {
    window.location.href = '<?= url_cal(['vista'=>'dia','dia'=>'__ISO__','mes'=>'__MES__']) ?>'
        .replace('__ISO__', iso).replace('__MES__', iso.substr(0,7));
}

</script>

<?php if (es_admin()): ?>
<div id="modal-reemplazo" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:28px;width:100%;max-width:480px;position:relative">
        <button onclick="cerrarModalReemplazo()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
        <h2 style="margin-top:0">Reemplazar en el turno</h2>
        <p id="reemplazo-quien" class="small" style="margin-bottom:16px"></p>
        <form method="post">
            <input type="hidden" name="reemplazar" value="1">
            <input type="hidden" name="id_turno_prog" id="reemplazo-id">

            <label>Quién lo cubre <span style="color:red">*</span></label>
            <select name="id_reemplazante" id="reemplazo-persona" class="buscador" required>
                <option value="">Elegir...</option>
                <?php foreach ($personal as $p): ?>
                    <option value="<?= $p['id_usuario'] ?>" data-rol="<?= h($p['rol']) ?>"><?= h($p['nombre']) ?> (<?= h($p['rol']) ?>)</option>
                <?php endforeach; ?>
            </select>

            <label>Motivo <span style="color:red">*</span></label>
            <input name="motivo_reemplazo" required minlength="4">

            <br>
            <button type="submit" class="btn orange">Registrar reemplazo</button>
            <button type="button" class="btn gray" onclick="cerrarModalReemplazo()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModalReemplazo(idTurno, nombreActual, idActual) {
    document.getElementById('reemplazo-id').value = idTurno;
    document.getElementById('reemplazo-quien').textContent =
        'Hoy lo cubre ' + nombreActual + '. Elegí quién lo reemplaza.';

    var sel = document.getElementById('reemplazo-persona');
    Array.from(sel.options).forEach(function (o) {
        o.hidden = (o.value && String(o.value) === String(idActual));
    });
    sel.value = '';
    if (window.refrescarBuscador) refrescarBuscador('id_reemplazante');

    document.getElementById('modal-reemplazo').style.display = 'flex';
}
function cerrarModalReemplazo() {
    document.getElementById('modal-reemplazo').style.display = 'none';
}
document.getElementById('modal-reemplazo').addEventListener('click', function (e) {
    if (e.target === this) cerrarModalReemplazo();
});
</script>
<?php endif; ?>

<?php include '../config/footer.php'; ?>



