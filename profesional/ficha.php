<?php
require_once '../config/session.php';
require_once '../config/db.php';
if (!es_profesional()) acceso_denegado();

$med = $pdo->prepare("SELECT * FROM medicos WHERE id_usuario=? AND estado='activo'");
$med->execute([uid()]);
$med = $med->fetch();
if (!$med) acceso_denegado();

$tipo = $med['tipo_profesional'];
$id_medico = $med['id_medico'];

require_once '../config/accesos_prof.php';
$PUEDE_RECETAS       = in_array($tipo, TIPOS_PUEDE_RECETAS);
$PUEDE_TAREAS        = in_array($tipo, TIPOS_PUEDE_TAREAS);
$PUEDE_RESTRICCIONES = in_array($tipo, TIPOS_PUEDE_RESTRICCIONES);
$PUEDE_CONTROLES     = in_array($tipo, TIPOS_PUEDE_CONTROLES);
$PUEDE_MEDICACION    = in_array($tipo, TIPOS_PUEDE_MEDICACION);

$id_res = (int)($_GET['id'] ?? 0);
if (!$id_res) { header('Location: index.php'); exit; }

$check = $pdo->prepare("SELECT COUNT(*) FROM visitas_medicas WHERE id_medico=? AND id_residente=?");
$check->execute([$id_medico, $id_res]);
if (!$check->fetchColumn()) acceso_denegado('Este residente no está asignado a tu perfil.');

$res = $pdo->prepare("SELECT * FROM residentes WHERE id_residente=? AND estado='activo'");
$res->execute([$id_res]);
$res = $res->fetch();
if (!$res) { header('Location: index.php'); exit; }

