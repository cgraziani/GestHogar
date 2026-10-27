<?php
// ============================================================
// MEDICACIÓN
// Medicamentos vigentes por horario + historial de los últimos 7 días.
// Recetas y tareas viven en recetas.php y tareas.php.
// ============================================================
$seccion = 'medicacion';
require_once 'comun.php';
?>

<h1>Medicación</h1>

<?php mostrar_mensaje_sesion(); ?>

<?php
$boton_cargar = $id_residente > 0
    ? '<button class="btn" onclick="document.getElementById(\'modal-medicacion\').style.display=\'flex\'">Cargar medicación</button>'
    : '<button class="btn" disabled style="opacity:.4;cursor:not-allowed">Cargar medicación</button>';
card_seleccionar_residente($res, $id_residente, $res_sel, $boton_cargar);
?>

<?php if ($id_residente > 0 && !empty($rows) && $res_sel): ?>
<div id="modal-medicacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;
            justify-content:center;overflow-y:auto;padding:20px;box-sizing:border-box">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:28px;width:100%;max-width:680px;position:relative;margin:auto">
        <button onclick="document.getElementById('modal-medicacion').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">✕</button>
        <h2 style="margin-top:0">
            Cargar medicación — <span style="color:#2D9B8A"><?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?></span>
        </h2>
        <p class="small">Registrá el estado de cada medicamento del turno actual. El motivo es <b>obligatorio</b> si no fue administrado.</p>
        <?php foreach ($rows as $r): ?>
        <div style="border:1px solid var(--border-color,#eee);border-radius:8px;padding:16px;margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                <div>
                    <b><?= h($r['med']) ?></b> <span class="small"><?= h($r['presentacion']) ?></span><br>
                    <span class="small">Dosis: <?= h($r['dosis']) ?> · Horario: <b><?= $r['horario'] ?></b><?= $r['diagnostico'] ? ' · ' . htmlspecialchars($r['diagnostico']) : '' ?></span>
                </div>
                <div class="estado-cuadro <?= estado_class($r['ultimo_estado']) ?>"><?= estado_label($r['ultimo_estado']) ?></div>
            </div>
            <form method="post" class="accion-unica">
                <input type="hidden" name="id_plan"        value="<?= $r['id_plan'] ?>">
                <input type="hidden" name="id_residente"   value="<?= $r['id_residente'] ?>">
                <input type="hidden" name="id_medicamento" value="<?= $r['id_medicamento'] ?>">
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <select name="accion_medicacion" class="selector-accion" required style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid var(--border-color,#ddd);border-radius:6px;font-size:13px">
                        <option value="">Elegir estado...</option>
                        <option value="administrado">🟢 Administrado</option>
                        <option value="no_pudo">🟡 No pude administrar</option>
                        <option value="no_dado">🔴 No administrado</option>
                    </select>
                    <button type="submit" class="btn">Guardar</button>
                </div>
                <textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio para 🟡 y 🔴"
                          style="display:none;width:100%;margin-top:8px;padding:8px;border:1.5px solid var(--border-color,#ddd);border-radius:6px;font-size:13px;box-sizing:border-box"></textarea>
            </form>
        </div>
        <?php endforeach; ?>
        <button onclick="document.getElementById('modal-medicacion').style.display='none'" class="btn gray">Cerrar</button>
    </div>
</div>
<script>
document.getElementById('modal-medicacion').addEventListener('click', function(e){
    if(e.target === this) this.style.display = 'none';
});
</script>
<?php endif; ?>

<?php bloquear_si_falta_residente($debe_elegir, 'Medicación cerrada',
    'Elegí un residente en el selector de arriba para ver su medicación.'); ?>

<?php barra_acciones_admin([['modal-medicamento', '+ Crear medicamento']]); ?>

<?php if (es_admin()): ?>
<!-- MODAL: Crear medicamento -->
<div id="modal-medicamento" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-medicamento')" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">✕</button>
        <h3 style="margin-top:0">Crear medicamento</h3>
        <p class="small">El medicamento queda disponible para armar recetas desde la sección Recetas.</p>
        <form method="post">
            <input type="hidden" name="nuevo_medicamento" value="1">
            <label>Nombre del medicamento</label>
            <input name="nombre" placeholder="Ej: Enalapril" required>
            <label>Presentación</label>
            <input name="presentacion" placeholder="Ej: comprimido 10mg">
            <label>Descripción</label>
            <textarea name="descripcion" placeholder="Descripción opcional"></textarea>
            <br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-medicamento')">Cancelar</button>
        </form>
    </div>
</div>
<?php js_modales_admin(['modal-medicamento']); ?>
<?php endif; ?>

<?php aviso_solo_lectura(); ?>

<h2>Medicamentos vigentes por horario</h2>
<?php if (empty($rows)): ?>
    <?php sin_datos('No hay medicamentos vigentes para mostrar. Se cargan creando una receta desde la sección Recetas.'); ?>
<?php else: ?>
<table class="datatable">
    <thead><tr><th>Residente</th><th>Tratamiento</th><th>Medicamento</th><th>Dosis</th><th>Horario</th><th>Vence</th><th>Último registro</th><th>Registrar estado</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r['rn']) . ' ' . h($r['ra']) ?></td>
            <td><?= htmlspecialchars($r['diagnostico'] ?: '—') ?></td>
            <td><?= h($r['med']) ?> <span class="small"><?= h($r['presentacion']) ?></span></td>
            <td><?= h($r['dosis']) ?></td>
            <td><b><?= $r['horario'] ?></b></td>
            <td><?= $r['r_fin'] ?: 'Permanente' ?></td>
            <td>
                <div class="estado-cuadro <?= estado_class($r['ultimo_estado']) ?>"><?= estado_label($r['ultimo_estado']) ?></div>
                <span class="small"><?= $r['ultimo_horario'] ?><?= $r['ultimo_usuario'] ? ' · '.$r['ultimo_usuario'] : '' ?><?php if($r['ultimo_motivo']):?><br><?= htmlspecialchars($r['ultimo_motivo']) ?><?php endif;?></span>
            </td>
            <td>
                <form method="post" class="accion-unica">
                    <input type="hidden" name="id_plan"        value="<?= $r['id_plan'] ?>">
                    <input type="hidden" name="id_residente"   value="<?= $r['id_residente'] ?>">
                    <input type="hidden" name="id_medicamento" value="<?= $r['id_medicamento'] ?>">
                    <select name="accion_medicacion" class="selector-accion" required>
                        <option value="">Elegir estado</option>
                        <option value="administrado">🟢 Administrado</option>
                        <option value="no_pudo">🟡 No pude administrar</option>
                        <option value="no_dado">🔴 No administrado</option>
                    </select>
                    <textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio" style="display:none"></textarea>
                    <button class="btn">Guardar</button>
                </form>
                <?php if(es_admin()):?><a class="btn red" href="?eliminar=<?= $r['id_plan'] ?><?= sufijo_residente($id_residente) ?>" onclick="return confirmarLink(this,'¿Dar de baja este plan de medicación?','Dar de baja','red')">Dar de baja</a><?php endif;?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 style="margin-top:32px">Historial de medicación — últimos 7 días</h2>
<?php if (empty($hist)): ?>
    <?php sin_datos('No hay registros de medicación en los últimos 7 días.'); ?>
<?php else: ?>
<table class="datatable">
    <thead><tr><th>Fecha y hora</th><th>Residente</th><th>Medicamento</th><th>Dosis</th><th>Tratamiento</th><th>Estado</th><th>Registrado por</th><th>Observaciones</th></tr></thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
        <tr>
            <td><?= $h['fecha_hora'] ?></td>
            <td><?= h($h['rn']) . ' ' . h($h['ra']) ?></td>
            <td><?= h($h['med']) . ' ' . h($h['presentacion']) ?></td>
            <td><?= h($h['dosis']) ?></td>
            <td><?= htmlspecialchars($h['diagnostico'] ?: '—') ?></td>
            <td><div class="estado-cuadro <?= estado_class($h['estado_adm']) ?>"><?= estado_label($h['estado_adm']) ?></div></td>
            <td><?= h($h['cuidadora']) ?: '—' ?></td>
            <td><?= htmlspecialchars($h['observaciones']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php js_selector_accion(); ?>

<?php include '../config/footer.php'; ?>
