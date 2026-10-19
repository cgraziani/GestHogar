<?php
$seccion = 'tareas';
require_once 'comun.php';
?>

<h1>Tareas de cuidado</h1>

<?php mostrar_mensaje_sesion(); ?>

<?php card_seleccionar_residente($res, $id_residente, $res_sel); ?>

<?php bloquear_si_falta_residente($debe_elegir, 'Tareas cerradas',
    'Elegí un residente en el selector de arriba para ver sus tareas de cuidado.'); ?>

<?php barra_acciones_admin([['modal-tarea', '+ Registrar tarea de cuidado']]); ?>

<?php $puede_acc_tar = es_admin() || (es_profesional() && prof_puede_tareas($pdo)); ?>
<?php if ($puede_acc_tar): ?>
<div id="modal-tarea" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-tarea')" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin-top:0">Registrar tarea de cuidado</h3>
        <form method="post">
            <input type="hidden" name="nueva_tarea" value="1">
            <label>Residente</label>
            <select name="id_residente" class="buscador">
                <?php foreach ($res as $x): ?>
                    <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Tipo de tarea</label>
            <select name="tipo_tarea">
                <option value="fija">Fija (se repite todos los días)</option>
                <option value="puntual">Puntual (solo para un turno)</option>
            </select>
            <label>Tarea</label>
            <input name="titulo" required>
            <label>Horario</label>
            <input type="time" name="horario">
            <label>Detalle</label>
            <textarea name="descripcion" placeholder="Descripción detallada de la tarea"></textarea>
            <br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-tarea')">Cancelar</button>
        </form>
    </div>
</div>
<?php js_modales_admin(['modal-tarea']); ?>
<?php endif; ?>

<?php aviso_solo_lectura(); ?>

<?php
$tareas_vencidas = array_filter($tareas, function($t) {
    if (empty($t['horario'])) return false;
    $hora_tarea = strtotime(date('Y-m-d') . ' ' . $t['horario']);
    $ya_paso    = $hora_tarea < time();
    $sin_estado = empty($t['ultimo_estado']) ||
                  (!empty($t['ultimo_horario']) && date('Y-m-d', strtotime($t['ultimo_horario'])) < date('Y-m-d'));
    return $ya_paso && $sin_estado;
});
?>

<?php if (!empty($tareas_vencidas)): ?>
<div style="background:#fdecea;border-left:5px solid #E05C5C;padding:14px 18px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
    <div>
        <b style="color:#c0392b">Hay <?= count($tareas_vencidas) ?> tarea<?= count($tareas_vencidas) > 1 ? 's' : '' ?> vencida<?= count($tareas_vencidas) > 1 ? 's' : '' ?> sin registrar</b><br>
        <span style="font-size:13px;color:#555">El horario ya pasó y no se registró el estado. Revisá la<?= count($tareas_vencidas) > 1 ? 's filas marcadas' : ' fila marcada' ?> en rojo.</span>
    </div>
</div>
<?php endif; ?>

<h2>Tareas de cuidado</h2>
<?php if (empty($tareas)): ?>
    <?php sin_datos('No hay tareas de cuidado cargadas para mostrar.'); ?>
<?php else: ?>
<table class="datatable">
    <thead><tr>
        <th>Residente</th><th>Tarea</th><th>Tipo</th><th>Horario</th><th>Detalle</th>
        <?php if ($puede_acc_tar): ?><th>Acciones</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($tareas as $t): ?>
        <tr>
            <td><?= h($t['rn']) . ' ' . h($t['ra']) ?></td>
            <td><b><?= htmlspecialchars($t['titulo']) ?></b></td>
            <td><span class="badge"><?= h($t['tipo']) ?></span></td>
            <td><b><?= $t['horario'] ?: '' ?></b></td>
            <td><span class="small"><?= htmlspecialchars($t['descripcion'] ?: '') ?></span></td>
            <?php if ($puede_acc_tar): ?>
            <td>
                <a class="btn red"
                   href="?eliminar_tarea=<?= $t['id_tarea'] ?><?= sufijo_residente($id_residente) ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja esta tarea? Ya no aparecerá en la agenda del turno.','Dar de baja','red')">
                   Dar de baja
                </a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php js_selector_accion(); ?>

<?php include '../config/footer.php'; ?>
