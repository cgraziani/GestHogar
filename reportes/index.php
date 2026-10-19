<?php
require_once 'comun.php';
abrir_pagina_reporte();

$rango = 'desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta);

$grupos = [
    'Residentes' => [
        ['residentes.php',          'Padrón de residentes', true],
        ['residente.php',           'Evolución de salud', false],
        ['hogar.php',               'Habitaciones y ocupación', true],
    ],
    'Atención clínica' => [
        ['medicacion.php',          'Medicación', true],
        ['incidentes.php',          'Incidentes', true],
        ['tareas.php',              'Tareas de rutina', true],
        ['actividades.php',         'Actividades recreativas', true],
        ['../controles/listar.php', 'Controles clínicos', false],
    ],
    'Visitas y turnos' => [
        ['visitas.php',             'Visitas', true],
        ['traspasos.php',           'Traspaso de turno', true],
        ['profesionales.php',       'Atenciones de profesionales', true],
    ],
];
?>

<h1>Reportes</h1>

<div class="card no-imprimir">
    <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Desde</label>
            <input type="date" name="desde" value="<?= h($desde) ?>">
        </div>
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Hasta</label>
            <input type="date" name="hasta" value="<?= h($hasta) ?>">
        </div>
        <button type="submit" class="btn">Aplicar rango</button>
        <a class="btn gray" href="dashboard.php?<?= h($rango) ?>">Ver dashboard</a>
    </form>
    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn gray" href="?desde=<?= date('Y-m-d') ?>&hasta=<?= date('Y-m-d') ?>">Hoy</a>
        <a class="btn gray" href="?desde=<?= date('Y-m-d', strtotime('-6 days')) ?>&hasta=<?= date('Y-m-d') ?>">Últimos 7 días</a>
        <a class="btn gray" href="?desde=<?= date('Y-m-d', strtotime('-29 days')) ?>&hasta=<?= date('Y-m-d') ?>">Últimos 30 días</a>
 
    </div>
</div>

<?php foreach ($grupos as $titulo_grupo => $reportes): ?>
<h2 style="margin-top:28px"><?= h($titulo_grupo) ?></h2>
<div class="grid">
    <?php foreach ($reportes as [$archivo, $titulo, $con_rango]): ?>
        <a href="<?= h($archivo . ($con_rango ? '?' . $rango : '')) ?>" style="text-decoration:none">
            <div class="card" style="cursor:pointer;transition:box-shadow .15s"
                 onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)'"
                 onmouseout="this.style.boxShadow=''">
                <b style="display:block;margin:0;font-size:15px"><?= h($titulo) ?></b>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="card" style="margin-top:28px">
    <h2 style="margin-top:0">Exportación rápida a Excel</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn blue" href="<?= h('medicacion.php?'.$rango.'&exportar=1') ?>">Medicación</a>
        <a class="btn blue" href="<?= h('incidentes.php?'.$rango.'&exportar=1') ?>">Incidentes</a>
        <a class="btn blue" href="<?= h('visitas.php?'.$rango.'&exportar=1') ?>">Visitas</a>
        <a class="btn gray" href="<?= h('traspasos.php?'.$rango.'&exportar=1') ?>">Traspasos</a>
        <a class="btn gray" href="<?= h('actividades.php?'.$rango.'&exportar=1') ?>">Actividades</a>
        <a class="btn gray" href="<?= h('tareas.php?'.$rango.'&exportar=1') ?>">Tareas</a>
        <a class="btn gray" href="<?= h('residentes.php?'.$rango.'&exportar=1') ?>">Residentes</a>
        <a class="btn gray" href="<?= h('hogar.php?'.$rango.'&exportar=1') ?>">Habitaciones</a>
    </div>
</div>

<?php include '../config/footer.php'; ?>


