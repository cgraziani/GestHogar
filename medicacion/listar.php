
<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';
 
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
    $name = 'receta_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $dest = '../uploads/recetas/' . $name;
    if (move_uploaded_file($_FILES['foto_receta']['tmp_name'], $dest)) return 'uploads/recetas/' . $name;
    return null;
}
 
function volver_residente($id) {
    header('Location: listar.php?residente=' . (int)$id);
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
    $_SESSION['msg'] = 'Registro guardado correctamente. Quedó guardado quién lo registró, fecha y hora.';
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
        $pdo->prepare('INSERT INTO medicamentos(nombre,presentacion,descripcion) VALUES(?,?,?)')
            ->execute([$_POST['nombre'], $_POST['presentacion'], $_POST['descripcion']]);
        header('Location: listar.php?toast=Medicamento+creado+correctamente&tipo=ok');
        exit;
    }
 
    if (isset($_POST['nueva_receta'])) {
        $foto      = subir_receta();
        $fecha_fin = trim($_POST['fecha_fin'] ?? '') ?: null;
        $pdo->prepare('INSERT INTO recetas_medicas(id_residente,diagnostico,indicaciones_generales,fecha_inicio,fecha_fin,foto_receta,creado_por) VALUES(?,?,?,?,?,?,?)')
            ->execute([$_POST['id_residente'], $_POST['diagnostico'], $_POST['indicaciones_generales'], $_POST['fecha_inicio'], $fecha_fin, $foto, uid()]);
        $id_receta = $pdo->lastInsertId();
        $horarios = preg_split('/[,;\n]+/', $_POST['horarios']);
        foreach ($horarios as $h) {
            $h = trim($h); if ($h === '') continue;
            $pdo->prepare('INSERT INTO medicacion_residente(id_receta,id_residente,id_medicamento,dosis,horario,indicacion,fecha_inicio,fecha_fin,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$id_receta, $_POST['id_residente'], $_POST['id_medicamento'], $_POST['dosis'], $h, $_POST['indicacion'], $_POST['fecha_inicio'], $fecha_fin, uid()]);
        }
        header('Location: listar.php?toast=Receta+y+horarios+creados&tipo=ok');
        exit;
    }
 
    if (isset($_POST['nueva_tarea'])) {
        $pdo->prepare('INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,created_by) VALUES(?,?,?,?,?)')
            ->execute([$_POST['id_residente'], $_POST['titulo'], $_POST['descripcion'], $_POST['horario'], uid()]);
        header('Location: listar.php?toast=Tarea+creada+correctamente&tipo=ok');
        exit;
    }
}
 
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare('UPDATE medicacion_residente SET eliminado=1 WHERE id_plan=?')->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('medicacion_residente',?,'eliminado',?)")
        ->execute([$_GET['eliminar'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Plan+de+medicaci%C3%B3n+eliminado&tipo=warn'); exit;
}
if (isset($_GET['eliminar_receta']) && es_admin()) {
    $pdo->prepare('UPDATE recetas_medicas SET eliminado=1 WHERE id_receta=?')->execute([$_GET['eliminar_receta']]);
    $pdo->prepare('UPDATE medicacion_residente SET eliminado=1 WHERE id_receta=?')->execute([$_GET['eliminar_receta']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('recetas_medicas',?,'eliminado',?)")
        ->execute([$_GET['eliminar_receta'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Receta+eliminada&tipo=warn'); exit;
}
if (isset($_GET['eliminar_tarea']) && es_admin()) {
    $pdo->prepare('UPDATE tareas_residente SET eliminado=1 WHERE id_tarea=?')->execute([$_GET['eliminar_tarea']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('tareas_residente',?,'eliminado',?)")
        ->execute([$_GET['eliminar_tarea'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Tarea+eliminada&tipo=warn'); exit;
}
 
include '../config/menu.php';
 
$res  = $pdo->query("SELECT * FROM residentes WHERE eliminado=0 AND estado='activo' ORDER BY apellido,nombre")->fetchAll();
$meds = $pdo->query("SELECT * FROM medicamentos WHERE eliminado=0 AND estado='activo' ORDER BY nombre")->fetchAll();
 
$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
 
$debe_elegir = (!es_admin() && $id_residente <= 0);
 
$whereRes  = $id_residente ? ' AND mr.id_residente=' . $id_residente : '';
$whereRec  = $id_residente ? ' AND rec.id_residente=' . $id_residente : '';
$whereHist = $id_residente ? ' AND am.id_residente=' . $id_residente : '';
$whereTar  = $id_residente ? ' AND tr.id_residente=' . $id_residente : '';
 
$rows = []; $recetas = []; $hist = []; $tareas = [];
if (!$debe_elegir) {
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
        WHERE mr.eliminado=0 AND mr.estado='activo'
        AND (mr.fecha_inicio IS NULL OR CURDATE()>=mr.fecha_inicio)
        AND (mr.fecha_fin IS NULL OR CURDATE()<=mr.fecha_fin)
        $whereRes ORDER BY r.apellido,mr.horario")->fetchAll();
 
    $recetas = $pdo->query("SELECT rec.*,r.nombre rn,r.apellido ra,u.nombre creador
        FROM recetas_medicas rec
        JOIN residentes r ON r.id_residente=rec.id_residente
        LEFT JOIN usuarios u ON u.id_usuario=rec.creado_por
        WHERE rec.eliminado=0 $whereRec ORDER BY rec.fecha_inicio DESC")->fetchAll();
 
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
 
    $tareas = $pdo->query("SELECT tr.*,r.nombre rn,r.apellido ra,
        (SELECT at.estado_tarea FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_estado,
        (SELECT at.motivo FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_motivo,
        (SELECT at.fecha_hora FROM administracion_tareas at WHERE at.id_tarea=tr.id_tarea ORDER BY at.fecha_hora DESC LIMIT 1) ultimo_horario
        FROM tareas_residente tr
        JOIN residentes r ON r.id_residente=tr.id_residente
        WHERE tr.eliminado=0 AND tr.estado='activo'
        $whereTar ORDER BY tr.horario,r.apellido")->fetchAll();
}
 
$res_sel = null;
if ($id_residente > 0) {
    $tmp = array_filter($res, fn($x) => $x['id_residente'] == $id_residente);
    $res_sel = reset($tmp) ?: null;
}
?>
 
<h1>Medicación, recetas y tareas</h1>
 
<?php if (!empty($_SESSION['msg'])): ?>
    <div class="alert"><?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); ?></div>
<?php endif; ?>
 
<div class="card">
    <h2>Seleccionar residente</h2>
 
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
 
        <select id="select-residente"
                onchange="window.location='listar.php?residente='+this.value"
                style="min-width:260px;padding:10px 14px;border:1.5px solid #ddd;
                       border-radius:8px;font-size:14px">
            <?php if (es_admin()): ?>
                <option value="0" <?= $id_residente == 0 ? 'selected' : '' ?>>
                    Todos los residentes
                </option>
            <?php else: ?>
                <option value="" <?= $id_residente == 0 ? 'selected' : '' ?> disabled>
                    Elegir residente...
                </option>
            <?php endif; ?>
            <?php foreach ($res as $x): ?>
                <option value="<?= $x['id_residente'] ?>"
                    <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?>
                    <?= $x['id_habitacion'] ? ' (Hab. ' . $x['id_habitacion'] . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
 
        <?php if ($id_residente > 0): ?>
            <button class="btn"
                    onclick="document.getElementById('modal-medicacion').style.display='flex'">
                Cargar medicación
            </button>
            <a class="btn gray"
               href="<?= BASE_URL ?>/residentes/listar.php?ver=<?= $id_residente ?>">
                Ver ficha completa
            </a>
        <?php else: ?>
            <button class="btn" disabled style="opacity:.4;cursor:not-allowed">
                Cargar medicación
            </button>
        <?php endif; ?>
 
    </div>
 
    <?php if ($res_sel): ?>
        <p style="margin-top:12px;color:#2D9B8A;font-weight:bold">
Residente seleccionado: <?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?>
        </p>
    <?php endif; ?>
</div>
 
<?php if ($id_residente > 0 && !empty($rows) && $res_sel): ?>
<div id="modal-medicacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;
            justify-content:center;overflow-y:auto;padding:20px;box-sizing:border-box">
    <div style="background:white;border-radius:12px;padding:28px;width:100%;
                max-width:680px;position:relative;margin:auto">
 
        <button onclick="document.getElementById('modal-medicacion').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;
                       border:none;font-size:22px;cursor:pointer">×</button>
 
        <h2 style="margin-top:0">
            Cargar medicación:
            <span style="color:#2D9B8A">
                <?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?>
            </span>
        </h2>
 
        <?php foreach ($rows as $r): ?>
        <div style="border:1px solid #eee;border-radius:8px;padding:16px;margin-bottom:12px">
 
            <div style="display:flex;justify-content:space-between;
                        align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                <div>
                    <b><?= $r['med'] ?></b>
                    <span class="small"> <?= $r['presentacion'] ?></span><br>
                    <span class="small">
                        Dosis: <?= $r['dosis'] ?> ·
                        Horario: <b><?= $r['horario'] ?></b>
                        <?= $r['diagnostico'] ? ' · ' . htmlspecialchars($r['diagnostico']) : '' ?>
                    </span>
                </div>
                <div class="estado-cuadro <?= estado_class($r['ultimo_estado']) ?>">
                    <?= estado_label($r['ultimo_estado']) ?>
                </div>
            </div>
 
            <form method="post" class="accion-unica">
                <input type="hidden" name="id_plan"        value="<?= $r['id_plan'] ?>">
                <input type="hidden" name="id_residente"   value="<?= $r['id_residente'] ?>">
                <input type="hidden" name="id_medicamento" value="<?= $r['id_medicamento'] ?>">
 
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <select name="accion_medicacion" class="selector-accion" required
                            style="flex:1;min-width:200px;padding:8px 12px;
                                   border:1.5px solid #ddd;border-radius:6px;font-size:13px">
                        <option value="">Elegir estado...</option>
                        <option value="administrado">Administrado</option>
                        <option value="no_pudo">No pude administrar</option>
                        <option value="no_dado">No administrado</option>
                    </select>
                    <button type="submit" class="btn">Guardar</button>
                </div>
 
                <textarea name="motivo" class="motivo-js"
                          placeholder="Motivo obligatorio si no se administró"
                          style="display:none;width:100%;margin-top:8px;padding:8px;
                                 border:1.5px solid #ddd;border-radius:6px;
                                 font-size:13px;box-sizing:border-box"></textarea>
            </form>
        </div>
        <?php endforeach; ?>
 
        <button onclick="document.getElementById('modal-medicacion').style.display='none'"
                class="btn gray" style="margin-top:4px">Cerrar</button>
    </div>
</div>
 
<script>
document.getElementById('modal-medicacion').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
});
</script>
<?php endif; ?>
 
<?php if ($debe_elegir): ?>
    <div class="card bloqueo-med">
        <h2>Medicación cerrada</h2>
    </div>
    <?php include '../config/footer.php'; exit; ?>
<?php endif; ?>
 
<?php if (es_admin()): ?>
<div class="card">
    <h3 style="margin-top:0">Acciones de administración</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" onclick="abrirModalAdmin('modal-medicamento')">+ Crear medicamento</button>
        <button class="btn" onclick="abrirModalAdmin('modal-receta')">+ Crear receta / tratamiento</button>
        <button class="btn" onclick="abrirModalAdmin('modal-tarea')">+ Crear tarea de cuidado</button>
    </div>
</div>
 
<div id="modal-medicamento" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;
                max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-medicamento')"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">×</button>
        <h3 style="margin-top:0">Crear medicamento</h3>
        <form method="post">
            <input type="hidden" name="nuevo_medicamento" value="1">
            <label>Nombre del medicamento</label>
            <input name="nombre" required>
            <label>Presentación</label>
            <input name="presentacion">
            <label>Descripción</label>
            <textarea name="descripcion" placeholder="Descripción opcional"></textarea>
            <br>
            <button type="submit" class="btn">Guardar medicamento</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-medicamento')">Cancelar</button>
        </form>
    </div>
</div>
 
<div id="modal-receta" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;
                max-width:560px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-receta')"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">×</button>
        <h3 style="margin-top:0">Crear receta / tratamiento</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="nueva_receta" value="1">
            <label>Residente</label>
            <select name="id_residente">
                <?php foreach ($res as $x): ?>
                    <option value="<?= $x['id_residente'] ?>"
                        <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Medicamento</label>
            <select name="id_medicamento">
                <?php foreach ($meds as $m): ?>
                    <option value="<?= $m['id_medicamento'] ?>"><?= $m['nombre'] ?>, <?= $m['presentacion'] ?></option>
                <?php endforeach; ?>
            </select>
            <label>Motivo / diagnóstico</label>
            <input name="diagnostico" required>
            <label>Dosis</label>
            <input name="dosis" required>
            <label>Horarios (separados por coma)</label>
            <textarea name="horarios" required></textarea>
            <label>Indicación específica del medicamento</label>
            <textarea name="indicacion"></textarea>
            <label>Indicaciones generales de la receta</label>
            <textarea name="indicaciones_generales" placeholder="Observaciones generales"></textarea>
            <label>Fecha de inicio</label>
            <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
            <label>Fecha de fin (vacío si es permanente)</label>
            <input type="date" name="fecha_fin">
            <label>Foto de receta (opcional)</label>
            <input type="file" name="foto_receta" accept="image/*">
            <br>
            <button type="submit" class="btn">Guardar receta y horarios</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-receta')">Cancelar</button>
        </form>
    </div>
</div>
 
<div id="modal-tarea" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;
                max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-tarea')"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">×</button>
        <h3 style="margin-top:0">Crear tarea de cuidado</h3>
        <form method="post">
            <input type="hidden" name="nueva_tarea" value="1">
            <label>Residente</label>
            <select name="id_residente">
                <?php foreach ($res as $x): ?>
                    <option value="<?= $x['id_residente'] ?>"
                        <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label>Tarea</label>
            <input name="titulo" required>
            <label>Horario</label>
            <input type="time" name="horario">
            <label>Detalle</label>
            <textarea name="descripcion" placeholder="Descripción detallada de la tarea"></textarea>
            <br>
            <button type="submit" class="btn">Guardar tarea</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-tarea')">Cancelar</button>
        </form>
    </div>
</div>
 
<script>
function abrirModalAdmin(id) {
    document.getElementById(id).style.display = 'flex';
}
function cerrarModalAdmin(id) {
    document.getElementById(id).style.display = 'none';
}
['modal-medicamento','modal-receta','modal-tarea'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){
        if(e.target === this) cerrarModalAdmin(id);
    });
});
</script>
 
<?php else: ?>
    <div class="card">
        <b>Modo cuidadora/asistente:</b> solo podés ver lo asignado y confirmar.
        El sistema guarda automáticamente tu nombre, fecha y hora.
    </div>
<?php endif; ?>
 
<div style="display:flex;gap:8px;flex-wrap:wrap;margin:20px 0 0 0">
    <button class="tab-btn active" onclick="mostrarTab('tab-recetas', this)">
        Recetas / tratamientos
    </button>
    <button class="tab-btn" onclick="mostrarTab('tab-medicamentos', this)">
        Medicamentos vigentes
    </button>
    <button class="tab-btn" onclick="mostrarTab('tab-historial', this)">
        Historial últimos 7 días
    </button>
    <button class="tab-btn" onclick="mostrarTab('tab-tareas', this)">
        Tareas de cuidado
    </button>
</div>
 
<style>
.tab-btn {
    padding: 8px 18px;
    border: 2px solid var(--color-primary, #2D9B8A);
    background: transparent;
    color: var(--color-primary, #2D9B8A);
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: background .2s, color .2s;
}
.tab-btn.active {
    background: var(--color-primary, #2D9B8A);
    color: white;
}
.tab-content { display: none; }
.tab-content.active { display: block; }
</style>
 
<div id="tab-recetas" class="tab-content active">
<h2>Recetas / tratamientos</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Residente</th>
            <th>Diagnóstico</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Foto</th>
            <th>Estado</th>
            <?php if (es_admin()): ?><th>Acciones</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($recetas as $r):
        $vencida = ($r['fecha_fin'] && $r['fecha_fin'] < date('Y-m-d'));
    ?>
        <tr>
            <td><?= $r['rn'] . ' ' . $r['ra'] ?></td>
            <td>
                <?= htmlspecialchars($r['diagnostico']) ?>
                <?php if ($r['indicaciones_generales']): ?>
                    <br><span class="small"><?= htmlspecialchars($r['indicaciones_generales']) ?></span>
                <?php endif; ?>
            </td>
            <td><?= $r['fecha_inicio'] ?></td>
            <td><?= $r['fecha_fin'] ?: 'Permanente' ?></td>
            <td>
                <?php if ($r['foto_receta']): ?>
                    <a class="btn blue" href="<?= BASE_URL . '/' . $r['foto_receta'] ?>" target="_blank">Ver receta</a>
                <?php else: ?><?php endif; ?>
            </td>
            <td>
                <span class="pill estado-<?= $vencida ? 'baja' : 'activo' ?>">
                    <?= $vencida ? 'Vencida' : 'Vigente' ?>
                </span>
            </td>
            <?php if (es_admin()): ?>
            <td>
                <a class="btn red" href="?eliminar_receta=<?= $r['id_receta'] ?>"
                   onclick="return confirm('¿Eliminar esta receta y sus horarios?')">Papelera</a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
 
</div>
 
<div id="tab-medicamentos" class="tab-content">
<h2>Medicamentos vigentes por horario</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Residente</th>
            <th>Tratamiento</th>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Horario</th>
            <th>Vence</th>
            <th>Último registro</th>
            <th>Registrar estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= $r['rn'] . ' ' . $r['ra'] ?></td>
            <td><?= htmlspecialchars($r['diagnostico'] ?: '-') ?></td>
            <td><?= $r['med'] ?> <span class="small"><?= $r['presentacion'] ?></span></td>
            <td><?= $r['dosis'] ?></td>
            <td><b><?= $r['horario'] ?></b></td>
            <td><?= $r['fecha_fin'] ?: 'Permanente' ?></td>
            <td>
                <div class="estado-cuadro <?= estado_class($r['ultimo_estado']) ?>">
                    <?= estado_label($r['ultimo_estado']) ?>
                </div>
                <span class="small">
                    <?= $r['ultimo_horario'] ?>
                    <?= $r['ultimo_usuario'] ? ' · ' . $r['ultimo_usuario'] : '' ?>
                    <?php if ($r['ultimo_motivo']): ?>
                        <br><?= htmlspecialchars($r['ultimo_motivo']) ?>
                    <?php endif; ?>
                </span>
            </td>
            <td>
                <form method="post" class="accion-unica">
                    <input type="hidden" name="id_plan"        value="<?= $r['id_plan'] ?>">
                    <input type="hidden" name="id_residente"   value="<?= $r['id_residente'] ?>">
                    <input type="hidden" name="id_medicamento" value="<?= $r['id_medicamento'] ?>">
                    <select name="accion_medicacion" class="selector-accion" required>
                        <option value="">Elegir estado</option>
                        <option value="administrado">Administrado</option>
                        <option value="no_pudo">No pude administrar</option>
                        <option value="no_dado">No administrado</option>
                    </select>
                    <textarea name="motivo" class="motivo-js"
                              placeholder="Motivo obligatorio si no se administró"
                              style="display:none"></textarea>
                    <button>Guardar</button>
                </form>
                <?php if (es_admin()): ?>
                    <a class="btn red" href="?eliminar=<?= $r['id_plan'] ?>"
                       onclick="return confirm('¿Eliminar este plan de medicación?')">Papelera</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
 
<div id="tab-historial" class="tab-content">
<h2>Historial de medicación: últimos 7 días</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Residente</th>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Tratamiento</th>
            <th>Estado</th>
            <th>Registrado por</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
        <tr>
            <td><?= $h['fecha_hora'] ?></td>
            <td><?= $h['rn'] . ' ' . $h['ra'] ?></td>
            <td><?= $h['med'] ?> <?= $h['presentacion'] ?></td>
            <td><?= $h['dosis'] ?></td>
            <td><?= htmlspecialchars($h['diagnostico'] ?: '-') ?></td>
            <td>
                <div class="estado-cuadro <?= estado_class($h['estado_adm']) ?>">
                    <?= estado_label($h['estado_adm']) ?>
                </div>
            </td>
            <td><?= $h['cuidadora'] ?: '-' ?></td>
            <td><?= htmlspecialchars($h['observaciones']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
 
<div id="tab-tareas" class="tab-content">
<h2>Tareas de cuidado</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Residente</th>
            <th>Tarea</th>
            <th>Horario</th>
            <th>Detalle</th>
            <th>Último registro</th>
            <th>Registrar estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= $t['rn'] . ' ' . $t['ra'] ?></td>
            <td><?= htmlspecialchars($t['titulo']) ?></td>
            <td><b><?= $t['horario'] ?></b></td>
            <td><?= htmlspecialchars($t['descripcion']) ?></td>
            <td>
                <div class="estado-cuadro <?= estado_class($t['ultimo_estado']) ?>">
                    <?= estado_label($t['ultimo_estado']) ?>
                </div>
                <span class="small">
                    <?= $t['ultimo_horario'] ?>
                    <?php if ($t['ultimo_motivo']): ?>
                        <br><?= htmlspecialchars($t['ultimo_motivo']) ?>
                    <?php endif; ?>
                </span>
            </td>
            <td>
                <form method="post" class="accion-unica">
                    <input type="hidden" name="id_tarea"     value="<?= $t['id_tarea'] ?>">
                    <input type="hidden" name="id_residente" value="<?= $t['id_residente'] ?>">
                    <select name="accion_tarea" class="selector-accion" required>
                        <option value="">Elegir estado</option>
                        <option value="realizada">Realizada</option>
                        <option value="no_pudo">No pude realizarla</option>
                        <option value="no_realizada">No realizada</option>
                    </select>
                    <textarea name="motivo" class="motivo-js"
                              placeholder="Motivo obligatorio si no se realizó"
                              style="display:none"></textarea>
                    <button>Guardar</button>
                </form>
                <?php if (es_admin()): ?>
                    <a class="btn red" href="?eliminar_tarea=<?= $t['id_tarea'] ?>"
                       onclick="return confirm('¿Eliminar esta tarea?')">Papelera</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
 
</div>
 
<script>
document.querySelectorAll('.selector-accion').forEach(function (sel) {
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
 
function mostrarTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(function(el){
        el.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(function(el){
        el.classList.remove('active');
    });
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
 
    setTimeout(function(){
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    }, 10);
}
</script>
 
<?php include '../config/footer.php'; ?>