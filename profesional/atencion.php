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
$id_res_get = (int)($_GET['id'] ?? 0);

$residentes = $pdo->prepare("SELECT DISTINCT r.id_residente, r.nombre, r.apellido
    FROM residentes r
    JOIN visitas_medicas vm ON vm.id_residente = r.id_residente
    WHERE r.estado='activo' AND vm.id_medico=?
    ORDER BY r.apellido, r.nombre");
$residentes->execute([$id_medico]);
$residentes = $residentes->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_atencion'])) {
    $id_res   = (int)$_POST['id_residente'];
    $motivo   = trim($_POST['motivo'] ?? '');
    $diag     = trim($_POST['diagnostico'] ?? '');
    $indic    = trim($_POST['indicaciones'] ?? '');
    $obs      = trim($_POST['observaciones'] ?? '');
    $receta   = trim($_POST['receta'] ?? '');
    $fecha    = $_POST['fecha_hora'] ?? date('Y-m-d H:i:s');
    $gen      = isset($_POST['genera_tareas']) ? 1 : 0;

    if (!$id_res) { header('Location: atencion.php?error='.urlencode('Seleccioná un residente')); exit; }
    if (!$motivo) { header('Location: atencion.php?id='.$id_res.'&error='.urlencode('El motivo es obligatorio')); exit; }

    try {
        $pdo->prepare("INSERT INTO visitas_medicas
            (id_residente,id_medico,id_usuario,fecha_hora,motivo,observaciones,
             diagnostico,indicaciones,receta,genera_tareas)
            VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id_res,$id_medico,uid(),$fecha,$motivo,
                       $obs?:null,$diag?:null,$indic?:null,$receta?:null,$gen]);

        if ($gen && $indic) {
            $titulo_t = 'Indicación '.($med['apellido'] ?? '').': '.mb_substr($indic,0,80);
            $desc_t   = 'Indicado por '.$med['nombre'].' '.$med['apellido']
                       .' el '.date('d/m/Y', strtotime($fecha)).'. '.$indic;
            $pdo->prepare("INSERT INTO tareas_residente(id_residente,titulo,descripcion,tipo,created_by)
                           VALUES(?,?,?,'indicacion_prof',?)")
                ->execute([$id_res,$titulo_t,$desc_t,uid()]);
        }

        header('Location: atencion.php?id='.$id_res.'&guardado=1&toast='.urlencode('Atención registrada correctamente').'&tipo=ok');
    } catch (PDOException $e) {
        header('Location: atencion.php?id='.$id_res.'&error='.urlencode('Error al guardar. Intentá de nuevo.'));
    }
    exit;
}

include '../config/menu.php';
$error = $_GET['error'] ?? '';
$TIPOS_LABELS = ['medico'=>'Médico/a','kinesiologo'=>'Kinesiólogo/a','fisioterapeuta'=>'Fisioterapeuta',
    'podologo'=>'Podólogo/a','enfermero'=>'Enfermero/a','nutricionista'=>'Nutricionista','otro'=>'Otro'];
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
    <a href="index.php" class="btn gray" style="font-size:12px">← Mis residentes</a>
    <h1 style="margin:0">Registrar atención</h1>
</div>

