<?php
require_once 'comun.php';
require_once '../medicacion/responsable.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)  $where .= ' AND am.id_residente = ' . $id_residente;
if ($filtro_estado === 'omisiones') $where .= " AND am.estado_adm IN('no_pudo','no_dado')";
elseif ($filtro_estado !== '')      $where .= " AND am.estado_adm = " . $pdo->quote($filtro_estado);

$dias_rango = max(1, (strtotime($hasta) - strtotime($desde)) / 86400 + 1);
$dias_rango = min((int)$dias_rango, 30);

$adhesion_real = $pdo->prepare("SELECT
    COUNT(*) esperadas,
    SUM(CASE WHEN am.estado_adm='administrado' THEN 1 ELSE 0 END) administradas,
    SUM(CASE WHEN am.estado_adm IN('no_pudo','no_dado') THEN 1 ELSE 0 END) omitidas,
    SUM(CASE WHEN am.id_adm IS NULL THEN 1 ELSE 0 END) sin_registrar
    FROM (
        SELECT mr.id_plan, mr.id_residente,
               DATE_ADD(?, INTERVAL seq.n DAY) AS dia
        FROM medicacion_residente mr
        JOIN (
            SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
            UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
            UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
            UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
            UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
        ) seq ON DATE_ADD(?, INTERVAL seq.n DAY) BETWEEN ? AND ?
        WHERE mr.estado='activo'
          AND (mr.fecha_inicio IS NULL OR DATE_ADD(?, INTERVAL seq.n DAY) >= mr.fecha_inicio)
          AND (mr.fecha_fin   IS NULL OR DATE_ADD(?, INTERVAL seq.n DAY) <= mr.fecha_fin)
        " . ($id_residente ? "AND mr.id_residente=$id_residente" : "") . "
    ) tomas
    LEFT JOIN administracion_medicacion am
        ON am.id_plan = tomas.id_plan
       AND DATE(am.fecha_hora) = tomas.dia");
$adhesion_real->execute([$desde,$desde,$desde,$hasta,$desde,$desde]);
$adh = $adhesion_real->fetch();
$porc_adhesion = $adh['esperadas'] > 0
    ? round($adh['administradas'] * 100 / $adh['esperadas'])
    : null;

$adh_semana = $pdo->prepare("SELECT
    DATE_FORMAT(DATE_SUB(am.fecha_hora, INTERVAL WEEKDAY(am.fecha_hora) DAY), '%d/%m') semana,
    ROUND(SUM(am.estado_adm='administrado') * 100 / COUNT(*)) porc,
    COUNT(*) total,
    SUM(am.estado_adm IN('no_pudo','no_dado')) omisiones
    FROM administracion_medicacion am
    WHERE am.fecha_hora BETWEEN ? AND ?
    " . ($id_residente ? "AND am.id_residente=$id_residente" : "") . "
    GROUP BY semana ORDER BY MIN(am.fecha_hora)");
$adh_semana->execute([$desde_dt, $hasta_dt]);
$datos_semana = $adh_semana->fetchAll();

$omis_res = $pdo->prepare("SELECT r.nombre, r.apellido,
    SUM(am.estado_adm IN('no_pudo','no_dado')) omisiones,
    COUNT(*) total,
    ROUND(SUM(am.estado_adm='administrado') * 100 / COUNT(*)) adhesion
    FROM administracion_medicacion am
    JOIN residentes r ON r.id_residente=am.id_residente
    WHERE am.fecha_hora BETWEEN ? AND ?
    " . ($id_residente ? "AND am.id_residente=$id_residente" : "") . "
    GROUP BY am.id_residente ORDER BY omisiones DESC");
$omis_res->execute([$desde_dt, $hasta_dt]);
$datos_por_res = $omis_res->fetchAll();

$omis_cui = $pdo->prepare("SELECT u.nombre, u.apellido,
    COUNT(*) total_registros,
    SUM(am.estado_adm='administrado') administradas,
    SUM(am.estado_adm IN('no_pudo','no_dado')) omisiones
    FROM administracion_medicacion am
    LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
    WHERE am.fecha_hora BETWEEN ? AND ?
    GROUP BY am.id_cuidadora_usuario ORDER BY omisiones DESC");
$omis_cui->execute([$desde_dt, $hasta_dt]);
$datos_por_cui = $omis_cui->fetchAll();

$sth = $pdo->prepare("SELECT am.fecha_hora, am.estado_adm, am.observaciones,
    r.nombre rn, r.apellido ra,
    m.nombre med, m.presentacion,
    mr.dosis, mr.horario AS horario_pautado, mr.id_responsable,
    rec.diagnostico,
    CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora
    FROM administracion_medicacion am
    JOIN residentes r    ON r.id_residente = am.id_residente
    JOIN medicamentos m  ON m.id_medicamento = am.id_medicamento
    LEFT JOIN medicacion_residente mr ON mr.id_plan = am.id_plan
    LEFT JOIN recetas_medicas rec     ON rec.id_receta = mr.id_receta
    LEFT JOIN usuarios u ON u.id_usuario = am.id_cuidadora_usuario
    WHERE am.fecha_hora BETWEEN ? AND ? $where
    ORDER BY am.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Fecha y hora','Residente','Medicamento','Dosis','Horario pautado',
         'Tratamiento','Estado','Motivo / observación','Registrado por'];

$filas = [];
$conteo = ['administrado'=>0,'no_pudo'=>0,'no_dado'=>0];
foreach ($datos as $d) {
    if (isset($conteo[$d['estado_adm']])) $conteo[$d['estado_adm']]++;
    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'].' '.$d['ra'],
        $d['med'].($d['presentacion'] ? ' ('.$d['presentacion'].')' : ''),
        $d['dosis'] ?: '',
        $d['horario_pautado'] ?: '',
        $d['diagnostico'] ?: '',
        pill_estado_medicacion($d['estado_adm']),
        $d['observaciones'] ?: '',
        $d['cuidadora'] ?: '',
    ];
}

$omisiones = $conteo['no_pudo'] + $conteo['no_dado'];
$resumen = [
    ['Adhesión real',    $porc_adhesion === null ? '' : $porc_adhesion.'%', $adh['esperadas'].' tomas esperadas'],
    ['Administradas',   $adh['administradas'],  'Sin problema'],
    ['Omitidas',        $adh['omitidas'],        'No se pudo o no se dio'],
    ['Sin registrar',   $adh['sin_registrar'],   'Nadie tocó el botón'],
];

$sel = fn($v) => $filtro_estado === $v ? 'selected' : '';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="omisiones" '.$sel('omisiones').'>Solo omisiones</option>'
       . '<option value="administrado" '.$sel('administrado').'>Administradas</option>'
       . '<option value="no_pudo" '.$sel('no_pudo').'>No se pudo</option>'
       . '<option value="no_dado" '.$sel('no_dado').'>No administradas</option>'
       . '</select></div>';

salida_reporte('Reporte de medicación', 'medicacion', $cols, $filas);

echo '<h1>Reporte de medicación</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

<?php if ($adh['esperadas'] > 0): ?>
<div class="card" style="margin:0">
    <h3 style="margin-top:0">Distribución de estados</h3>
    <canvas id="grafico-estados" height="220"></canvas>
</div>
<?php endif; ?>

<?php if (!empty($datos_semana)): ?>
<div class="card" style="margin:0">
    <h3 style="margin-top:0">Adhesión por semana</h3>
    <canvas id="grafico-adhesion" height="220"></canvas>
</div>
<?php endif; ?>

</div>

<?php if (!empty($datos_por_res)): ?>
<div class="card">
    <h3 style="margin-top:0">Adhesión por residente</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Residente</th><th>Tomas</th><th>Omisiones</th><th>Adhesión</th></tr></thead>
        <tbody>
        <?php foreach ($datos_por_res as $r): ?>
            <tr>
                <td><?= h($r['nombre'].' '.$r['apellido']) ?></td>
                <td><?= (int)$r['total'] ?></td>
                <td>
                    <?php if ($r['omisiones'] > 0): ?>
                        <b style="color:#e74c3c"><?= (int)$r['omisiones'] ?></b>
                    <?php else: ?>
                        <span style="color:#27ae60">0</span>
                    <?php endif; ?>
                </td>
                <td>
                    <b style="color:<?= $r['adhesion'] >= 90 ? '#27ae60' : ($r['adhesion'] >= 70 ? '#e67e22' : '#e74c3c') ?>">
                        <?= (int)$r['adhesion'] ?>%
                    </b>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($datos_por_cui)): ?>
<div class="card">
    <h3 style="margin-top:0">Registros por cuidadora</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Cuidadora</th><th>Registros</th><th>Administradas</th><th>Omisiones</th></tr></thead>
        <tbody>
        <?php foreach ($datos_por_cui as $c): ?>
            <tr>
                <td><?= h(($c['nombre'] ?? '').' '.($c['apellido'] ?? '')) ?></td>
                <td><?= (int)$c['total_registros'] ?></td>
                <td><?= (int)$c['administradas'] ?></td>
                <td><?= (int)$c['omisiones'] > 0 ? '<b style="color:#e74c3c">'.(int)$c['omisiones'].'</b>' : '0' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php tabla_reporte($cols, $filas); ?>

<?php if (!empty($datos_semana) || $adh['esperadas'] > 0): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if ($adh['esperadas'] > 0): ?>
new Chart(document.getElementById('grafico-estados'), {
    type: 'doughnut',
    data: {
        labels: ['Administradas', 'No se pudo', 'No administradas', 'Sin registrar'],
        datasets: [{
            data: [
                <?= (int)$adh['administradas'] ?>,
                <?= (int)($adh['esperadas'] - $adh['administradas'] - $adh['omitidas'] - ($adh['sin_registrar']??0)) < 0 ? 0 : (int)($adh['esperadas'] - $adh['administradas'] - ($adh['sin_registrar']??0) - (int)$adh['omitidas']) ?>,
                <?= (int)$adh['omitidas'] ?>,
                <?= (int)($adh['sin_registrar']??0) ?>
            ],
            backgroundColor: ['#8cbf9e','#c4a97d','#c98080','#aaa'],
            borderWidth: 2
        }]
    },
    options: {
        cutout: '60%',
        plugins: { legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } }
    }
});
<?php endif; ?>
<?php if (!empty($datos_semana)): ?>
new Chart(document.getElementById('grafico-adhesion'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($datos_semana,'semana')) ?>,
        datasets: [{
            label: 'Adhesión %',
            data:  <?= json_encode(array_column($datos_semana,'porc')) ?>,
            borderColor: '#2D9B8A', backgroundColor: 'rgba(45,155,138,.1)',
            tension: .3, fill: true, pointRadius: 5
        }]
    },
    options: {
        scales: { y: { min:0, max:100, ticks:{ callback: v => v+'%' } } },
        plugins: { legend:{ display:false } }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
