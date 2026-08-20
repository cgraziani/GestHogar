<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)  $where .= ' AND at.id_residente = ' . $id_residente;
if ($filtro_estado === 'incumplidas') $where .= " AND at.estado_tarea IN('no_realizada','no_pudo')";
elseif ($filtro_estado !== '')        $where .= ' AND at.estado_tarea = ' . $pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT at.fecha_hora, at.estado_tarea, at.motivo,
        r.nombre rn, r.apellido ra,
        tr.titulo, tr.descripcion, tr.tipo, tr.horario,
        u.nombre cuidadora
    FROM administracion_tareas at
    JOIN residentes r        ON r.id_residente = at.id_residente
    LEFT JOIN tareas_residente tr ON tr.id_tarea = at.id_tarea
    LEFT JOIN usuarios u     ON u.id_usuario = at.id_cuidadora_usuario
    WHERE at.fecha_hora BETWEEN ? AND ? $where
    ORDER BY at.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Fecha y hora', 'Residente', 'Tarea', 'Tipo', 'Horario pautado',
         'Estado', 'Motivo', 'Registrado por'];

$filas = []; $hechas = 0; $incumplidas = 0;
foreach ($datos as $d) {
    if ($d['estado_tarea'] === 'realizada') $hechas++;
    if (in_array($d['estado_tarea'], ['no_realizada', 'no_pudo'], true)) $incumplidas++;

    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'] . ' ' . $d['ra'],
        $d['titulo'] ?: 'Tarea eliminada',
        $d['tipo'] ? ucfirst($d['tipo']) : '',
        $d['horario'] ?: '',
        pill_estado_medicacion($d['estado_tarea']),
        $d['motivo'] ?: '',
        $d['cuidadora'] ?: '',
    ];
}

$total = count($datos);
$cumplimiento = $total ? round($hechas * 100 / $total) : 0;
$resumen = [
    ['Registros', $total, 'Tareas registradas en el período'],
    ['Realizadas', $hechas, 'Se completaron'],
    ['Incumplidas', $incumplidas, 'No se hicieron o no se pudo'],
    ['Cumplimiento', $cumplimiento . '%', 'Realizadas sobre el total'],
];

$sinRegistro = $pdo->query("SELECT COUNT(*) FROM tareas_residente tr
    WHERE tr.estado='activo'
    AND NOT EXISTS (SELECT 1 FROM administracion_tareas a WHERE a.id_tarea = tr.id_tarea)")->fetchColumn();

$sel = function ($v) use ($filtro_estado) { return $filtro_estado === $v ? 'selected' : ''; };
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="incumplidas" ' . $sel('incumplidas') . '>Solo incumplidas</option>'
       . '<option value="realizada" ' . $sel('realizada') . '>Realizadas</option>'
       . '<option value="no_pudo" ' . $sel('no_pudo') . '>No se pudo</option>'
       . '<option value="no_realizada" ' . $sel('no_realizada') . '>No realizadas</option>'
       . '</select></div>';

salida_reporte('Reporte de tareas de rutina', 'tareas', $cols, $filas);

echo '<h1>Reporte de tareas de rutina</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
if ($sinRegistro > 0) {
    echo '<div class="card" style="border-left:4px solid #e6a700">'
       . '<b>' . (int)$sinRegistro . ' tarea' . ($sinRegistro > 1 ? 's' : '') . ' sin ningún registro de cumplimiento.</b></div>';
}
tabla_reporte($cols, $filas);
include '../config/footer.php';
