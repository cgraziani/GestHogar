<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

$PARENTESCOS = ['Hijo/a', 'Nieto/a', 'Cónyuge', 'Hermano/a', 'Sobrino/a',
                'Yerno/Nuera', 'Amigo/a', 'Tutor/a legal', 'Otro'];

$TIPOS_VISITA = [
    'familiar'      => 'Familiar',
    'medico'        => 'Médico/a',
    'kinesiologo'   => 'Kinesiólogo/a',
    'fisioterapeuta'=> 'Fisioterapeuta',
    'podologo'      => 'Podólogo/a',
    'enfermero'     => 'Enfermero/a',
    'nutricionista' => 'Nutricionista',
    'peluquero'     => 'Peluquero/a',
    'autorizado'    => 'Otra persona autorizada',
    'otro'          => 'Otro',
];
$TIPOS_SALUD = ['medico','kinesiologo','fisioterapeuta','podologo','enfermero','nutricionista'];

function etiqueta_tipo_visita($t) {
    global $TIPOS_VISITA;
    return $TIPOS_VISITA[$t] ?? $t;
}
function es_profesional_salud($t) {
    global $TIPOS_SALUD;
    return in_array($t, $TIPOS_SALUD, true);
}

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

    if (!(int)$_POST['id_residente']) volver_visitas('Seleccioná un residente.', 'error');
    if ($nombre === '') volver_visitas('El nombre del visitante es obligatorio.', 'error');
    if ($apellido === '') volver_visitas('El apellido del visitante es obligatorio.', 'error');
    if ($fecha === '') volver_visitas('La fecha programada es obligatoria.', 'error');
    if ($lazo === '') volver_visitas('El parentesco o relación es obligatorio.', 'error');

    $tipo = $_POST['tipo_visita'] ?? 'familiar';
    if (!array_key_exists($tipo, $TIPOS_VISITA)) $tipo = 'familiar';
    $id_profesional = $_POST['id_profesional'] ?: null;

    try {
        $pdo->prepare('INSERT INTO visitas(id_residente,tipo_visita,id_profesional,id_usuario,nombre_visitante,apellido_visitante,
                       parentesco,telefono_visitante,fecha_programada,observaciones,estado)
                       VALUES(?,?,?,?,?,?,?,?,?,?,\'agendada\')')
            ->execute([$_POST['id_residente'], $tipo, $id_profesional, uid(), $nombre, $apellido, $lazo,
                       trim($_POST['telefono_visitante'] ?? '') ?: null, $fecha,
                       trim($_POST['observaciones'] ?? '') ?: null]);

        $id_visita_nueva = $pdo->lastInsertId();
        $diag  = trim($_POST['diagnostico'] ?? '');
        $indic = trim($_POST['indicaciones'] ?? '');
        $medic = trim($_POST['medicacion_indicada'] ?? '');
        if (es_profesional_salud($tipo) && ($diag !== '' || $indic !== '' || $medic !== '')) {
            $pdo->prepare('INSERT INTO visitas_medicas
                    (id_visita,id_residente,id_medico,id_usuario,fecha_hora,motivo,diagnostico,indicaciones,medicacion_indicada)
                    VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$id_visita_nueva, $_POST['id_residente'], $id_profesional, uid(), $fecha,
                           trim($_POST['observaciones'] ?? '') ?: 'Visita profesional',
                           $diag ?: null, $indic ?: null, $medic ?: null]);
        }
        volver_visitas('Visita registrada correctamente');
    } catch (PDOException $e) {
        volver_visitas('Error al registrar la visita. Verificá los datos e intentá de nuevo.', 'error');
    }
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

