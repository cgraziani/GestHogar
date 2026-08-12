<?php
// ============================================================
// TAREAS DE CUIDADO
// Tareas fijas y puntuales por residente, con registro de estado.
// ============================================================
$seccion = 'tareas';
require_once 'comun.php';
?>

<h1>Tareas de cuidado</h1>

<?php mostrar_mensaje_sesion(); ?>

<?php card_seleccionar_residente($res, $id_residente, $res_sel); ?>

<?php bloquear_si_falta_residente($debe_elegir, 'Tareas cerradas',
    'Elegí un residente en el selector de arriba para ver sus tareas de cuidado.'); ?>

<?php barra_acciones_admin([['modal-tarea', '+ Crear tarea de cuidado']]); ?>

<?php if (es_admin()): ?>
<!-- MODAL: Crear tarea -->
<div id="modal-tarea" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-tarea')" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">✕</button>
        <h3 style="margin-top:0">Crear tarea de cuidado</h3>
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
            <input name="titulo" placeholder="Ej: controlar presión, baño, caminata" required>
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
// Tareas vencidas: horario anterior a ahora, sin estado registrado hoy
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
    <span style="font-size:22px">⚠️</span>
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
    <thead><tr><th>Residente</th><th>Tarea</th><th>Tipo</th><th>Horario</th><th>Detalle</th><th>Último registro</th><th>Registrar estado</th></tr></thead>
    <tbody>
    <?php foreach ($tareas as $t):
        // Detectar si esta tarea específica está vencida y sin registrar
        $hora_tarea  = !empty($t['horario']) ? strtotime(date('Y-m-d') . ' ' . $t['horario']) : null;
        $ya_paso     = $hora_tarea && $hora_tarea < time();
        $sin_estado  = empty($t['ultimo_estado']) ||
                       (!empty($t['ultimo_horario']) && date('Y-m-d', strtotime($t['ultimo_horario'])) < date('Y-m-d'));
        $es_vencida  = $ya_paso && $sin_estado;
    ?>
        <tr style="<?= $es_vencida ? 'background:#fdecea;border-left:4px solid #E05C5C;' : '' ?>">
            <td><?= h($t['rn']) . ' ' . h($t['ra']) ?></td>
            <td>
                <?= htmlspecialchars($t['titulo']) ?>
                <?php if ($es_vencida): ?>
                    <span style="background:#E05C5C;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:6px;font-weight:600">⚠ VENCIDA</span>
                <?php endif; ?>
            </td>
            <td><span class="badge"><?= h($t['tipo']) ?></span></td>
            <td><b style="<?= $es_vencida ? 'color:#E05C5C' : '' ?>"><?= $t['horario'] ?></b></td>
            <td><?= htmlspecialchars($t['descripcion']) ?></td>
            <td>
                <?php if ($es_vencida && empty($t['ultimo_estado'])): ?>
                    <div class="estado-cuadro" style="background:#fdecea;border:1px solid #E05C5C;color:#c0392b;padding:4px 10px;border-radius:6px;font-size:13px">
                        ⚠️ Sin registrar
                    </div>
                <?php else: ?>
                    <div class="estado-cuadro <?= estado_class($t['ultimo_estado']) ?>"><?= estado_label($t['ultimo_estado']) ?></div>
                    <span class="small"><?= $t['ultimo_horario'] ?><?php if($t['ultimo_motivo']):?><br><?= htmlspecialchars($t['ultimo_motivo']) ?><?php endif;?></span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" class="accion-unica">
                    <input type="hidden" name="id_tarea"     value="<?= $t['id_tarea'] ?>">
                    <input type="hidden" name="id_residente" value="<?= $t['id_residente'] ?>">
                    <select name="accion_tarea" class="selector-accion" required>
                        <option value="">Elegir estado</option>
                        <option value="realizada">🟢 Realizada</option>
                        <option value="no_pudo">🟡 No pude realizarla</option>
                        <option value="no_realizada">🔴 No realizada</option>
                    </select>
                    <textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio" style="display:none"></textarea>
                    <button class="btn">Guardar</button>
                </form>
                <?php if(es_admin()):?>
                    <a class="btn red" href="?eliminar_tarea=<?= $t['id_tarea'] ?><?= sufijo_residente($id_residente) ?>"
                       onclick="return confirmarLink(this,'¿Dar de baja esta tarea de cuidado?','Dar de baja','red')">Dar de baja</a>
                <?php endif;?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php js_selector_accion(); ?>

<?php include '../config/footer.php'; ?>
