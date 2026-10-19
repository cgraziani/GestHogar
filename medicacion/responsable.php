<?php

function personal_de_turno(PDO $pdo, $fecha, $hora) {
    static $cache = [];
    $clave = $fecha . ' ' . $hora;
    if (isset($cache[$clave])) return $cache[$clave];

    $minuto = (int)substr($hora, 0, 2) * 60 + (int)substr($hora, 3, 2);

    $st = $pdo->prepare("SELECT tp.fecha, tp.hora_inicio, tp.hora_fin,
                                u.id_usuario, u.nombre, u.apellido, u.rol, u.color
                         FROM turnos_programados tp
                         JOIN usuarios u ON u.id_usuario = tp.id_cuidadora_usuario
                         WHERE tp.estado = 'activo'
                           AND tp.fecha IN (?, DATE_SUB(?, INTERVAL 1 DAY))
                         ORDER BY tp.hora_inicio");
    $st->execute([$fecha, $fecha]);

    $encontrados = [];
    foreach ($st->fetchAll() as $t) {
        $ini = (int)substr($t['hora_inicio'], 0, 2) * 60 + (int)substr($t['hora_inicio'], 3, 2);
        $fin = (int)substr($t['hora_fin'], 0, 2) * 60 + (int)substr($t['hora_fin'], 3, 2);

        if ($fin === $ini)      $fin = $ini + 1440;
        elseif ($fin < $ini)    $fin += 1440;

        $desplazamiento = ($t['fecha'] === $fecha) ? 0 : -1440;
        $ini += $desplazamiento;
        $fin += $desplazamiento;

        if ($minuto >= $ini && $minuto < $fin) {
            $encontrados[$t['id_usuario']] = $t;
        }
    }

    $cache[$clave] = array_values($encontrados);
    return $cache[$clave];
}

function responsable_de_toma(PDO $pdo, array $plan, $fecha = null) {
    $fecha = $fecha ?: date('Y-m-d');

    if (!empty($plan['id_responsable'])) {
        $st = $pdo->prepare('SELECT nombre, apellido FROM usuarios WHERE id_usuario=?');
        $st->execute([$plan['id_responsable']]);
        if ($u = $st->fetch()) {
            return ['texto' => trim($u['nombre'] . ' ' . $u['apellido']), 'origen' => 'asignado'];
        }
    }

    $turno = personal_de_turno($pdo, $fecha, $plan['horario']);
    if (empty($turno)) {
        return ['texto' => 'Sin turno cubierto', 'origen' => 'sin_turno'];
    }

    usort($turno, function ($a, $b) {
        $orden = ['cuidadora' => 0, 'asistente' => 1, 'cocinera' => 2];
        return ($orden[$a['rol']] ?? 9) <=> ($orden[$b['rol']] ?? 9);
    });

    $nombres = array_map(fn($t) => trim($t['nombre'] . ' ' . $t['apellido']), $turno);
    return ['texto' => implode(' / ', $nombres), 'origen' => 'turno'];
}

function pill_responsable(PDO $pdo, array $plan, $fecha = null) {
    $r = $responsable = responsable_de_toma($pdo, $plan, $fecha);
    $clase = $r['origen'] === 'sin_turno' ? 'pill-mal'
           : ($r['origen'] === 'asignado' ? 'pill-ok' : 'pill-neutro');
    $sufijo = $r['origen'] === 'asignado' ? ' (asignado)' : '';
    return '<span class="pill ' . $clase . '">' . h($r['texto'] . $sufijo) . '</span>';
}