$profesionales = $pdo->query("SELECT id_medico, nombre, apellido, tipo_profesional, especialidad
    FROM medicos WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();

$visitas = $pdo->query("SELECT v.*, r.nombre rn, r.apellido ra,
    u.nombre registro_por, uc.nombre cerro_por,
    p.nombre prof_nombre, p.apellido prof_apellido, p.especialidad,
    (SELECT COUNT(*) FROM visitas_medicas vm WHERE vm.id_visita = v.id_visita) tiene_atencion
    FROM visitas v
    JOIN residentes r ON r.id_residente=v.id_residente
    LEFT JOIN usuarios u  ON u.id_usuario=v.id_usuario
    LEFT JOIN usuarios uc ON uc.id_usuario=v.cerrado_por
    LEFT JOIN medicos p   ON p.id_medico = v.id_profesional
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

<h1>Visitas al establecimiento
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
                <span style="font-size:12px;color:#555"> <?= htmlspecialchars($s['telefono_visitante']) ?></span>
            <?php endif; ?>
            <?php if ($s['observaciones']): ?>
                <span style="font-size:12px;color:#555;display:block;margin-top:2px"> <?= htmlspecialchars($s['observaciones']) ?></span>
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
        <button onclick="cerrarModalVisita()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
        <h3 style="margin:0 0 16px">Registrar visita</h3>
        <form method="post">
            <input type="hidden" name="nueva_visita" value="1">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:4px">
                <div>
                    <label style="font-size:13px;font-weight:600">Tipo de visita *</label>
                    <select name="tipo_visita" id="v-tipo" required onchange="cambiarTipoVisita()" style="width:100%;box-sizing:border-box">
                        <?php foreach ($TIPOS_VISITA as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="bloque-profesional" style="display:none">
                    <label style="font-size:13px;font-weight:600">Profesional registrado</label>
                    <select name="id_profesional" id="v-profesional" class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">No está en la lista / externo</option>
                        <?php foreach ($profesionales as $p): ?>
                            <option value="<?= $p['id_medico'] ?>"><?= h($p['apellido'] . ', ' . $p['nombre']) ?><?= $p['especialidad'] ? ' (' . h($p['especialidad']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

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
                <div id="bloque-parentesco">
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

            <div id="bloque-clinico" style="display:none;margin-top:14px;border-top:1px solid var(--border-color);padding-top:14px">
                <b style="font-size:14px">Registro de la atención</b>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                        <label style="font-size:13px;font-weight:600">Diagnóstico / motivo de la atención</label>
                        <input name="diagnostico" style="width:100%;box-sizing:border-box">
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:600">Indicaciones</label>
                        <input name="indicaciones" style="width:100%;box-sizing:border-box">
                    </div>
                </div>
                <div style="margin-top:10px">
                    <label style="font-size:13px;font-weight:600">Medicación indicada</label>
                    <textarea name="medicacion_indicada" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Registrar visita</button>
                <button type="button" class="btn gray" onclick="cerrarModalVisita()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<h2>Visitas registradas</h2>
<?php if (!empty($visitas)): ?>
<table class="datatable">
    <thead><tr>
        <th>Visitante</th><th>Tipo</th><th>Lazo</th><th>Residente</th><th>Fecha agendada</th>
        <th>Ingreso</th><th>Egreso</th><th>Estado</th><th>Registro</th>
    </tr></thead>
    <tbody>
    <?php foreach ($visitas as $v):
        $estados_pill = [
            'agendada'   => ['Agendada',  'pill-neutro'],
            'en_curso'   => [' En curso',  'pill-ok'],
            'finalizada' => [' Finalizada', 'pill-ok'],
            'cancelada'  => [' Cancelada',  'pill-mal'],
            'solicitada' => [' Solicitada', 'pill-aviso'],
            'rechazada'  => [' Rechazada',  'pill-mal'],
        ];
        [$est_txt, $est_cls] = $estados_pill[$v['estado']] ?? [$v['estado'], 'pill-neutro'];
    ?>
        <tr>
            <td>
                <b><?= h($v['nombre_visitante'].' '.$v['apellido_visitante']) ?></b>
                <?php if ($v['telefono_visitante']): ?><br><span class="small"><?= h($v['telefono_visitante']) ?></span><?php endif; ?>
            </td>
            <td>
                <span class="badge"><?= h(etiqueta_tipo_visita($v['tipo_visita'])) ?></span>
                <?php if ($v['especialidad']): ?><br><span class="small"><?= h($v['especialidad']) ?></span><?php endif; ?>
                <?php if ($v['tiene_atencion']): ?><br><span class="pill pill-ok" style="font-size:10px">Con atención</span><?php endif; ?>
            </td>
            <td><?= $v['tipo_visita']==='familiar' ? '<span class="badge">'.h($v['parentesco']).'</span>' : '<span class="small"></span>' ?></td>
            <td><?= h($v['rn'].' '.$v['ra']) ?></td>
            <td><?= $v['fecha_programada'] ? date('d/m/Y H:i', strtotime($v['fecha_programada'])) : '' ?></td>
            <td><?= $v['hora_ingreso'] ? date('d/m/Y H:i', strtotime($v['hora_ingreso'])) : '' ?></td>
            <td><?= $v['hora_egreso']  ? date('d/m/Y H:i', strtotime($v['hora_egreso']))  : '' ?></td>
            <td>
                <span class="pill <?= $est_cls ?>" style="display:inline-block;margin-bottom:4px"><?= $est_txt ?></span>
                <?php if ($v['observaciones']): ?><br><span class="small"><?= h($v['observaciones']) ?></span><?php endif; ?>
                <?php if ($v['motivo_cancelacion']): ?><br><span class="small" style="color:#e74c3c">Motivo: <?= h($v['motivo_cancelacion']) ?></span><?php endif; ?>
            </td>
            <td>
                <?php if ($v['estado']==='agendada'): ?>
                    <a class="btn" href="?ingreso=<?= $v['id_visita'] ?>"
                       onclick="return confirmarLink(this,'¿Registrar el ingreso de <?= h($v['nombre_visitante'], ENT_QUOTES) ?>?','Registrar ingreso','green')">
                       ↩ Ingreso
                    </a>
                <?php elseif ($v['estado']==='en_curso'): ?>
                    <a class="btn blue" href="?egreso=<?= $v['id_visita'] ?>"
                       onclick="return confirmarLink(this,'¿Registrar el egreso y finalizar la visita?','Finalizar','green')">
                       ↪ Egreso
                    </a>
                <?php endif; ?>
                <?php if (es_admin() && in_array($v['estado'], ['agendada','en_curso'])): ?>
                    <a class="btn red" style="font-size:11px" href="?cancelar=<?= $v['id_visita'] ?>"
                       onclick="return confirmarLink(this,'¿Cancelar esta visita?','Cancelar','red')">Cancelar</a>
                <?php endif; ?>
                <?php if ($v['registro_por']): ?>
                    <br><span class="small" style="color:#aaa"><?= h($v['registro_por']) ?></span>
                <?php endif; ?>
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

var TIPOS_SALUD = <?= json_encode($TIPOS_SALUD) ?>;
var PROFESIONALES = <?= json_encode($profesionales) ?>;

function cambiarTipoVisita() {
    var tipo    = document.getElementById('v-tipo').value;
    var esSalud = TIPOS_SALUD.indexOf(tipo) !== -1;
    var esFamiliar = tipo === 'familiar';

    document.getElementById('bloque-clinico').style.display     = esSalud ? 'block' : 'none';
    document.getElementById('bloque-profesional').style.display = (esSalud || tipo === 'peluquero') ? 'block' : 'none';
    document.getElementById('bloque-parentesco').style.display  = esFamiliar ? 'block' : 'none';

    document.getElementById('sel-parentesco').required = esFamiliar;
}

document.getElementById('v-profesional').addEventListener('change', function () {
    var p = PROFESIONALES.find(function (x) { return String(x.id_medico) === this.value; }, this);
    if (!p) return;
    document.querySelector('[name="nombre_visitante"]').value   = p.nombre;
    document.querySelector('[name="apellido_visitante"]').value = p.apellido;
});

cambiarTipoVisita();

(function () {
    var sel = document.getElementById('sel-parentesco');
    var otro = document.getElementById('bloque-otro');
    function revisar() { otro.style.display = (sel.value === 'Otro') ? 'block' : 'none'; }
    sel.addEventListener('change', revisar);
    revisar();
})();
</script>

<?php include '../config/footer.php'; ?>

