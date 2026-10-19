<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';
require_once '../config/ajustes.php';

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$id_res = (int)($_GET['residente'] ?? 0);

$residentes = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes
    WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();

$where = "vm.motivo != 'Asignación inicial por administradora'";

if ($id_res) $where .= " AND vm.id_residente=$id_res";
$where_fecha = "DATE(vm.fecha_hora) BETWEEN '$desde' AND '$hasta' AND $where";

$detalle = $pdo->query("
    SELECT r.nombre rn, r.apellido ra,
           m.nombre mn, m.apellido ma,
           m.tipo_profesional, m.especialidad,
           vm.fecha_hora, vm.motivo, vm.diagnostico, vm.indicaciones
    FROM visitas_medicas vm
    JOIN residentes r ON r.id_residente=vm.id_residente
    JOIN medicos m ON m.id_medico=vm.id_medico
    WHERE $where_fecha
    ORDER BY r.apellido, r.nombre, vm.fecha_hora DESC
")->fetchAll();

$TIPOS = ['medico'=>'Médico/a','psiquiatra'=>'Psiquiatra','enfermero'=>'Enfermero/a',
    'kinesiologo'=>'Kinesiólogo/a','fisioterapeuta'=>'Fisioterapeuta','podologo'=>'Podólogo/a',
    'fonoaudiologo'=>'Fonoaudiólogo/a','psicologo'=>'Psicólogo/a',
    'terapista_ocupacional'=>'Terapista Ocupacional','nutricionista'=>'Nutricionista',
    'peluquero'=>'Peluquero/a','manicura'=>'Manicura','otro'=>'Otro'];

include '../config/menu.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <h1 style="margin:0">Atenciones de profesionales por residente</h1>
    <a href="index.php" class="btn gray">← Reportes</a>
</div>

<div class="card no-imprimir" style="margin-bottom:16px">
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Residente</label>
            <select name="residente" class="buscador" style="min-width:200px">
                <option value="0">Todos</option>
                <?php foreach ($residentes as $r): ?>
                    <option value="<?= $r['id_residente'] ?>" <?= $id_res==$r['id_residente']?'selected':'' ?>>
                        <?= h($r['nombre'].' '.$r['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Desde</label>
            <input type="date" name="desde" value="<?= h($desde) ?>">
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Hasta</label>
            <input type="date" name="hasta" value="<?= h($hasta) ?>">
        </div>
        <button type="submit" class="btn">Filtrar</button>
        <a href="profesionales.php" class="btn gray">Limpiar</a>
        <button type="button" class="btn gray no-imprimir" onclick="window.print()">Imprimir</button>
    </form>
</div>

<?php if (empty($detalle)): ?>
<div style="text-align:center;padding:48px;color:var(--text-muted);background:var(--bg-surface);border-radius:10px">
    <b>Sin atenciones en el período seleccionado</b>
</div>
<?php else: ?>
<div class="card">
    <table class="datatable" style="width:100%;font-size:13px">
        <thead><tr>
            <th>Residente</th>
            <th>Profesional</th>
            <th>Rubro</th>
            <th>Fecha y hora</th>
            <th>Motivo</th>
            <th>Diagnóstico / Evolución</th>
            <th>Indicaciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($detalle as $d): ?>
        <tr>
            <td><b><?= h($d['rn'].' '.$d['ra']) ?></b></td>
            <td><?= h($d['ma'].', '.$d['mn']) ?></td>
            <td><span class="badge"><?= h($TIPOS[$d['tipo_profesional']] ?? $d['tipo_profesional']) ?></span></td>
            <td><?= date('d/m/Y H:i', strtotime($d['fecha_hora'])) ?></td>
            <td><?= h($d['motivo']) ?></td>
            <td><span class="small"><?= h($d['diagnostico'] ?: '') ?></span></td>
            <td><span class="small"><?= h($d['indicaciones'] ?: '') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include '../config/footer.php'; ?>

