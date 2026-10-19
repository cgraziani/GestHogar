<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/db.php';
require_once '../config/accesos_prof.php';
if (!(es_admin() || es_cuidadora() || es_asistente() || (es_profesional() && prof_puede_controles($pdo))))
    acceso_denegado('No tenés permisos para ver esta sección.');

$rangos = rangos_control($pdo);

$labels = [
    'tension_sistolica'   => 'Tensión sistólica',
    'tension_diastolica'  => 'Tensión diastólica',
    'temperatura'         => 'Temperatura',
    'peso'                => 'Peso',
    'glucemia'            => 'Glucemia',
    'saturacion_oxigeno'  => 'Saturación de oxígeno',
    'frecuencia_cardiaca' => 'Frecuencia cardíaca',
];

include '../config/menu.php';

$residentes = es_profesional()
    ? query_residentes($pdo)
    : $pdo->query("SELECT id_residente, nombre, apellido FROM residentes WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();
$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$whereRes     = $id_residente ? ' AND cc.id_residente=' . $id_residente : '';

$controles = $pdo->query("
    SELECT cc.*, r.nombre res_nombre, r.apellido res_apellido,
           CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) registrado_por
    FROM controles_clinicos cc
    JOIN residentes r ON r.id_residente = cc.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = cc.id_usuario
    WHERE cc.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY) $whereRes
    ORDER BY cc.fecha_hora DESC
    LIMIT 200
")->fetchAll();

$fuera_de_rango = array_filter($controles, function($c) use ($rangos) {
    $r = $rangos[$c['tipo_control']] ?? null;
    return $r && ((float)$c['valor'] < $r['min'] || (float)$c['valor'] > $r['max']);
});
?>

<style>
.fuera-rango { background:#fff3cd; border-left:3px solid #e67e22 }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <h1 style="margin:0">Controles clínicos</h1>
    <a class="btn gray" href="<?= BASE_URL ?>/medicacion/agenda.php">← Agenda del turno</a>
</div>

<div style="display:flex;gap:8px;margin-bottom:20px">
    <?php if (es_admin()): ?>
    <a class="tab-btn" href="plan.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">
Plan de controles
    </a>
    <?php endif; ?>
    <a class="tab-btn active" href="listar.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">
Historial de controles
    </a>
</div>
<style>
.tab-btn{padding:8px 18px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
.tab-btn.active{background:var(--color-primary,#2D9B8A);color:#fff}
</style>


<?php if (!empty($fuera_de_rango)): ?>
<div style="background:#fff3cd;border-left:4px solid #e67e22;padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:13px">
Hay <b><?= count($fuera_de_rango) ?></b> valor<?= count($fuera_de_rango) > 1 ? 'es' : '' ?>
    fuera de rango en los últimos 30 días. Verificá con el médico tratante.
</div>
<?php endif; ?>

<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <label style="font-size:13px;font-weight:600">Filtrar por residente:</label>
    <select class="buscador"
            onchange="window.location='listar.php?residente='+this.value"
            style="min-width:240px;padding:8px 12px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
        <option value="0" <?= $id_residente == 0 ? 'selected' : '' ?>>Todos los residentes</option>
        <?php foreach ($residentes as $r): ?>
            <option value="<?= $r['id_residente'] ?>"
                <?= $id_residente == $r['id_residente'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($id_residente): ?>
        <a href="listar.php" class="btn gray">Ver todos</a>
        <a href="<?= BASE_URL ?>/controles/plan.php?residente=<?= $id_residente ?>" class="btn gray">
            Ver plan de controles
        </a>
    <?php endif; ?>
</div>

<?php if (empty($controles)): ?>
    <div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <b>No hay controles registrados en los últimos 30 días<?= $id_residente ? ' para este residente' : '' ?></b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Residente</th>
            <th>Tipo de control</th>
            <th style="text-align:center">Valor</th>
            <th style="text-align:center">Rango esperado</th>
            <th>Observaciones</th>
            <th>Registrado por</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($controles as $c):
        $tipo  = $c['tipo_control'];
        $valor = (float)$c['valor'];
        $rango = $rangos[$tipo] ?? null;
        $fuera = $rango && ($valor < $rango['min'] || $valor > $rango['max']);
    ?>
        <tr class="<?= $fuera ? 'fuera-rango' : '' ?>">
            <td><?= date('d/m/Y H:i', strtotime($c['fecha_hora'])) ?></td>
            <td><?= htmlspecialchars($c['res_nombre'] . ' ' . $c['res_apellido']) ?></td>
            <td><?= htmlspecialchars($labels[$tipo] ?? $tipo) ?></td>
            <td style="text-align:center">
                <b><?= number_format($valor, 2, ',', '.') ?>
                   <?= htmlspecialchars($c['unidad']) ?></b>
                <?php if ($fuera): ?>
                    <span style="color:#e67e22;font-size:11px;display:block">Fuera de rango</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;font-size:12px;color:#888">
                <?php if ($rango): ?>
                    <?= $rango['min'] ?> – <?= $rango['max'] ?> <?= $rango['unidad'] ?>
                <?php else: ?><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($c['observaciones'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['registrado_por'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include '../config/footer.php'; ?>



