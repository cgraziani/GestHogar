<?php
// ============================================================
// CENTRO DE REPORTES
// Punto de entrada único: desde acá se llega a cada reporte y a
// las exportaciones rápidas a Excel del último mes.
// ============================================================
require_once 'comun.php';
abrir_pagina_reporte();

$rango = 'desde=' . urlencode($desde) . '&hasta=' . urlencode($hasta);

$reportes = [
    ['dashboard.php',   '📊', 'Estado del hogar',     'Traspasos sin cerrar, medicación pendiente e incidentes, en tiempo real.', false],
    ['medicacion.php',  '💊', 'Medicación',           'Qué se administró, en qué horario y el motivo de cada omisión.', true],
    ['traspasos.php',   '🔁', 'Traspaso de turno',    'Novedades al cierre de cada turno, con saliente y entrante.', true],
    ['visitas.php',     '👥', 'Visitas',              'Ingresos y egresos de familiares, con parentesco y permanencia.', true],
    ['incidentes.php',  '🚨', 'Incidentes',           'Historial auditable por gravedad, con acciones tomadas y estado.', true],
    ['actividades.php', '🎲', 'Actividades',          'Actividades recreativas, participantes y observaciones.', true],
    ['tareas.php',      '✅', 'Tareas de rutina',     'Cumplimiento de las tareas diarias y motivo de cada incumplimiento.', true],
    ['residente.php',   '📄', 'Consolidado por residente', 'Medicación, incidentes y controles clínicos en un solo PDF.', false],
];
?>

<h1>Reportes</h1>
<p class="small">Todos los reportes se filtran por fecha y se pueden exportar a Excel o guardar como PDF.</p>

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
    <p class="small" style="margin-top:10px">El rango elegido se aplica a los links de abajo.</p>
</div>

<div class="grid">
    <?php foreach ($reportes as [$archivo, $icono, $titulo, $desc, $con_rango]): ?>
        <a href="<?= h($archivo . ($con_rango ? '?' . $rango : '')) ?>">
            <div class="card">
                <div style="font-size:30px;line-height:1"><?= $icono ?></div>
                <b style="display:block;margin:8px 0 4px;font-size:16px"><?= h($titulo) ?></b>
                <span class="small"><?= h($desc) ?></span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2 style="margin-top:0">⬇ Exportación rápida a Excel</h2>
    <p class="small">Descarga directa del rango elegido arriba
        (<?= h(fecha_corta($desde)) ?> al <?= h(fecha_corta($hasta)) ?>).
        Los archivos salen en CSV con separador «;» y codificación UTF-8, que Excel abre
        en columnas sin ningún paso extra.</p>
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
