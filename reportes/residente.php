<?php
require_once 'comun.php';
abrir_pagina_reporte();

echo '<h1>Evolución de salud del residente</h1>';

global $desde, $hasta, $residentes;
?>
<div class="card no-imprimir">
    <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Residente <span style="color:red">*</span></label>
            <select name="residente" required style="min-width:220px">
                <option value="">Elegir residente</option>
                <?php foreach ($residentes as $r): ?>
                    <option value="<?= $r['id_residente'] ?>"
                        <?= $id_residente == $r['id_residente'] ? 'selected' : '' ?>>
                        <?= h($r['nombre'].' '.$r['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Desde</label>
            <input type="date" name="desde" value="<?= h($desde) ?>">
        </div>
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Hasta</label>
            <input type="date" name="hasta" value="<?= h($hasta) ?>">
        </div>
        <button type="submit" class="btn">Ver evolución</button>
    </form>
</div>
<?php

if (!$id_residente) {
    echo '<div class="card" style="text-align:center;padding:40px">
        <b>Seleccioná un residente arriba para ver su evolución de salud.</b>
    </div>';
    include '../config/footer.php';
    exit;
}

$res = $pdo->prepare("SELECT r.*, h.numero habitacion, c.identificador cama
    FROM residentes r
    LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
    LEFT JOIN camas c ON c.id_cama=r.id_cama
    WHERE r.id_residente=?");
$res->execute([$id_residente]);
$residente = $res->fetch();
if (!$residente) { header('Location: residente.php'); exit; }

$rangos_raw = $pdo->query("SELECT * FROM rangos_control")->fetchAll();
$rangos = [];
foreach ($rangos_raw as $r) $rangos[$r['tipo_control']] = $r;

$controles = $pdo->prepare("SELECT cc.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) registrado_por
    FROM controles_clinicos cc
    LEFT JOIN usuarios u ON u.id_usuario=cc.id_usuario
    WHERE cc.id_residente=? AND cc.fecha_hora BETWEEN ? AND ?
    ORDER BY cc.fecha_hora");
$controles->execute([$id_residente, $desde_dt, $hasta_dt]);
$datos_ctrl = $controles->fetchAll();

$ctrl_por_tipo = [];
foreach ($datos_ctrl as $c) {
    $ctrl_por_tipo[$c['tipo_control']][] = $c;
}

$med = $pdo->prepare("SELECT am.fecha_hora, am.estado_adm, am.observaciones,
    m.nombre med, mr.dosis, mr.horario, rec.diagnostico, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora
    FROM administracion_medicacion am
    JOIN medicamentos m ON m.id_medicamento=am.id_medicamento
    LEFT JOIN medicacion_residente mr ON mr.id_plan=am.id_plan
    LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
    LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
    WHERE am.id_residente=? AND am.fecha_hora BETWEEN ? AND ?
    ORDER BY am.fecha_hora DESC");
$med->execute([$id_residente, $desde_dt, $hasta_dt]);
$datos_med = $med->fetchAll();
$adm_ok  = count(array_filter($datos_med, fn($d) => $d['estado_adm']==='administrado'));
$adm_tot = count($datos_med);

$inc = $pdo->prepare("SELECT i.*, u.nombre reporto
    FROM incidentes i
    LEFT JOIN usuarios u ON u.id_usuario=i.id_usuario
    WHERE i.id_residente=? AND i.fecha_hora BETWEEN ? AND ?
    ORDER BY i.fecha_hora DESC");
$inc->execute([$id_residente, $desde_dt, $hasta_dt]);
$datos_inc = $inc->fetchAll();

$vis = $pdo->prepare("SELECT * FROM visitas
    WHERE id_residente=? AND COALESCE(hora_ingreso, fecha_programada, created_at) BETWEEN ? AND ?
    ORDER BY COALESCE(hora_ingreso, fecha_programada) DESC");
$vis->execute([$id_residente, $desde_dt, $hasta_dt]);
$datos_vis = $vis->fetchAll();

$etiq_ctrl = ['tension_sistolica'=>'Tensión sistólica','tension_diastolica'=>'Tensión diastólica',
    'temperatura'=>'Temperatura','peso'=>'Peso','glucemia'=>'Glucemia',
    'saturacion_oxigeno'=>'Saturación O₂','frecuencia_cardiaca'=>'Frecuencia cardíaca'];
$etiq_inc = ['caida'=>'Caída','lesion'=>'Lesión','descompensacion'=>'Descompensación',
    'error_medicacion'=>'Error medicación','conducta'=>'Conducta','fuga'=>'Salida s/aviso',
    'atragantamiento'=>'Atragantamiento','otro'=>'Otro'];

$cols_ctrl = ['Fecha y hora','Tipo de control','Valor','Unidad','Observaciones','Registrado por'];
$filas_ctrl = [];
foreach ($datos_ctrl as $c) {
    $rango = $rangos[$c['tipo_control']] ?? null;
    $fuera = $rango && ((float)$c['valor'] < $rango['valor_min'] || (float)$c['valor'] > $rango['valor_max']);
    $filas_ctrl[] = [
        fecha_hora($c['fecha_hora']),
        $etiq_ctrl[$c['tipo_control']] ?? $c['tipo_control'],
        ['texto'=>$c['valor'], 'html'=>'<b'.($fuera?' style="color:#e74c3c"':'').'>'.$c['valor'].'</b>'],
        $c['unidad'],
        $c['observaciones'] ?: '',
        $c['registrado_por'] ?: '',
    ];
}

if (exportando()) exportar_csv('controles-'.$residente['apellido'], $cols_ctrl, $filas_ctrl);
if (imprimiendo()) { vista_impresion(
    'Evolución de salud: '.$residente['nombre'].' '.$residente['apellido'],
    subtitulo_rango(), $cols_ctrl, $filas_ctrl
); exit; }

echo '<h2>'.h($residente['nombre'].' '.$residente['apellido']).'</h2>';
$edad = $residente['fecha_nacimiento']
    ? (new DateTime($residente['fecha_nacimiento']))->diff(new DateTime())->y . ' años'
    : '';
echo '<p class="small">'.h($edad).' · Hab. '.h($residente['habitacion'] ?: '').' · Cama '.h($residente['cama'] ?: '')
   .' · '.date('d/m/Y', strtotime($desde)).' al '.date('d/m/Y', strtotime($hasta)).'</p>';

echo '<div class="grid">';
$adh_porc = $adm_tot > 0 ? round($adm_ok * 100 / $adm_tot) : null;
$color_adh = $adh_porc === null ? '#888' : ($adh_porc >= 90 ? '#27ae60' : ($adh_porc >= 70 ? '#e67e22' : '#e74c3c'));
echo '<div class="card" style="text-align:center"><div class="stat">'.count($datos_ctrl).'</div><b>Controles clínicos</b></div>';
echo '<div class="card" style="text-align:center"><div class="stat" style="color:'.$color_adh.'">'.($adh_porc !== null ? $adh_porc.'%' : '').'</div><b>Adhesión medicación</b><br><span class="small">'.$adm_ok.' de '.$adm_tot.' tomas</span></div>';
echo '<div class="card" style="text-align:center"><div class="stat">'.count($datos_inc).'</div><b>Incidentes</b><br><span class="small">'.count(array_filter($datos_inc, fn($i)=>$i['gravedad']==='grave')).' graves</span></div>';
echo '<div class="card" style="text-align:center"><div class="stat">'.count($datos_vis).'</div><b>Visitas</b></div>';
echo '</div>';

$tipos_graficos = ['tension_sistolica','tension_diastolica','glucemia','peso','saturacion_oxigeno','temperatura'];
$graficos = [];
foreach ($tipos_graficos as $tipo) {
    if (!empty($ctrl_por_tipo[$tipo])) {
        $graficos[$tipo] = $ctrl_por_tipo[$tipo];
    }
}

if (!empty($graficos)): ?>
<div class="card">
    <h3 style="margin-top:0">Evolución de controles clínicos</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(380px,1fr))">
        <?php foreach ($graficos as $tipo => $puntos):
            $rango = $rangos[$tipo] ?? null;
        ?>
        <div style="position:relative;margin-bottom:16px">
            <b><?= h($etiq_ctrl[$tipo] ?? $tipo) ?></b>
            <canvas id="grafico-<?= $tipo ?>" height="160"></canvas>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php foreach ($graficos as $tipo => $puntos):
    $rango = $rangos[$tipo] ?? null;
    $labels = array_map(fn($p) => date('d/m H:i', strtotime($p['fecha_hora'])), $puntos);
    $valores = array_map(fn($p) => (float)$p['valor'], $puntos);
    $rango_tmp = $rango;
    $colores = array_map(function($v) use ($rango_tmp) {
        return ($rango_tmp && ($v < $rango_tmp['valor_min'] || $v > $rango_tmp['valor_max']))
            ? '#e74c3c' : '#2D9B8A';
    }, $valores);
?>
new Chart(document.getElementById('grafico-<?= $tipo ?>'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: '<?= h($etiq_ctrl[$tipo] ?? $tipo) ?>',
            data:  <?= json_encode($valores) ?>,
            borderColor: '#2D9B8A',
            backgroundColor: 'rgba(45,155,138,.08)',
            pointBackgroundColor: <?= json_encode($colores) ?>,
            pointRadius: 5, tension: .3, fill: true
        }
        <?php if ($rango): ?>
        , {
            label: 'Rango máx.',
            data:  Array(<?= count($valores) ?>).fill(<?= $rango['valor_max'] ?>),
            borderColor: 'rgba(231,76,60,.3)', borderDash:[6,4],
            pointRadius:0, fill:'+1', backgroundColor:'rgba(231,76,60,.05)'
        }, {
            label: 'Rango mín.',
            data:  Array(<?= count($valores) ?>).fill(<?= $rango['valor_min'] ?>),
            borderColor: 'rgba(231,76,60,.3)', borderDash:[6,4],
            pointRadius:0, fill:false
        }
        <?php endif; ?>
        ]
    },
    options:{
        plugins:{ legend:{ display:false } },
        scales:{ y:{ ticks:{ callback: v => v+' <?= h($rango['unidad'] ?? '') ?>' } } }
    }
});
<?php endforeach; ?>
</script>
<?php endif; ?>

<?php if (!empty($datos_ctrl)): ?>
<div class="card">
    <h3 style="margin-top:0">Historial de controles clínicos</h3>
    <?php tabla_reporte($cols_ctrl, $filas_ctrl); ?>
</div>
<?php endif; ?>

<?php if (!empty($datos_med)): ?>
<div class="card">
    <h3 style="margin-top:0">Medicación en el período</h3>
    <table class="datatable">
        <thead><tr><th>Fecha</th><th>Medicamento</th><th>Dosis</th><th>Tratamiento</th><th>Estado</th><th>Motivo</th><th>Cuidadora</th></tr></thead>
        <tbody>
        <?php foreach ($datos_med as $m): ?>
            <tr>
                <td><?= fecha_hora($m['fecha_hora']) ?></td>
                <td><?= h($m['med']) ?></td>
                <td><?= h($m['dosis'] ?: '') ?></td>
                <td><?= h($m['diagnostico'] ?: '') ?></td>
                <td><?= celda_html(pill_estado_medicacion($m['estado_adm'])) ?></td>
                <td><?= h($m['observaciones'] ?: '') ?></td>
                <td><?= h($m['cuidadora'] ?: '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($datos_inc)): ?>
<div class="card">
    <h3 style="margin-top:0">Incidentes en el período</h3>
    <table class="datatable">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Gravedad</th><th>Descripción</th><th>Acciones tomadas</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($datos_inc as $i): ?>
            <tr>
                <td><?= fecha_hora($i['fecha_hora']) ?></td>
                <td><?= h($etiq_inc[$i['tipo_incidente']] ?? $i['tipo_incidente']) ?></td>
                <td><?= celda_html(pill_gravedad($i['gravedad'])) ?></td>
                <td><?= h($i['descripcion']) ?></td>
                <td><?= h($i['acciones_tomadas']) ?></td>
                <td><?= $i['estado']==='revisado' ? celda_html(pill('Revisado','pill-ok')) : celda_html(pill('Pendiente','pill-aviso')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($datos_vis)): ?>
<div class="card">
    <h3 style="margin-top:0">Visitas en el período</h3>
    <table class="datatable">
        <thead><tr><th>Fecha</th><th>Visitante</th><th>Parentesco</th><th>Ingreso</th><th>Egreso</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($datos_vis as $v): ?>
            <tr>
                <td><?= fecha_corta($v['fecha_programada'] ?? $v['hora_ingreso']) ?></td>
                <td><?= h($v['nombre_visitante'].($v['apellido_visitante'] ? ' '.$v['apellido_visitante'] : '')) ?></td>
                <td><?= h($v['parentesco']) ?></td>
                <td><?= $v['hora_ingreso'] ? fecha_hora($v['hora_ingreso']) : '' ?></td>
                <td><?= $v['hora_egreso']  ? fecha_hora($v['hora_egreso'])  : '' ?></td>
                <td><?= h($v['estado']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
