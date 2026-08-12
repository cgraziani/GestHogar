<?php
// ============================================================
// REPORTE DE VISITAS
// Historial de ingresos y egresos de familiares.
// ============================================================
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)         $where .= ' AND v.id_residente = ' . $id_residente;
if ($filtro_estado !== '') $where .= ' AND v.estado = ' . $pdo->quote($filtro_estado);

// Una visita se ubica en el tiempo por su ingreso real; si todavía no
// ingresó, por la fecha para la que estaba agendada.
$sth = $pdo->prepare("SELECT v.*, r.nombre rn, r.apellido ra,
        u.nombre registro_por, uc.nombre cerro_por
    FROM visitas v
    JOIN residentes r    ON r.id_residente = v.id_residente
    LEFT JOIN usuarios u  ON u.id_usuario = v.id_usuario
    LEFT JOIN usuarios uc ON uc.id_usuario = v.cerrado_por
    WHERE COALESCE(v.hora_ingreso, v.fecha_programada, v.created_at) BETWEEN ? AND ? $where
    ORDER BY COALESCE(v.hora_ingreso, v.fecha_programada, v.created_at) DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Visitante', 'Parentesco', 'Residente visitado', 'Teléfono',
         'Ingreso', 'Egreso', 'Permanencia', 'Estado', 'Observaciones'];

$estados_pill = [
    'finalizada' => ['Finalizada', 'pill-ok'],
    'en_curso'   => ['En curso', 'pill-aviso'],
    'agendada'   => ['Agendada', 'pill-neutro'],
    'cancelada'  => ['Cancelada', 'pill-mal'],
    'solicitada' => ['Solicitada', 'pill-aviso'],
    'rechazada'  => ['Rechazada', 'pill-mal'],
];

$filas = []; $en_curso = 0; $finalizadas = 0; $minutos_total = 0; $con_permanencia = 0;
foreach ($datos as $d) {
    if ($d['estado'] === 'en_curso')   $en_curso++;
    if ($d['estado'] === 'finalizada') $finalizadas++;

    $permanencia = '—';
    if ($d['hora_ingreso'] && $d['hora_egreso']) {
        $min = (strtotime($d['hora_egreso']) - strtotime($d['hora_ingreso'])) / 60;
        $minutos_total += $min; $con_permanencia++;
        $permanencia = floor($min / 60) . 'h ' . str_pad((int)($min % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    [$txt, $cls] = $estados_pill[$d['estado']] ?? [$d['estado'], 'pill-neutro'];

    $filas[] = [
        trim($d['nombre_visitante'] . ' ' . ($d['apellido_visitante'] ?? '')),
        $d['parentesco'],
        $d['rn'] . ' ' . $d['ra'],
        $d['telefono_visitante'] ?: '—',
        fecha_hora($d['hora_ingreso']),
        fecha_hora($d['hora_egreso']),
        $permanencia,
        pill($txt, $cls),
        $d['observaciones'] ?: '—',
    ];
}

$promedio = $con_permanencia ? round($minutos_total / $con_permanencia) : 0;
$resumen = [
    ['Visitas', count($datos), 'En el período elegido'],
    ['Finalizadas', $finalizadas, 'Con ingreso y egreso registrados'],
    ['En curso', $en_curso, 'Familiares dentro del hogar'],
    ['Permanencia media', $promedio ? floor($promedio / 60) . 'h ' . ($promedio % 60) . 'm' : '—', 'Promedio por visita'],
];

$sel = function ($v) use ($filtro_estado) { return $filtro_estado === $v ? 'selected' : ''; };
$opts = '';
foreach ($estados_pill as $k => $v) $opts .= '<option value="' . $k . '" ' . $sel($k) . '>' . $v[0] . '</option>';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">— Todos —</option>' . $opts . '</select></div>';

salida_reporte('Reporte de visitas', 'visitas', $cols, $filas);

echo '<h1>Reporte de visitas</h1>';
echo '<p class="small">Ingresos y egresos de familiares, con parentesco y tiempo de permanencia.</p>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
