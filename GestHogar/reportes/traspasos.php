<?php
// ============================================================
// REPORTE DE TRASPASO DE TURNO
// Novedades que deja cada turno al cerrar, para que la siguiente
// cuidadora y Carla tengan continuidad sin depender de WhatsApp.
// ============================================================
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($filtro_estado !== '') $where .= ' AND t.estado = ' . $pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT t.*, us.nombre saliente, us.apellido saliente_ape,
        ue.nombre entrante, ue.apellido entrante_ape
    FROM traspaso_turno t
    LEFT JOIN usuarios us ON us.id_usuario = t.id_usuario_saliente
    LEFT JOIN usuarios ue ON ue.id_usuario = t.id_usuario_entrante
    WHERE COALESCE(t.fecha_apertura, t.fecha_cierre, t.created_at) BETWEEN ? AND ? $where
    ORDER BY COALESCE(t.fecha_apertura, t.fecha_cierre, t.created_at) DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Apertura', 'Cierre', 'Turno saliente', 'Turno entrante', 'Novedades', 'Duración', 'Estado'];

$filas = [];
$pendientes = 0;
foreach ($datos as $d) {
    if ($d['estado'] === 'pendiente') $pendientes++;

    // Solo tiene sentido si el cierre es posterior a la apertura; en los
    // datos hay traspasos cargados con las horas invertidas.
    $duracion = '—';
    if ($d['fecha_apertura'] && $d['fecha_cierre']) {
        $min = (strtotime($d['fecha_cierre']) - strtotime($d['fecha_apertura'])) / 60;
        $duracion = $min >= 0
            ? floor($min / 60) . 'h ' . str_pad((int)($min % 60), 2, '0', STR_PAD_LEFT) . 'm'
            : 'Horas invertidas';
    }

    $filas[] = [
        $d['fecha_apertura'] ? fecha_hora($d['fecha_apertura'])
                             : ['texto' => 'Sin registrar', 'html' => '<span class="pill pill-neutro">Sin registrar</span>'],
        $d['fecha_cierre'] ? fecha_hora($d['fecha_cierre'])
                           : ['texto' => 'Sin cerrar', 'html' => '<span class="pill pill-aviso">Sin cerrar</span>'],
        trim(($d['saliente'] ?? '') . ' ' . ($d['saliente_ape'] ?? '')) ?: '—',
        trim(($d['entrante'] ?? '') . ' ' . ($d['entrante_ape'] ?? '')) ?: '—',
        $d['novedades'] ?: '— sin novedades registradas —',
        $duracion,
        $d['estado'] === 'cerrado' ? pill('Cerrado', 'pill-ok') : pill('Pendiente', 'pill-aviso'),
    ];
}

$resumen = [
    ['Traspasos', count($datos), 'Registrados en el período'],
    ['Cerrados', count($datos) - $pendientes, 'Con novedades confirmadas'],
    ['Sin cerrar', $pendientes, 'Requieren atención'],
];

$sel = function ($v) use ($filtro_estado) { return $filtro_estado === $v ? 'selected' : ''; };
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">— Todos —</option>'
       . '<option value="pendiente" ' . $sel('pendiente') . '>Sin cerrar</option>'
       . '<option value="cerrado" ' . $sel('cerrado') . '>Cerrados</option>'
       . '</select></div>';

salida_reporte('Reporte de traspaso de turno', 'traspasos', $cols, $filas);

echo '<h1>Reporte de traspaso de turno</h1>';
echo '<p class="small">Novedades registradas al cierre de cada turno, con quién entrega y quién recibe.</p>';
barra_filtros(['residente' => false, 'extra' => $extra]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
