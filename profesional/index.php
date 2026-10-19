<?php
require_once '../config/session.php';
require_once '../config/db.php';
if (!es_profesional()) acceso_denegado();

$med = $pdo->prepare("SELECT * FROM medicos WHERE id_usuario=? AND estado='activo'");
$med->execute([uid()]);
$med = $med->fetch();
if (!$med) acceso_denegado('Tu perfil no está configurado. Contactá a la administradora.');

$tipo = $med['tipo_profesional'];
$id_medico = $med['id_medico'];

$_SESSION['_tipo_prof'] = $tipo;

$TIPOS_LABELS = [
    'medico'=>'Médico/a','kinesiologo'=>'Kinesiólogo/a','fisioterapeuta'=>'Fisioterapeuta',
    'podologo'=>'Podólogo/a','enfermero'=>'Enfermero/a','nutricionista'=>'Nutricionista',
    'peluquero'=>'Peluquero/a','otro'=>'Otro'
];

$residentes = $pdo->prepare("
    SELECT r.id_residente, r.nombre, r.apellido, r.fecha_nacimiento,
        COUNT(vm.id_visita_medica) atenciones,
        MAX(vm.fecha_hora) ultima_atencion,
        SUM(vm.motivo != 'Asignación inicial por administradora') atenciones_reales
    FROM visitas_medicas vm
    JOIN residentes r ON r.id_residente=vm.id_residente
    WHERE vm.id_medico=? AND r.estado='activo'
    GROUP BY r.id_residente ORDER BY r.apellido, r.nombre");
$residentes->execute([$id_medico]);
$residentes = $residentes->fetchAll();

include '../config/menu.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
    <div>
        <h1 style="margin:0">Mis residentes</h1>
        <p class="small" style="margin:4px 0 0;color:var(--text-muted)">
            <?= h($TIPOS_LABELS[$tipo] ?? $tipo) ?>
            <?php if ($med['especialidad']): ?>, <?= h($med['especialidad']) ?><?php endif; ?>
            <?php if ($med['matricula']): ?> · Mat. <?= h($med['matricula']) ?><?php endif; ?>
        </p>
    </div>
    <a class="btn" href="atencion.php">+ Registrar atención</a>
</div>

<?php if (empty($residentes)): ?>
<div class="card" style="text-align:center;padding:48px;color:var(--text-muted)">
    <div style="font-size:40px;margin-bottom:12px"></div>
    <b>No tenés residentes atendidos todavía.</b><br>
    <br>
    <a class="btn" href="atencion.php">+ Registrar primera atención</a>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
    <?php foreach ($residentes as $r):
        $edad = $r['fecha_nacimiento']
            ? (int)date_diff(new DateTime($r['fecha_nacimiento']), new DateTime())->y : null;
    ?>
    <div class="card" style="margin:0;padding:16px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--color-primary,#2D9B8A);
                        color:#fff;display:flex;align-items:center;justify-content:center;
                        font-size:18px;font-weight:700;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($r['nombre'],0,1).mb_substr($r['apellido'],0,1)) ?>
            </div>
            <div>
                <b style="font-size:15px"><?= h($r['nombre'].' '.$r['apellido']) ?></b><br>
                <span class="small" style="color:var(--text-muted)"><?= $edad ? $edad.' años' : '' ?></span>
            </div>
        </div>
        <div class="small" style="color:var(--text-muted);margin-bottom:12px">
            <?php if ((int)$r['atenciones_reales'] > 0): ?>
                <?= (int)$r['atenciones_reales'] ?> atención<?= $r['atenciones_reales']!=1?'es':'' ?>
                · Última: <?= date('d/m/Y', strtotime($r['ultima_atencion'])) ?>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a class="btn" href="ficha.php?id=<?= $r['id_residente'] ?>">Ver ficha</a>
            <a class="btn gray" href="atencion.php?id=<?= $r['id_residente'] ?>">Registrar atención</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
