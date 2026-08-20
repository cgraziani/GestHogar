<?php
require_once 'comun.php';
abrir_pagina_reporte();

$rango = 'desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta);

$reportes = [
    ['dashboard.php', 'Estado del hogar', false],
    ['medicacion.php', 'Medicación', true],
    ['traspasos.php', 'Traspaso de turno', true],
    ['visitas.php', 'Visitas', true],
    ['incidentes.php', 'Incidentes', true],
    ['actividades.php', 'Actividades', true],
    ['tareas.php', 'Tareas de rutina', true],
    ['residente.php', 'Consolidado por residente', false],
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
        <button type="submit" class="btn">Usar este rango</button>
    </form>
</div>

<div class="grid">
    <?php foreach ($reportes as [$archivo, $titulo, $con_rango]): ?>
        <a href="<?= h($archivo . ($con_rango ? '?' . $rango : '')) ?>">
            <div class="card">
                <b style="display:block;margin:0;font-size:16px"><?= h($titulo) ?></b>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2 style="margin-top:0">Exportación rápida a Excel</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn blue" href="<?= h('medicacion.php?' . $rango . '&exportar=1') ?>">Medicación</a>
        <a class="btn blue" href="<?= h('visitas.php?' . $rango . '&exportar=1') ?>">Visitas</a>
        <a class="btn blue" href="<?= h('incidentes.php?' . $rango . '&exportar=1') ?>">Incidentes</a>
        <a class="btn gray" href="<?= h('traspasos.php?' . $rango . '&exportar=1') ?>">Traspasos</a>
        <a class="btn gray" href="<?= h('actividades.php?' . $rango . '&exportar=1') ?>">Actividades</a>
        <a class="btn gray" href="<?= h('tareas.php?' . $rango . '&exportar=1') ?>">Tareas</a>
    </div>
</div>

<?php include '../config/footer.php'; ?>
