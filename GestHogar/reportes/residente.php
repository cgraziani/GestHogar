<?php
// ============================================================
// REPORTE CONSOLIDADO POR RESIDENTE
// Un solo documento con medicación, incidentes y controles clínicos
// en un rango de fechas. Es el que Carla lleva a las reuniones con
// médicos o familiares.
//
// Se genera como PDF desde el propio navegador: la vista de impresión
// abre el diálogo de impresión y ahí se elige "Guardar como PDF".
// Así no hace falta sumar ninguna librería al proyecto.
// ============================================================
require_once 'comun.php';
abrir_pagina_reporte();

// ── Sin residente elegido: pantalla de selección ────────────
if (!$id_residente) {
    echo '<h1>Reporte consolidado por residente</h1>';
    echo '<p class="small">Reúne medicación, incidentes y controles clínicos de un residente '
       . 'en un solo documento, listo para imprimir o guardar como PDF.</p>';
    barra_filtros_consolidado();
    echo '<div class="card" style="text-align:center;color:var(--text-muted)">'
       . 'Elegí un residente arriba para generar su reporte.</div>';
    include '../config/footer.php';
    exit;
}

$res = null;
foreach ($residentes as $r) if ($r['id_residente'] == $id_residente) $res = $r;
if (!$res) {
    echo '<div class="card">Ese residente no existe.</div>';
    include '../config/footer.php';
    exit;
}

