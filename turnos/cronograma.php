<?php
require_once '../config/session.php';
require_login();
require_once '../config/db.php';

include '../config/menu.php';

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

$primerDia = new DateTime($mes . '-01');
$ultimoDia = (clone $primerDia)->modify('last day of this month');
$mesAnt    = (clone $primerDia)->modify('-1 month')->format('Y-m');
$mesSig    = (clone $primerDia)->modify('+1 month')->format('Y-m');

$filtro = es_admin() ? '' : ' AND tp.id_cuidadora_usuario = ' . intval(uid());

$st = $pdo->prepare("SELECT tp.*, u.nombre, u.apellido, u.rol, u.color,
                            ut.nombre titular_nombre, ut.apellido titular_apellido
                     FROM turnos_programados tp
                     JOIN usuarios u ON u.id_usuario = tp.id_cuidadora_usuario
                     LEFT JOIN usuarios ut ON ut.id_usuario = tp.id_titular
                     WHERE tp.estado = 'activo'
                       AND tp.fecha BETWEEN ? AND ?
                       $filtro
                     ORDER BY tp.fecha, tp.hora_inicio");
$st->execute([$primerDia->format('Y-m-d'), $ultimoDia->format('Y-m-d')]);

$porDia = [];
foreach ($st->fetchAll() as $t) {
    $porDia[$t['fecha']][] = $t;
}

$resumen = [];
foreach ($porDia as $turnos) {
    foreach ($turnos as $t) {
        $k = $t['id_cuidadora_usuario'];
        if (!isset($resumen[$k])) {
            $resumen[$k] = ['nombre' => trim($t['nombre'] . ' ' . $t['apellido']),
                            'rol' => $t['rol'], 'color' => $t['color'], 'turnos' => 0, 'reemplazos' => 0];
        }
        $resumen[$k]['turnos']++;
        if ($t['id_titular']) $resumen[$k]['reemplazos']++;
    }
}
uasort($resumen, fn($a, $b) => $b['turnos'] <=> $a['turnos']);

$dias_semana = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$hoy = date('Y-m-d');

$offset = ((int)$primerDia->format('N')) - 1;
?>

<style>
.crono-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
.crono-cab {
    text-align:center; font-weight:700; font-size:13px; padding:6px 0;
    color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;
}
.crono-dia {
    min-height:96px; border:1px solid var(--border-color); border-radius:10px;
    padding:6px; background:var(--bg-surface-alt); display:flex; flex-direction:column; gap:4px;
}
.crono-dia.vacio { background:transparent; border:none; }
.crono-dia.hoy { border-color:var(--color-accent); border-width:2px; }
.crono-dia.finde { background:var(--bg-surface-muted); }
.crono-num { font-size:12px; font-weight:700; color:var(--text-muted); }
.crono-dia.hoy .crono-num { color:var(--color-accent); }
.crono-turno {
    font-size:11px; line-height:1.25; padding:3px 6px; border-radius:6px;
    border-left:4px solid var(--c,#888); background:var(--bg-surface);
}
.crono-turno b { display:block; }
.crono-reemplazo { color:var(--text-estado-alerta); font-size:10px; }
@media print { .no-imprimir { display:none } }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:8px">
    <h1 style="margin:0">Cronograma de turnos</h1>
    <div class="no-imprimir" style="display:flex;gap:8px;align-items:center">
        <a class="btn gray" href="?mes=<?= $mesAnt ?>">◀ Mes anterior</a>
        <b style="min-width:150px;text-align:center"><?= ucfirst(strftime_es($primerDia)) ?></b>
        <a class="btn gray" href="?mes=<?= $mesSig ?>">Mes siguiente ▶</a>
    </div>
</div>


<div class="card no-imprimir" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <a class="btn" href="<?= BASE_URL ?>/turnos/calendario.php">Cargar o modificar turnos</a>
    <a class="btn gray" href="javascript:window.print()">Imprimir el mes</a>
</div>

<?php if (!empty($resumen)): ?>
<div class="card">
    <h2 style="margin-top:0">Cobertura del mes</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach ($resumen as $r): ?>
        <div style="display:flex;align-items:center;gap:8px;border:1px solid var(--border-color);border-radius:10px;padding:8px 14px">
            <span style="width:12px;height:12px;border-radius:50%;background:<?= h($r['color'] ?: '#888') ?>"></span>
            <div>
                <b><?= h($r['nombre']) ?></b> <span class="badge"><?= h($r['rol']) ?></span><br>
                <span class="small"><?= $r['turnos'] ?> turno<?= $r['turnos'] > 1 ? 's' : '' ?>
                <?= $r['reemplazos'] ? ' · ' . $r['reemplazos'] . ' como reemplazo' : '' ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="crono-grid">
        <?php foreach ($dias_semana as $d): ?>
            <div class="crono-cab"><?= $d ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="crono-dia vacio"></div>
        <?php endfor; ?>

        <?php
        $cursor = clone $primerDia;
        while ($cursor <= $ultimoDia):
            $iso     = $cursor->format('Y-m-d');
            $numDia  = (int)$cursor->format('N');
            $clases  = 'crono-dia';
            if ($iso === $hoy)  $clases .= ' hoy';
            if ($numDia >= 6)   $clases .= ' finde';
        ?>
            <div class="<?= $clases ?>">
                <span class="crono-num"><?= $cursor->format('j') ?></span>
                <?php foreach ($porDia[$iso] ?? [] as $t): ?>
                    <div class="crono-turno" style="--c:<?= h($t['color'] ?: '#888') ?>"
                         title="<?= h($t['nombre'] . ' ' . $t['apellido'] . ' · ' . substr($t['hora_inicio'],0,5) . ' a ' . substr($t['hora_fin'],0,5)) ?>">
                        <b><?= h($t['nombre']) ?></b>
                        <?= h(substr($t['hora_inicio'], 0, 5)) ?>–<?= h(substr($t['hora_fin'], 0, 5)) ?>
                        <?php if ($t['id_titular']): ?>
                            <span class="crono-reemplazo">cubre a <?= h($t['titular_nombre']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
            $cursor->modify('+1 day');
        endwhile;
        ?>
    </div>
</div>

<?php
function strftime_es(DateTime $d) {
    $meses = [1=>'enero','febrero','marzo','abril','mayo','junio','julio',
              'agosto','septiembre','octubre','noviembre','diciembre'];
    return $meses[(int)$d->format('n')] . ' ' . $d->format('Y');
}
?>

<?php include '../config/footer.php'; ?>
