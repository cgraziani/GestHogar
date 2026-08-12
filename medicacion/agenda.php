<?php
$seccion = 'agenda';
require_once '../config/session.php';
require_once '../config/db.php';
puede_cuidar();

$fecha       = isset($_GET['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_label = date('d/m/Y', strtotime($fecha));
$es_hoy      = ($fecha === date('Y-m-d'));
$hora_actual = date('H:i');

$meds = $pdo->prepare("
    SELECT mr.id_plan, mr.id_residente, mr.horario, mr.dosis, mr.indicacion,
           m.nombre med_nombre,
           r.nombre res_nombre, r.apellido res_apellido,
           h.numero hab_numero, h.sector hab_sector, h.id_habitacion,
           c.identificador cama_id,
           (SELECT am.estado_adm FROM administracion_medicacion am
            WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = ?
            ORDER BY am.fecha_hora DESC LIMIT 1) estado_hoy,
           (SELECT am.observaciones FROM administracion_medicacion am
            WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = ?
            ORDER BY am.fecha_hora DESC LIMIT 1) obs_hoy
    FROM medicacion_residente mr
    JOIN medicamentos m   ON m.id_medicamento = mr.id_medicamento
    JOIN residentes r     ON r.id_residente   = mr.id_residente
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = r.id_cama
    WHERE mr.estado = 'activo' AND r.estado = 'activo'
      AND mr.horario IS NOT NULL AND mr.horario != ''
    ORDER BY mr.horario, h.numero, r.apellido
");
$meds->execute([$fecha, $fecha]);

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
            ORDER BY at2.fecha_hora DESC LIMIT 1) obs_hoy
    FROM tareas_residente tr
    JOIN residentes r ON r.id_residente = tr.id_residente
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = r.id_cama
    WHERE tr.estado = 'activo' AND r.estado = 'activo'
      AND tr.horario IS NOT NULL AND tr.horario != ''
    ORDER BY tr.horario, h.numero, r.apellido
");
$tar->execute([$fecha, $fecha]);

$agenda   = [];

foreach ($meds->fetchAll() as $m) {
    $hora   = substr($m['horario'], 0, 5);
    $id_hab = $m['id_habitacion'] ?? 0;
    $id_res = $m['id_residente'];
    $agenda[$hora][$id_hab][$id_res]['info'] = [
        'res_nombre'  => $m['res_nombre'],
        'res_apellido'=> $m['res_apellido'],
        'cama'        => $m['cama_id'],
        'hab_numero'  => $m['hab_numero'],
        'hab_sector'  => $m['hab_sector'],
    ];
    $agenda[$hora][$id_hab][$id_res]['items'][] = array_merge($m, ['tipo_item' => 'med']);
}

foreach ($tar->fetchAll() as $t) {
    $hora   = substr($t['horario'], 0, 5);
    $id_hab = $t['id_habitacion'] ?? 0;
    $id_res = $t['id_residente'];
    $agenda[$hora][$id_hab][$id_res]['info'] = [
        'res_nombre'  => $t['res_nombre'],
        'res_apellido'=> $t['res_apellido'],
        'cama'        => $t['cama_id'],
        'hab_numero'  => $t['hab_numero'],
        'hab_sector'  => $t['hab_sector'],
    ];
    $agenda[$hora][$id_hab][$id_res]['items'][] = array_merge($t, ['tipo_item' => 'tar']);
}

ksort($agenda);

function ag_clase($estado, $hora, $es_hoy, $hora_actual) {
    if ($estado === 'administrado' || $estado === 'realizada') return 'ag-ok';
    if ($estado === 'no_pudo')                                  return 'ag-nopudo';
    if ($estado === 'no_realizada' || $estado === 'no_dado')    return 'ag-no';
    if ($es_hoy && $hora < $hora_actual)                        return 'ag-vencido';
    return 'ag-pendiente';
}

$total_vencidos = 0;
foreach ($agenda as $hora => $habs)
    foreach ($habs as $ress)
        foreach ($ress as $rd)
            foreach ($rd['items'] as $item)
                if (ag_clase($item['estado_hoy'], $hora, $es_hoy, $hora_actual) === 'ag-vencido')
                    $total_vencidos++;

include '../config/header.php';
include '../config/menu.php';
?>
<style>
.agenda-header { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px }

.hora-bloque   { margin-bottom:20px }
.hora-titulo   { display:flex;align-items:center;gap:12px;margin-bottom:10px }
.hora-pill     { background:#1F4E79;color:#fff;padding:6px 18px;border-radius:20px;font-size:17px;font-weight:700;min-width:70px;text-align:center }
.hora-pill.vencida { background:#c0392b;animation:pulso .9s infinite alternate }
.hora-linea    { flex:1;height:1px;background:var(--border-color,#ddd) }

.res-bloque    { display:flex;gap:0;margin-bottom:8px;border-radius:8px;overflow:hidden;border:1px solid var(--border-color,#ddd) }
.res-info      { background:#2E75B6;color:#fff;padding:10px 14px;min-width:200px;max-width:220px;display:flex;flex-direction:column;justify-content:center;gap:2px }
.res-info b    { font-size:14px }
.res-info small { font-size:11px;opacity:.8 }
.res-items     { flex:1;padding:8px 12px;background:var(--bg-surface);display:flex;flex-direction:column;gap:6px }

.ag-item       { display:flex;align-items:flex-start;gap:8px;padding:6px 10px;border-radius:6px;font-size:13px }
.ag-badge      { font-size:10px;padding:1px 7px;border-radius:8px;font-weight:700;white-space:nowrap;flex-shrink:0 }
.badge-med     { background:#1F4E79;color:#fff }
.badge-tar     { background:#375623;color:#fff }
.ag-body       { flex:1 }
.ag-body b     { font-size:13px }
.ag-body small { display:block;font-size:11px;opacity:.7;margin-top:1px }

.ag-ok         { background:#d4edda;border-left:3px solid #28a745;color:#155724 }
.ag-nopudo     { background:#fff3cd;border-left:3px solid #ffc107;color:#856404 }
.ag-no         { background:#fdecea;border-left:3px solid #E05C5C;color:#721c24 }
.ag-vencido    { background:#fdecea;border-left:3px solid #c0392b;color:#c0392b;animation:pulso .9s infinite alternate }
.ag-pendiente  { background:#e8f4fd;border-left:3px solid #2980b9;color:#1a5276 }
@keyframes pulso { from{opacity:1} to{opacity:.55} }

.ag-alerta     { background:#fdecea;border-left:5px solid #c0392b;padding:12px 18px;border-radius:8px;margin-bottom:16px;display:flex;gap:12px;align-items:center }
.ag-vacio      { text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px }
.leyenda       { display:flex;gap:10px;flex-wrap:wrap;font-size:12px;margin-bottom:16px;align-items:center }
.leyenda-item  { display:flex;align-items:center;gap:5px }
.leyenda-box   { width:12px;height:12px;border-radius:3px;flex-shrink:0 }
</style>

<div class="agenda-header">
    <h1 style="margin:0">Agenda del turno</h1>
    <form method="get" style="display:flex;align-items:center;gap:8px">
        <label style="font-size:13px;font-weight:600">Fecha:</label>
        <input type="date" name="fecha" value="<?= $fecha ?>"
               style="padding:6px 10px;border:1px solid #ddd;border-radius:6px;font-size:13px">
        <button type="submit" class="btn">Ver</button>
        <?php if (!$es_hoy): ?>
            <a href="agenda.php" class="btn gray">Hoy</a>
        <?php endif; ?>
    </form>
</div>

<div style="background:var(--bg-surface);border-radius:8px;padding:10px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
    <div>
        <b><?= $es_hoy ? 'Hoy: ' : '' ?><?= $fecha_label ?></b>
        <?php if ($es_hoy): ?>
            <span style="font-size:12px;color:#888;margin-left:8px">Hora actual: <?= $hora_actual ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if ($total_vencidos > 0): ?>
<div class="ag-alerta">
    <div>
        <b style="color:#c0392b">Hay <?= $total_vencidos ?> item<?= $total_vencidos > 1 ? 's' : '' ?> vencido<?= $total_vencidos > 1 ? 's' : '' ?> sin registrar</b><br>
        <span style="font-size:13px;color:#555">El horario ya paso y no se registro el estado.</span>
    </div>
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
</div>

<?php if (empty($agenda)): ?>
<div class="ag-vacio">
    <b>No hay medicaciones ni tareas programadas para el <?= $fecha_label ?></b><br>
    <span style="font-size:13px">Verifique que haya residentes activos con planes de medicacion y tareas con horario asignado.</span>
</div>
<?php else: ?>
    <?php foreach ($agenda as $hora => $habs):
        $hora_vencida = $es_hoy && $hora < $hora_actual;
    ?>
    <div class="hora-bloque">

        <div class="hora-titulo">
            <div class="hora-pill <?= $hora_vencida ? 'vencida' : '' ?>"><?= $hora ?></div>
            <div class="hora-linea"></div>
        </div>

        <?php foreach ($habs as $id_hab => $ress):
            foreach ($ress as $id_res => $rd):
                $info = $rd['info'];
                $label_hab = $info['hab_numero']
                    ? 'Hab. ' . $info['hab_numero'] . ($info['hab_sector'] ? ', ' . $info['hab_sector'] : '')
                    : 'Sin habitacion';
        ?>
        <div class="res-bloque">

            <div class="res-info">
                <b><?= htmlspecialchars($info['res_nombre'] . ' ' . $info['res_apellido']) ?></b>
                <small><?= htmlspecialchars($label_hab) ?></small>
                <?php if ($info['cama']): ?>
                    <small>Cama: <?= htmlspecialchars($info['cama']) ?></small>
                <?php endif; ?>
            </div>

            <div class="res-items">
                <?php foreach ($rd['items'] as $item):
                    $clase  = ag_clase($item['estado_hoy'], $hora, $es_hoy, $hora_actual);
                    $es_med = $item['tipo_item'] === 'med';
                ?>
                <div class="ag-item <?= $clase ?>">
                    <span class="ag-badge <?= $es_med ? 'badge-med' : 'badge-tar' ?>"><?= $es_med ? 'MED' : 'TAR' ?></span>
                    <div class="ag-body">
                        <b><?= htmlspecialchars($es_med ? $item['med_nombre'] : $item['titulo']) ?></b>
                        <?php if ($es_med && $item['dosis']): ?>
                            <small><?= htmlspecialchars($item['dosis']) ?><?= $item['indicacion'] ? ', ' . htmlspecialchars($item['indicacion']) : '' ?></small>
                        <?php elseif (!$es_med && $item['descripcion']): ?>
                            <small><?= htmlspecialchars($item['descripcion']) ?></small>
                        <?php endif; ?>
                        <?php if ($item['obs_hoy']): ?>
                            <small style="font-style:italic">Nota: <?= htmlspecialchars($item['obs_hoy']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php endforeach; endforeach; ?>

    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include '../config/footer.php'; ?>

