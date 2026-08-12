<?php
// ============================================================
// BASE COMÚN DE LOS REPORTES
//
// Cada reporte arma dos cosas y delega el resto acá:
//   $cols  = ['Fecha', 'Residente', ...]
//   $filas = [ [valor, valor, ...], ... ]
//
// Un valor puede ser:
//   - un texto suelto  -> se usa igual en pantalla y en la exportación
//   - ['texto' => '...', 'html' => '...'] -> el texto va al CSV y el html
//     a la pantalla (sirve para las píldoras de color)
//
// Con eso salen solas las tres salidas: pantalla, CSV para Excel
// e impresión / PDF.
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

// ── Filtros que comparten todos los reportes ────────────────
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
// Si vienen dados vuelta los ordena, así el rango nunca queda vacío
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;

// Rango completo en formato datetime, para comparar contra columnas DATETIME
$desde_dt = $desde . ' 00:00:00';
$hasta_dt = $hasta . ' 23:59:59';

$residentes = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes ORDER BY apellido, nombre")->fetchAll();

function exportando() { return isset($_GET['exportar']); }
function imprimiendo() { return isset($_GET['imprimir']); }

// Texto plano de una celda (para CSV)
function celda_texto($c) { return is_array($c) ? ($c['texto'] ?? '') : (string)$c; }
// HTML de una celda (para pantalla)
function celda_html($c)  { return is_array($c) ? ($c['html'] ?? h($c['texto'] ?? '')) : h($c); }

// ── Píldoras de color reutilizables ─────────────────────────
function pill($texto, $clase) {
    return ['texto' => $texto, 'html' => '<span class="pill ' . $clase . '">' . h($texto) . '</span>'];
}

function pill_estado_medicacion($e) {
    $map = ['administrado' => ['Administrado', 'pill-ok'], 'realizada' => ['Realizada', 'pill-ok'],
            'no_pudo' => ['No se pudo', 'pill-aviso'], 'no_dado' => ['No administrado', 'pill-mal'],
            'no_realizada' => ['No realizada', 'pill-mal'], 'pendiente' => ['Pendiente', 'pill-neutro']];
    [$txt, $cls] = $map[$e] ?? [$e ?: 'Pendiente', 'pill-neutro'];
    return pill($txt, $cls);
}

function pill_gravedad($g) {
    $map = ['leve' => 'pill-ok', 'moderado' => 'pill-aviso', 'grave' => 'pill-mal'];
    return pill(ucfirst($g), $map[$g] ?? 'pill-neutro');
}

function fecha_hora($v) { return $v ? date('d/m/Y H:i', strtotime($v)) : '—'; }
function fecha_corta($v) { return $v ? date('d/m/Y', strtotime($v)) : '—'; }

// ── Exportación a CSV que abre bien en Excel ────────────────
// Separador ';' y BOM UTF-8: así Excel en español separa las columnas
// solo y respeta los acentos, sin depender de ninguna librería.
function exportar_csv($nombre_archivo, $cols, $filas) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols, ';');
    foreach ($filas as $fila) {
        fputcsv($out, array_map('celda_texto', $fila), ';');
    }
    fclose($out);
    exit;
}

