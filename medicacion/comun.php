<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once __DIR__ . '/../config/accesos_prof.php';
$seccion_actual = $seccion ?? '';
$acceso_ok = es_admin() || es_cuidadora() || es_asistente();
if (!$acceso_ok && es_profesional()) {
    $acceso_ok = match($seccion_actual) {
        'recetas'    => prof_puede_recetas($pdo),
        'medicacion' => prof_puede_medicacion($pdo),
        'tareas'     => prof_puede_tareas($pdo),
        'agenda'     => prof_puede_medicacion($pdo),
        default      => false,
    };
}
if (!$acceso_ok) acceso_denegado('No tenés permisos para acceder a esta sección.');

$seccion = $seccion ?? 'medicacion';

function pagina_actual() {
    return basename($_SERVER['PHP_SELF']);
}

function pill_estado_med($e) {
    return match($e) {
        'administrado' => '<span class="pill pill-ok">Administrado</span>',
        'no_pudo'      => '<span class="pill pill-aviso">No se pudo</span>',
        'no_dado'      => '<span class="pill pill-mal">No administrado</span>',
        default        => '<span class="pill pill-neutro">Sin registrar</span>',
    };
}

function estado_label($e) {
    $map = [
        'administrado' => 'Administrado',
        'realizada'    => 'Realizada',
        'no_pudo'      => 'No se pudo',
        'no_dado'      => 'No administrado',
        'no_realizada' => 'No realizada',
        'pendiente'    => 'Pendiente',
        ''             => 'Pendiente',
        null           => 'Pendiente'
    ];
    return $map[$e] ?? $e;
}

function estado_class($e) {
    if ($e === 'administrado' || $e === 'realizada') return 'estado-ok';
    if ($e === 'no_pudo')                            return 'estado-alerta';
    if ($e === 'no_dado'  || $e === 'no_realizada')  return 'estado-problema';
    return 'estado-pendiente';
}

function subir_receta() {
    if (empty($_FILES['foto_receta']['name'])) return null;
    if (!is_dir('../uploads/recetas')) mkdir('../uploads/recetas', 0777, true);
    $ext  = pathinfo($_FILES['foto_receta']['name'], PATHINFO_EXTENSION);
    $name = 'receta_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $dest = '../uploads/recetas/' . $name;
    if (move_uploaded_file($_FILES['foto_receta']['tmp_name'], $dest)) return 'uploads/recetas/' . $name;
    return null;
}

function volver_residente($id) {
    header('Location: ' . pagina_actual() . '?residente=' . (int)$id);
    exit;
}

