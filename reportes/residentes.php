<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)                                        $where .= ' AND r.id_residente = ' . $id_residente;
if (in_array($filtro_estado, ['activo','pausado','baja'], true)) $where .= ' AND r.estado = ' . $pdo->quote($filtro_estado);

$datos = $pdo->query("SELECT r.*, h.numero habitacion, h.sector, c.identificador cama,
        co.nombre con_nombre, co.apellido con_apellido,
        (SELECT COUNT(*) FROM documentos_residente d
          WHERE d.id_residente = r.id_residente AND d.estado='activo') n_docs
    FROM residentes r
    LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = r.id_cama
    LEFT JOIN residentes co  ON co.id_residente = r.id_conyuge
    WHERE 1=1 $where
    ORDER BY FIELD(r.estado,'activo','pausado','baja'), r.apellido, r.nombre")->fetchAll();

$cols = ['Residente', 'DNI', 'Sexo', 'Edad', 'Ingreso', 'Habitación', 'Cama',
         'Familiar responsable', 'Parentesco', 'Teléfono', 'Estado', 'Motivo', 'Documentos'];

$filas = []; $activos = 0; $suspendidos = 0; $bajas = 0; $edades = [];
foreach ($datos as $d) {
    if ($d['estado'] === 'activo')  $activos++;
    if ($d['estado'] === 'pausado') $suspendidos++;
    if ($d['estado'] === 'baja')    $bajas++;

    $edad = '';
    if ($d['fecha_nacimiento']) {
        $edad = (new DateTime($d['fecha_nacimiento']))->diff(new DateTime())->y;
        if ($d['estado'] === 'activo') $edades[] = $edad;
    }

    $estado_pill = ['activo'  => ['Activo', 'pill-ok'],
                    'pausado' => ['Servicio suspendido', 'pill-aviso'],
                    'baja'    => ['Baja', 'pill-mal']][$d['estado']] ?? [$d['estado'], 'pill-neutro'];

    $motivo = $d['motivo_estado'] ?: '';
    if ($d['estado'] === 'baja' && $d['tipo_baja']) {
        $motivo = ucfirst($d['tipo_baja']) . ($d['motivo_estado'] ? ' · ' . $d['motivo_estado'] : '');
    } elseif ($d['estado'] === 'pausado' && $d['fecha_regreso_estimada']) {
        $motivo .= ' (regreso ' . fecha_corta($d['fecha_regreso_estimada']) . ')';
    }

    $filas[] = [
        $d['nombre'] . ' ' . $d['apellido']
            . ($d['con_nombre'] ? ' (comparte con ' . $d['con_nombre'] . ' ' . $d['con_apellido'] . ')' : ''),
        $d['dni'],
        $d['sexo'] === 'F' ? 'Femenino' : ($d['sexo'] === 'M' ? 'Masculino' : ''),
        $edad,
        fecha_corta($d['fecha_ingreso']),
        $d['habitacion'] ? 'Hab. ' . $d['habitacion'] . ($d['sector'] ? ' · ' . $d['sector'] : '') : 'Sin asignar',
        $d['cama'] ?: '',
        $d['contacto_familiar'] ?: '',
        $d['parentesco_familiar'] ?: '',
        $d['telefono_familiar'] ?: '',
        pill($estado_pill[0], $estado_pill[1]),
        $motivo,
        (int)$d['n_docs'],
    ];
}

$promedio = $edades ? round(array_sum($edades) / count($edades)) : null;
$resumen = [
    ['Residentes', count($datos), 'En el listado'],
    ['Activos', $activos, 'Alojados actualmente'],
    ['Suspendidos', $suspendidos, 'Ausencia temporal'],
    ['Edad promedio', $promedio ?: '', 'De los activos'],
];

$sel = fn($v) => $filtro_estado === $v ? 'selected' : '';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="activo" ' . $sel('activo') . '>Activos</option>'
       . '<option value="pausado" ' . $sel('pausado') . '>Servicio suspendido</option>'
       . '<option value="baja" ' . $sel('baja') . '>Bajas</option>'
       . '</select></div>';

salida_reporte('Reporte de residentes', 'residentes', $cols, $filas);

echo '<h1>Reporte de residentes</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
tabla_reporte($cols, $filas);
include '../config/footer.php';
