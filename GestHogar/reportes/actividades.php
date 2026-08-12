<?php
// ============================================================
// REPORTE DE ACTIVIDADES RECREATIVAS
// Qué se hizo, quiénes participaron y cómo les fue. Sirve para
// mirar el bienestar y la participación de cada residente.
// ============================================================
require_once 'comun.php';
abrir_pagina_reporte();

// Cuando se filtra por residente, solo interesan las actividades
// en las que ese residente participó.
$where = '';
if ($id_residente) {
    $where = ' AND EXISTS (SELECT 1 FROM actividad_residentes ar
                           WHERE ar.id_actividad = a.id_actividad
                           AND ar.id_residente = ' . $id_residente . ')';
}

$sth = $pdo->prepare("SELECT a.*, u.nombre organizo
    FROM actividades a
    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
    WHERE a.estado = 'activo' AND a.fecha BETWEEN ? AND ? $where
    ORDER BY a.fecha DESC");
$sth->execute([$desde, $hasta]);
$datos = $sth->fetchAll();

// Participantes de todas las actividades del período, en una sola consulta
$participantes = [];
if ($datos) {
    $ids = implode(',', array_map(fn($a) => (int)$a['id_actividad'], $datos));
    $sthP = $pdo->query("SELECT ar.id_actividad, ar.observacion_participacion,
            r.nombre, r.apellido
        FROM actividad_residentes ar
        JOIN residentes r ON r.id_residente = ar.id_residente
        WHERE ar.id_actividad IN ($ids)
        ORDER BY r.apellido, r.nombre");
    foreach ($sthP->fetchAll() as $p) {
        $participantes[$p['id_actividad']][] = $p;
    }
}

$cols = ['Fecha', 'Actividad', 'Descripción', 'Participantes', 'Cantidad',
         'Observaciones de participación', 'Observaciones generales', 'Organizó'];

$filas = []; $total_part = 0;
foreach ($datos as $d) {
    $lista = $participantes[$d['id_actividad']] ?? [];
    $total_part += count($lista);

    $nombres = implode(', ', array_map(fn($p) => $p['nombre'] . ' ' . $p['apellido'], $lista)) ?: '— sin participantes —';

    $obs = [];
    foreach ($lista as $p) {
        if (trim((string)$p['observacion_participacion']) !== '') {
            $obs[] = $p['nombre'] . ': ' . $p['observacion_participacion'];
        }
    }

    $filas[] = [
        fecha_corta($d['fecha']),
        $d['nombre'],
        $d['descripcion'] ?: '—',
        $nombres,
        count($lista),
        $obs ? implode(' · ', $obs) : '—',
        $d['observaciones'] ?: '—',
        $d['organizo'] ?: '—',
    ];
}

$promedio = count($datos) ? round($total_part / count($datos), 1) : 0;
$resumen = [
    ['Actividades', count($datos), 'Realizadas en el período'],
    ['Participaciones', $total_part, 'Suma de asistentes'],
    ['Promedio', $promedio, 'Participantes por actividad'],
];

salida_reporte('Reporte de actividades recreativas', 'actividades', $cols, $filas);

echo '<h1>Reporte de actividades recreativas</h1>';
echo '<p class="small">Actividades realizadas con los residentes, quiénes participaron y cómo les fue.</p>';
barra_filtros(['residente' => true]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