function volver_con_toast($mensaje, $tipo = 'ok', $id_residente = 0) {
    $url = pagina_actual() . '?toast=' . urlencode($mensaje) . '&tipo=' . $tipo;
    if ($id_residente > 0) $url .= '&residente=' . (int)$id_residente;
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_medicacion'])) {
    $estado = $_POST['accion_medicacion'];
    $motivo = trim($_POST['motivo'] ?? '');
    if (($estado === 'no_pudo' || $estado === 'no_dado') && $motivo === '') {
        $_SESSION['msg'] = 'Para marcar NO PUDE o NO SE DIO, tenés que escribir el motivo.';
        volver_residente($_POST['id_residente'] ?? 0);
    }
    $pdo->prepare("INSERT INTO administracion_medicacion(id_plan,id_residente,id_medicamento,id_cuidadora_usuario,fecha_hora,estado_adm,observaciones) VALUES(?,?,?,?,NOW(),?,?)")
        ->execute([$_POST['id_plan'], $_POST['id_residente'], $_POST['id_medicamento'], uid(), $estado, $motivo]);
    $_SESSION['msg'] = 'Registro guardado correctamente.';
    volver_residente($_POST['id_residente']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_tarea'])) {
    $estado = $_POST['accion_tarea'];
    $motivo = trim($_POST['motivo'] ?? '');
    if (($estado === 'no_realizada' || $estado === 'no_pudo') && $motivo === '') {
        $_SESSION['msg'] = 'Para marcar NO PUDE o NO REALIZADA, tenés que escribir el motivo.';
        volver_residente($_POST['id_residente'] ?? 0);
    }
    $pdo->prepare("INSERT INTO administracion_tareas(id_tarea,id_residente,id_cuidadora_usuario,fecha_hora,estado_tarea,motivo) VALUES(?,?,?,NOW(),?,?)")
        ->execute([$_POST['id_tarea'], $_POST['id_residente'], uid(), $estado, $motivo]);
    $_SESSION['msg'] = 'Tarea registrada correctamente.';
    volver_residente($_POST['id_residente']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && es_admin()) {

    if (isset($_POST['nuevo_medicamento'])) {
        $nombre_med = trim($_POST['nombre'] ?? '');
        if (!$nombre_med) volver_con_toast('El nombre del medicamento es obligatorio.', 'error');
        $dup = $pdo->prepare('SELECT COUNT(*) FROM medicamentos WHERE nombre=?');
        $dup->execute([$nombre_med]);
        if ($dup->fetchColumn() > 0) volver_con_toast('Ya existe un medicamento con ese nombre.', 'warn');
        try {
            $pdo->prepare('INSERT INTO medicamentos(nombre,presentacion,descripcion,created_by) VALUES(?,?,?,?)')
                ->execute([$nombre_med, $_POST['presentacion'], $_POST['descripcion'], uid()]);
            volver_con_toast('Medicamento creado correctamente', 'ok');
        } catch (PDOException $e) {
            volver_con_toast('Error al guardar el medicamento. Intentá de nuevo.', 'error');
        }
    }

    if (isset($_POST['nueva_receta'])) {
        $id_res_r = (int)$_POST['id_residente'];
        $diag_r   = trim($_POST['diagnostico'] ?? '');
        $dosis_r  = trim($_POST['dosis'] ?? '');
        $hors_r   = trim($_POST['horarios'] ?? '');
        if (!$id_res_r) volver_con_toast('Seleccioná un residente.', 'error');
        if (!$diag_r)   volver_con_toast('El diagnóstico / motivo es obligatorio.', 'error');
        if (!$dosis_r)  volver_con_toast('La dosis es obligatoria.', 'error');
        if (!$hors_r)   volver_con_toast('Ingresá al menos un horario.', 'error');
        try {
            $foto      = subir_receta();
            $fecha_fin = trim($_POST['fecha_fin'] ?? '') ?: null;
            $pdo->prepare('INSERT INTO recetas_medicas(id_residente,diagnostico,indicaciones_generales,fecha_inicio,fecha_fin,foto_receta,created_by) VALUES(?,?,?,?,?,?,?)')
                ->execute([$id_res_r, $diag_r, $_POST['indicaciones_generales'], $_POST['fecha_inicio'], $fecha_fin, $foto, uid()]);
            $id_receta = $pdo->lastInsertId();
            $horarios  = preg_split('/[,;\n]+/', $hors_r);
            foreach ($horarios as $h) {
                $h = trim($h); if ($h === '') continue;
                $pdo->prepare('INSERT INTO medicacion_residente(id_receta,id_residente,id_medicamento,dosis,horario,id_responsable,indicacion,fecha_inicio,fecha_fin,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$id_receta, $id_res_r, $_POST['id_medicamento'], $dosis_r, $h,
                               $_POST['id_responsable'] ?: null,
                               $_POST['indicacion'], $_POST['fecha_inicio'], $fecha_fin, uid()]);
            }
            volver_con_toast('Receta y horarios creados correctamente', 'ok');
        } catch (PDOException $e) {
            volver_con_toast('Error al guardar la receta. Verificá los datos e intentá de nuevo.', 'error');
        }
    }

    if (isset($_POST['nueva_tarea'])) {
        $titulo_t = trim($_POST['titulo'] ?? '');
        $id_res_t = (int)$_POST['id_residente'];
        if (!$id_res_t)  volver_con_toast('Seleccioná un residente.', 'error');
        if (!$titulo_t)  volver_con_toast('El nombre de la tarea es obligatorio.', 'error');
        try {
            $pdo->prepare('INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,tipo,created_by) VALUES(?,?,?,?,?,?)')
                ->execute([$id_res_t, $titulo_t, $_POST['descripcion'], $_POST['horario'], $_POST['tipo_tarea'] ?? 'fija', uid()]);
            volver_con_toast('Tarea creada correctamente', 'ok');
        } catch (PDOException $e) {
            volver_con_toast('Error al guardar la tarea. Intentá de nuevo.', 'error');
        }
    }
}

if (isset($_GET['eliminar']) && (es_admin() || (es_profesional() && prof_puede_medicacion($pdo)))) {
    $pdo->prepare("UPDATE medicacion_residente SET estado='baja', updated_by=? WHERE id_plan=?")
        ->execute([uid(), $_GET['eliminar']]);
    volver_con_toast('Plan de medicación dado de baja', 'warn', $_GET['residente'] ?? 0);
}
if (isset($_GET['eliminar_receta']) && (es_admin() || (es_profesional() && prof_puede_recetas($pdo)))) {
    $pdo->prepare("UPDATE recetas_medicas SET estado='finalizada', updated_by=? WHERE id_receta=?")
        ->execute([uid(), $_GET['eliminar_receta']]);
    $pdo->prepare("UPDATE medicacion_residente SET estado='baja', updated_by=? WHERE id_receta=?")
        ->execute([uid(), $_GET['eliminar_receta']]);
    volver_con_toast('Receta dada de baja', 'warn', $_GET['residente'] ?? 0);
}
if (isset($_GET['eliminar_tarea']) && (es_admin() || (es_profesional() && prof_puede_tareas($pdo)))) {
    $pdo->prepare("UPDATE tareas_residente SET estado='baja', updated_by=? WHERE id_tarea=?")
        ->execute([uid(), $_GET['eliminar_tarea']]);
    volver_con_toast('Tarea dada de baja', 'warn', $_GET['residente'] ?? 0);
}

include '../config/menu.php';

$res = es_profesional()
    ? query_residentes($pdo, '*')
    : $pdo->query("SELECT * FROM residentes WHERE estado='activo' ORDER BY apellido,nombre")->fetchAll();
$meds = $pdo->query("SELECT * FROM medicamentos WHERE estado='activo' ORDER BY nombre")->fetchAll();

$personal_med = $pdo->query("SELECT id_usuario, nombre, apellido, rol FROM usuarios
    WHERE rol IN('cuidadora','asistente','admin') AND estado='activo'
    ORDER BY FIELD(rol,'cuidadora','asistente','admin'), apellido, nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$debe_elegir  = (!es_admin() && $id_residente <= 0);

$res_sel = null;
if ($id_residente > 0) {
    $tmp     = array_filter($res, fn($x) => $x['id_residente'] == $id_residente);
    $res_sel = reset($tmp) ?: null;
}

$rows = []; $recetas = []; $hist = []; $tareas = [];

if (!$debe_elegir) {
    $whereRes  = $id_residente ? ' AND mr.id_residente='  . $id_residente : '';
    $whereRec  = $id_residente ? ' AND rec.id_residente=' . $id_residente : '';
    $whereHist = $id_residente ? ' AND am.id_residente='  . $id_residente : '';
    $whereTar  = $id_residente ? ' AND tr.id_residente='  . $id_residente : '';

    if ($seccion === 'medicacion') {
        $rows = $pdo->query("SELECT mr.*,r.nombre rn,r.apellido ra,m.nombre med,m.presentacion,
            rec.diagnostico,rec.fecha_inicio r_inicio,rec.fecha_fin r_fin,rec.foto_receta,
            (SELECT am.estado_adm FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_estado,
            (SELECT am.observaciones FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_motivo,
            (SELECT am.fecha_hora FROM administracion_medicacion am WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_horario,
            (SELECT u.nombre FROM administracion_medicacion am LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario WHERE am.id_plan=mr.id_plan ORDER BY am.fecha_hora DESC LIMIT 1) ultimo_usuario
            FROM medicacion_residente mr
            JOIN residentes r ON r.id_residente=mr.id_residente
            JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento
            LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
            WHERE mr.estado='activo'
            AND (mr.fecha_inicio IS NULL OR CURDATE()>=mr.fecha_inicio)
            AND (mr.fecha_fin IS NULL OR CURDATE()<=mr.fecha_fin)
            $whereRes ORDER BY r.apellido,mr.horario")->fetchAll();

        $hist = $pdo->query("SELECT am.*,r.nombre rn,r.apellido ra,m.nombre med,m.presentacion,
            CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora,mr.dosis,mr.horario,rec.diagnostico
            FROM administracion_medicacion am
            JOIN residentes r ON r.id_residente=am.id_residente
            JOIN medicamentos m ON m.id_medicamento=am.id_medicamento
            LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
            LEFT JOIN medicacion_residente mr ON mr.id_plan=am.id_plan
            LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
            WHERE am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            $whereHist ORDER BY am.fecha_hora DESC")->fetchAll();
    }

    if ($seccion === 'recetas') {
        $recetas = $pdo->query("SELECT rec.*,r.nombre rn,r.apellido ra,u.nombre creador
            FROM recetas_medicas rec
            JOIN residentes r ON r.id_residente=rec.id_residente
            LEFT JOIN usuarios u ON u.id_usuario=rec.created_by
            WHERE rec.estado != 'finalizada' $whereRec ORDER BY rec.fecha_inicio DESC")->fetchAll();
    }

    if ($seccion === 'tareas') {
        $tareas = $pdo->query("SELECT tr.*,r.nombre rn,r.apellido ra,
            (SELECT at2.estado_tarea FROM administracion_tareas at2 WHERE at2.id_tarea=tr.id_tarea ORDER BY at2.fecha_hora DESC LIMIT 1) ultimo_estado,
            (SELECT at2.motivo FROM administracion_tareas at2 WHERE at2.id_tarea=tr.id_tarea ORDER BY at2.fecha_hora DESC LIMIT 1) ultimo_motivo,
            (SELECT at2.fecha_hora FROM administracion_tareas at2 WHERE at2.id_tarea=tr.id_tarea ORDER BY at2.fecha_hora DESC LIMIT 1) ultimo_horario
            FROM tareas_residente tr
            JOIN residentes r ON r.id_residente=tr.id_residente
            WHERE tr.estado='activo'
            $whereTar ORDER BY tr.horario,r.apellido")->fetchAll();
    }
}

function mostrar_mensaje_sesion() {
    if (empty($_SESSION['msg'])) return;
    echo '<div class="alert">' . htmlspecialchars($_SESSION['msg']) . '</div>';
    unset($_SESSION['msg']);
}

function card_seleccionar_residente($res, $id_residente, $res_sel, $boton_extra = '') {
    ?>
    <div class="card">
        <h2>Seleccionar residente</h2>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <select id="select-residente" class="buscador"
                    onchange="window.location='<?= pagina_actual() ?>?residente='+this.value"
                    style="min-width:260px;padding:10px 14px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
                <?php if (es_admin()): ?>
                    <option value="0" <?= $id_residente == 0 ? 'selected' : '' ?>>Todos los residentes</option>
                <?php else: ?>
                    <option value="" <?= $id_residente == 0 ? 'selected' : '' ?> disabled>Elegir residente...</option>
                <?php endif; ?>
                <?php foreach ($res as $x): ?>
                    <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) . ($x['id_habitacion'] ? ' (Hab. ' . $x['id_habitacion'] . ')' : '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $boton_extra ?>
            <?php if ($id_residente > 0): ?>
                <a class="btn gray" href="<?= BASE_URL ?>/residentes/listar.php?ver=<?= $id_residente ?>">Ver ficha completa</a>
            <?php endif; ?>
        </div>
        <?php if ($res_sel): ?>
            <p style="margin-top:12px;color:#2D9B8A;font-weight:bold">
Residente seleccionado: <?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

function bloquear_si_falta_residente($debe_elegir, $titulo, $texto) {
    if (!$debe_elegir) return;
    echo '<div class="card bloqueo-med"><h2>' . htmlspecialchars($titulo) . '</h2><p>'
       . htmlspecialchars($texto) . '</p></div>';
    include '../config/footer.php';
    exit;
}

function aviso_solo_lectura() {
    if (es_admin() || es_profesional()) return;
    echo '<div class="card"><b>Modo cuidadora/asistente:</b> solo podés ver lo asignado y confirmar. '
       . 'El sistema guarda tu nombre, fecha y hora automáticamente.</div>';
}

function barra_acciones_admin($botones) {
    if (!es_admin() && !es_profesional()) return;
    ?>
    <div class="card">
        <h3 style="margin-top:0">Acciones de administración</h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php foreach ($botones as $b): ?>
                <button class="btn" onclick="abrirModalAdmin('<?= $b[0] ?>')"><?= htmlspecialchars($b[1]) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function js_modales_admin($ids) {
    ?>
    <script>
    function abrirModalAdmin(id) { document.getElementById(id).style.display = 'flex'; }
    function cerrarModalAdmin(id) { document.getElementById(id).style.display = 'none'; }
    <?= json_encode($ids) ?>.forEach(function(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function(e){ if (e.target === this) cerrarModalAdmin(id); });
    });
    </script>
    <?php
}

function js_selector_accion() {
    ?>
    <script>
    document.querySelectorAll('.selector-accion').forEach(function(sel) {
        function revisar() {
            var form   = sel.closest('form');
            var motivo = form.querySelector('.motivo-js');
            var v      = sel.value;
            if (v === 'no_pudo' || v === 'no_dado' || v === 'no_realizada') {
                motivo.style.display = 'block';
                motivo.required      = true;
            } else {
                motivo.style.display = 'none';
                motivo.required      = false;
                motivo.value         = '';
            }
        }
        sel.addEventListener('change', revisar);
        revisar();
    });
    </script>
    <?php
}

function sufijo_residente($id_residente) {
    return $id_residente > 0 ? '&residente=' . (int)$id_residente : '';
}

function sin_datos($texto) {
    echo '<div class="card" style="text-align:center;color:var(--text-muted,#888);font-size:14px">'
       . htmlspecialchars($texto) . '</div>';
}
