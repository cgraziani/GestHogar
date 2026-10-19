<?php
$seccion = 'recetas';
require_once 'comun.php';
?>

<h1>Recetas y medicación</h1>

<?php mostrar_mensaje_sesion(); ?>

<?php
require_once __DIR__.'/../config/accesos_prof.php';
$muestra_tabs = es_profesional() && prof_puede_recetas($pdo) && prof_puede_medicacion($pdo);
if ($muestra_tabs): ?>
<div style="display:flex;gap:8px;margin-bottom:16px">
    <a class="tab-btn active" href="recetas.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">Recetas</a>
    <a class="tab-btn" href="listar.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">Medicación vigente</a>
</div>
<style>
.tab-btn{padding:7px 16px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;font-size:13px;font-weight:600;text-decoration:none}
.tab-btn.active{background:var(--color-primary,#2D9B8A);color:#fff}
</style>
<?php endif; ?>

<?php card_seleccionar_residente($res, $id_residente, $res_sel); ?>

<?php bloquear_si_falta_residente($debe_elegir, 'Recetas cerradas',
    'Elegí un residente en el selector de arriba para ver sus recetas y tratamientos.'); ?>

<?php barra_acciones_admin([['modal-receta', '+ Registrar receta / tratamiento']]); ?>

<?php if (es_admin() || (es_profesional() && prof_puede_recetas($pdo))): ?>
<div id="modal-receta" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:24px 28px;width:100%;max-width:640px;position:relative;color:var(--text-main);max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalAdmin('modal-receta')" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin:0 0 16px">Registrar receta / tratamiento</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="nueva_receta" value="1">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Residente</label>
                    <select name="id_residente" class="buscador" style="width:100%;box-sizing:border-box">
                        <?php foreach ($res as $x): ?>
                            <option value="<?= $x['id_residente'] ?>" <?= $id_residente == $x['id_residente'] ? 'selected' : '' ?>><?= htmlspecialchars($x['nombre'] . ' ' . $x['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Medicamento</label>
                    <select name="id_medicamento" class="buscador" style="width:100%;box-sizing:border-box">
                        <?php foreach ($meds as $m): ?>
                            <option value="<?= $m['id_medicamento'] ?>"><?= h($m['nombre']) ?>, <?= h($m['presentacion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Motivo / diagnóstico *</label>
                    <input name="diagnostico" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Dosis *</label>
                    <input name="dosis" required style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Horarios (separados por coma) *</label>
                    <input name="horarios" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Indicación específica</label>
                    <input name="indicacion" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Quién debe administrarlo</label>
                    <select name="id_responsable" class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Quien esté de turno a esa hora</option>
                        <?php foreach ($personal_med as $p): ?>
                            <option value="<?= $p['id_usuario'] ?>"><?= h($p['apellido'] . ', ' . $p['nombre']) ?> (<?= h($p['rol']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha de inicio *</label>
                    <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha de fin <span style="color:#aaa;font-weight:400">(vacío = permanente)</span></label>
                    <input type="date" name="fecha_fin" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Indicaciones generales</label>
                <textarea name="indicaciones_generales" placeholder="Observaciones generales de la receta" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Foto de receta (opcional)</label>
                <input type="file" name="foto_receta" accept="image/*" style="width:100%;box-sizing:border-box">
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="cerrarModalAdmin('modal-receta')">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php js_modales_admin(['modal-receta']); ?>
<?php endif; ?>

<?php aviso_solo_lectura(); ?>

<h2>Recetas / tratamientos vigentes</h2>
<?php if (empty($recetas)): ?>
    <?php sin_datos('No hay recetas ni tratamientos vigentes para mostrar.'); ?>
<?php else: ?>
<table class="datatable">
    <?php $puede_acc_rec = es_admin() || (es_profesional() && prof_puede_recetas($pdo)); ?>
    <thead><tr><th>Residente</th><th>Diagnóstico</th><th>Inicio</th><th>Vencimiento</th><th>Foto</th><th>Estado</th><?php if($puede_acc_rec):?><th>Acciones</th><?php endif;?></tr></thead>
    <tbody>
    <?php foreach ($recetas as $r):
        $vencida = ($r['fecha_fin'] && $r['fecha_fin'] < date('Y-m-d'));
    ?>
        <tr>
            <td><?= h($r['rn']) . ' ' . h($r['ra']) ?></td>
            <td><?= htmlspecialchars($r['diagnostico']) ?><?php if($r['indicaciones_generales']):?><br><span class="small"><?= htmlspecialchars($r['indicaciones_generales']) ?></span><?php endif;?></td>
            <td><?= $r['fecha_inicio'] ?></td>
            <td><?= $r['fecha_fin'] ?: 'Permanente' ?></td>
            <td><?php if($r['foto_receta']):?><a class="btn blue" href="<?= BASE_URL.'/'.$r['foto_receta'] ?>" target="_blank">Ver receta</a><?php else:?><?php endif;?></td>
            <td><span class="pill estado-<?= $vencida ? 'baja' : 'activo' ?>"><?= $vencida ? 'Vencida' : 'Vigente' ?></span></td>
            <?php if($puede_acc_rec):?><td><a class="btn red" href="?eliminar_receta=<?= $r['id_receta'] ?><?= sufijo_residente($id_residente) ?>" onclick="return confirmarLink(this,'¿Dar de baja esta receta y todos sus horarios?','Dar de baja','red')">Dar de baja</a></td><?php endif;?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
