<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_login();
if (!(es_admin() || es_cuidadora() || es_asistente()))
    acceso_denegado('Esta sección es para cuidadoras, asistentes y la administradora.');

const SECCIONES_TRASPASO = [
    'nov_residentes' => ['Residentes',       ''],
    'nov_medicacion' => ['Medicación',       ''],
    'nov_cuidados'   => ['Cuidados',         ''],
    'nov_incidentes' => ['Incidentes',       ''],
    'nov_visitas'    => ['Visitas',          ''],
    'nov_otras'      => ['Otras novedades',  ''],
];

function generar_resumen_turno($pdo, $desde) {
    $meds = $pdo->prepare("
        SELECT COUNT(*) total,
               SUM(estado_adm='administrado') administrados,
               SUM(estado_adm='no_pudo') no_pudo,
               SUM(estado_adm='no_dado') no_dado
        FROM administracion_medicacion
        WHERE fecha_hora >= ?
    ");
    $meds->execute([$desde]);
    $m = $meds->fetch();

    $tareas = $pdo->prepare("
        SELECT COUNT(*) total,
               SUM(estado_tarea='realizada') realizadas,
               SUM(estado_tarea='no_realizada') no_realizadas,
               SUM(estado_tarea='no_pudo') no_pudo
        FROM administracion_tareas WHERE fecha_hora >= ?
    ");
    $tareas->execute([$desde]);
    $t = $tareas->fetch();

    $inc = $pdo->prepare("SELECT COUNT(*) FROM incidentes WHERE fecha_hora >= ?");
    $inc->execute([$desde]);
    $inc_total = $inc->fetchColumn();

    $ctrl = $pdo->prepare("SELECT COUNT(*) FROM controles_clinicos WHERE fecha_hora >= ?");
    $ctrl->execute([$desde]);
    $ctrl_total = $ctrl->fetchColumn();

    $vis = $pdo->prepare("SELECT COUNT(*) FROM visitas WHERE hora_ingreso >= ?");
    $vis->execute([$desde]);
    $vis_total = $vis->fetchColumn();

    return [
        'med_total'=>$m['total'], 'med_ok'=>$m['administrados'], 'med_nopudo'=>$m['no_pudo'], 'med_nodado'=>$m['no_dado'],
        'tar_total'=>$t['total'], 'tar_ok'=>$t['realizadas'], 'tar_no'=>$t['no_realizadas'], 'tar_nopudo'=>$t['no_pudo'],
        'incidentes'=>$inc_total, 'controles'=>$ctrl_total, 'visitas'=>$vis_total,
    ];
}

$ultimo_cierre = $pdo->query("SELECT MAX(fecha_cierre) FROM traspaso_turno WHERE estado='cerrado'")->fetchColumn();
$desde_turno   = $ultimo_cierre ?: date('Y-m-d 00:00:00');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_turno']) && !es_admin()) {
    $secciones = [];
    $partes    = [];
    foreach (SECCIONES_TRASPASO as $campo => [$titulo, $ph]) {
        $txt = trim($_POST[$campo] ?? '');
        $secciones[$campo] = $txt ?: null;
        if ($txt !== '') $partes[] = $titulo . ': ' . $txt;
    }
    $novedades = implode(' | ', $partes);

    if (strlen($novedades) < 10) {
        header('Location: listar.php?toast=' . urlencode('Tenés que dejar novedades en al menos una sección') . '&tipo=error');
        exit;
    }

    $pendientes = $pdo->query("
        SELECT mr.id_plan, m.nombre, mr.horario, r.nombre res_nombre, r.apellido res_apellido
        FROM medicacion_residente mr
        JOIN medicamentos m ON m.id_medicamento = mr.id_medicamento
        JOIN residentes r   ON r.id_residente   = mr.id_residente
        WHERE mr.estado='activo' AND r.estado='activo'
          AND mr.horario <= TIME(NOW())
          AND NOT EXISTS (
              SELECT 1 FROM administracion_medicacion am
              WHERE am.id_plan = mr.id_plan AND DATE(am.fecha_hora) = CURDATE()
          )
        ORDER BY mr.horario
    ")->fetchAll();

    if (!empty($pendientes)) {
        $_SESSION['cb08_pendientes'] = $pendientes;
        $_SESSION['cb08_novedades']  = $novedades;
        header('Location: listar.php?cb08=1');
        exit;
    }

    $resumen = generar_resumen_turno($pdo, $desde_turno);

    $resumen_txt = sprintf(
        "Medicación: %d administradas, %d no pudo, %d no dado (de %d planes activos). ".
        "Tareas: %d realizadas, %d no realizadas, %d no pudo (de %d activas). ".
        "Controles clínicos: %d. Incidentes: %d. Visitas: %d.",
        $resumen['med_ok'], $resumen['med_nopudo'], $resumen['med_nodado'], $resumen['med_total'],
        $resumen['tar_ok'], $resumen['tar_no'], $resumen['tar_nopudo'], $resumen['tar_total'],
        $resumen['controles'], $resumen['incidentes'], $resumen['visitas']
    );

    try {
        $pdo->prepare("INSERT INTO traspaso_turno
                (id_usuario_saliente, novedades, nov_residentes, nov_medicacion, nov_cuidados,
                 nov_incidentes, nov_visitas, nov_otras, resumen_automatico, fecha_cierre, estado)
                VALUES (?,?,?,?,?,?,?,?,?,NOW(),'pendiente')")
            ->execute([uid(), $novedades,
                       $secciones['nov_residentes'], $secciones['nov_medicacion'], $secciones['nov_cuidados'],
                       $secciones['nov_incidentes'], $secciones['nov_visitas'], $secciones['nov_otras'],
                       $resumen_txt]);
        header('Location: listar.php?toast=Turno+cerrado+correctamente&tipo=ok');
    } catch (PDOException $e) {
        header('Location: listar.php?toast='.urlencode('Error al cerrar el turno. Intentá de nuevo.').'&tipo=error');
    }
    exit;
}

if (isset($_GET['confirmar'])) {
    $pdo->prepare("UPDATE traspaso_turno SET estado='cerrado', id_usuario_entrante=?, fecha_apertura=NOW()
                    WHERE id_traspaso=? AND estado='pendiente'")
        ->execute([uid(), (int)$_GET['confirmar']]);
    header('Location: listar.php?toast=Recepci%C3%B3n+confirmada&tipo=ok');
    exit;
}

include '../config/menu.php';

$pendientes_confirmar = $pdo->query("
    SELECT t.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) saliente
    FROM traspaso_turno t
    LEFT JOIN usuarios u ON u.id_usuario = t.id_usuario_saliente
    WHERE t.estado = 'pendiente'
    ORDER BY t.fecha_cierre DESC
")->fetchAll();

$historial = $pdo->query("
    SELECT t.*, CONCAT(u1.nombre,' ',COALESCE(u1.apellido,'')) saliente, CONCAT(u2.nombre,' ',COALESCE(u2.apellido,'')) entrante
    FROM traspaso_turno t
    LEFT JOIN usuarios u1 ON u1.id_usuario = t.id_usuario_saliente
    LEFT JOIN usuarios u2 ON u2.id_usuario = t.id_usuario_entrante
    WHERE t.estado = 'cerrado'
    ORDER BY t.fecha_cierre DESC LIMIT 20
")->fetchAll();

$resumen_preview = generar_resumen_turno($pdo, $desde_turno);
?>

<?php if (isset($_GET['cb08']) && !empty($_SESSION['cb08_pendientes'])):
    $pends = $_SESSION['cb08_pendientes'];
?>
<div style="background:#fdecea;border-left:5px solid #c0392b;padding:16px 20px;border-radius:8px;margin-bottom:20px">
    <b style="color:#c0392b;font-size:15px">No podés cerrar el turno: hay <?= count($pends) ?> medicación<?= count($pends)>1?'es':'' ?> sin registrar</b>
    <p style="font-size:13px;color:#555;margin:8px 0">Registrá el estado de todas las medicaciones antes de cerrar el turno.</p>
    <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:8px">
        <thead><tr style="background:#c0392b;color:#fff">
            <th style="padding:6px 10px;text-align:left">Horario</th>
            <th style="padding:6px 10px;text-align:left">Residente</th>
            <th style="padding:6px 10px;text-align:left">Medicamento</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pends as $mp): ?>
            <tr style="border-bottom:1px solid #f5c6cb">
                <td style="padding:6px 10px"><b><?= substr($mp['horario'],0,5) ?></b></td>
                <td style="padding:6px 10px"><?= htmlspecialchars($mp['res_nombre'].' '.$mp['res_apellido']) ?></td>
                <td style="padding:6px 10px"><?= htmlspecialchars($mp['nombre']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:14px;display:flex;gap:10px">
        <a href="<?= BASE_URL ?>/medicacion/agenda.php" class="btn">Ir a registrar medicación</a>
        <a href="listar.php" class="btn gray">Cancelar</a>
    </div>
</div>
<?php unset($_SESSION['cb08_pendientes'], $_SESSION['cb08_novedades']); endif; ?>

<h1>Traspaso de turno</h1>

<?php if (!empty($pendientes_confirmar)): ?>
<div style="background:#fff3cd;border-left:5px solid #e67e22;border-radius:8px;padding:16px 20px;margin-bottom:20px">
    <b style="color:#e67e22;font-size:15px">Traspaso pendiente de confirmación</b>
    <p style="font-size:13px;color:#666;margin:4px 0 14px">La cuidadora saliente cerró el turno. Leé el resumen y confirmá la recepción.</p>
    <?php foreach ($pendientes_confirmar as $p): ?>
    <div style="background:#fff;border-radius:8px;padding:14px 16px;margin-bottom:10px;border:1px solid #f0c36d">
        <b>Cerrado por: <?= htmlspecialchars($p['saliente'] ?? '') ?></b>
        <span style="font-size:12px;color:#888;margin-left:8px"><?= date('d/m/Y H:i', strtotime($p['fecha_cierre'])) ?></span>

        <div style="background:#f8f9fa;border-radius:6px;padding:10px 14px;margin:10px 0;font-size:13px">
            <b>Resumen automático del turno:</b><br>
            <?= htmlspecialchars($p['resumen_automatico'] ?? 'Sin datos') ?>
        </div>

        <div style="font-size:13px;margin-bottom:10px">
            <b>Novedades:</b><br><?= nl2br(htmlspecialchars($p['novedades'])) ?>
        </div>

        <a class="btn green" href="?confirmar=<?= $p['id_traspaso'] ?>"
           onclick="return confirmarLink(this,'¿Confirmás que leíste el resumen y las novedades del turno?','Confirmar recepción','green')">
            Confirmar recepción
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="background:var(--bg-surface);border-radius:8px;padding:16px 20px;margin-bottom:20px;border:1px solid var(--border-color,#ddd)">
    <b style="font-size:14px">Resumen del turno en curso (desde <?= date('d/m H:i', strtotime($desde_turno)) ?>)</b>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:12px">
        <div style="background:#e8f4fd;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#1F4E79"><?= $resumen_preview['med_ok'] ?>/<?= $resumen_preview['med_total'] ?></div>
            <small>Medicación administrada</small>
        </div>
        <div style="background:#e2f0e5;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#375623"><?= $resumen_preview['tar_ok'] ?>/<?= $resumen_preview['tar_total'] ?></div>
            <small>Tareas realizadas</small>
        </div>
        <div style="background:#fff3cd;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#856404"><?= $resumen_preview['controles'] ?></div>
            <small>Controles clínicos</small>
        </div>
        <div style="background:#fdecea;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#c0392b"><?= $resumen_preview['incidentes'] ?></div>
            <small>Incidentes</small>
        </div>
        <div style="background:#e8f4fd;border-radius:8px;padding:10px;text-align:center">
            <div style="font-size:22px;font-weight:700;color:#2980b9"><?= $resumen_preview['visitas'] ?></div>
            <small>Visitas</small>
        </div>
    </div>
</div>

<?php if (!es_admin()): ?>
<div style="background:var(--bg-surface);border-radius:8px;padding:16px 20px;margin-bottom:20px;border:1px solid var(--border-color,#ddd)">
    <h3 style="margin:0 0 10px">Cerrar mi turno</h3>
    <form method="post">
        <input type="hidden" name="cerrar_turno" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
            <?php foreach (SECCIONES_TRASPASO as $campo => [$titulo, $ph]): ?>
                <div class="campo">
                    <label style="font-size:13px;font-weight:600"><?= h($titulo) ?></label>
                    <textarea name="<?= $campo ?>" rows="2"
                              style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:10px">Cerrar turno</button>
    </form>
</div>
<?php else: ?>

<?php endif; ?>

<h2>Historial de traspasos</h2>
<?php if (empty($historial)): ?>
    <div style="text-align:center;padding:30px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <b>No hay traspasos cerrados todavía</b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead><tr>
        <th>Cierre</th><th>Saliente</th><th>Apertura</th><th>Entrante</th><th>Resumen</th><th>Novedades</th>
    </tr></thead>
    <tbody>
    <?php foreach ($historial as $h): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($h['fecha_cierre'])) ?></td>
            <td><?= htmlspecialchars($h['saliente'] ?? '') ?></td>
            <td><?= $h['fecha_apertura'] ? date('d/m/Y H:i', strtotime($h['fecha_apertura'])) : '' ?></td>
            <td><?= htmlspecialchars($h['entrante'] ?? '') ?></td>
            <td style="font-size:12px;max-width:220px"><?= htmlspecialchars($h['resumen_automatico'] ?? '') ?></td>
            <td style="font-size:12px;max-width:340px">
                <?php
                $tiene_secciones = false;
                foreach (SECCIONES_TRASPASO as $campo => [$titulo, $ph]) {
                    if (!empty($h[$campo])) { $tiene_secciones = true; break; }
                }
                ?>
                <?php if ($tiene_secciones): ?>
                    <?php foreach (SECCIONES_TRASPASO as $campo => [$titulo, $ph]): ?>
                        <?php if (!empty($h[$campo])): ?>
                            <div style="margin-bottom:4px">
                                <b><?= h($titulo) ?>:</b> <?= h($h[$campo]) ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?= htmlspecialchars($h['novedades']) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
