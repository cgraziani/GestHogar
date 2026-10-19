<?php
$seccion = 'agenda';
require_once '../config/session.php';
require_once '../config/db.php';
require_once 'responsable.php';
puede_cuidar();

$id_hab_filtro = isset($_GET['habitacion']) ? (int)$_GET['habitacion'] : (isset($_POST['habitacion_vista']) ? (int)$_POST['habitacion_vista'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ag_medicacion'])) {
    $estado = $_POST['ag_medicacion'];
    $motivo = trim($_POST['motivo'] ?? '');
    $fv     = $_POST['fecha_vista'] ?? '';
    $hv     = $_POST['habitacion_vista'] ?? '';
    $vuelta = 'agenda.php';
    $params = [];
    if ($fv) $params[] = 'fecha=' . $fv;
    if ($hv) $params[] = 'habitacion=' . $hv;
    if ($params) $vuelta .= '?' . implode('&', $params);
    $sep    = strpos($vuelta,'?') !== false ? '&' : '?';

    if (($estado === 'no_pudo' || $estado === 'no_dado') && $motivo === '') {
        header('Location: '.$vuelta.$sep.'toast='.urlencode('Para NO PUDE o NO SE DIO tenes que escribir el motivo').'&tipo=error');
        exit;
    }
    $pdo->prepare("INSERT INTO administracion_medicacion
        (id_plan,id_residente,id_medicamento,id_cuidadora_usuario,fecha_hora,estado_adm,observaciones)
        VALUES(?,?,?,?,NOW(),?,?)")
        ->execute([(int)$_POST['id_plan'],(int)$_POST['id_residente'],
                   (int)$_POST['id_medicamento'],uid(),$estado,$motivo ?: null]);
    header('Location: '.$vuelta.$sep.'toast=Medicacion+registrada&tipo=ok');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ag_control'])) {
    $id_res   = (int)$_POST['id_residente'];
    $tipo     = trim($_POST['tipo_control'] ?? '');
    $valor    = trim($_POST['valor_control'] ?? '');
    $obs      = trim($_POST['motivo'] ?? '');
    $fv       = $_POST['fecha_vista'] ?? '';
    $hv       = $_POST['habitacion_vista'] ?? '';
    $vuelta   = 'agenda.php';
    $params   = [];
    if ($fv) $params[] = 'fecha=' . $fv;
    if ($hv) $params[] = 'habitacion=' . $hv;
    if ($params) $vuelta .= '?' . implode('&', $params);
    $sep = strpos($vuelta,'?') !== false ? '&' : '?';

    if (!is_numeric($valor) || (float)$valor < 0) {
        header('Location: '.$vuelta.$sep.'toast='.urlencode('El valor debe ser numérico y positivo').'&tipo=error');
        exit;
    }

    require_once '../config/ajustes.php';
    $rangos = rangos_control($pdo);
    $unidad = $rangos[$tipo]['unidad'] ?? '';

    $fuera  = false;
    if (isset($rangos[$tipo])) {
        $r = $rangos[$tipo];
        if ((float)$valor < $r['min'] || (float)$valor > $r['max']) $fuera = true;
    }

    $pdo->prepare("INSERT INTO controles_clinicos
        (id_residente, id_usuario, tipo_control, valor, unidad, observaciones, fecha_hora)
        VALUES (?, ?, ?, ?, ?, ?, NOW())")
        ->execute([$id_res, uid(), $tipo, (float)$valor, $unidad, $obs ?: null]);

    if ($fuera) {
        $msg = 'Control registrado. Valor fuera de rango. Verificá con el médico.';
        header('Location: '.$vuelta.$sep.'toast='.urlencode($msg).'&tipo=warn');
    } else {
        header('Location: '.$vuelta.$sep.'toast=Control+registrado&tipo=ok');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ag_tarea'])) {
    $estado = $_POST['ag_tarea'];
    $motivo = trim($_POST['motivo'] ?? '');
    $fv     = $_POST['fecha_vista'] ?? '';
    $hv     = $_POST['habitacion_vista'] ?? '';
    $vuelta = 'agenda.php';
    $params = [];
    if ($fv) $params[] = 'fecha=' . $fv;
    if ($hv) $params[] = 'habitacion=' . $hv;
    if ($params) $vuelta .= '?' . implode('&', $params);
    $sep    = strpos($vuelta,'?') !== false ? '&' : '?';

    if (($estado === 'no_realizada' || $estado === 'no_pudo') && $motivo === '') {
        header('Location: '.$vuelta.$sep.'toast='.urlencode('Para NO PUDE o NO REALIZADA tenes que escribir el motivo').'&tipo=error');
        exit;
    }
    $pdo->prepare("INSERT INTO administracion_tareas
        (id_tarea,id_residente,id_cuidadora_usuario,fecha_hora,estado_tarea,motivo)
        VALUES(?,?,?,NOW(),?,?)")
        ->execute([(int)$_POST['id_tarea'],(int)$_POST['id_residente'],
                   uid(),$estado,$motivo ?: null]);
    header('Location: '.$vuelta.$sep.'toast=Tarea+registrada&tipo=ok');
    exit;
}

$fecha       = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_label = date('d/m/Y', strtotime($fecha));
$es_hoy      = ($fecha === date('Y-m-d'));
$hora_actual = date('H:i');

$meds = $pdo->prepare("
    SELECT mr.id_plan, mr.id_residente, mr.id_medicamento, mr.horario, mr.dosis, mr.indicacion,
           mr.id_responsable,
           m.nombre med_nombre, m.presentacion med_presentacion,
           r.nombre res_nombre, r.apellido res_apellido,
           h.numero hab_numero, h.sector hab_sector, h.id_habitacion,
           c.identificador cama_id,
           (SELECT am.estado_adm FROM administracion_medicacion am
            WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = ?
            ORDER BY am.fecha_hora DESC LIMIT 1) estado_hoy,
           (SELECT am.observaciones FROM administracion_medicacion am
            WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = ?
            ORDER BY am.fecha_hora DESC LIMIT 1) obs_hoy,
           (SELECT u.nombre FROM administracion_medicacion am
            LEFT JOIN usuarios u ON u.id_usuario = am.id_cuidadora_usuario
            WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = ?
            ORDER BY am.fecha_hora DESC LIMIT 1) reg_por
    FROM medicacion_residente mr
    JOIN medicamentos m   ON m.id_medicamento = mr.id_medicamento
    JOIN residentes r     ON r.id_residente   = mr.id_residente
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = r.id_cama
    WHERE mr.estado = 'activo' AND r.estado = 'activo'
      AND mr.horario IS NOT NULL AND mr.horario != ''
      " . ($id_hab_filtro ? "AND r.id_habitacion = $id_hab_filtro " : '') . "
    ORDER BY mr.horario, h.numero, r.apellido");
$meds->execute([$fecha, $fecha, $fecha]);

$tar = $pdo->prepare("
    SELECT tr.id_tarea, tr.id_residente, tr.titulo, tr.horario, tr.descripcion,
           r.nombre res_nombre, r.apellido res_apellido,
           h.numero hab_numero, h.sector hab_sector, h.id_habitacion,
           c.identificador cama_id,
           (SELECT at2.estado_tarea FROM administracion_tareas at2
            WHERE at2.id_tarea = tr.id_tarea AND DATE(at2.fecha_hora) = ?
            ORDER BY at2.fecha_hora DESC LIMIT 1) estado_hoy,
           (SELECT at2.motivo FROM administracion_tareas at2
            WHERE at2.id_tarea = tr.id_tarea AND DATE(at2.fecha_hora) = ?
            ORDER BY at2.fecha_hora DESC LIMIT 1) obs_hoy,
           (SELECT u.nombre FROM administracion_tareas at2
            LEFT JOIN usuarios u ON u.id_usuario = at2.id_cuidadora_usuario
            WHERE at2.id_tarea = tr.id_tarea AND DATE(at2.fecha_hora) = ?
            ORDER BY at2.fecha_hora DESC LIMIT 1) reg_por
    FROM tareas_residente tr
    JOIN residentes r ON r.id_residente = tr.id_residente
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = r.id_cama
    WHERE tr.estado = 'activo' AND r.estado = 'activo'
      AND tr.horario IS NOT NULL AND tr.horario != ''
      " . ($id_hab_filtro ? "AND r.id_habitacion = $id_hab_filtro " : '') . "
    ORDER BY tr.horario, h.numero, r.apellido");
$tar->execute([$fecha, $fecha, $fecha]);

$controles_plan = $pdo->prepare("
    SELECT cr.id_residente, cr.tipo_control, cr.frecuencia, cr.observaciones,
           r.nombre res_nombre, r.apellido res_apellido,
           h.numero hab_numero, h.sector hab_sector, h.id_habitacion,
           c.identificador cama_id,
           (SELECT cc.valor FROM controles_clinicos cc
            WHERE cc.id_residente=cr.id_residente
              AND cc.tipo_control=cr.tipo_control
              AND DATE(cc.fecha_hora)=?
            ORDER BY cc.fecha_hora DESC LIMIT 1) valor_hoy,
           (SELECT cc.fecha_hora FROM controles_clinicos cc
            WHERE cc.id_residente=cr.id_residente
              AND cc.tipo_control=cr.tipo_control
              AND DATE(cc.fecha_hora)=?
            ORDER BY cc.fecha_hora DESC LIMIT 1) hora_hoy,
           (SELECT u.nombre FROM controles_clinicos cc
            LEFT JOIN usuarios u ON u.id_usuario=cc.id_usuario
            WHERE cc.id_residente=cr.id_residente
              AND cc.tipo_control=cr.tipo_control
              AND DATE(cc.fecha_hora)=?
            ORDER BY cc.fecha_hora DESC LIMIT 1) reg_por
    FROM controles_residente cr
    JOIN residentes r ON r.id_residente=cr.id_residente
    LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
    LEFT JOIN camas c ON c.id_cama=r.id_cama
    WHERE cr.estado='activo' AND r.estado='activo'
    " . ($id_hab_filtro ? "AND r.id_habitacion=$id_hab_filtro" : "") . "
    ORDER BY r.apellido, cr.tipo_control");
$controles_plan->execute([$fecha, $fecha, $fecha]);
$controles_dia = $controles_plan->fetchAll();

$dia_semana = date('N', strtotime($fecha));
function horarios_control($frecuencia, $dia_semana) {
    switch ($frecuencia) {
        case 'diario':        return ['08:00'];
        case 'dos_veces_dia': return ['08:00', '18:00'];
        case 'semanal':       return $dia_semana == 1 ? ['09:00'] : [];
        case 'quincenal':     return [];
        case 'mensual':       return [];
        case 'a_demanda':     return ['09:00'];
        default:              return ['08:00'];
    }
}

$comidas = $pdo->query("SELECT tipo_comida, horario, descripcion FROM horarios_comidas WHERE estado='activo' ORDER BY horario")->fetchAll();

$menu_dia = $pdo->prepare("SELECT titulo FROM cocina WHERE tipo='receta' AND estado='activo' AND fecha=?");
$menu_dia->execute([$fecha]);
$menu_dia = $menu_dia->fetchAll();

$agenda = [];
foreach ($meds->fetchAll() as $m) {
    $hora = substr($m['horario'],0,5);
    $ih = $m['id_habitacion'] ?? 0;
    $ir = $m['id_residente'];
    $agenda[$hora]['res'][$ih][$ir]['info'] = [
        'res_nombre'=>$m['res_nombre'],'res_apellido'=>$m['res_apellido'],
        'cama'=>$m['cama_id'],'hab_numero'=>$m['hab_numero'],'hab_sector'=>$m['hab_sector']];
    $agenda[$hora]['res'][$ih][$ir]['items'][] = array_merge($m,['tipo_item'=>'med']);
}
foreach ($tar->fetchAll() as $t) {
    $hora = substr($t['horario'],0,5);
    $ih = $t['id_habitacion'] ?? 0;
    $ir = $t['id_residente'];
    $agenda[$hora]['res'][$ih][$ir]['info'] = [
        'res_nombre'=>$t['res_nombre'],'res_apellido'=>$t['res_apellido'],
        'cama'=>$t['cama_id'],'hab_numero'=>$t['hab_numero'],'hab_sector'=>$t['hab_sector']];
    $agenda[$hora]['res'][$ih][$ir]['items'][] = array_merge($t,['tipo_item'=>'tar']);
}
$labels_control = [
    'tension_sistolica'   => 'Tensión sistólica',
    'tension_diastolica'  => 'Tensión diastólica',
    'frecuencia_cardiaca' => 'Frecuencia cardíaca',
    'temperatura'         => 'Temperatura',
    'peso'                => 'Peso',
    'glucemia'            => 'Glucemia',
    'saturacion_oxigeno'  => 'Saturación de oxígeno',
];
foreach ($controles_dia as $ctl) {
    $horarios = horarios_control($ctl['frecuencia'], $dia_semana);
    if (empty($horarios)) continue;
    foreach ($horarios as $hora) {
        $ih = $ctl['id_habitacion'] ?? 0;
        $ir = $ctl['id_residente'];
        $agenda[$hora]['res'][$ih][$ir]['info'] = [
            'res_nombre'  => $ctl['res_nombre'],
            'res_apellido'=> $ctl['res_apellido'],
            'cama'        => $ctl['cama_id'],
            'hab_numero'  => $ctl['hab_numero'],
            'hab_sector'  => $ctl['hab_sector'],
        ];
        $agenda[$hora]['res'][$ih][$ir]['items'][] = array_merge($ctl, [
            'tipo_item'   => 'ctl',
            'titulo'      => $labels_control[$ctl['tipo_control']] ?? $ctl['tipo_control'],
            'estado_hoy'  => $ctl['valor_hoy'] !== null ? 'registrado' : null,
            'obs_hoy'     => $ctl['valor_hoy'] !== null ? 'Valor: ' . $ctl['valor_hoy'] : null,
            'reg_por'     => $ctl['reg_por'],
        ]);
    }
}

foreach ($comidas as $c) {
    $hora = substr($c['horario'],0,5);
    $agenda[$hora]['comida'] = $c;
}
ksort($agenda);

function ag_clase($e,$h,$eh,$ha) {
    if ($e==='administrado'||$e==='realizada'||$e==='registrado') return 'ag-ok';
    if ($e==='no_pudo') return 'ag-nopudo';
    if ($e==='no_realizada'||$e==='no_dado') return 'ag-no';
    if ($eh && $h < $ha) return 'ag-vencido';
    return 'ag-pendiente';
}
function ag_lbl($e) {
    return match($e){
        'administrado' => 'Administrado',
        'realizada'    => 'Realizada',
        'registrado'   => 'Registrado',
        'no_pudo'      => 'No pudo',
        'no_dado'      => 'No se dio',
        'no_realizada' => 'No realizada',
        default        => 'Pendiente'
    };
}

$total_vencidos = 0;
foreach ($agenda as $hora=>$blk) {
    if (!isset($blk['res'])) continue;
    foreach ($blk['res'] as $ress)
        foreach ($ress as $rd)
            foreach ($rd['items'] as $it)
                if (ag_clase($it['estado_hoy'],$hora,$es_hoy,$hora_actual)==='ag-vencido') $total_vencidos++;
}

include '../config/header.php';
include '../config/menu.php';
?>
<style>
.agenda-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px}
.hora-bloque{margin-bottom:20px}
.hora-titulo{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.hora-pill{background:#1F4E79;color:#fff;padding:6px 18px;border-radius:20px;font-size:17px;font-weight:700;min-width:70px;text-align:center}
.hora-pill.vencida{background:#c0392b}
.hora-linea{flex:1;height:1px;background:var(--border-color,#ddd)}
.comida-bloque{background:linear-gradient(90deg,#f39c12,#e67e22);color:#fff;border-radius:8px;padding:10px 16px;margin-bottom:8px;display:flex;align-items:center;gap:12px}
.res-bloque{display:flex;margin-bottom:8px;border-radius:8px;overflow:hidden;border:1px solid var(--border-color,#ddd)}
.res-info{background:#2E75B6;color:#fff;padding:10px 14px;min-width:190px;max-width:210px;display:flex;flex-direction:column;justify-content:center;gap:2px}
.res-info b{font-size:14px}
.res-info small{font-size:11px;opacity:.85}
.res-items{flex:1;padding:8px 12px;background:var(--bg-surface);display:flex;flex-direction:column;gap:6px}
.ag-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:6px;font-size:13px;cursor:pointer}
.ag-item:hover{transform:translateX(2px)}
.ag-badge{font-size:10px;padding:2px 7px;border-radius:8px;font-weight:700;white-space:nowrap;flex-shrink:0}
.badge-med{background:#1F4E79;color:#fff}
.badge-tar{background:#375623;color:#fff}
.badge-ctl{background:#6B2FA0;color:#fff}
.ag-body{flex:1}
.ag-body b{font-size:13px}
.ag-body small{display:block;font-size:11px;opacity:.75;margin-top:1px}
.ag-check{font-size:18px;flex-shrink:0}
.ag-ok{background:#d4edda;border-left:3px solid #28a745;color:#155724}
.ag-nopudo{background:#fff3cd;border-left:3px solid #ffc107;color:#856404}
.ag-no{background:#fdecea;border-left:3px solid #E05C5C;color:#721c24}
.ag-vencido{background:#fdecea;border-left:3px solid #c0392b;color:#c0392b}
.ag-pendiente{background:#e8f4fd;border-left:3px solid #2980b9;color:#1a5276}
.ag-alerta{background:#fdecea;border-left:5px solid #c0392b;padding:12px 18px;border-radius:8px;margin-bottom:16px}
.ag-vacio{text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px}
.leyenda{display:flex;gap:10px;flex-wrap:wrap;font-size:12px;margin-bottom:16px;align-items:center}
.leyenda-item{display:flex;align-items:center;gap:5px}
.leyenda-box{width:12px;height:12px;border-radius:3px;flex-shrink:0}
#mini-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.mini-card{background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:22px 26px;width:100%;max-width:420px;position:relative}
.btn-estado{display:flex;align-items:center;gap:10px;width:100%;padding:12px 14px;border:2px solid transparent;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;margin-bottom:8px;text-align:left}
.be-ok{background:#d4edda;color:#155724;border-color:#28a745}
.be-nopudo{background:#fff3cd;color:#856404;border-color:#ffc107}
.be-no{background:#fdecea;color:#721c24;border-color:#E05C5C}
</style>

<?php
$titulo_agenda = 'Agenda del turno';
$link_hoy = 'agenda.php';
if ($id_hab_filtro) {
    $hab_nombre = $pdo->prepare("SELECT numero, sector FROM habitaciones WHERE id_habitacion=?");
    $hab_nombre->execute([$id_hab_filtro]);
    $hab_nombre = $hab_nombre->fetch();
    if ($hab_nombre) {
        $titulo_agenda = 'Habitación ' . $hab_nombre['numero'] . ', ' . $hab_nombre['sector'];
    }
    $link_hoy = 'agenda.php?habitacion=' . $id_hab_filtro;
}
?>

<div class="agenda-header">
    <h1 style="margin:0"><?= htmlspecialchars($titulo_agenda) ?></h1>
    <form method="get" style="display:flex;align-items:center;gap:8px">
        <?php if ($id_hab_filtro): ?><input type="hidden" name="habitacion" value="<?= $id_hab_filtro ?>"><?php endif; ?>
        <label style="font-size:13px;font-weight:600">Fecha:</label>
        <input type="date" name="fecha" value="<?= $fecha ?>" style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px">
        <button type="submit" class="btn">Ver</button>
        <?php if (!$es_hoy): ?><a href="<?= $link_hoy ?>" class="btn gray">Hoy</a><?php endif; ?>
        <?php if ($id_hab_filtro): ?><a href="<?= BASE_URL ?>/habitaciones/listar.php" class="btn gray">← Todas las habitaciones</a><?php endif; ?>
    </form>
</div>


<div style="background:var(--bg-surface);border-radius:8px;padding:10px 16px;margin-bottom:14px">
    <b><?= $es_hoy ? 'Hoy: ' : '' ?><?= $fecha_label ?></b>
    <?php if ($es_hoy): ?><span style="font-size:12px;color:#888;margin-left:8px">Hora actual: <?= $hora_actual ?></span><?php endif; ?>
    <?php if (!empty($menu_dia)): ?>
        <span style="font-size:12px;color:#e67e22;margin-left:12px">Menu del dia: <?= htmlspecialchars(implode(' - ', array_column($menu_dia,'titulo'))) ?></span>
    <?php endif; ?>
</div>

<?php if ($total_vencidos > 0): ?>
<div class="ag-alerta">
    <b style="color:#c0392b">Hay <?= $total_vencidos ?> item<?= $total_vencidos>1?'s':'' ?> vencido<?= $total_vencidos>1?'s':'' ?> sin registrar</b>
</div>
<?php endif; ?>

<div class="leyenda">
    <span class="leyenda-item"><div class="leyenda-box" style="background:#d4edda;border-left:3px solid #28a745"></div> Completado</span>
    <span class="leyenda-item"><div class="leyenda-box" style="background:#fff3cd;border-left:3px solid #ffc107"></div> No pudo</span>
    <span class="leyenda-item"><div class="leyenda-box" style="background:#fdecea;border-left:3px solid #E05C5C"></div> No realizado</span>
    <span class="leyenda-item"><div class="leyenda-box" style="background:#fdecea;border-left:3px solid #c0392b"></div> Vencido</span>
    <span class="leyenda-item"><div class="leyenda-box" style="background:#e8f4fd;border-left:3px solid #2980b9"></div> Pendiente</span>
    <span class="leyenda-item"><span class="ag-badge badge-med">MED</span> Medicacion</span>
    <span class="leyenda-item"><span class="ag-badge badge-tar">TAR</span> Tarea</span>
    <span class="leyenda-item"><span class="ag-badge badge-ctl">CTL</span> Control clínico</span>
    <span class="leyenda-item"><span class="ag-badge" style="background:#e67e22;color:#fff">COMIDA</span> Comida</span>
</div>

<?php if (empty($agenda)): ?>
<div class="ag-vacio"><b>No hay medicaciones, tareas ni comidas para el <?= $fecha_label ?></b></div>
<?php else: ?>
    <?php foreach ($agenda as $hora => $blk):
        $hv = $es_hoy && $hora < $hora_actual; ?>
    <div class="hora-bloque">
        <div class="hora-titulo">
            <div class="hora-pill <?= $hv?'vencida':'' ?>"><?= $hora ?></div>
            <div class="hora-linea"></div>
        </div>

        <?php if (isset($blk['comida'])): $com = $blk['comida']; ?>
        <div class="comida-bloque">
            <span class="ag-badge" style="background:rgba(255,255,255,.25);color:#fff">COMIDA</span>
            <div>
                <b><?= ucfirst(htmlspecialchars($com['tipo_comida'])) ?></b>
                <?php if ($com['descripcion']): ?><small> - <?= htmlspecialchars($com['descripcion']) ?></small><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($blk['res'])):
            foreach ($blk['res'] as $ih => $ress):
                foreach ($ress as $ir => $rd):
                    $info = $rd['info'];
                    $lh = $info['hab_numero'] ? 'Hab. '.$info['hab_numero'].($info['hab_sector']?' - '.$info['hab_sector']:'') : 'Sin habitacion';
        ?>
        <div class="res-bloque">
            <div class="res-info">
                <b><?= htmlspecialchars($info['res_nombre'].' '.$info['res_apellido']) ?></b>
                <small><?= htmlspecialchars($lh) ?></small>
                <?php if ($info['cama']): ?><small>Cama: <?= htmlspecialchars($info['cama']) ?></small><?php endif; ?>
            </div>
            <div class="res-items">
                <?php foreach ($rd['items'] as $it):
                    $cl = ag_clase($it['estado_hoy'],$hora,$es_hoy,$hora_actual);
                    $em = $it['tipo_item']==='med';
                    $ec = $it['tipo_item']==='ctl';
                    $yr = !empty($it['estado_hoy']);
                    $ti = $em ? $it['med_nombre'] : $it['titulo'];
                    $de = $em
                        ? trim(($it['dosis']??'').($it['indicacion']?' - '.$it['indicacion']:''))
                        : ($it['descripcion'] ?? ($it['observaciones'] ?? ''));
                    $payload = json_encode([
                        'tipo'         => $it['tipo_item'],
                        'id_plan'      => $it['id_plan']??null,
                        'id_tarea'     => $it['id_tarea']??null,
                        'id_med'       => $it['id_medicamento']??null,
                        'id_res'       => $it['id_residente'],
                        'tipo_control' => $it['tipo_control']??null,
                        'titulo'       => $ti,
                        'detalle'      => $de,
                        'residente'    => $info['res_nombre'].' '.$info['res_apellido'],
                        'hora'         => $hora,
                        'ya_reg'       => $yr,
                        'estado'       => $it['estado_hoy'],
                        'obs'          => $it['obs_hoy'],
                        'reg_por'      => $it['reg_por'],
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    $badge_clase = $em ? 'badge-med' : ($ec ? 'badge-ctl' : 'badge-tar');
                    $badge_texto = $em ? 'MED'       : ($ec ? 'CTL'       : 'TAR');
                ?>
                <div class="ag-item <?= $cl ?>" onclick='abrirMini(<?= $payload ?>)'>
                    <span class="ag-badge <?= $badge_clase ?>"><?= $badge_texto ?></span>
                    <div class="ag-body">
                        <b><?= htmlspecialchars($ti) ?></b>
                        <?php if ($em && !empty($it['med_presentacion'])): ?>
                            <small><?= htmlspecialchars($it['med_presentacion']) ?></small>
                        <?php endif; ?>
                        <?php if ($de): ?><small><?= htmlspecialchars($de) ?></small><?php endif; ?>
                        <?php if ($em && !$yr): ?>
                            <small>Debe administrar: <?= pill_responsable($pdo, $it, $fecha) ?></small>
                        <?php endif; ?>
                        <?php if ($yr): ?>
                        <small><b><?= ag_lbl($it['estado_hoy']) ?></b><?= $it['reg_por']?' por '.htmlspecialchars($it['reg_por']):'' ?><?= $it['obs_hoy']?' - '.htmlspecialchars($it['obs_hoy']):'' ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="ag-check"><?= $yr ? '&#10003;' : '&#9711;' ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; endforeach; endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div id="mini-modal">
    <div class="mini-card">
        <button onclick="cerrarMini()" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">&times;</button>
        <div style="margin-bottom:14px">
            <div id="mini-hora" style="display:inline-block;background:#1F4E79;color:#fff;padding:3px 12px;border-radius:12px;font-size:13px;font-weight:700"></div>
            <h3 id="mini-titulo" style="margin:8px 0 2px;font-size:17px"></h3>
            <div id="mini-detalle" style="font-size:13px;color:#888"></div>
            <div id="mini-residente" style="font-size:13px;color:#2E75B6;font-weight:600;margin-top:4px"></div>
        </div>
        <div id="mini-ya" style="display:none;background:#d4edda;border-left:4px solid #28a745;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px">
            <b>Ya registrado</b><br><span id="mini-estado-actual"></span>
        </div>
        <form method="post" id="mini-form">
            <input type="hidden" name="fecha_vista"      value="<?= $fecha ?>">
            <input type="hidden" name="habitacion_vista" value="<?= $id_hab_filtro ?: '' ?>">
            <input type="hidden" name="id_plan"          id="mp">
            <input type="hidden" name="id_tarea"         id="mt">
            <input type="hidden" name="id_medicamento"   id="mm">
            <input type="hidden" name="id_residente"     id="mr">
            <input type="hidden" name="tipo_control"     id="mc">

            <div id="mini-botones">
                <button type="button" class="btn-estado be-ok"    onclick="elegir('ok')">    <span style="font-size:18px">&#10003;</span> <span id="lbl-ok">Administrado</span></button>
                <button type="button" class="btn-estado be-nopudo" onclick="elegir('nopudo')"><span style="font-size:18px">&#9888;</span>  No pudo tomarlo</button>
                <button type="button" class="btn-estado be-no"    onclick="elegir('no')">    <span style="font-size:18px">&#10007;</span> <span id="lbl-no">No se dio</span></button>
            </div>

            <div id="mini-ctl-box" style="display:none">
                <label style="font-size:13px;font-weight:600">Valor medido <span style="color:#c0392b">*</span></label>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
                    <input type="number" name="valor_control" id="mini-valor"
                           step="0.01" min="0"
                           style="flex:1;padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:15px">
                    <span id="mini-unidad" style="font-size:13px;color:#888;min-width:40px"></span>
                </div>
                <div id="mini-rango-aviso" style="display:none;background:#fff3cd;border-left:4px solid #e67e22;padding:8px 12px;border-radius:6px;font-size:12px;color:#856404;margin-bottom:10px">
El valor está fuera del rango esperado. Se guardará igual. Verificá con el médico.
                </div>
                <label style="font-size:13px;font-weight:600">Observación <span style="color:#aaa;font-weight:400">(opcional)</span></label>
                <textarea name="motivo" id="mini-obs-ctl" rows="2"
                          style="width:100%;box-sizing:border-box;resize:vertical;margin-bottom:10px"></textarea>
                <div style="display:flex;gap:8px">
                    <button type="submit" class="btn">Guardar control</button>
                    <button type="button" class="btn gray" onclick="cerrarMini()">Cancelar</button>
                </div>
            </div>

            <div id="mini-motivo-box" style="display:none;margin-top:12px">
                <label style="font-size:13px;font-weight:600">Motivo <span style="color:#c0392b">*</span></label>
                <textarea name="motivo" id="mini-motivo" rows="2" placeholder="Explica brevemente por que" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                <div style="display:flex;gap:8px;margin-top:10px">
                    <button type="submit" class="btn">Confirmar</button>
                    <button type="button" class="btn gray" onclick="volverBotones()">Volver</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
var miniData = null;

var RANGOS = {
    tension_sistolica:   {min:90,  max:140, unidad:'mmHg'},
    tension_diastolica:  {min:60,  max:90,  unidad:'mmHg'},
    frecuencia_cardiaca: {min:50,  max:100, unidad:'lpm'},
    temperatura:         {min:35,  max:37.5,unidad:'°C'},
    peso:                {min:30,  max:200, unidad:'kg'},
    glucemia:            {min:70,  max:130, unidad:'mg/dL'},
    saturacion_oxigeno:  {min:90,  max:100, unidad:'%'},
};

function abrirMini(d) {
    miniData = d;
    document.getElementById('mini-hora').textContent      = d.hora;
    document.getElementById('mini-titulo').textContent    = d.titulo;
    document.getElementById('mini-detalle').textContent   = d.detalle || '';
    document.getElementById('mini-residente').textContent = d.residente;
    document.getElementById('mp').value = d.id_plan    || '';
    document.getElementById('mt').value = d.id_tarea   || '';
    document.getElementById('mm').value = d.id_med     || '';
    document.getElementById('mr').value = d.id_res     || '';
    document.getElementById('mc').value = d.tipo_control || '';

    var esCtl = d.tipo === 'ctl';
    var esMed = d.tipo === 'med';

    document.getElementById('mini-botones').style.display  = esCtl ? 'none' : 'block';
    document.getElementById('mini-ctl-box').style.display  = esCtl ? 'block' : 'none';
    document.getElementById('mini-motivo-box').style.display = 'none';

    if (esCtl) {
        var hc = document.getElementById('mini-hidden-ctl');
        if (!hc) {
            hc = document.createElement('input');
            hc.type = 'hidden'; hc.name = 'ag_control'; hc.value = '1'; hc.id = 'mini-hidden-ctl';
            document.getElementById('mini-form').appendChild(hc);
        }
        var rango = RANGOS[d.tipo_control] || {};
        document.getElementById('mini-unidad').textContent = rango.unidad || '';
        document.getElementById('mini-valor').value = '';
        document.getElementById('mini-obs-ctl').value = '';
        document.getElementById('mini-rango-aviso').style.display = 'none';

        document.getElementById('mini-valor').oninput = function() {
            var v = parseFloat(this.value);
            var aviso = document.getElementById('mini-rango-aviso');
            if (!isNaN(v) && rango.min !== undefined && (v < rango.min || v > rango.max)) {
                aviso.style.display = 'block';
            } else {
                aviso.style.display = 'none';
            }
        };
    } else {
        var hc = document.getElementById('mini-hidden-ctl');
        if (hc) hc.remove();
        document.getElementById('lbl-ok').textContent = esMed ? 'Administrado' : 'Realizada';
        document.getElementById('lbl-no').textContent = esMed ? 'No se dio'     : 'No realizada';
    }

    var box = document.getElementById('mini-ya');
    if (d.ya_reg) {
        var t = esCtl ? d.obs : estadoLabel(d.estado);
        if (d.reg_por) t += ' por ' + d.reg_por;
        document.getElementById('mini-estado-actual').textContent = t;
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }

    document.getElementById('mini-modal').style.display = 'flex';
}

function estadoLabel(e) {
    var m = {administrado:'Administrado',realizada:'Realizada',no_pudo:'No pudo',no_dado:'No se dio',no_realizada:'No realizada'};
    return m[e] || 'Pendiente';
}

function elegir(tipo) {
    var esMed = miniData.tipo === 'med';
    var form = document.getElementById('mini-form');
    var campo = esMed ? 'ag_medicacion' : 'ag_tarea';
    var valor;
    if (tipo === 'ok') valor = esMed ? 'administrado' : 'realizada';
    else if (tipo === 'nopudo') valor = 'no_pudo';
    else valor = esMed ? 'no_dado' : 'no_realizada';

    var h = document.getElementById('mini-hidden');
    if (h) h.remove();
    h = document.createElement('input');
    h.type = 'hidden'; h.name = campo; h.value = valor; h.id = 'mini-hidden';
    form.appendChild(h);

    if (tipo === 'ok') { form.submit(); }
    else {
        document.getElementById('mini-botones').style.display = 'none';
        document.getElementById('mini-motivo-box').style.display = 'block';
        document.getElementById('mini-motivo').focus();
    }
}

function volverBotones() {
    document.getElementById('mini-botones').style.display = 'block';
    document.getElementById('mini-motivo-box').style.display = 'none';
    document.getElementById('mini-motivo').value = '';
    var h = document.getElementById('mini-hidden');
    if (h) h.remove();
}

function cerrarMini() { document.getElementById('mini-modal').style.display = 'none'; }

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('mini-modal')) cerrarMini();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarMini();
});
</script>

<?php include '../config/footer.php'; ?>