// ── Barra de filtros ────────────────────────────────────────
// $opciones: ['residente' => true/false, 'extra' => html adicional]
function barra_filtros($opciones = []) {
    global $desde, $hasta, $id_residente, $residentes;
    $con_residente = $opciones['residente'] ?? true;
    ?>
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
            <?php if ($con_residente): ?>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Residente</label>
                <select name="residente" class="buscador" style="min-width:220px">
                    <option value="0">— Todos —</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_residente == $r['id_residente'] ? 'selected' : '' ?>><?= h($r['nombre'] . ' ' . $r['apellido']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?= $opciones['extra'] ?? '' ?>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn">Aplicar</button>
                <a class="btn gray" href="?">Limpiar</a>
            </div>
        </form>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn blue" href="<?= h(url_con(['exportar' => 1])) ?>">⬇ Exportar a Excel</a>
            <a class="btn gray" href="<?= h(url_con(['imprimir' => 1])) ?>" target="_blank">🖨 Imprimir / Guardar PDF</a>
        </div>
    </div>
    <?php
}

// Arma la URL actual agregando o pisando parámetros
function url_con($extra) {
    $q = array_merge($_GET, $extra);
    return '?' . http_build_query($q);
}

// ── Tarjetas de resumen (los números de arriba) ─────────────
function resumen_reporte($items) {
    if (empty($items)) return;
    echo '<div class="grid">';
    foreach ($items as $it) {
        echo '<div class="card" style="text-align:center">';
        echo '<div class="stat">' . h($it[1]) . '</div><b>' . h($it[0]) . '</b>';
        if (!empty($it[2])) echo '<br><span class="small">' . h($it[2]) . '</span>';
        echo '</div>';
    }
    echo '</div>';
}

// ── Salida final ────────────────────────────────────────────
// Solo se llama cuando el pedido es una exportación o una impresión;
// la vista en pantalla la arma cada reporte con sus propios filtros.
function salida_reporte($titulo, $archivo, $cols, $filas) {
    if (exportando()) exportar_csv($archivo, $cols, $filas);
    if (imprimiendo()) {
        vista_impresion($titulo, subtitulo_rango(), $cols, $filas);
        exit;
    }
}

// "Del 01/06/2026 al 30/06/2026 · Residente: Rosa Gomez"
function subtitulo_rango() {
    global $desde, $hasta, $id_residente, $residentes;
    $s = 'Del ' . fecha_corta($desde) . ' al ' . fecha_corta($hasta);
    foreach ($residentes as $r) {
        if ($id_residente && $r['id_residente'] == $id_residente) {
            $s .= ' · Residente: ' . $r['nombre'] . ' ' . $r['apellido'];
        }
    }
    return $s;
}

function tabla_reporte($cols, $filas) {
    if (empty($filas)) {
        echo '<div class="card" style="text-align:center;color:var(--text-muted)">'
           . 'No hay registros en el rango de fechas elegido.</div>';
        return;
    }
    echo '<table class="datatable"><thead><tr>';
    foreach ($cols as $c) echo '<th>' . h($c) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($fila as $celda) echo '<td>' . celda_html($celda) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// Página suelta y limpia, pensada para "Imprimir → Guardar como PDF"
function vista_impresion($titulo, $subtitulo, $cols, $filas, $bloques = null) {
    ?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= h($titulo) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color:#1a1a1a; margin:28px; font-size:12px; }
        h1 { font-size:20px; margin:0 0 4px; }
        h2 { font-size:15px; margin:22px 0 8px; border-bottom:1px solid #ccc; padding-bottom:4px; }
        .sub { color:#555; font-size:12px; margin:0 0 4px; }
        .marca { color:#888; font-size:11px; margin:0 0 18px; }
        table { width:100%; border-collapse:collapse; margin-bottom:16px; }
        th, td { border:1px solid #ccc; padding:6px 8px; text-align:left; vertical-align:top; }
        th { background:#f0f0f0; font-size:11px; text-transform:uppercase; letter-spacing:.03em; }
        tr { page-break-inside:avoid; }
        thead { display:table-header-group; }
        .vacio { color:#777; font-style:italic; }
        .pie { margin-top:24px; border-top:1px solid #ccc; padding-top:8px; color:#777; font-size:10px; }
        @media print { .no-imprimir { display:none } body { margin:0 } }
    </style>
</head>
<body onload="window.print()">
    <h1><?= h($titulo) ?></h1>
    <p class="sub"><?= h($subtitulo) ?></p>
    <p class="marca">GestHogar — Hogar Nongo · Generado el <?= date('d/m/Y H:i') ?> por <?= h($_SESSION['usuario']['nombre'] ?? '') ?></p>

    <?php if ($bloques !== null): ?>
        <?php foreach ($bloques as $b): ?>
            <h2><?= h($b['titulo']) ?></h2>
            <?php tabla_impresion($b['cols'], $b['filas']); ?>
        <?php endforeach; ?>
    <?php else: ?>
        <?php tabla_impresion($cols, $filas); ?>
    <?php endif; ?>

    <p class="pie">Documento generado automáticamente por GestHogar. Los registros reflejan lo cargado por el personal al momento de la impresión.</p>
</body>
</html><?php
}

function tabla_impresion($cols, $filas) {
    if (empty($filas)) { echo '<p class="vacio">Sin registros en el período.</p>'; return; }
    echo '<table><thead><tr>';
    foreach ($cols as $c) echo '<th>' . h($c) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($filas as $fila) {
        echo '<tr>';
        foreach ($fila as $celda) echo '<td>' . h(celda_texto($celda)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// El menú se incluye recién acá: si el pedido era una exportación,
// no queremos haber mandado HTML antes de los headers.
function abrir_pagina_reporte() {
    if (exportando() || imprimiendo()) return;
    include '../config/menu.php';
}
