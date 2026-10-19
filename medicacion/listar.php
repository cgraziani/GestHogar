<?php
$seccion = 'medicacion';
require_once 'comun.php';
$puede_acc_med = es_admin() || (es_profesional() && prof_puede_medicacion($pdo));
?>

<h1>Recetas y medicación</h1>

<?php mostrar_mensaje_sesion(); ?>

<?php
require_once __DIR__.'/../config/accesos_prof.php';
$muestra_tabs_med = es_profesional() && prof_puede_recetas($pdo) && prof_puede_medicacion($pdo);
if ($muestra_tabs_med): ?>
<div style="display:flex;gap:8px;margin-bottom:16px">
    <a class="tab-btn" href="recetas.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">Recetas</a>
    <a class="tab-btn active" href="listar.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">Medicación vigente</a>
</div>
<style>
.tab-btn{padding:7px 16px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;font-size:13px;font-weight:600;text-decoration:none}
.tab-btn.active{background:var(--color-primary,#2D9B8A);color:#fff}
</style>
<?php endif; ?>

<?php
$boton_cargar = '';
if (es_admin() && $id_residente > 0) {
    $boton_cargar = '<button class="btn" onclick="document.getElementById(\'modal-medicacion\').style.display=\'flex\'">Registrar medicación</button>';
} elseif (es_admin()) {
    $boton_cargar = '<button class="btn" disabled style="opacity:.4;cursor:not-allowed">Registrar medicación</button>';
}
card_seleccionar_residente($res, $id_residente, $res_sel, $boton_cargar);
?>

<?php if (es_admin() && $id_residente > 0 && !empty($rows) && $res_sel): ?>
<div id="modal-medicacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;
            justify-content:center;overflow-y:auto;padding:20px;box-sizing:border-box">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:28px;width:100%;max-width:680px;position:relative;margin:auto">
        <button onclick="document.getElementById('modal-medicacion').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
        <h2 style="margin-top:0">
            Registrar medicación: <span style="color:#2D9B8A"><?= htmlspecialchars($res_sel['nombre'] . ' ' . $res_sel['apellido']) ?></span>
        </h2>
        <?php foreach ($rows as $r): ?>
        <div style="border:1px solid var(--border-color,#eee);border-radius:8px;padding:16px;margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
                <div>
                    <b><?= h($r['med']) ?></b> <span class="small"><?= h($r['presentacion']) ?></span><br>
                    <span class="small">Dosis: <?= h($r['dosis']) ?> · Horario: <b><?= $r['horario'] ?></b><?= $r['diagnostico'] ? ' · ' . htmlspecialchars($r['diagnostico']) : '' ?></span>
                </div>
                <?= pill_estado_med($r['ultimo_estado']) ?>
            </div>
            <form method="post" class="accion-unica">
                <input type="hidden" name="id_plan"        value="<?= $r['id_plan'] ?>">
                <input type="hidden" name="id_residente"   value="<?= $r['id_residente'] ?>">
                <input type="hidden" name="id_medicamento" value="<?= $r['id_medicamento'] ?>">
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                    <select name="accion_medicacion" class="selector-accion" required style="flex:1;min-width:200px;padding:8px 12px;border:1.5px solid var(--border-color,#ddd);border-radius:6px;font-size:13px">
                        <option value="">Elegir estado...</option>
                        <option value="administrado">Administrado</option>
                        <option value="no_pudo">No pude administrar</option>
                        <option value="no_dado">No administrado</option>
                    </select>
                    <button type="submit" class="btn">Guardar</button>
                </div>
                <textarea name="motivo" class="motivo-js" placeholder="Motivo obligatorio para  y "
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

<?php barra_acciones_admin([['modal-medicamento', '+ Registrar medicamento']]); ?>

<?php if ($puede_acc_med): ?>
<div id="modal-medicamento" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-medicamento')" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
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
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-medicamento')">Cancelar</button>
        </form>
    </div>
</div>
<?php js_modales_admin(['modal-medicamento']); ?>
<?php endif; ?>

<?php if (!es_admin() && !es_profesional()): ?>
<div class="card">
    <b>Modo consulta:</b> podés ver el plan de medicación de cada residente, pero el registro de
    administración se hace desde <a href="<?= BASE_URL ?>/medicacion/agenda.php">Agenda del turno</a>.
</div>
<?php endif; ?>

<h2>Medicamentos vigentes por horario</h2>
<?php if (empty($rows)): ?>
    <?php sin_datos('No hay medicamentos vigentes para mostrar. Se cargan creando una receta desde la sección Recetas.'); ?>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Residente</th><th>Tratamiento</th><th>Medicamento</th><th>Dosis</th><th>Horario</th><th>Vence</th>
            <?php if ($puede_acc_med): ?><th>Acciones</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r['rn']) . ' ' . h($r['ra']) ?></td>
            <td><?= htmlspecialchars($r['diagnostico'] ?: '') ?></td>
            <td><b><?= h($r['med']) ?></b> <span class="small"><?= h($r['presentacion']) ?></span></td>
            <td><?= h($r['dosis']) ?></td>
            <td><b><?= $r['horario'] ?></b></td>
            <td><?= $r['r_fin'] ?: 'Permanente' ?></td>
            <?php if ($puede_acc_med): ?>
            <td>
                <a class="btn red" href="?eliminar=<?= $r['id_plan'] ?><?= sufijo_residente($id_residente) ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja este plan de medicación? Ya no aparecerá en la agenda del turno.','Dar de baja','red')">
                   Dar de baja
                </a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 style="margin-top:32px">Historial de medicación: últimos 7 días</h2>
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
            <td><?= htmlspecialchars($h['diagnostico'] ?: '') ?></td>
            <td><?= pill_estado_med($h['estado_adm']) ?></td>
            <td><?= h($h['cuidadora']) ?: '' ?></td>
            <td><?= htmlspecialchars($h['observaciones']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php js_selector_accion(); ?>

<?php include '../config/footer.php'; ?>