$atenciones = $pdo->prepare("SELECT * FROM visitas_medicas
    WHERE id_medico=? AND id_residente=? ORDER BY fecha_hora DESC LIMIT 10");
$atenciones->execute([$id_medico, $id_res]);
$atenciones = $atenciones->fetchAll();

$medicacion = $recetas = $tareas_mias = $restricciones = $controles = [];

if ($PUEDE_MEDICACION) {
    $medicacion = $pdo->prepare("SELECT mr.*, med.nombre med_nombre, med.presentacion
        FROM medicacion_residente mr JOIN medicamentos med ON med.id_medicamento=mr.id_medicamento
        WHERE mr.id_residente=? AND mr.estado='activo' ORDER BY mr.horario");
    $medicacion->execute([$id_res]);
    $medicacion = $medicacion->fetchAll();

    $recetas = $pdo->prepare("SELECT * FROM recetas_medicas
        WHERE id_residente=? AND estado='activa' ORDER BY fecha_inicio DESC");
    $recetas->execute([$id_res]);
    $recetas = $recetas->fetchAll();

    $controles = $pdo->prepare("SELECT * FROM controles_clinicos
        WHERE id_residente=? ORDER BY fecha_hora DESC LIMIT 20");
    $controles->execute([$id_res]);
    $controles = $controles->fetchAll();
}

if ($PUEDE_TAREAS) {
    $tareas_mias = $pdo->prepare("SELECT tr.*,
        (SELECT estado_tarea FROM administracion_tareas at
         WHERE at.id_tarea=tr.id_tarea ORDER BY fecha_hora DESC LIMIT 1) ultimo_estado,
        (SELECT fecha_hora FROM administracion_tareas at2
         WHERE at2.id_tarea=tr.id_tarea ORDER BY fecha_hora DESC LIMIT 1) ultima_fecha
        FROM tareas_residente tr
        WHERE tr.id_residente=? AND tr.created_by=? AND tr.estado='activo'
        ORDER BY tr.horario");
    $tareas_mias->execute([$id_res, uid()]);
    $tareas_mias = $tareas_mias->fetchAll();
}

if ($PUEDE_RESTRICCIONES) {
    $restricciones = $pdo->prepare("SELECT * FROM restricciones_residente
        WHERE id_residente=? AND estado='activo' ORDER BY severidad DESC, alimento");
    $restricciones->execute([$id_res]);
    $restricciones = $restricciones->fetchAll();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nueva_tarea_prof'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo_t = in_array($_POST['tipo_tarea'] ?? '', ['fija','puntual']) ? $_POST['tipo_tarea'] : 'fija';
    if ($titulo) {
        $pdo->prepare("INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,tipo,created_by)
                       VALUES(?,?,?,?,?,?)")
            ->execute([$id_res, $titulo, trim($_POST['descripcion']??'')?: null,
                       trim($_POST['horario']??'')?: null, $tipo_t, uid()]);
        header('Location: ficha.php?id='.$id_res.'&toast='.urlencode('Tarea creada correctamente').'&tipo=ok'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nueva_restriccion_prof'])) {
    $alimento = trim($_POST['alimento'] ?? '');
    if ($alimento) {
        $pdo->prepare("INSERT INTO restricciones_residente(id_residente,tipo,alimento,severidad,detalle,created_by)
                       VALUES(?,?,?,?,?,?)")
            ->execute([$id_res, $_POST['tipo']??'intolerancia', $alimento,
                       $_POST['severidad']??'moderada', trim($_POST['detalle']??'')?: null, uid()]);
        header('Location: ficha.php?id='.$id_res.'&toast='.urlencode('Restricción registrada').'&tipo=ok'); exit;
    }
}

if (isset($_GET['baja_tarea']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE tareas_residente SET estado='baja'
                   WHERE id_tarea=? AND id_residente=? AND created_by=?")
        ->execute([(int)$_GET['baja_tarea'], $id_res, uid()]);
    header('Location: ficha.php?id='.$id_res.'&toast='.urlencode('Tarea dada de baja').'&tipo=ok'); exit;
}

if (isset($_GET['baja_restr']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE restricciones_residente SET estado='baja'
                   WHERE id_restriccion=? AND id_residente=?")
        ->execute([(int)$_GET['baja_restr'], $id_res]);
    header('Location: ficha.php?id='.$id_res.'&toast='.urlencode('Restricción dada de baja').'&tipo=ok'); exit;
}

$TIPOS_LABELS = ['medico'=>'Médico/a','kinesiologo'=>'Kinesiólogo/a','fisioterapeuta'=>'Fisioterapeuta',
    'podologo'=>'Podólogo/a','enfermero'=>'Enfermero/a','nutricionista'=>'Nutricionista','otro'=>'Otro'];
$SEV = ['leve'=>'Leve','moderada'=>'Moderada','grave'=>'Grave'];
$TIPO_REST = ['alergia'=>'Alergia','intolerancia'=>'Intolerancia','patologia'=>'Patología',
    'textura'=>'Textura','religiosa'=>'Religiosa','preferencia'=>'Preferencia'];

include '../config/menu.php';
$edad = $res['fecha_nacimiento']
    ? (int)date_diff(new DateTime($res['fecha_nacimiento']), new DateTime())->y : null;
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:14px">
        <a href="index.php" class="btn gray" style="font-size:12px">← Volver</a>
        <div>
            <h1 style="margin:0"><?= h($res['nombre'].' '.$res['apellido']) ?></h1>
            <p class="small" style="margin:0;color:var(--text-muted)">
                <?= $edad ? $edad.' años' : '' ?>
                <?php if ($res['diagnostico']): ?> · <?= h($res['diagnostico']) ?><?php endif; ?>
            </p>
        </div>
    </div>
    <a class="btn" href="atencion.php?id=<?= $id_res ?>">+ Registrar atención</a>
</div>

<?php if ($PUEDE_MEDICACION): ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
    <div class="card" style="margin:0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <h3 style="margin:0">Recetas vigentes</h3>
            <?php if ($PUEDE_RECETAS): ?>
            <a class="btn" style="font-size:12px" href="../medicacion/recetas.php?residente=<?= $id_res ?>">+ Registrar receta</a>
            <?php endif; ?>
        </div>
        <?php if (!empty($recetas)): ?>
            <?php foreach ($recetas as $rc): ?>
            <div style="border-left:3px solid var(--color-primary,#2D9B8A);padding:6px 10px;margin-bottom:8px;font-size:13px">
                <b><?= h($rc['diagnostico']) ?></b><br>
                <span class="small"><?= $rc['fecha_inicio'] ?> <?= $rc['fecha_fin'] ? '→ '.$rc['fecha_fin'] : '(permanente)' ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card" style="margin:0">
        <h3 style="margin:0 0 10px">Medicación actual</h3>
        <?php if (!empty($medicacion)): ?>
            <?php foreach ($medicacion as $m): ?>
            <div style="display:flex;justify-content:space-between;padding:5px 0;
                        border-bottom:1px solid var(--border-color,#eee);font-size:13px">
                <span><b><?= h($m['med_nombre']) ?></b> <?= h($m['dosis']) ?></span>
                <span class="small" style="color:var(--text-muted)"><?= h($m['horario']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($controles)): ?>
<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">Controles clínicos recientes</h3>
    <table class="no-datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Fecha</th><th>Control</th><th>Valor</th><th>Observaciones</th></tr></thead>
        <tbody>
        <?php
        $labels_ctl = ['tension_sistolica'=>'T. sistólica','tension_diastolica'=>'T. diastólica',
            'glucemia'=>'Glucemia','temperatura'=>'Temperatura','peso'=>'Peso',
            'saturacion_oxigeno'=>'Saturación O₂','frecuencia_cardiaca'=>'Frec. cardíaca'];
        foreach ($controles as $c): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($c['fecha_hora'])) ?></td>
            <td><?= h($labels_ctl[$c['tipo_control']] ?? $c['tipo_control']) ?></td>
            <td><b><?= h($c['valor']) ?> <?= h($c['unidad']) ?></b></td>
            <td><span class="small"><?= h($c['observaciones'] ?? '') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($PUEDE_TAREAS): ?>
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h3 style="margin:0">Mis indicaciones de tareas</h3>
        <button class="btn" onclick="document.getElementById('modal-tarea-prof').style.display='flex'">
            + Registrar tarea
        </button>
    </div>
    <?php if (!empty($tareas_mias)): ?>
    <table class="no-datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Tarea</th><th>Tipo</th><th>Horario</th><th>Último cumplimiento</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($tareas_mias as $t):
            $cls_est = match($t['ultimo_estado'] ?? '') {
                'realizada'    => 'pill-ok',
                'no_realizada' => 'pill-mal',
                'no_pudo'      => 'pill-aviso',
                default        => 'pill-neutro'
            };
            $lbl_est = match($t['ultimo_estado'] ?? '') {
                'realizada'    => 'Realizada',
                'no_realizada' => 'No realizada',
                'no_pudo'      => 'No se pudo',
                default        => 'Sin registrar'
            };
        ?>
        <tr>
            <td><b><?= h($t['titulo']) ?></b><?php if($t['descripcion']): ?><br><span class="small"><?= h($t['descripcion']) ?></span><?php endif; ?></td>
            <td><span class="badge"><?= $t['tipo'] ?></span></td>
            <td><?= $t['horario'] ?: '' ?></td>
            <td>
                <span class="pill <?= $cls_est ?>"><?= $lbl_est ?></span>
                <?php if ($t['ultima_fecha']): ?>
                    <br><span class="small"><?= date('d/m/Y', strtotime($t['ultima_fecha'])) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <a class="btn red" style="font-size:11px;padding:3px 8px;white-space:nowrap"
                   href="?id=<?= $id_res ?>&baja_tarea=<?= $t['id_tarea'] ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja esta tarea?','Dar de baja','red')">
                   Dar de baja
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div id="modal-tarea-prof" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;
                padding:24px 28px;width:100%;max-width:480px;position:relative">
        <button onclick="document.getElementById('modal-tarea-prof').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;
                       font-size:22px;cursor:pointer;color:inherit"></button>
        <h3 style="margin:0 0 16px">Registrar tarea para <?= h($res['nombre'].' '.$res['apellido']) ?></h3>
        <form method="post">
            <input type="hidden" name="nueva_tarea_prof" value="1">
            <label style="font-size:13px;font-weight:600;display:block">Tarea *</label>
            <input name="titulo" required style="width:100%;box-sizing:border-box;margin-bottom:10px">
            <label style="font-size:13px;font-weight:600;display:block">Detalle</label>
            <textarea name="descripcion" rows="2" placeholder="Descripción detallada"
                      style="width:100%;box-sizing:border-box;resize:vertical;margin-bottom:10px"></textarea>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Horario</label>
                    <input type="time" name="horario" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Tipo</label>
                    <select name="tipo_tarea" style="width:100%;box-sizing:border-box">
                        <option value="fija">Fija (todos los días)</option>
                        <option value="puntual">Puntual (una vez)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn">Guardar tarea</button>
                <button type="button" class="btn gray"
                        onclick="document.getElementById('modal-tarea-prof').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($PUEDE_RESTRICCIONES): ?>
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <h3 style="margin:0">Restricciones alimentarias</h3>
        <button class="btn" onclick="document.getElementById('modal-restr-prof').style.display='flex'">
            + Agregar restricción
        </button>
    </div>
    <?php if (!empty($restricciones)): ?>
    <table class="no-datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Alimento</th><th>Tipo</th><th>Severidad</th><th>Detalle</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($restricciones as $rx): ?>
        <tr>
            <td><b><?= h($rx['alimento']) ?></b></td>
            <td><?= h($TIPO_REST[$rx['tipo']] ?? $rx['tipo']) ?></td>
            <td>
                <span class="pill <?= $rx['severidad']==='grave'?'pill-mal':($rx['severidad']==='moderada'?'pill-aviso':'pill-neutro') ?>">
                    <?= h($SEV[$rx['severidad']] ?? $rx['severidad']) ?>
                </span>
            </td>
            <td><span class="small"><?= h($rx['detalle'] ?? '') ?></span></td>
            <td>
                <a class="btn red" style="font-size:11px;padding:3px 8px;white-space:nowrap"
                   href="?id=<?= $id_res ?>&baja_restr=<?= $rx['id_restriccion'] ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja esta restricción?','Dar de baja','red')">
                   Dar de baja
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div id="modal-restr-prof" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;
                padding:24px 28px;width:100%;max-width:480px;position:relative">
        <button onclick="document.getElementById('modal-restr-prof').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;
                       font-size:22px;cursor:pointer;color:inherit"></button>
        <h3 style="margin:0 0 16px">Registrar restricción alimentaria</h3>
        <form method="post">
            <input type="hidden" name="nueva_restriccion_prof" value="1">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Alimento *</label>
                    <input name="alimento" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Tipo</label>
                    <select name="tipo" style="width:100%;box-sizing:border-box">
                        <?php foreach ($TIPO_REST as $k=>$v): ?><option value="<?=$k?>"><?=$v?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Severidad</label>
                    <select name="severidad" style="width:100%;box-sizing:border-box">
                        <option value="leve">Leve</option>
                        <option value="moderada" selected>Moderada</option>
                        <option value="grave">Grave</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Detalle</label>
                    <input name="detalle" style="width:100%;box-sizing:border-box">
                </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray"
                        onclick="document.getElementById('modal-restr-prof').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top:0">Mis atenciones con <?= h($res['nombre']) ?></h3>
    <?php if (!empty($atenciones)): ?>
    <table class="no-datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Fecha</th><th>Motivo</th><th>Diagnóstico</th><th>Indicaciones</th></tr></thead>
        <tbody>
        <?php foreach ($atenciones as $at): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($at['fecha_hora'])) ?></td>
            <td><?= h($at['motivo']) ?></td>
            <td><span class="small"><?= h($at['diagnostico'] ?? '') ?></span></td>
            <td><span class="small"><?= h($at['indicaciones'] ?? '') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
window.addEventListener('click', function(e) {
    ['modal-tarea-prof','modal-restr-prof'].forEach(function(id) {
        var m = document.getElementById(id);
        if (m && e.target === m) m.style.display = 'none';
    });
});
</script>

<?php include '../config/footer.php'; ?>
