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
    $id_res  = (int)$_GET['id'];
    $accion  = $_GET['accion'];

    $actual = $pdo->prepare('SELECT estado, id_habitacion, id_cama FROM residentes WHERE id_residente=?');
    $actual->execute([$id_res]);
    $res_actual = $actual->fetch();
    $estado_anterior = $res_actual['estado'];

    if ($accion === 'pausado') {
        $motivo = trim($_GET['motivo'] ?? '');
        if (empty($motivo)) {
            header('Location: listar.php?toast=El+motivo+es+obligatorio+para+suspender+el+servicio&tipo=error');
            exit;
        }
    }

    if ($accion === 'baja' && !isset($_GET['confirmar_baja'])) {
        $med_pendiente = $pdo->prepare("
            SELECT COUNT(*) FROM medicacion_residente mr
            WHERE mr.id_residente = ?
              AND mr.estado = 'activo'
              AND mr.horario <= TIME(NOW())
              AND NOT EXISTS (
                SELECT 1 FROM administracion_medicacion am
                WHERE am.id_plan = mr.id_plan
                  AND DATE(am.fecha_hora) = CURDATE()
              )
        ");
        $med_pendiente->execute([$id_res]);
        if ($med_pendiente->fetchColumn() > 0) {
            header('Location: listar.php?cb03_warn=' . $id_res);
            exit;
        }
    }

    $motivo    = trim($_GET['motivo'] ?? '');
    $fecha_fin = trim($_GET['fecha_fin'] ?? '') ?: null;

    $pdo->prepare('UPDATE residentes SET estado=?, updated_by=? WHERE id_residente=?')
        ->execute([$accion, uid(), $id_res]);

    $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por,motivo,fecha_fin) VALUES(?,?,?,?,?,?,?)')
        ->execute(['residente', $id_res, $estado_anterior, $accion, uid(), $motivo ?: null, $fecha_fin]);

    if ($accion === 'baja') {
        if ($res_actual['id_habitacion']) {
            $pdo->prepare("UPDATE habitaciones SET estado='disponible', updated_by=? WHERE id_habitacion=?")
                ->execute([uid(), $res_actual['id_habitacion']]);
        }
        if ($res_actual['id_cama']) {
            $pdo->prepare("UPDATE camas SET estado='disponible', updated_by=? WHERE id_cama=?")
                ->execute([uid(), $res_actual['id_cama']]);
        }
        $pdo->prepare('UPDATE residentes SET fecha_egreso=CURDATE() WHERE id_residente=?')
            ->execute([$id_res]);
    }

    if ($accion === 'pausado') {
        $pdo->prepare("UPDATE medicacion_residente SET estado='pausado', updated_by=? WHERE id_residente=? AND estado='activo'")
            ->execute([uid(), $id_res]);
    }

    if ($accion === 'activo') {
        $pdo->prepare('UPDATE residentes SET fecha_egreso=NULL WHERE id_residente=?')
            ->execute([$id_res]);
        $pdo->prepare("UPDATE medicacion_residente SET estado='activo', updated_by=? WHERE id_residente=? AND estado='pausado'")
            ->execute([uid(), $id_res]);
    }

    header('Location: listar.php?toast=Estado+actualizado+correctamente&tipo=ok');
    exit;
}

include '../config/menu.php';

