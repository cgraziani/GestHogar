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

if (isset($_GET['accion']) && es_admin()) {
    $pdo->prepare('UPDATE residentes SET estado=? WHERE id_residente=?')
        ->execute([$_GET['accion'], $_GET['id']]);
    header('Location: listar.php?toast=Estado+actualizado+correctamente&tipo=ok');
    exit;
}
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare('UPDATE residentes SET eliminado=1 WHERE id_residente=?')
        ->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('residentes',?,'eliminado',?)")
        ->execute([$_GET['eliminar'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Residente+enviado+a+la+papelera&tipo=warn');
    exit;
}


if (isset($_GET['eliminar_baja']) && es_admin()) {
    $id = (int)$_GET['eliminar_baja'];
    if ($id > 0) {
        try {
            $pdo->prepare('DELETE FROM residentes WHERE id_residente=?')->execute([$id]);
        } catch(Exception $e) {
            $pdo->prepare("UPDATE residentes SET eliminado=1, estado='baja' WHERE id_residente=?")->execute([$id]);
        }
        $pdo->prepare("DELETE FROM papelera WHERE tabla='residentes' AND registro_id=?")->execute([$id]);
        header('Location: listar.php?ver_bajas=1&toast=Residente+eliminado+definitivamente&tipo=warn');
        exit;
    }
}

include '../config/menu.php';

if (isset($_GET['ver'])) {

    $st = $pdo->prepare('SELECT r.*,h.numero habitacion,h.sector,h.piso,h.cama,h.estado estado_habitacion
                         FROM residentes r
                         LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                         WHERE r.id_residente=?');
    $st->execute([$_GET['ver']]);
    $r = $st->fetch();

    $med = $pdo->prepare("SELECT mr.*,m.nombre med,m.presentacion,
                                 rec.diagnostico,rec.fecha_inicio r_inicio,rec.fecha_fin r_fin,rec.foto_receta
                          FROM medicacion_residente mr
                          JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento
                          LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
                          WHERE mr.id_residente=? AND mr.eliminado=0
                          ORDER BY rec.fecha_inicio DESC, mr.horario");
    $med->execute([$r['id_residente']]);
    $med = $med->fetchAll();

    $hist = $pdo->prepare("SELECT am.*,m.nombre med,m.presentacion,u.nombre cuidadora,
                                  mr.dosis,mr.horario,rec.diagnostico
                           FROM administracion_medicacion am
                           JOIN medicamentos m ON m.id_medicamento=am.id_medicamento
                           LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
                           LEFT JOIN medicacion_residente mr ON mr.id_plan=am.id_plan
                           LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
                           WHERE am.id_residente=?
                           AND am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY am.fecha_hora DESC");
    $hist->execute([$r['id_residente']]);
    $hist = $hist->fetchAll();
?>

<h1>Ficha del residente</h1>

<div class="card">
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">

        <?php if ($r['foto']): ?>
            <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                 style="width:90px;height:90px;object-fit:cover;border-radius:10px;border:2px solid #ddd">
        <?php endif; ?>

        <div style="flex:1">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                <h2 style="margin:0"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></h2>
                <span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span>
            </div>

            <table class="no-datatable" style="width:100%">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Fecha de nacimiento</th>
                        <th>Habitación</th>
                        <th>Movilidad autónoma</th>
                        <th>Familiar</th>
                        <th>Teléfono familiar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($r['dni']) ?></td>
                        <td><?= $r['fecha_nacimiento'] ?></td>
                        <td>
                            <?php if ($r['habitacion']): ?>
                                Hab. <?= $r['habitacion'] ?> · <?= $r['sector'] ?><br>
                                <span class="small"><?= $r['piso'] ?> · <?= $r['cama'] ?></span>
                            <?php else: ?>
                                <span class="small">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['movilidad_autonoma'] ? 'Sí' : 'No' ?></td>
                        <td><?= htmlspecialchars($r['contacto_familiar']) ?></td>
                        <td><?= htmlspecialchars($r['telefono_familiar']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <h3 style="margin-top:0">Observaciones médicas</h3>
    <p style="line-height:1.7;white-space:pre-wrap">
        <?= $r['obs_medicas'] ? htmlspecialchars($r['obs_medicas']) : '' ?>
    </p>
</div>

<h2>Medicamentos y recetas</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Tratamiento / receta</th>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Horario</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Indicación</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($med as $m): ?>
        <tr>
            <td>
                <?= htmlspecialchars($m['diagnostico'] ?: '-') ?>
                <?php if ($m['foto_receta']): ?>
                    <br><a class="btn blue" target="_blank"
                       href="<?= BASE_URL . '/' . $m['foto_receta'] ?>">Ver foto receta</a>
                <?php endif; ?>
            </td>
            <td><?= $m['med'] ?> <?= $m['presentacion'] ?></td>
            <td><?= $m['dosis'] ?></td>
            <td><b><?= $m['horario'] ?></b></td>
            <td><?= $m['fecha_inicio'] ?></td>
            <td><?= $m['fecha_fin'] ?: 'Permanente' ?></td>
            <td><?= htmlspecialchars($m['indicacion']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Historial de medicación: últimos 7 días</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
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

<br>
<?php if (es_admin()): ?>
    <a class="btn" href="crear.php?id=<?= $r['id_residente'] ?>">Modificar datos</a>
<?php endif; ?>
<a class="btn gray" href="listar.php">Volver al listado</a>

<?php
    include '../config/footer.php';
    exit;
}

$rows = $pdo->query('SELECT r.*,h.numero habitacion,h.sector
                     FROM residentes r
                     LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                     WHERE r.eliminado=0 AND r.estado != \'baja\'
                     ORDER BY r.apellido,r.nombre')->fetchAll();
?>

<h1>Residentes</h1>

<?php if (isset($_GET['ver_bajas']) && es_admin()): ?>
    <?php
    $bajas = $pdo->query("SELECT r.*,h.numero habitacion FROM residentes r
                          LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                          WHERE r.eliminado=0 AND r.estado='baja'
                          ORDER BY r.apellido,r.nombre")->fetchAll();
    ?>
    <h2>Residentes dados de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (!empty($bajas)): ?>
    <table class="datatable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Habitación</th>
                <th>Familiar</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
                <td><?= $b['dni'] ?></td>
                <td><?= $b['habitacion'] ? 'Hab. ' . $b['habitacion'] : '' ?></td>
                <td><?= htmlspecialchars($b['contacto_familiar']) ?></td>
                <td>
                    <a class="btn blue" href="?ver=<?= $b['id_residente'] ?>">Ver ficha</a>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_residente'] ?>"
                       onclick="return confirm('¿Reincorporar a <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?>?')">
                       Reincorporar
                    </a>
                    <a class="btn red"
                       href="?eliminar_baja=<?= $b['id_residente'] ?>"
                       onclick="return confirm('¿Eliminar definitivamente a <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?>? Esta acción no se puede deshacer.')">
                       Eliminar
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include '../config/footer.php'; exit; ?>
<?php endif; ?>

<?php if (es_admin()): ?>
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <a class="btn" href="crear.php">+ Crear residente</a>
        <a class="btn gray" href="?ver_bajas=1">Ver residentes dados de baja</a>
    </div>
<?php else: ?>
    <div class="card">
        <b>Solo lectura:</b> podés consultar la ficha completa de cada residente,
        pero no podés crear ni modificar residentes.
    </div>
<?php endif; ?>

<table class="datatable">
    <thead>
        <tr>
            <th>Foto</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Habitación</th>
            <th>Familiar</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <?php if ($r['foto']): ?>
                    <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                         style="width:40px;height:40px;object-fit:cover;border-radius:6px">
                <?php else: ?>
                    <span style="color:#ccc"></span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></td>
            <td><?= $r['dni'] ?></td>
            <td>
                <?= $r['habitacion']
                    ? 'Hab. ' . $r['habitacion'] . ' · ' . $r['sector']
                    : '<span class="small" style="color:#aaa">Sin asignar</span>' ?>
            </td>
            <td>
                <?= htmlspecialchars($r['contacto_familiar']) ?><br>
                <span class="small"><?= htmlspecialchars($r['telefono_familiar']) ?></span>
            </td>
            <td><span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span></td>
            <td>
                <a class="btn blue" href="?ver=<?= $r['id_residente'] ?>">Ver ficha</a>

                <?php if (es_admin()): ?>
                    <a class="btn" href="crear.php?id=<?= $r['id_residente'] ?>">Modificar</a>

                    <?php if ($r['estado'] === 'activo'): ?>
                        <a class="btn orange"
                           href="?accion=pausado&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Pausar a este residente?')">Pausar</a>
                        <a class="btn red"
                           href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?>?')">Dar de baja</a>

                    <?php elseif ($r['estado'] === 'pausado'): ?>
                        <a class="btn green"
                           href="?accion=activo&id=<?= $r['id_residente'] ?>">Activar</a>
                        <a class="btn red"
                           href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?>?')">Dar de baja</a>

                    <?php elseif ($r['estado'] === 'baja'): ?>
                        <a class="btn green"
                           href="?accion=activo&id=<?= $r['id_residente'] ?>">Reincorporar</a>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
