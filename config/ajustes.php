<?php

function ajustes(PDO $pdo = null) {
    static $cache = null;
    if ($cache !== null) return $cache;

    $cache = [];
    if ($pdo === null) return $cache;

    try {
        foreach ($pdo->query('SELECT clave, valor FROM configuracion')->fetchAll() as $f) {
            $cache[$f['clave']] = $f['valor'];
        }
    } catch (PDOException $e) {
        $cache = [];
    }
    return $cache;
}

function ajuste($clave, $porDefecto = '') {
    global $pdo;
    $todo = ajustes($pdo ?? null);
    $v = $todo[$clave] ?? null;
    return ($v === null || $v === '') ? $porDefecto : $v;
}

function ajuste_num($clave, $porDefecto = 0) {
    return (int) ajuste($clave, (string)$porDefecto);
}

function nombre_institucion() {
    return ajuste('institucion_nombre', 'GestHogar');
}

function datos_institucion() {
    $partes = array_filter([
        ajuste('institucion_nombre', ''),
        ajuste('institucion_direccion', ''),
        ajuste('institucion_telefono', '') ? 'Tel. ' . ajuste('institucion_telefono', '') : '',
        ajuste('institucion_cuit', '') ? 'CUIT ' . ajuste('institucion_cuit', '') : '',
    ]);
    return implode(' · ', $partes);
}

function momentos_comida() {
    return [
        'desayuno'     => ['Desayuno',     ajuste('comida_desayuno', '08:00')],
        'media_manana' => ['Media mañana', ajuste('comida_media_manana', '10:30')],
        'almuerzo'     => ['Almuerzo',     ajuste('comida_almuerzo', '12:00')],
        'merienda'     => ['Merienda',     ajuste('comida_merienda', '16:00')],
        'cena'         => ['Cena',         ajuste('comida_cena', '20:00')],
        'colacion'     => ['Colación',     ajuste('comida_colacion', '22:00')],
    ];
}

function rangos_control(PDO $pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;

    $defecto = [
        'tension_sistolica'   => ['min' => 70, 'max' => 180, 'unidad' => 'mmHg'],
        'tension_diastolica'  => ['min' => 40, 'max' => 120, 'unidad' => 'mmHg'],
        'temperatura'         => ['min' => 34, 'max' => 41,  'unidad' => '°C'],
        'peso'                => ['min' => 30, 'max' => 200, 'unidad' => 'kg'],
        'glucemia'            => ['min' => 50, 'max' => 400, 'unidad' => 'mg/dL'],
        'saturacion_oxigeno'  => ['min' => 70, 'max' => 100, 'unidad' => '%'],
        'frecuencia_cardiaca' => ['min' => 40, 'max' => 180, 'unidad' => 'lpm'],
    ];

    try {
        $filas = $pdo->query('SELECT * FROM rangos_control')->fetchAll();
        if (!$filas) { $cache = $defecto; return $cache; }
        $cache = [];
        foreach ($filas as $f) {
            $cache[$f['tipo_control']] = [
                'min'    => (float)$f['valor_min'],
                'max'    => (float)$f['valor_max'],
                'unidad' => $f['unidad'],
            ];
        }
    } catch (PDOException $e) {
        $cache = $defecto;
    }
    return $cache;
}