// ── Datos del residente ─────────────────────────────────────
$sth = $pdo->prepare("SELECT r.*, h.numero AS habitacion, h.sector
    FROM residentes r
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    WHERE r.id_residente = ?");
$sth->execute([$id_residente]);
$ficha = $sth->fetch();

// ── Bloque 1: medicación ────────────────────────────────────
$sth = $pdo->prepare("SELECT am.fecha_hora, am.estado_adm, am.observaciones,
        m.nombre med, m.presentacion, mr.dosis, mr.horario, u.nombre cuidadora
    FROM administracion_medicacion am
    JOIN medicamentos m ON m.id_medicamento = am.id_medicamento
    LEFT JOIN medicacion_residente mr ON mr.id_plan = am.id_plan
    LEFT JOIN usuarios u ON u.id_usuario = am.id_cuidadora_usuario
    WHERE am.id_residente = ? AND am.fecha_hora BETWEEN ? AND ?
    ORDER BY am.fecha_hora DESC");
$sth->execute([$id_residente, $desde_dt, $hasta_dt]);
$med = $sth->fetchAll();

$cols_med = ['Fecha y hora', 'Medicamento', 'Dosis', 'Horario pautado', 'Estado', 'Observación', 'Registró'];
$filas_med = [];
foreach ($med as $d) {
    $filas_med[] = [
        fecha_hora($d['fecha_hora']),
        trim($d['med'] . ' ' . $d['presentacion']),
        $d['dosis'] ?: '—',
        $d['horario'] ?: '—',
        pill_estado_medicacion($d['estado_adm']),
        $d['observaciones'] ?: '—',
        $d['cuidadora'] ?: '—',
    ];
}

// ── Bloque 2: incidentes ────────────────────────────────────
$sth = $pdo->prepare("SELECT i.*, u.nombre reporto
    FROM incidentes i
    LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
    WHERE i.id_residente = ? AND i.fecha_hora BETWEEN ? AND ?
    ORDER BY i.fecha_hora DESC");
$sth->execute([$id_residente, $desde_dt, $hasta_dt]);
$inc = $sth->fetchAll();

$cols_inc = ['Fecha y hora', 'Gravedad', 'Descripción', 'Acciones tomadas', 'Médico', 'Estado', 'Reportó'];
$filas_inc = [];
foreach ($inc as $d) {
    $filas_inc[] = [
        fecha_hora($d['fecha_hora']),
        pill_gravedad($d['gravedad']),
        $d['descripcion'] ?: '—',
        $d['acciones_tomadas'] ?: '—',
        $d['medico_interviniente'] ?: '—',
        $d['estado'] === 'revisado' ? pill('Revisado', 'pill-ok') : pill('Pendiente', 'pill-aviso'),
        $d['reporto'] ?: '—',
    ];
}

// ── Bloque 3: controles clínicos ────────────────────────────
$sth = $pdo->prepare("SELECT c.*, u.nombre tomo
    FROM controles_clinicos c
    LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
    WHERE c.id_residente = ? AND c.fecha_hora BETWEEN ? AND ?
    ORDER BY c.fecha_hora DESC");
$sth->execute([$id_residente, $desde_dt, $hasta_dt]);
$ctr = $sth->fetchAll();

$nombres_control = [
    'tension_sistolica'  => 'Tensión sistólica',
    'tension_diastolica' => 'Tensión diastólica',
    'temperatura'        => 'Temperatura',
    'peso'               => 'Peso',
    'glucemia'           => 'Glucemia',
    'saturacion_oxigeno' => 'Saturación de oxígeno',
    'frecuencia_cardiaca'=> 'Frecuencia cardíaca',
];

$cols_ctr = ['Fecha y hora', 'Control', 'Valor', 'Observaciones', 'Tomado por'];
$filas_ctr = [];
foreach ($ctr as $d) {
    $filas_ctr[] = [
        fecha_hora($d['fecha_hora']),
        $nombres_control[$d['tipo_control']] ?? $d['tipo_control'],
        trim($d['valor'] . ' ' . ($d['unidad'] ?? '')),
        $d['observaciones'] ?: '—',
        $d['tomo'] ?: '—',
    ];
}

$titulo = 'Reporte consolidado — ' . $ficha['nombre'] . ' ' . $ficha['apellido'];

// ── Impresión: los tres bloques en un documento ─────────────
if (imprimiendo()) {
    $sub = 'Del ' . fecha_corta($desde) . ' al ' . fecha_corta($hasta)
         . ' · DNI ' . ($ficha['dni'] ?: 's/d')
         . ($ficha['habitacion'] ? ' · Hab. ' . $ficha['habitacion'] . ' (' . $ficha['sector'] . ')' : '');
    vista_impresion($titulo, $sub, null, null, [
        ['titulo' => 'Medicación administrada (' . count($filas_med) . ')', 'cols' => $cols_med, 'filas' => $filas_med],
        ['titulo' => 'Incidentes registrados (' . count($filas_inc) . ')',  'cols' => $cols_inc, 'filas' => $filas_inc],
        ['titulo' => 'Controles clínicos (' . count($filas_ctr) . ')',      'cols' => $cols_ctr, 'filas' => $filas_ctr],
    ]);
    exit;
}

// ── Exportación: los tres bloques uno debajo del otro ───────
if (exportando()) {
    $cols = ['Bloque', 'Fecha y hora', 'Detalle', 'Dato 1', 'Dato 2', 'Estado', 'Observaciones', 'Responsable'];
    $filas = [];
    foreach ($filas_med as $f) $filas[] = ['Medicación', $f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6]];
    foreach ($filas_inc as $f) $filas[] = ['Incidente', $f[0], $f[2], $f[1], $f[4], $f[5], $f[3], $f[6]];
    foreach ($filas_ctr as $f) $filas[] = ['Control clínico', $f[0], $f[1], $f[2], '', '', $f[3], $f[4]];
    exportar_csv('consolidado-' . strtolower($ficha['apellido']), $cols, $filas);
}

echo '<h1>' . h($titulo) . '</h1>';
echo '<p class="small">Del ' . h(fecha_corta($desde)) . ' al ' . h(fecha_corta($hasta))
   . ' · DNI ' . h($ficha['dni'] ?: 's/d')
   . ($ficha['habitacion'] ? ' · Hab. ' . h($ficha['habitacion']) . ' (' . h($ficha['sector']) . ')' : '') . '</p>';

barra_filtros_consolidado();

resumen_reporte([
    ['Administraciones', count($filas_med), 'Registros de medicación'],
    ['Incidentes', count($filas_inc), 'En el período'],
    ['Controles clínicos', count($filas_ctr), 'Mediciones tomadas'],
]);

echo '<h2>Medicación administrada</h2>';
tabla_reporte($cols_med, $filas_med);
echo '<h2 style="margin-top:28px">Incidentes registrados</h2>';
tabla_reporte($cols_inc, $filas_inc);
echo '<h2 style="margin-top:28px">Controles clínicos</h2>';
tabla_reporte($cols_ctr, $filas_ctr);

include '../config/footer.php';


// El consolidado siempre necesita un residente elegido, así que su
// barra de filtros no lleva la opción "Todos".
function barra_filtros_consolidado() {
    global $desde, $hasta, $id_residente, $residentes;
    ?>
    <div class="card no-imprimir">
        <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Residente</label>
                <select name="residente" class="buscador" required style="min-width:240px">
                    <option value="">Elegir residente...</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_residente == $r['id_residente'] ? 'selected' : '' ?>><?= h($r['nombre'] . ' ' . $r['apellido']) ?></option>
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
            <button type="submit" class="btn">Generar</button>
        </form>
        <?php if ($id_residente): ?>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="<?= h(url_con(['imprimir' => 1])) ?>" target="_blank">📄 Generar PDF</a>
            <a class="btn blue" href="<?= h(url_con(['exportar' => 1])) ?>">⬇ Exportar a Excel</a>
        </div>
        <p class="small" style="margin-top:8px">El botón de PDF abre el diálogo de impresión: elegí «Guardar como PDF» como destino.</p>
        <?php endif; ?>
    </div>
    <?php
}
