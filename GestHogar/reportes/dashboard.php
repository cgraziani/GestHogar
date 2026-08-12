<?php
// ============================================================
// DASHBOARD EN TIEMPO REAL
// Estado operativo del hogar ahora mismo, para que Carla vea si hay
// algo que atender sin tener que llamar por teléfono.
//
// La misma página responde en JSON con ?json=1: el navegador la
// consulta cada 30 segundos y actualiza los números sin recargar.
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

// ── Consultas del estado actual ─────────────────────────────
function estado_hogar(PDO $pdo) {
    $e = [];

    // Traspasos de turno sin cerrar
    $e['traspasos'] = $pdo->query("SELECT t.id_traspaso, t.fecha_apertura, t.novedades,
            us.nombre saliente, ue.nombre entrante
        FROM traspaso_turno t
        LEFT JOIN usuarios us ON us.id_usuario = t.id_usuario_saliente
        LEFT JOIN usuarios ue ON ue.id_usuario = t.id_usuario_entrante
        WHERE t.estado = 'pendiente'
        ORDER BY COALESCE(t.fecha_apertura, t.created_at) DESC")->fetchAll();

    // Medicación de hoy que todavía nadie registró.
    // Se considera vencida cuando el horario pautado ya pasó.
    $e['medicacion'] = $pdo->query("SELECT mr.id_plan, mr.horario, mr.dosis,
            r.nombre rn, r.apellido ra, m.nombre med,
            (mr.horario < CURTIME()) AS vencida
        FROM medicacion_residente mr
        JOIN residentes r   ON r.id_residente = mr.id_residente
        JOIN medicamentos m ON m.id_medicamento = mr.id_medicamento
        WHERE mr.estado = 'activo'
        AND (mr.fecha_inicio IS NULL OR CURDATE() >= mr.fecha_inicio)
        AND (mr.fecha_fin    IS NULL OR CURDATE() <= mr.fecha_fin)
        AND NOT EXISTS (SELECT 1 FROM administracion_medicacion am
                        WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = CURDATE())
        ORDER BY mr.horario")->fetchAll();

    // Incidentes graves o moderados sin revisar
    $e['incidentes'] = $pdo->query("SELECT i.id_incidente, i.fecha_hora, i.gravedad,
            i.descripcion, r.nombre rn, r.apellido ra, u.nombre reporto
        FROM incidentes i
        JOIN residentes r    ON r.id_residente = i.id_residente
        LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
        WHERE i.estado = 'pendiente'
        ORDER BY FIELD(i.gravedad,'grave','moderado','leve'), i.fecha_hora DESC")->fetchAll();

    // Familiares dentro del hogar en este momento
    $e['visitas'] = $pdo->query("SELECT v.id_visita, v.nombre_visitante, v.apellido_visitante,
            v.parentesco, v.hora_ingreso, r.nombre rn, r.apellido ra
        FROM visitas v
        JOIN residentes r ON r.id_residente = v.id_residente
        WHERE v.estado = 'en_curso'
        ORDER BY v.hora_ingreso")->fetchAll();

    // Tareas de rutina de hoy sin registrar
    $e['tareas'] = $pdo->query("SELECT tr.id_tarea, tr.titulo, tr.horario,
            r.nombre rn, r.apellido ra,
            (tr.horario < CURTIME()) AS vencida
        FROM tareas_residente tr
        JOIN residentes r ON r.id_residente = tr.id_residente
        WHERE tr.estado = 'activo'
        AND NOT EXISTS (SELECT 1 FROM administracion_tareas at
                        WHERE at.id_tarea = tr.id_tarea AND DATE(at.fecha_hora) = CURDATE())
        ORDER BY tr.horario")->fetchAll();

    // Quién está de turno ahora
    $e['turno'] = $pdo->query("SELECT tp.hora_inicio, tp.hora_fin, u.nombre, u.apellido, u.rol, u.color
        FROM turnos_programados tp
        JOIN usuarios u ON u.id_usuario = tp.id_cuidadora_usuario
        WHERE tp.estado = 'activo' AND tp.fecha = CURDATE()
        ORDER BY tp.hora_inicio")->fetchAll();

    return $e;
}

$e = estado_hogar($pdo);

// Conteos que alimentan los badges
$n = [
    'traspasos'         => count($e['traspasos']),
    'medicacion'        => count($e['medicacion']),
    'medicacion_vencida'=> count(array_filter($e['medicacion'], fn($x) => $x['vencida'])),
    'incidentes'        => count($e['incidentes']),
    'incidentes_graves' => count(array_filter($e['incidentes'], fn($x) => $x['gravedad'] === 'grave')),
    'visitas'           => count($e['visitas']),
    'tareas'            => count($e['tareas']),
    'tareas_vencidas'   => count(array_filter($e['tareas'], fn($x) => $x['vencida'])),
];

// ── Respuesta JSON para el autorefresco ─────────────────────
if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['conteos' => $n, 'hora' => date('H:i:s')], JSON_UNESCAPED_UNICODE);
    exit;
}

include '../config/menu.php';

// Nivel de atención general: rojo si hay algo grave, ámbar si hay
// pendientes, verde si está todo al día.
$hay_grave  = $n['incidentes_graves'] > 0 || $n['medicacion_vencida'] > 0;
$hay_aviso  = $n['traspasos'] > 0 || $n['medicacion'] > 0 || $n['incidentes'] > 0 || $n['tareas_vencidas'] > 0;
$estado_gen = $hay_grave ? ['🚨', 'Requiere atención inmediata', 'pill-mal']
            : ($hay_aviso ? ['⚠️', 'Hay pendientes por resolver', 'pill-aviso']
                          : ['✅', 'Todo al día', 'pill-ok']);
?>

<h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    Estado del hogar
    <span class="pill <?= $estado_gen[2] ?>"><?= $estado_gen[0] ?> <?= h($estado_gen[1]) ?></span>
</h1>
<p class="small">
    Actualizado a las <b id="hora-dato"><?= date('H:i:s') ?></b>.
    Se refresca solo cada 30 segundos.
    <a href="?" style="margin-left:8px">Actualizar ahora</a>
</p>

<!-- ── Badges de conteo ─────────────────────────────────── -->
<div class="grid">
    <a href="<?= BASE_URL ?>/turnos/listar.php">
        <div class="card" style="text-align:center">
            <div class="stat" data-conteo="traspasos"><?= $n['traspasos'] ?></div>
            <b>Traspasos sin cerrar</b><br>
            <span class="small">La cuidadora entrante no confirmó</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/medicacion/listar.php">
        <div class="card" style="text-align:center">
            <div class="stat" data-conteo="medicacion"><?= $n['medicacion'] ?></div>
            <b>Medicación sin registrar hoy</b><br>
            <span class="small"><?= $n['medicacion_vencida'] ?> con el horario ya pasado</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/incidentes/listar.php">
        <div class="card" style="text-align:center">
            <div class="stat" data-conteo="incidentes"><?= $n['incidentes'] ?></div>
            <b>Incidentes sin revisar</b><br>
            <span class="small"><?= $n['incidentes_graves'] ?> de gravedad alta</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/visitas/listar.php">
        <div class="card" style="text-align:center">
            <div class="stat" data-conteo="visitas"><?= $n['visitas'] ?></div>
            <b>Visitas en curso</b><br>
            <span class="small">Familiares dentro del hogar</span>
        </div>
    </a>
    <a href="<?= BASE_URL ?>/medicacion/tareas.php">
        <div class="card" style="text-align:center">
            <div class="stat" data-conteo="tareas"><?= $n['tareas'] ?></div>
            <b>Tareas sin registrar hoy</b><br>
            <span class="small"><?= $n['tareas_vencidas'] ?> con el horario ya pasado</span>
        </div>
    </a>
</div>

<!-- ── Detalle de lo urgente ────────────────────────────── -->
<?php if ($n['incidentes'] > 0): ?>
<div class="card">
    <h2 style="margin-top:0">🚨 Incidentes sin revisar</h2>
    <table class="datatable">
        <thead><tr><th>Fecha</th><th>Residente</th><th>Gravedad</th><th>Descripción</th><th>Reportó</th></tr></thead>
        <tbody>
        <?php foreach ($e['incidentes'] as $i): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($i['fecha_hora'])) ?></td>
                <td><?= h($i['rn'] . ' ' . $i['ra']) ?></td>
                <td><span class="pill <?= $i['gravedad'] === 'grave' ? 'pill-mal' : ($i['gravedad'] === 'moderado' ? 'pill-aviso' : 'pill-ok') ?>"><?= h(ucfirst($i['gravedad'])) ?></span></td>
                <td><?= h($i['descripcion']) ?></td>
                <td><?= h($i['reporto'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($n['medicacion'] > 0): ?>
<div class="card">
    <h2 style="margin-top:0">💊 Medicación de hoy sin registrar</h2>
    <table class="datatable">
        <thead><tr><th>Horario</th><th>Residente</th><th>Medicamento</th><th>Dosis</th><th>Situación</th></tr></thead>
        <tbody>
        <?php foreach ($e['medicacion'] as $m): ?>
            <tr>
                <td><b><?= h($m['horario']) ?></b></td>
                <td><?= h($m['rn'] . ' ' . $m['ra']) ?></td>
                <td><?= h($m['med']) ?></td>
                <td><?= h($m['dosis']) ?></td>
                <td><?= $m['vencida']
                        ? '<span class="pill pill-mal">Horario pasado</span>'
                        : '<span class="pill pill-neutro">Más tarde</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($n['traspasos'] > 0): ?>
<div class="card">
    <h2 style="margin-top:0">⏳ Traspasos sin cerrar</h2>
    <table class="datatable">
        <thead><tr><th>Apertura</th><th>Sale</th><th>Entra</th><th>Novedades</th></tr></thead>
        <tbody>
        <?php foreach ($e['traspasos'] as $t): ?>
            <tr>
                <td><?= $t['fecha_apertura'] ? date('d/m/Y H:i', strtotime($t['fecha_apertura'])) : '—' ?></td>
                <td><?= h($t['saliente'] ?: '—') ?></td>
                <td><?= h($t['entrante'] ?: '—') ?></td>
                <td><?= h($t['novedades'] ?: '— sin novedades —') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($n['visitas'] > 0): ?>
<div class="card">
    <h2 style="margin-top:0">👥 Familiares en el hogar ahora</h2>
    <table class="datatable">
        <thead><tr><th>Visitante</th><th>Parentesco</th><th>Visita a</th><th>Entró</th></tr></thead>
        <tbody>
        <?php foreach ($e['visitas'] as $v): ?>
            <tr>
                <td><?= h(trim($v['nombre_visitante'] . ' ' . ($v['apellido_visitante'] ?? ''))) ?></td>
                <td><?= h($v['parentesco']) ?></td>
                <td><?= h($v['rn'] . ' ' . $v['ra']) ?></td>
                <td><?= $v['hora_ingreso'] ? date('H:i', strtotime($v['hora_ingreso'])) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h2 style="margin-top:0">🕐 Personal de turno hoy</h2>
    <?php if (empty($e['turno'])): ?>
        <p class="small">No hay turnos cargados para hoy.</p>
    <?php else: ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($e['turno'] as $t): ?>
            <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-color);border-radius:10px;padding:8px 14px">
                <span style="width:12px;height:12px;border-radius:50%;background:<?= h($t['color'] ?: '#888') ?>"></span>
                <div>
                    <b><?= h($t['nombre'] . ' ' . $t['apellido']) ?></b>
                    <span class="badge"><?= h($t['rol']) ?></span><br>
                    <span class="small"><?= h(substr($t['hora_inicio'], 0, 5)) ?> a <?= h(substr($t['hora_fin'], 0, 5)) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!$hay_grave && !$hay_aviso): ?>
<div class="card" style="text-align:center;border-left:4px solid var(--color-accent)">
    <div style="font-size:34px">✅</div>
    <b>No hay nada pendiente.</b><br>
    <span class="small">Traspasos cerrados, medicación registrada e incidentes revisados.</span>
</div>
<?php endif; ?>

<script>
// Autorefresco: pide solo los conteos y los actualiza en el lugar.
// Si algún número cambia respecto de lo que se ve, recarga la página
// entera para que las tablas de detalle acompañen.
(function () {
    var CADA = 30000;
    function refrescar() {
        fetch('dashboard.php?json=1', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var cambio = false;
                document.querySelectorAll('[data-conteo]').forEach(function (el) {
                    var nuevo = String(d.conteos[el.dataset.conteo]);
                    if (el.textContent.trim() !== nuevo) { el.textContent = nuevo; cambio = true; }
                });
                document.getElementById('hora-dato').textContent = d.hora;
                if (cambio) window.location.reload();
            })
            .catch(function () { /* si falla, se reintenta en el próximo ciclo */ });
    }
    setInterval(refrescar, CADA);
})();
</script>

<?php include '../config/footer.php'; ?>
