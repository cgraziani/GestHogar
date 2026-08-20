<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_gravedad = $_GET['gravedad'] ?? '';
$filtro_estado   = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)           $where .= ' AND i.id_residente = ' . $id_residente;
if ($filtro_gravedad !== '') $where .= ' AND i.gravedad = ' . $pdo->quote($filtro_gravedad);
if ($filtro_estado !== '')   $where .= ' AND i.estado = ' . $pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT i.*, r.nombre rn, r.apellido ra, u.nombre reporto
    FROM incidentes i
    JOIN residentes r    ON r.id_residente = i.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
    WHERE i.fecha_hora BETWEEN ? AND ? $where
    ORDER BY FIELD(i.gravedad,'grave','moderado','leve'), i.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Fecha y hora', 'Residente', 'Gravedad', 'Descripción', 'Acciones tomadas',
         'Médico interviniente', 'Familiar notificado', 'Estado', 'Reportado por'];

$filas = []; $graves = 0; $pendientes = 0; $graves_pendientes = 0;
foreach ($datos as $d) {
    if ($d['gravedad'] === 'grave')    $graves++;
    if ($d['estado'] === 'pendiente')  $pendientes++;
    if ($d['gravedad'] === 'grave' && $d['estado'] === 'pendiente') $graves_pendientes++;

    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'] . ' ' . $d['ra'],
        pill_gravedad($d['gravedad']),
        $d['descripcion'] ?: '',
        $d['acciones_tomadas'] ?: '',
        $d['medico_interviniente'] ?: '',
        $d['notificado_responsable'] ? 'Sí' : 'No',
        $d['estado'] === 'revisado' ? pill('Revisado', 'pill-ok') : pill('Pendiente', 'pill-aviso'),
        $d['reporto'] ?: '',
    ];
}

$resumen = [
    ['Incidentes', count($datos), 'En el período elegido'],
    ['Graves', $graves, 'Requieren seguimiento'],
    ['Sin revisar', $pendientes, 'Pendientes de revisión'],
    ['Graves sin revisar', $graves_pendientes, 'Prioridad máxima'],
];

$selG = function ($v) use ($filtro_gravedad) { return $filtro_gravedad === $v ? 'selected' : ''; };
$selE = function ($v) use ($filtro_estado) { return $filtro_estado === $v ? 'selected' : ''; };
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Gravedad</label>'
       . '<select name="gravedad"><option value="">Todas</option>'
       . '<option value="grave" ' . $selG('grave') . '>Grave</option>'
       . '<option value="moderado" ' . $selG('moderado') . '>Moderado</option>'
       . '<option value="leve" ' . $selG('leve') . '>Leve</option>'
       . '</select></div>'
       . '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="pendiente" ' . $selE('pendiente') . '>Sin revisar</option>'
       . '<option value="revisado" ' . $selE('revisado') . '>Revisados</option>'
       . '</select></div>';

salida_reporte('Reporte de incidentes', 'incidentes', $cols, $filas);

echo '<h1>Reporte de incidentes / accidentes</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