if (isset($_GET['ver'])) {

    $st = $pdo->prepare('SELECT r.*, h.numero habitacion, h.sector, h.piso,
                                c.identificador cama, h.estado estado_habitacion
                         FROM residentes r
                         LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
                         LEFT JOIN camas c ON c.id_cama = r.id_cama
                         WHERE r.id_residente=?');
    $st->execute([$_GET['ver']]);
    $r = $st->fetch();

    $med = $pdo->prepare("SELECT mr.*, m.nombre med, m.presentacion,
                                 rec.diagnostico, rec.fecha_inicio r_inicio,
                                 rec.fecha_fin r_fin, rec.foto_receta
                          FROM medicacion_residente mr
                          JOIN medicamentos m ON m.id_medicamento = mr.id_medicamento
                          LEFT JOIN recetas_medicas rec ON rec.id_receta = mr.id_receta
                          WHERE mr.id_residente=? AND mr.estado='activo'
                          ORDER BY rec.fecha_inicio DESC, mr.horario");
    $med->execute([$r['id_residente']]);
    $med = $med->fetchAll();

    $hist = $pdo->prepare("SELECT am.*, m.nombre med, m.presentacion,
                                  u.nombre cuidadora, mr.dosis, mr.horario,
                                  rec.diagnostico
                           FROM administracion_medicacion am
                           JOIN medicamentos m ON m.id_medicamento = am.id_medicamento
                           LEFT JOIN usuarios u ON u.id_usuario = am.id_cuidadora_usuario
                           LEFT JOIN medicacion_residente mr ON mr.id_plan = am.id_plan
                           LEFT JOIN recetas_medicas rec ON rec.id_receta = mr.id_receta
                           WHERE am.id_residente=?
                           AND am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY am.fecha_hora DESC");
    $hist->execute([$r['id_residente']]);
    $hist = $hist->fetchAll();

    $controles = $pdo->prepare("SELECT cc.*, u.nombre registrado_por
                                FROM controles_clinicos cc
                                LEFT JOIN usuarios u ON u.id_usuario = cc.id_usuario
                                WHERE cc.id_residente=?
                                AND cc.fecha_hora >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                ORDER BY cc.fecha_hora DESC");
    $controles->execute([$r['id_residente']]);
    $controles = $controles->fetchAll();

    $historial_estados = $pdo->prepare("SELECT h.*, u.nombre as quien
                                        FROM historial_estados h
                                        LEFT JOIN usuarios u ON u.id_usuario = h.cambiado_por
                                        WHERE h.entidad='residente' AND h.id_registro=?
                                        ORDER BY h.created_at DESC");
    $historial_estados->execute([$r['id_residente']]);
    $historial_estados = $historial_estados->fetchAll();
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
                <?php if ($r['autorizado_foto']): ?>
                    <span class="badge" style="background:#e8f5e9;color:#2e7d32">Foto autorizada</span>
                <?php endif; ?>
            </div>

            <table class="no-datatable" style="width:100%">
                <thead>
                    <tr>
                        <th>DNI</th><th>Nacimiento</th><th>Ingreso</th>
                        <th>Habitación</th><th>Movilidad</th>
                        <th>Familiar</th><th>Teléfono</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($r['dni']) ?></td>
                        <td><?= $r['fecha_nacimiento'] ?></td>
                        <td><?= $r['fecha_ingreso'] ?: '' ?></td>
                        <td>
                            <?php if ($r['habitacion']): ?>
                                Hab. <?= $r['habitacion'] ?> · <?= h($r['sector']) ?><br>
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
        <?= $r['diagnostico'] ? htmlspecialchars($r['diagnostico']) : '' ?>
    </p>
</div>

<?php if ($controles): ?>
<h2>Controles clínicos: últimos 30 días</h2>
<table class="datatable">
    <thead>
        <tr><th>Fecha</th><th>Control</th><th>Valor</th><th>Unidad</th><th>Registrado por</th><th>Obs.</th></tr>
    </thead>
    <tbody>
    <?php foreach ($controles as $cc): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($cc['fecha_hora'])) ?></td>
            <td><?= str_replace('_', ' ', $cc['tipo_control']) ?></td>
            <td><b><?= $cc['valor'] ?></b></td>
            <td><?= $cc['unidad'] ?></td>
            <td><?= htmlspecialchars($cc['registrado_por'] ?? '') ?></td>
            <td><?= htmlspecialchars($cc['observaciones'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Medicamentos y recetas</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Tratamiento</th><th>Medicamento</th><th>Dosis</th>
            <th>Horario</th><th>Inicio</th><th>Vencimiento</th><th>Indicación</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($med as $m): ?>
        <tr>
            <td>
                <?= htmlspecialchars($m['diagnostico'] ?: '') ?>
                <?php if ($m['foto_receta']): ?>
                    <br><a class="btn blue" target="_blank"
                       href="<?= BASE_URL . '/' . $m['foto_receta'] ?>">Ver foto receta</a>
                <?php endif; ?>
            </td>
            <td><?= h($m['med']) ?> <?= h($m['presentacion']) ?></td>
            <td><?= h($m['dosis']) ?></td>
            <td><b><?= $m['horario'] ?></b></td>
            <td><?= $m['r_inicio'] ?></td>
            <td><?= $m['r_fin'] ?: 'Permanente' ?></td>
            <td><?= htmlspecialchars($m['indicacion']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Historial de medicación: últimos 7 días</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th><th>Medicamento</th><th>Dosis</th>
            <th>Tratamiento</th><th>Estado</th><th>Registrado por</th><th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
        <tr>
            <td><?= $h['fecha_hora'] ?></td>
            <td><?= h($h['med']) ?> <?= h($h['presentacion']) ?></td>
            <td><?= h($h['dosis']) ?></td>
            <td><?= htmlspecialchars($h['diagnostico'] ?: '') ?></td>
            <td><div class="estado-cuadro <?= estado_class($h['estado_adm']) ?>"><?= estado_label($h['estado_adm']) ?></div></td>
            <td><?= h($h['cuidadora']) ?: '' ?></td>
            <td><?= htmlspecialchars($h['observaciones']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($historial_estados): ?>
<h2>Historial de estados</h2>
<table class="no-datatable">
    <tr><th>Fecha</th><th>Cambio</th><th>Realizado por</th></tr>
    <?php foreach ($historial_estados as $h): ?>
    <tr>
        <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
        <td>
            <span class="pill estado-<?= $h['estado_anterior'] ?>"><?= $h['estado_anterior'] ?: 'alta' ?></span>
            → <span class="pill estado-<?= $h['estado_nuevo'] ?>"><?= $h['estado_nuevo'] ?></span>
        </td>
        <td><?= htmlspecialchars($h['quien'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<br>
<?php if (es_admin()): ?>
    <button class="btn" onclick="abrirModalResidente(<?= $r['id_residente'] ?>, <?= htmlspecialchars(json_encode($r)) ?>)">Modificar datos</button>
<?php endif; ?>
<a class="btn gray" href="listar.php">Volver al listado</a>

<?php
    include '../config/footer.php';
    exit;
}

$rows = $pdo->query("SELECT r.*, h.numero habitacion, h.sector
                     FROM residentes r
                     LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
                     WHERE r.estado != 'baja'
                     ORDER BY r.apellido, r.nombre")->fetchAll();
?>

<h1>Residentes</h1>

<?php if (isset($_GET['cb03_warn']) && es_admin()):
    $id_warn = (int)$_GET['cb03_warn'];
    $res_warn = $pdo->prepare("SELECT nombre, apellido FROM residentes WHERE id_residente=?");
    $res_warn->execute([$id_warn]);
    $rw = $res_warn->fetch();
?>
<div style="background:#fff3cd;border-left:5px solid #e67e22;padding:16px 20px;border-radius:8px;margin-bottom:20px">
    <b style="color:#856404;font-size:15px">Advertencia: medicación pendiente en el turno actual</b><br>
    <p style="margin:8px 0;color:#555;font-size:14px">
        <b><?= htmlspecialchars($rw['nombre'] . ' ' . $rw['apellido']) ?></b> tiene medicaciones
        en estado <b>Pendiente</b> en el turno de hoy. Si das de baja al residente ahora, esas
        medicaciones quedarán sin registrar.
    </p>
    <div style="display:flex;gap:10px;margin-top:12px">
        <a href="?accion=baja&id=<?= $id_warn ?>&confirmar_baja=1" class="btn red">
            Dar de baja de todas formas
        </a>
        <a href="listar.php" class="btn gray">Cancelar</a>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['ver_bajas']) && es_admin()): ?>
    <?php
    $bajas = $pdo->query("SELECT r.*, h.numero habitacion
                          FROM residentes r
                          LEFT JOIN habitaciones h ON h.id_habitacion = r.id_habitacion
                          WHERE r.estado='baja'
                          ORDER BY r.apellido, r.nombre")->fetchAll();
    ?>
    <h2>Residentes dados de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (!empty($bajas)): ?>
    <table class="datatable">
        <thead>
            <tr><th>Nombre</th><th>DNI</th><th>Fecha egreso</th><th>Familiar</th><th>Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
                <td><?= $b['dni'] ?></td>
                <td><?= $b['fecha_egreso'] ? date('d/m/Y', strtotime($b['fecha_egreso'])) : '' ?></td>
                <td><?= htmlspecialchars($b['contacto_familiar']) ?></td>
                <td>
                    <a class="btn blue" href="?ver=<?= $b['id_residente'] ?>">Ver ficha</a>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_residente'] ?>"
                       onclick="return confirmarLink(this,'¿Reincorporar a este residente?','Reincorporar','green')">
                       Reincorporar
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
        <button class="btn" onclick="abrirModalResidente(0, null)">+ Crear residente</button>
        <a class="btn gray" href="?ver_bajas=1">Ver residentes dados de baja</a>
    </div>
<?php else: ?>
    <div class="card">
        <b>Solo lectura:</b> podés consultar la ficha de cada residente, pero no podés crear ni modificar.
    </div>
<?php endif; ?>

<table class="datatable">
    <thead>
        <tr>
            <th>Foto</th><th>Nombre</th><th>DNI</th>
            <th>Habitación</th><th>Familiar</th><th>Estado</th><th>Acciones</th>
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
            <td><span class="pill estado-<?= $r['estado'] ?>"><?= ['activo'=>'Activo','pausado'=>'Servicio suspendido','baja'=>'Baja'][$r['estado']] ?? $r['estado'] ?></span></td>
            <td>
                <a class="btn blue" href="?ver=<?= $r['id_residente'] ?>">Ver ficha</a>
                <?php if (es_admin() && $r['token_visita']): ?>
                    <button class="btn gray" style="font-size:12px"
                        onclick="copiarLink('<?= BASE_URL ?>/visitas/solicitar.php?token=<?= $r['token_visita'] ?>', this)">
Link visitas
                    </button>
                <?php endif; ?>
                <?php if (es_admin()): ?>
                    <button class="btn" onclick="abrirModalResidente(<?= $r['id_residente'] ?>, <?= htmlspecialchars(json_encode($r)) ?>)">Modificar</button>
                    <?php if ($r['estado'] === 'activo'): ?>
                        <button class="btn orange"
                            onclick="abrirModalSuspender(<?= $r['id_residente'] ?>, '<?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>')">
                            Suspender servicio
                        </button>
                        <a class="btn red" href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirmarLink(this,'¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>? Se registrará la fecha de egreso y se liberará la cama.','Dar de baja','red')">
                           Dar de baja
                        </a>
                    <?php elseif ($r['estado'] === 'pausado'): ?>
                        <a class="btn green" href="?accion=activo&id=<?= $r['id_residente'] ?>"
                           onclick="return confirmarLink(this,'¿Reactivar el servicio de <?= htmlspecialchars($r['nombre']) ?>? Se reactivarán sus medicaciones.','Reactivar servicio','green')">
                           Reactivar servicio
                        </a>
                        <a class="btn red" href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirmarLink(this,'¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>?','Dar de baja','red')">
                           Dar de baja
                        </a>
                    <?php elseif ($r['estado'] === 'baja'): ?>
                        <a class="btn green" href="?accion=activo&id=<?= $r['id_residente'] ?>"
                           onclick="return confirmarLink(this,'¿Reincorporar a <?= htmlspecialchars($r['nombre']) ?>?','Reincorporar','green')">
                           Reincorporar
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$habitaciones_modal = $pdo->query("
    SELECT DISTINCT h.*
    FROM habitaciones h
    JOIN camas c ON c.id_habitacion = h.id_habitacion
    WHERE c.estado = 'disponible'
    ORDER BY h.numero
")->fetchAll();
$camas_modal        = $pdo->query("SELECT * FROM camas WHERE estado='disponible' ORDER BY id_habitacion, identificador")->fetchAll();
?>

<div id="modal-residente" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:680px;position:relative">

        <button onclick="cerrarModalResidente()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">×</button>

        <h2 id="modal-res-titulo" style="margin:0 0 16px">Crear residente</h2>

        <?php if(!empty($_SESSION['error_form'])): ?>
            <div style="background:#fdecea;border-left:4px solid #E05C5C;padding:10px 14px;border-radius:6px;margin-bottom:12px">
<?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="crear.php" enctype="multipart/form-data" id="form-residente">
            <input type="hidden" name="id"          id="f-res-id">
            <input type="hidden" name="foto_actual" id="f-res-foto">
            <input type="hidden" name="estado"      id="f-res-estado" value="activo">

            <div style="display:grid;grid-template-columns:1fr 1fr 140px;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre *</label>
                    <input name="nombre" id="f-res-nombre" placeholder="Nombre" required minlength="2" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Apellido *</label>
                    <input name="apellido" id="f-res-apellido" placeholder="Apellido" required minlength="2" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">DNI *</label>
                    <input name="dni" id="f-res-dni" placeholder="25123456" required pattern="[0-9]{7,8}" inputmode="numeric" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:10px;align-items:end">
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha de nacimiento</label>
                    <input type="date" name="fecha_nacimiento" id="f-res-fnac" max="<?= date('Y-m-d') ?>" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha de ingreso</label>
                    <input type="date" name="fecha_ingreso" id="f-res-fingreso" max="<?= date('Y-m-d') ?>" style="width:100%;box-sizing:border-box">
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;padding-bottom:4px">
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="checkbox" name="movilidad_autonoma" id="f-res-mov" style="width:auto"> Movilidad autónoma
                    </label>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="checkbox" name="autorizado_foto" id="f-res-fotoauth" style="width:auto"> Autoriza fotos
                    </label>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Habitación asignada</label>
                    <select name="id_habitacion" id="f-res-hab" class="buscador" onchange="filtrarCamasModal()" style="width:100%;box-sizing:border-box">
                        <option value="">Sin asignar</option>
                        <?php foreach ($habitaciones_modal as $h):
                            $camas_disp = array_filter($camas_modal, fn($c) => $c['id_habitacion'] == $h['id_habitacion']);
                        ?>
                            <option value="<?= $h['id_habitacion'] ?>">
                                Hab. <?= h($h['numero']) ?>, <?= h($h['sector']) ?> (<?= count($camas_disp) ?> cama<?= count($camas_disp) != 1 ? 's' : '' ?> libre<?= count($camas_disp) != 1 ? 's' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Cama asignada</label>
                    <select name="id_cama" id="f-res-cama" class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Seleccioná primero una habitación</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre del familiar</label>
                    <input name="contacto_familiar" id="f-res-familiar" placeholder="Nombre y apellido" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Teléfono del familiar</label>
                    <input name="telefono_familiar" id="f-res-telefam" type="tel" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Observaciones médicas</label>
                <textarea name="diagnostico" id="f-res-diag" rows="2"
                          placeholder="Diagnósticos, alergias, condiciones especiales..."
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:10px">
                <div id="bloque-foto-actual" style="display:none;margin-bottom:6px;display:flex;align-items:center;gap:10px">
                    <img id="f-res-foto-img" style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #ddd">
                    <span style="font-size:13px;color:#888">Foto actual</span>
                </div>
                <label style="font-size:13px;font-weight:600" id="label-foto">Foto del residente (opcional)</label>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" style="width:100%;box-sizing:border-box">
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="cerrarModalResidente()">Cancelar</button>
                <a class="btn gray" href="listar.php" id="btn-volver-residente" style="display:none">Volver al listado</a>
            </div>
        </form>
    </div>
</div>

<script>
const todasLasCamas = <?= json_encode(
    $pdo->query("SELECT id_cama, id_habitacion, identificador, estado FROM camas WHERE estado='disponible' ORDER BY id_habitacion, identificador")->fetchAll()
) ?>;

function abrirModalResidente(id, datos) {
    const modal = document.getElementById('modal-residente');
    modal.style.display = 'flex';

    document.getElementById('f-res-id').value       = id || '';
    document.getElementById('f-res-nombre').value   = datos ? datos.nombre             : '';
    document.getElementById('f-res-apellido').value = datos ? (datos.apellido  || '')  : '';
    document.getElementById('f-res-dni').value      = datos ? datos.dni                : '';
    document.getElementById('f-res-fnac').value     = datos ? (datos.fecha_nacimiento || '') : '';
    document.getElementById('f-res-fingreso').value = datos ? (datos.fecha_ingreso    || '') : '';
    document.getElementById('f-res-mov').checked    = datos ? !!parseInt(datos.movilidad_autonoma) : true;
    document.getElementById('f-res-fotoauth').checked = datos ? !!parseInt(datos.autorizado_foto)  : false;
    document.getElementById('f-res-familiar').value = datos ? (datos.contacto_familiar || '') : '';
    document.getElementById('f-res-telefam').value  = datos ? (datos.telefono_familiar || '') : '';
    document.getElementById('f-res-diag').value     = datos ? (datos.diagnostico       || '') : '';
    document.getElementById('f-res-foto').value     = datos ? (datos.foto              || '') : '';
    document.getElementById('f-res-estado').value   = datos ? (datos.estado            || 'activo') : 'activo';

    const bloqueImg = document.getElementById('bloque-foto-actual');
    const img       = document.getElementById('f-res-foto-img');
    if (datos && datos.foto) {
        img.src = '<?= BASE_URL ?>/' + datos.foto;
        bloqueImg.style.display = 'block';
        document.getElementById('label-foto').textContent = 'Reemplazar foto (opcional)';
    } else {
        bloqueImg.style.display = 'none';
        document.getElementById('label-foto').textContent = 'Foto del residente (opcional)';
    }

    document.getElementById('modal-res-titulo').textContent = id ? 'Modificar residente' : 'Crear residente';

    const selHab  = document.getElementById('f-res-hab');
    const habId   = datos ? (datos.id_habitacion || '') : '';
    if (habId && !Array.from(selHab.options).find(o => o.value == habId)) {
        const opt = document.createElement('option');
        opt.value = habId;
        opt.textContent = 'Hab. actual (ID ' + habId + ')';
        selHab.appendChild(opt);
    }
    selHab.value = habId;
    if (window.refrescarBuscador) refrescarBuscador('id_habitacion');

    filtrarCamasModal(datos ? (datos.id_cama || '') : '');
}

function cerrarModalResidente() {
    document.getElementById('modal-residente').style.display = 'none';
}

window.onclick = function(e) {
    const m = document.getElementById('modal-residente');
    if (e.target === m) cerrarModalResidente();
}

function filtrarCamasModal(camaSeleccionada) {
    const habId   = document.getElementById('f-res-hab').value;
    const selCama = document.getElementById('f-res-cama');

    selCama.innerHTML = '<option value="">Sin asignar</option>';

    if (!habId) {
        selCama.innerHTML = '<option value="">Seleccioná primero una habitación</option>';
        if (window.refrescarBuscador) refrescarBuscador('id_cama');
        return;
    }

    const camasFiltradas = todasLasCamas.filter(c => c.id_habitacion == habId);

    if (camasFiltradas.length === 0) {
        selCama.innerHTML = '<option value="">No hay camas disponibles</option>';
    } else {
        camasFiltradas.forEach(c => {
            const opt = document.createElement('option');
            opt.value              = c.id_cama;
            opt.dataset.habitacion = c.id_habitacion;
            opt.textContent        = 'Cama ' + c.identificador;
            if (camaSeleccionada && c.id_cama == camaSeleccionada) opt.selected = true;
            selCama.appendChild(opt);
        });
    }

    if (window.refrescarBuscador) refrescarBuscador('id_cama');
}

document.getElementById('f-res-hab').addEventListener('change', function() {
    filtrarCamasModal('');
});

document.getElementById('form-residente').addEventListener('submit', function(e) {
    const dni  = document.getElementById('f-res-dni').value.trim();
    const fecha= document.getElementById('f-res-fnac').value;

    if (dni && !/^[0-9]{7,8}$/.test(dni)) {
        alert('El DNI debe tener entre 7 y 8 dígitos numéricos.');
        e.preventDefault(); return;
    }
    if (fecha) {
        const hoy  = new Date();
        const nac  = new Date(fecha);
        const edad = hoy.getFullYear() - nac.getFullYear();
        if (edad < 50 && !confirm('La fecha indica que el residente tiene menos de 50 años (' + edad + '). ¿Es correcto?')) {
            e.preventDefault(); return;
        }
        if (nac > hoy) {
            alert('La fecha de nacimiento no puede ser futura.');
            e.preventDefault(); return;
        }
    }
});
</script>

<div id="modal-suspender" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:28px;width:100%;max-width:480px;position:relative">
        <button onclick="cerrarModalSuspender()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">×</button>
        <h2 style="margin-top:0">Suspender servicio</h2>
        <p id="suspender-nombre" style="color:var(--text-muted);margin-bottom:16px"></p>
        <p style="font-size:13px;background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:6px;margin-bottom:16px">
La cama <b>no se libera</b>. Solo se pausan las medicaciones activas del residente.
        </p>
        <form id="form-suspender" method="get" action="listar.php">
            <input type="hidden" name="accion" value="pausado">
            <input type="hidden" name="id" id="suspender-id">
            <label>Motivo <span style="color:red">*</span></label>
            <input name="motivo" id="suspender-motivo" required minlength="5">
            <label>Fecha estimada de regreso <span style="color:#aaa">(opcional)</span></label>
            <input type="date" name="fecha_fin" id="suspender-fecha" min="<?= date('Y-m-d') ?>">
            <br><br>
            <button type="submit" class="btn orange">Suspender servicio</button>
            <button type="button" class="btn gray" onclick="cerrarModalSuspender()">Cancelar</button>
        </form>
    </div>
</div>
<script>
function abrirModalSuspender(id, nombre) {
    document.getElementById('suspender-id').value    = id;
    document.getElementById('suspender-nombre').textContent = 'Residente: ' + nombre;
    document.getElementById('suspender-motivo').value = '';
    document.getElementById('suspender-fecha').value  = '';
    document.getElementById('modal-suspender').style.display = 'flex';
}
function cerrarModalSuspender() {
    document.getElementById('modal-suspender').style.display = 'none';
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('modal-suspender');
    if (e.target === m) cerrarModalSuspender();
});
</script>

<script>
function copiarLink(url, btn) {
    navigator.clipboard.writeText(url).then(function() {
        var orig = btn.textContent;
        btn.textContent = 'Copiado!';
        btn.style.background = '#27ae60';
        btn.style.color = '#fff';
        setTimeout(function() {
            btn.textContent = orig;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    });
}
</script>
<?php include '../config/footer.php'; ?>







