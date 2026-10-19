<?php

const TIPOS_PUEDE_RECETAS        = ['medico','psiquiatra'];
const TIPOS_PUEDE_MEDICACION     = ['medico','psiquiatra','enfermero'];
const TIPOS_PUEDE_CONTROLES      = ['medico','enfermero'];
const TIPOS_PUEDE_TAREAS         = ['medico','psiquiatra','enfermero','kinesiologo',
                                    'fisioterapeuta','podologo','fonoaudiologo',
                                    'psicologo','terapista_ocupacional','nutricionista'];
const TIPOS_PUEDE_RESTRICCIONES  = ['medico','nutricionista','fonoaudiologo'];
const TIPOS_PUEDE_REPORTES       = ['medico','psiquiatra','enfermero','kinesiologo',
                                    'fisioterapeuta','podologo','fonoaudiologo',
                                    'psicologo','terapista_ocupacional','nutricionista'];

const TIPOS_PROF_LABELS = [
    'medico'               => 'Médico/a',
    'psiquiatra'           => 'Psiquiatra',
    'enfermero'            => 'Enfermero/a',
    'kinesiologo'          => 'Kinesiólogo/a',
    'fisioterapeuta'       => 'Fisioterapeuta',
    'podologo'             => 'Podólogo/a',
    'fonoaudiologo'        => 'Fonoaudiólogo/a',
    'psicologo'            => 'Psicólogo/a',
    'terapista_ocupacional'=> 'Terapista Ocupacional',
    'nutricionista'        => 'Nutricionista',
    'peluquero'            => 'Peluquero/a',
    'manicura'             => 'Manicura',
    'otro'                 => 'Otro',
];

function tipo_profesional_actual(PDO $pdo): string {
    if (!es_profesional()) return '';
    if (!empty($_SESSION['_tipo_prof'])) return $_SESSION['_tipo_prof'];
    $st = $pdo->prepare("SELECT tipo_profesional FROM medicos WHERE id_usuario=? AND estado='activo'");
    $st->execute([uid()]);
    $tipo = $st->fetchColumn() ?: '';
    $_SESSION['_tipo_prof'] = $tipo;
    return $tipo;
}

function id_medico_actual(PDO $pdo): int {
    if (!empty($_SESSION['_id_medico'])) return (int)$_SESSION['_id_medico'];
    $st = $pdo->prepare("SELECT id_medico FROM medicos WHERE id_usuario=? AND estado='activo'");
    $st->execute([uid()]);
    $id = (int)($st->fetchColumn() ?: 0);
    $_SESSION['_id_medico'] = $id;
    return $id;
}

function ids_residentes_prof(PDO $pdo): array {
    if (!empty($_SESSION['_res_prof'])) return $_SESSION['_res_prof'];
    $id_med = id_medico_actual($pdo);
    if (!$id_med) return [];
    $st = $pdo->prepare("SELECT DISTINCT id_residente FROM visitas_medicas WHERE id_medico=?");
    $st->execute([$id_med]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['_res_prof'] = $ids;
    return $ids;
}

function query_residentes(PDO $pdo, string $campos = 'id_residente, nombre, apellido',
                          string $extra_where = '', string $order = 'apellido, nombre'): array {
    if (es_profesional()) {
        $ids = ids_residentes_prof($pdo);
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where = "estado='activo' AND id_residente IN($placeholders)";
        if ($extra_where) $where .= " AND $extra_where";
        $st = $pdo->prepare("SELECT $campos FROM residentes WHERE $where ORDER BY $order");
        $st->execute($ids);
        return $st->fetchAll();
    }
    $where = "estado='activo'";
    if ($extra_where) $where .= " AND $extra_where";
    return $pdo->query("SELECT $campos FROM residentes WHERE $where ORDER BY $order")->fetchAll();
}

function prof_puede_recetas(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_RECETAS);
}
function prof_puede_medicacion(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_MEDICACION);
}
function prof_puede_controles(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_CONTROLES);
}
function prof_puede_tareas(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_TAREAS);
}
function prof_puede_restricciones(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_RESTRICCIONES);
}
function prof_puede_reportes(PDO $pdo): bool {
    return in_array(tipo_profesional_actual($pdo), TIPOS_PUEDE_REPORTES);
}
