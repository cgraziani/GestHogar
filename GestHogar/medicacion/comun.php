<?php
// ============================================================
// CÓDIGO COMPARTIDO: MEDICACIÓN, RECETAS Y TAREAS
// Las tres secciones (listar.php, recetas.php, tareas.php) usan
// los mismos permisos, altas, bajas y selector de residente.
//
// Cada página define $seccion antes de incluir este archivo:
//   'medicacion' | 'recetas' | 'tareas'
// Eso decide qué datos se consultan.
// ============================================================
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

$seccion = $seccion ?? 'medicacion';

// Todas las redirecciones vuelven a la página actual, así el usuario
// no salta de sección al guardar.
function pagina_actual() {
    return basename($_SERVER['PHP_SELF']);
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

// ============================================================
// POST: registrar medicación
// ============================================================
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

// ============================================================
// POST: registrar tarea
// ============================================================
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

// ============================================================
// POST: acciones de admin (altas)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && es_admin()) {

    if (isset($_POST['nuevo_medicamento'])) {
        $pdo->prepare('INSERT INTO medicamentos(nombre,presentacion,descripcion,created_by) VALUES(?,?,?,?)')
            ->execute([$_POST['nombre'], $_POST['presentacion'], $_POST['descripcion'], uid()]);
        volver_con_toast('Medicamento creado correctamente', 'ok');
    }

    if (isset($_POST['nueva_receta'])) {
        $foto      = subir_receta();
        $fecha_fin = trim($_POST['fecha_fin'] ?? '') ?: null;
        $pdo->prepare('INSERT INTO recetas_medicas(id_residente,diagnostico,indicaciones_generales,fecha_inicio,fecha_fin,foto_receta,created_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([$_POST['id_residente'], $_POST['diagnostico'], $_POST['indicaciones_generales'], $_POST['fecha_inicio'], $fecha_fin, $foto, uid()]);
        $id_receta = $pdo->lastInsertId();
        $horarios  = preg_split('/[,;\n]+/', $_POST['horarios']);
        foreach ($horarios as $h) {
            $h = trim($h); if ($h === '') continue;
            $pdo->prepare('INSERT INTO medicacion_residente(id_receta,id_residente,id_medicamento,dosis,horario,indicacion,fecha_inicio,fecha_fin,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$id_receta, $_POST['id_residente'], $_POST['id_medicamento'], $_POST['dosis'], $h, $_POST['indicacion'], $_POST['fecha_inicio'], $fecha_fin, uid()]);
        }
        volver_con_toast('Receta y horarios creados', 'ok');
    }

    if (isset($_POST['nueva_tarea'])) {
        $pdo->prepare('INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,tipo,created_by) VALUES(?,?,?,?,?,?)')
            ->execute([$_POST['id_residente'], $_POST['titulo'], $_POST['descripcion'], $_POST['horario'], $_POST['tipo_tarea'] ?? 'fija', uid()]);
        volver_con_toast('Tarea creada correctamente', 'ok');
    }
}

// ============================================================
// GET: dar de baja (estado='baja' + updated_by, sin papelera)
// ============================================================
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare("UPDATE medicacion_residente SET estado='baja', updated_by=? WHERE id_plan=?")
        ->execute([uid(), $_GET['eliminar']]);
    volver_con_toast('Plan de medicación dado de baja', 'warn', $_GET['residente'] ?? 0);
}
if (isset($_GET['eliminar_receta']) && es_admin()) {
    $pdo->prepare("UPDATE recetas_medicas SET estado='finalizada', updated_by=? WHERE id_receta=?")
        ->execute([uid(), $_GET['eliminar_receta']]);
    $pdo->prepare("UPDATE medicacion_residente SET estado='baja', updated_by=? WHERE id_receta=?")
        ->execute([uid(), $_GET['eliminar_receta']]);
    volver_con_toast('Receta dada de baja', 'warn', $_GET['residente'] ?? 0);
}
if (isset($_GET['eliminar_tarea']) && es_admin()) {
    $pdo->prepare("UPDATE tareas_residente SET estado='baja', updated_by=? WHERE id_tarea=?")
        ->execute([uid(), $_GET['eliminar_tarea']]);
    volver_con_toast('Tarea dada de baja', 'warn', $_GET['residente'] ?? 0);
}

// ============================================================
// DATOS BASE (comunes a las tres secciones)
// ============================================================
include '../config/menu.php';

$res  = $pdo->query("SELECT * FROM residentes WHERE estado='activo' ORDER BY apellido,nombre")->fetchAll();
$meds = $pdo->query("SELECT * FROM medicamentos WHERE estado='activo' ORDER BY nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$debe_elegir  = (!es_admin() && $id_residente <= 0);

$res_sel = null;
if ($id_residente > 0) {
    $tmp     = array_filter($res, fn($x) => $x['id_residente'] == $id_residente);
    $res_sel = reset($tmp) ?: null;
}

// ============================================================
// DATOS DE CADA SECCIÓN
// Solo se consulta lo que la página realmente muestra.
// ============================================================
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
            u.nombre cuidadora,mr.dosis,mr.horario,rec.diagnostico
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

// ============================================================
// BLOQUES DE INTERFAZ REUTILIZABLES
// ============================================================

// Mensaje de sesión (validaciones de motivo obligatorio)
function mostrar_mensaje_sesion() {
    if (empty($_SESSION['msg'])) return;
    echo '<div class="alert">' . htmlspecialchars($_SESSION['msg']) . '</div>';
    unset($_SESSION['msg']);
}

// Selector de residente: idéntico en las tres secciones.
// $boton_extra permite agregar el botón propio de cada página.
function card_seleccionar_residente($res, $id_residente, $res_sel, $boton_extra = '') {
    ?>
    <div class="card">
        <h2>Seleccionar residente</h2>
        <p class="small">
            <?php if (!es_admin()): ?>
                Seleccioná el residente para ver lo que le corresponde.
            <?php else: ?>
                Podés filtrar por residente o ver todos a la vez.
            <?php endif; ?>
        </p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <select id="select-residente" class="buscador"
                    onchange="window.location='<?= pagina_actual() ?>?residente='+this.value"
                    style="min-width:260px;padding:10px 14px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
                <?php if (es_admin()): ?>
                    <option value="0" <?= $id_residente == 0 ? 'selected' : '' ?>>— Todos los residentes —</option>
                <?php else: ?>
                    <option value="" <?= $id_residente == 0 ? 'selected' : '' ?> disabled>Elegir residente...</option>
                <?php endif; ?>
                <?php foreach ($res as $x): ?>
                    <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) . ($x['id_habitacion'] ? ' — Hab. ' . $x['id_habitacion'] : '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $boton_extra ?>
            <?php if ($id_residente > 0): ?>
                <a class="btn gray" href="<?= BASE_URL ?>/residentes/listar.php?ver=<?= $id_residente ?>">Ver ficha completa</a>
            <?php endif; ?>
        </div>
        <?php if ($res_sel): ?>
            <p style="margin-top:12px;color:#2D9B8A;font-weight:bold">
                ✓ Residente seleccionado: <?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?>
            </p>
        <?php elseif (es_admin() && $id_residente == 0): ?>
            <p style="margin-top:12px;color:#888;font-size:13px">Mostrando todos los residentes.</p>
        <?php endif; ?>
    </div>
    <?php
}

// Cuidadora/asistente sin residente elegido: corta la página acá.
function bloquear_si_falta_residente($debe_elegir, $titulo, $texto) {
    if (!$debe_elegir) return;
    echo '<div class="card bloqueo-med"><h2>' . htmlspecialchars($titulo) . '</h2><p>'
       . htmlspecialchars($texto) . '</p></div>';
    include '../config/footer.php';
    exit;
}

// Aviso para roles que solo confirman
function aviso_solo_lectura() {
    if (es_admin()) return;
    echo '<div class="card"><b>Modo cuidadora/asistente:</b> solo podés ver lo asignado y confirmar. '
       . 'El sistema guarda tu nombre, fecha y hora automáticamente.</div>';
}

// Barra de acciones de admin. $botones = [[id_modal, etiqueta], ...]
function barra_acciones_admin($botones) {
    if (!es_admin()) return;
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

// Abre/cierra los modales de admin y los cierra al clickear el fondo.
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

// Muestra/oculta el textarea de motivo según el estado elegido.
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

// Conserva el residente filtrado en los links de baja.
function sufijo_residente($id_residente) {
    return $id_residente > 0 ? '&residente=' . (int)$id_residente : '';
}

// DataTables no se inicializa en tablas sin filas (ver footer.php), así que
// una tabla vacía quedaría con el encabezado suelto y sin buscador.
// En ese caso mostramos un aviso en lugar de la tabla.
function sin_datos($texto) {
    echo '<div class="card" style="text-align:center;color:var(--text-muted,#888);font-size:14px">'
       . htmlspecialchars($texto) . '</div>';
}
