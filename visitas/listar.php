<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

$PARENTESCOS = ['Hijo/a', 'Nieto/a', 'Cónyuge', 'Hermano/a', 'Sobrino/a',
                'Yerno/Nuera', 'Amigo/a', 'Tutor/a legal', 'Otro'];

function volver_visitas($mensaje, $tipo = 'ok') {
    header('Location: listar.php?toast=' . urlencode($mensaje) . '&tipo=' . $tipo);
    exit;
}

function estado_visita_class($e) {
    if ($e === 'en_curso')   return 'estado-ok';
    if ($e === 'agendada')   return 'estado-pendiente';
    if ($e === 'solicitada') return 'estado-alerta';
    if ($e === 'cancelada' || $e === 'rechazada') return 'estado-problema';
    return 'estado-alerta';
}

function estado_visita_label($e) {
    $map = [
        'solicitada' => 'Solicitada',
        'agendada'   => 'Agendada',
        'en_curso'   => 'En curso',
        'finalizada' => 'Finalizada',
        'cancelada'  => 'Cancelada',
        'rechazada'  => 'Rechazada',
    ];
    return $map[$e] ?? $e;
}

if (isset($_GET['aprobar'])) {
    $fecha = trim($_GET['fecha'] ?? '');
    if (!$fecha) $fecha = date('Y-m-d H:i:s', strtotime('+1 day'));
    $pdo->prepare("UPDATE visitas SET estado='agendada', hora_ingreso=?, aprobado_por=?
                   WHERE id_visita=? AND estado='solicitada'")
        ->execute([$fecha, uid(), (int)$_GET['aprobar']]);
    volver_visitas('Visita aprobada y agendada');
}

if (isset($_GET['rechazar'])) {
    $pdo->prepare("UPDATE visitas SET estado='rechazada', motivo_rechazo=?
                   WHERE id_visita=? AND estado='solicitada'")
        ->execute([trim($_GET['motivo'] ?? '') ?: 'Sin motivo especificado', (int)$_GET['rechazar']]);
    volver_visitas('Solicitud rechazada', 'warn');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_visita'])) {
    $nombre    = trim($_POST['nombre_visitante'] ?? '');
    $apellido  = trim($_POST['apellido_visitante'] ?? '');
    $fecha     = trim($_POST['fecha_programada'] ?? '');
    $lazo      = trim($_POST['parentesco'] ?? '');
    if ($lazo === 'Otro') $lazo = trim($_POST['parentesco_otro'] ?? '') ?: 'Otro';

    if ($nombre === '' || $apellido === '' || $fecha === '' || $lazo === '') {
        volver_visitas('Faltan datos: nombre, apellido, lazo y fecha son obligatorios.', 'error');
    }

    $pdo->prepare('INSERT INTO visitas(id_residente,id_usuario,nombre_visitante,apellido_visitante,
                   parentesco,telefono_visitante,fecha_programada,observaciones,estado)
                   VALUES(?,?,?,?,?,?,?,?,\'agendada\')')
        ->execute([$_POST['id_residente'], uid(), $nombre, $apellido, $lazo,
                   trim($_POST['telefono_visitante'] ?? '') ?: null, $fecha,
                   trim($_POST['observaciones'] ?? '') ?: null]);
    volver_visitas('Visita agendada correctamente');
}

if (isset($_GET['ingreso'])) {
    $pdo->prepare("UPDATE visitas SET hora_ingreso=NOW(), estado='en_curso', id_usuario=?
                   WHERE id_visita=? AND estado='agendada'")
        ->execute([uid(), (int)$_GET['ingreso']]);
    volver_visitas('Ingreso registrado');
}

if (isset($_GET['egreso'])) {
    $pdo->prepare("UPDATE visitas SET hora_egreso=NOW(), estado='finalizada', cerrado_por=?
                   WHERE id_visita=? AND estado='en_curso'")
        ->execute([uid(), (int)$_GET['egreso']]);
    volver_visitas('Egreso registrado, visita finalizada');
}

if (isset($_GET['cancelar']) && es_admin()) {
    $pdo->prepare("UPDATE visitas SET estado='cancelada', motivo_cancelacion=?
                   WHERE id_visita=? AND estado IN('agendada','en_curso')")
        ->execute([trim($_GET['motivo'] ?? '') ?: null, (int)$_GET['cancelar']]);
    volver_visitas('Visita cancelada', 'warn');
}

include '../config/menu.php';

$res = $pdo->query("SELECT * FROM residentes WHERE estado='activo' ORDER BY apellido,nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$whereRes     = $id_residente ? ' AND v.id_residente=' . $id_residente : '';

$visitas = $pdo->query("SELECT v.*, r.nombre rn, r.apellido ra,
    u.nombre registro_por, uc.nombre cerro_por
    FROM visitas v
    JOIN residentes r ON r.id_residente=v.id_residente
    LEFT JOIN usuarios u  ON u.id_usuario=v.id_usuario
    LEFT JOIN usuarios uc ON uc.id_usuario=v.cerrado_por
    WHERE 1=1 $whereRes
    ORDER BY COALESCE(v.hora_ingreso, v.created_at) DESC")->fetchAll();

$solicitudes = array_filter($visitas, fn($v) => $v['estado'] === 'solicitada');
$proximas    = array_filter($visitas, function ($v) {
    return $v['estado'] === 'agendada' && $v['hora_ingreso'] >= date('Y-m-d 00:00:00');
});
$en_curso = array_filter($visitas, fn($v) => $v['estado'] === 'en_curso');

$res_sel = null;
if ($id_residente > 0) {
    $tmp     = array_filter($res, fn($x) => $x['id_residente'] == $id_residente);
    $res_sel = reset($tmp) ?: null;
}
?>

<h1>Visitas de familiares
    <?php if (count($solicitudes) > 0): ?>
        <span style="background:#e67e22;color:#fff;font-size:13px;padding:3px 10px;border-radius:12px;margin-left:10px;vertical-align:middle">
            <?= count($solicitudes) ?> solicitud<?= count($solicitudes) > 1 ? 'es' : '' ?> pendiente<?= count($solicitudes) > 1 ? 's' : '' ?>
        </span>
    <?php endif; ?>
</h1>

<?php if (!empty($solicitudes)): ?>
<div style="background:#fff3cd;border-left:5px solid #e67e22;border-radius:8px;padding:16px 20px;margin-bottom:20px">
    <b style="color:#e67e22;font-size:15px">Solicitudes de visita pendientes de aprobación</b>
    <?php foreach ($solicitudes as $s): ?>
    <div style="background:#fff;border-radius:8px;padding:14px 16px;margin-bottom:10px;border:1px solid #f0c36d;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <b><?= htmlspecialchars($s['nombre_visitante'] . ' ' . ($s['apellido_visitante'] ?? '')) ?></b>
            <span style="color:#888;font-size:12px;margin-left:6px"><?= htmlspecialchars($s['parentesco']) ?></span><br>
            <span style="font-size:13px">Residente: <b><?= htmlspecialchars($s['rn'] . ' ' . $s['ra']) ?></b></span><br>
            <?php if ($s['telefono_visitante']): ?>
                <span style="font-size:12px;color:#555"><?= htmlspecialchars($s['telefono_visitante']) ?></span>
            <?php endif; ?>
            <?php if ($s['observaciones']): ?>
                <span style="font-size:12px;color:#555;display:block;margin-top:2px"><?= htmlspecialchars($s['observaciones']) ?></span>
            <?php endif; ?>
            <span style="font-size:11px;color:#aaa;display:block;margin-top:4px">
                Solicitado: <?= date('d/m/Y H:i', strtotime($s['created_at'])) ?>
                <?php if ($s['hora_ingreso']): ?>
                    · Prefiere: <?= date('d/m/Y H:i', strtotime($s['hora_ingreso'])) ?>
                <?php endif; ?>
            </span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn green"
               href="?aprobar=<?= $s['id_visita'] ?>&fecha=<?= urlencode($s['hora_ingreso'] ?? date('Y-m-d H:i:s', strtotime('+1 day'))) ?>"
               onclick="return confirmarLink(this,'¿Aprobar la visita de <?= htmlspecialchars($s['nombre_visitante']) ?>?','Aprobar','green')">
Aprobar
            </a>
            <a class="btn red"
               href="?rechazar=<?= $s['id_visita'] ?>&motivo=No+disponible"
               onclick="return confirmarLink(this,'¿Rechazar esta solicitud?','Rechazar','red')">
Rechazar
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Filtrar por residente</h2>
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <select id="select-residente" class="buscador"
                onchange="window.location='listar.php?residente='+this.value"
                style="min-width:260px;padding:10px 14px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
            <option value="0" <?= $id_residente == 0 ? 'selected' : '' ?>>Todos los residentes</option>
            <?php foreach ($res as $x): ?>
                <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" onclick="document.getElementById('modal-visita').style.display='flex'">+ Agendar visita</button>
        <?php if ($id_residente > 0): ?>
            <a class="btn gray" href="<?= BASE_URL ?>/residentes/listar.php?ver=<?= $id_residente ?>">Ver ficha del residente</a>
        <?php endif; ?>
    </div>
    <?php if ($res_sel): ?>
        <p style="margin-top:12px;color:#2D9B8A;font-weight:bold">
Mostrando visitas de <?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?>
        </p>
    <?php endif; ?>
</div>

<div class="grid">
    <div class="card" style="text-align:center">
        <div class="stat"><?= count($en_curso) ?></div>
        <b>Visitas en curso</b>
    </div>
    <div class="card" style="text-align:center">
        <div class="stat"><?= count($proximas) ?></div>
        <b>Visitas agendadas</b>
    </div>
    <div class="card" style="text-align:center">
        <div class="stat"><?= count($visitas) ?></div>
        <b>Total registradas</b>
    </div>
</div>

<div id="modal-visita" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:24px 28px;width:100%;max-width:600px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalVisita()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin:0 0 16px">Agendar visita</h3>
        <form method="post">
            <input type="hidden" name="nueva_visita" value="1">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre del visitante *</label>
                    <input name="nombre_visitante" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Apellido *</label>
                    <input name="apellido_visitante" required style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Residente que visita *</label>
                    <select name="id_residente" class="buscador" required style="width:100%;box-sizing:border-box">
                        <?php foreach ($res as $x): ?>
                            <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Lazo con el residente *</label>
                    <select name="parentesco" id="sel-parentesco" required style="width:100%;box-sizing:border-box">
                        <?php foreach ($PARENTESCOS as $p): ?>
                            <option value="<?= $p ?>"><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="bloque-otro" style="display:none;margin-top:10px">
                <label style="font-size:13px;font-weight:600">¿Cuál es el lazo?</label>
                <input name="parentesco_otro" style="width:100%;box-sizing:border-box">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha y hora de la visita *</label>
                    <input type="datetime-local" name="fecha_programada" required
                           value="<?= date('Y-m-d\TH:i') ?>" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Teléfono de contacto</label>
                    <input name="telefono_visitante" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Observaciones</label>
                <textarea name="observaciones" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Agendar</button>
                <button type="button" class="btn gray" onclick="cerrarModalVisita()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<h2>Visitas registradas</h2>
<?php if (!empty($visitas)): ?>
<table class="datatable">
    <thead><tr>
        <th>Visitante</th><th>Lazo</th><th>Residente</th><th>Fecha agendada</th>
        <th>Ingreso</th><th>Egreso</th><th>Estado</th><th>Acciones</th>
    </tr></thead>
    <tbody>
    <?php foreach ($visitas as $v): ?>
        <tr>
            <td>
                <b><?= htmlspecialchars($v['nombre_visitante'] . ' ' . $v['apellido_visitante']) ?></b>
                <?php if ($v['telefono_visitante']): ?><br><span class="small"><?= htmlspecialchars($v['telefono_visitante']) ?></span><?php endif; ?>
            </td>
            <td><span class="badge"><?= htmlspecialchars($v['parentesco']) ?></span></td>
            <td><?= htmlspecialchars($v['rn'] . ' ' . $v['ra']) ?></td>
            <td><?= $v['fecha_programada'] ? date('d/m/Y H:i', strtotime($v['fecha_programada'])) : '' ?></td>
            <td><?= $v['hora_ingreso'] ? date('d/m/Y H:i', strtotime($v['hora_ingreso'])) : '' ?></td>
            <td><?= $v['hora_egreso'] ? date('d/m/Y H:i', strtotime($v['hora_egreso'])) : '' ?></td>
            <td>
                <div class="estado-cuadro <?= estado_visita_class($v['estado']) ?>"><?= estado_visita_label($v['estado']) ?></div>
                <?php if ($v['observaciones']): ?><span class="small"><?= htmlspecialchars($v['observaciones']) ?></span><?php endif; ?>
                <?php if ($v['motivo_cancelacion']): ?><br><span class="small">Motivo: <?= htmlspecialchars($v['motivo_cancelacion']) ?></span><?php endif; ?>
            </td>
            <td>
                <?php if ($v['estado'] === 'agendada'): ?>
                    <a class="btn" href="?ingreso=<?= $v['id_visita'] ?>" onclick="return confirmarLink(this,'¿Registrar el ingreso de <?= htmlspecialchars($v['nombre_visitante'], ENT_QUOTES) ?>?','Registrar ingreso','green')">Registrar ingreso</a>
                <?php elseif ($v['estado'] === 'en_curso'): ?>
                    <a class="btn blue" href="?egreso=<?= $v['id_visita'] ?>" onclick="return confirmarLink(this,'¿Registrar el egreso y finalizar la visita?','Registrar egreso','green')">Registrar egreso</a>
                <?php else: ?>
                    <span class="small"></span>
                <?php endif; ?>
                <?php if (es_admin() && in_array($v['estado'], ['agendada','en_curso'])): ?>
                    <a class="btn red" href="?cancelar=<?= $v['id_visita'] ?>" onclick="return confirmarLink(this,'¿Cancelar esta visita?','Cancelar visita','red')">Cancelar</a>
                <?php endif; ?>
                <?php if ($v['registro_por']): ?><br><span class="small">Registró: <?= htmlspecialchars($v['registro_por']) ?></span><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
function cerrarModalVisita() { document.getElementById('modal-visita').style.display = 'none'; }
document.getElementById('modal-visita').addEventListener('click', function(e){
    if (e.target === this) cerrarModalVisita();
});

(function () {
    var sel = document.getElementById('sel-parentesco');
    var otro = document.getElementById('bloque-otro');
    function revisar() { otro.style.display = (sel.value === 'Otro') ? 'block' : 'none'; }
    sel.addEventListener('change', revisar);
    revisar();
})();
</script>

<?php include '../config/footer.php'; ?>