<?php if ($error): ?>
<div style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= h($error) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:680px">
    <form method="post">
        <input type="hidden" name="guardar_atencion" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Residente *</label>
                <select name="id_residente" required class="buscador" style="width:100%;box-sizing:border-box">
                    <option value="">Elegir...</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_res_get==$r['id_residente']?'selected':'' ?>>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Fecha y hora</label>
                <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>"
                       style="width:100%;box-sizing:border-box">
            </div>
            <div style="grid-column:1/-1">
                <label style="font-size:13px;font-weight:600;display:block">Motivo *</label>
                <input name="motivo" required
                       style="width:100%;box-sizing:border-box">
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">
                    <?= in_array($tipo,['medico','enfermero']) ? 'Diagnóstico / Evolución' : 'Evaluación' ?>
                </label>
                <textarea name="diagnostico" rows="3"
                          placeholder="Diagnóstico o evolución del residente"
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Observaciones</label>
                <textarea name="observaciones" rows="3" placeholder="Notas adicionales"
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>
            <div style="grid-column:1/-1">
                <label style="font-size:13px;font-weight:600;display:block">
                    Indicaciones para las cuidadoras
                    <span style="font-weight:400;color:var(--text-muted)">(pueden convertirse en tarea automática)</span>
                </label>
                <textarea name="indicaciones" rows="3"
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>
            <div style="grid-column:1/-1">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px">
                    <input type="checkbox" name="genera_tareas" value="1" style="width:auto">
                    <span>
                        <b>Convertir indicaciones en tarea para las cuidadoras</b>
                    </span>
                </label>
            </div>
        </div>
        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn" id="btn-guardar">Guardar atención</button>
            <a href="<?= $id_res_get ? 'ficha.php?id='.$id_res_get : 'index.php' ?>" class="btn gray">Cancelar</a>
        </div>
    </form>
</div>

<?php
$res_nombre_completo = '';
if ($id_res_get) {
    $rn = $pdo->prepare("SELECT nombre, apellido FROM residentes WHERE id_residente=?");
    $rn->execute([$id_res_get]);
    $rn = $rn->fetch();
    $res_nombre_completo = $rn ? $rn['nombre'].' '.$rn['apellido'] : '';
}
$url_recetas   = BASE_URL.'/medicacion/recetas.php?residente='.$id_res_get;
$url_controles = BASE_URL.'/controles/plan.php?residente='.$id_res_get;
$url_tareas    = BASE_URL.'/medicacion/tareas.php?residente='.$id_res_get;
$url_ficha     = BASE_URL.'/profesional/ficha.php?id='.$id_res_get;
require_once '../config/accesos_prof.php';
$es_medico     = in_array($tipo, TIPOS_PUEDE_RECETAS);
$puede_ctrl    = in_array($tipo, TIPOS_PUEDE_CONTROLES);
$puede_tareas  = in_array($tipo, TIPOS_PUEDE_TAREAS);
?>

<?php if (isset($_GET['guardado']) && $id_res_get): ?>
<script>
(function() {
    var resNombre = <?= json_encode($res_nombre_completo) ?>;
    var urlRecetas   = <?= json_encode($url_recetas) ?>;
    var urlControles = <?= json_encode($url_controles) ?>;
    var urlTareas    = <?= json_encode($url_tareas) ?>;
    var urlFicha     = <?= json_encode($url_ficha) ?>;
    var esMedico     = <?= $es_medico ? 'true' : 'false' ?>;
    var puedeCtrl    = <?= $puede_ctrl ? 'true' : 'false' ?>;
    var puedeTablas  = <?= $puede_tareas ? 'true' : 'false' ?>;

    function preguntarTareas() {
        if (!puedeTablas) { window.location.href = urlFicha; return; }
        if (confirm('¿Querés crear tareas de cuidado para ' + resNombre + '?\n\nLas tareas aparecerán en la agenda de las cuidadoras.')) {
            window.location.href = urlTareas;
        } else {
            window.location.href = urlFicha;
        }
    }

    function preguntarControles() {
        if (!puedeCtrl) { preguntarTareas(); return; }
        if (confirm('¿Querés actualizar el plan de controles clínicos de ' + resNombre + '?\n\n(Presión, glucemia, saturación, etc.)')) {
            window.location.href = urlControles;
        } else {
            preguntarTareas();
        }
    }

    function preguntarRecetas() {
        if (!esMedico) { preguntarControles(); return; }
        if (confirm('¿Querés crear o actualizar una receta para ' + resNombre + '?')) {
            window.location.href = urlRecetas;
        } else {
            preguntarControles();
        }
    }

    preguntarRecetas();
})();
</script>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
