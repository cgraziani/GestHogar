<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)  $where .= ' AND am.id_residente = ' . $id_residente;
if ($filtro_estado === 'omisiones') $where .= " AND am.estado_adm IN('no_pudo','no_dado')";
elseif ($filtro_estado !== '')      $where .= " AND am.estado_adm = " . $pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT am.fecha_hora, am.estado_adm, am.observaciones,
        r.nombre rn, r.apellido ra,
        m.nombre med, m.presentacion,
        mr.dosis, mr.horario AS horario_pautado,
        rec.diagnostico,
        u.nombre cuidadora
    FROM administracion_medicacion am
    JOIN residentes r    ON r.id_residente = am.id_residente
    JOIN medicamentos m  ON m.id_medicamento = am.id_medicamento
    LEFT JOIN medicacion_residente mr ON mr.id_plan = am.id_plan
    LEFT JOIN recetas_medicas rec     ON rec.id_receta = mr.id_receta
    LEFT JOIN usuarios u ON u.id_usuario = am.id_cuidadora_usuario
    WHERE am.fecha_hora BETWEEN ? AND ? $where
    ORDER BY am.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$cols = ['Fecha y hora', 'Residente', 'Medicamento', 'Dosis', 'Horario pautado',
         'Tratamiento', 'Estado', 'Motivo / observación', 'Registrado por'];

$filas = [];
$conteo = ['administrado' => 0, 'no_pudo' => 0, 'no_dado' => 0];
foreach ($datos as $d) {
    if (isset($conteo[$d['estado_adm']])) $conteo[$d['estado_adm']]++;
    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'] . ' ' . $d['ra'],
        trim($d['med'] . ' ' . $d['presentacion']),
        $d['dosis'] ?: '',
        $d['horario_pautado'] ?: '',
        $d['diagnostico'] ?: '',
        pill_estado_medicacion($d['estado_adm']),
        $d['observaciones'] ?: '',
        $d['cuidadora'] ?: '',
    ];
}

$total     = count($datos);
$omisiones = $conteo['no_pudo'] + $conteo['no_dado'];
$adhesion  = $total ? round($conteo['administrado'] * 100 / $total) : 0;

$resumen = [
    ['Registros', $total, 'Administraciones en el período'],
    ['Administradas', $conteo['administrado'], 'Se dieron sin problema'],
    ['Omisiones', $omisiones, 'No se pudo o no se dio'],
    ['Adhesión', $adhesion . '%', 'Administradas sobre el total'],
];

$sel = function ($v) use ($filtro_estado) { return $filtro_estado === $v ? 'selected' : ''; };
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado">'
       . '<option value="">Todos</option>'
       . '<option value="omisiones" ' . $sel('omisiones') . '>Solo omisiones</option>'
       . '<option value="administrado" ' . $sel('administrado') . '>Administradas</option>'
       . '<option value="no_pudo" ' . $sel('no_pudo') . '>No se pudo</option>'
       . '<option value="no_dado" ' . $sel('no_dado') . '>No administradas</option>'
       . '</select></div>';

salida_reporte('Reporte de medicación', 'medicacion', $cols, $filas);

echo '<h1>Reporte de medicación</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
