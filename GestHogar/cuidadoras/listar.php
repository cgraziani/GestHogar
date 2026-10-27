<?php
// ============================================================
// CUIDADORAS / PERSONAL DE CUIDADO
// v2: opera sobre tabla usuarios (cuidadoras fue eliminada)
// Filtra por rol IN ('cuidadora','asistente')
// Acciones: listar, ver detalle, editar datos laborales,
//           cambiar estado, historial de estados
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

$yo = uid();

// ============================================================
// CAMBIAR ESTADO
// Registra en historial_estados y cancela turnos si es baja
// ============================================================
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $accion = $_GET['accion'];
    if (!in_array($accion, ['activo','pausado','baja'])) {
        header('Location: listar.php'); exit;
    }

    $actual = $pdo->prepare('SELECT estado FROM usuarios WHERE id_usuario=?');
    $actual->execute([$id]);
    $estado_anterior = $actual->fetchColumn();

    $pdo->prepare('UPDATE usuarios SET estado=?, updated_by=? WHERE id_usuario=?')
        ->execute([$accion, $yo, $id]);

    $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por) VALUES(?,?,?,?,?)')
        ->execute(['usuario', $id, $estado_anterior, $accion, $yo]);

    if ($accion === 'baja') {
        $pdo->prepare("UPDATE turnos_programados SET estado='baja', updated_by=? WHERE id_cuidadora_usuario=? AND fecha >= CURDATE() AND estado='activo'")
            ->execute([$yo, $id]);
    }

    $labels = ['activo' => 'activada', 'pausado' => 'pausada', 'baja' => 'dada+de+baja'];
    header('Location: listar.php?toast=Cuidadora+' . $labels[$accion] . '+correctamente&tipo=ok');
    exit;
}

// ============================================================
// EDITAR CUIDADORA (POST del modal de edición)
// Maneja todos los campos: nombre, apellido, email, rol,
// dni, telefono, tipo_turno, estado, color
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
    $id = (int)$_POST['id_usuario'];

    if (!empty(trim($_POST['dni']))) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE dni=? AND id_usuario!=?');
        $q->execute([trim($_POST['dni']), $id]);
        if ($q->fetchColumn() > 0) {
            $_SESSION['error_form'] = 'Ya existe un usuario con ese DNI.';
            header('Location: listar.php'); exit;
        }
    }
    if (!empty(trim($_POST['email']))) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email=? AND id_usuario!=?');
        $q->execute([trim($_POST['email']), $id]);
        if ($q->fetchColumn() > 0) {
            $_SESSION['error_form'] = 'Ya existe un usuario con ese email.';
            header('Location: listar.php'); exit;
        }
    }

    $pdo->prepare('UPDATE usuarios SET nombre=?,apellido=?,email=?,rol=?,dni=?,telefono=?,tipo_turno=?,estado=?,color=?,updated_by=? WHERE id_usuario=?')
        ->execute([
            trim($_POST['nombre']),
            trim($_POST['apellido'] ?? ''),
            trim($_POST['email']),
            $_POST['rol'],
            trim($_POST['dni'])        ?: null,
            trim($_POST['telefono'])   ?: null,
            trim($_POST['tipo_turno']) ?: null,
            $_POST['estado'],
            $_POST['color'] ?: '#087f7a',
            $yo,
            $id
        ]);

    header('Location: listar.php?toast=Cuidadora+modificada+correctamente&tipo=ok'); exit;
}

include '../config/menu.php';

// ============================================================
// VISTA DE DETALLE (?ver=ID)
// ============================================================
if (isset($_GET['ver'])) {
    $u = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=? AND rol IN (\'cuidadora\',\'asistente\')');
    $u->execute([$_GET['ver']]); $u = $u->fetch();
    if (!$u) { header('Location: listar.php'); exit; }

    $turnos = $pdo->prepare("SELECT * FROM turnos_programados WHERE id_cuidadora_usuario=? AND estado='activo' ORDER BY fecha, hora_inicio LIMIT 20");
    $turnos->execute([$u['id_usuario']]); $turnos = $turnos->fetchAll();

    $historial = $pdo->prepare("SELECT h.*, ua.nombre quien FROM historial_estados h LEFT JOIN usuarios ua ON ua.id_usuario=h.cambiado_por WHERE h.entidad='usuario' AND h.id_registro=? ORDER BY h.created_at DESC");
    $historial->execute([$u['id_usuario']]); $historial = $historial->fetchAll();
?>

<h1>Detalle de cuidadora</h1>

<?php if (!empty($_SESSION['error_form'])): ?>
    <div class="alert" style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
        ⚠️ <?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">
        <span style="display:inline-block;width:44px;height:44px;background:<?= $u['color'] ?>;border-radius:50%"></span>
        <div>
            <h2 style="margin:0"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></h2>
            <span class="pill estado-<?= $u['estado'] ?>"><?= $u['estado'] ?></span>
            <span class="badge"><?= $u['rol'] ?></span>
        </div>
    </div>

    <table class="no-datatable" style="width:100%">
        <tr><th>Email</th><th>DNI</th><th>Teléfono</th><th>Tipo turno</th><th>Color</th></tr>
        <tr>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['dni'] ?? '—') ?></td>
            <td><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
            <td><?= htmlspecialchars($u['tipo_turno'] ?? '—') ?></td>
            <td>
                <span style="display:inline-block;width:28px;height:18px;background:<?= $u['color'] ?>;border-radius:4px"></span>
                <?= $u['color'] ?>
            </td>
        </tr>
    </table>
</div>

<!-- Editar datos laborales -->
<div class="card">
    <h3 style="margin-top:0">Editar datos laborales</h3>
    <form method="post">
        <input type="hidden" name="accion_editar" value="1">
        <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:150px">
                <label>DNI <span style="color:red">*</span></label>
                <input name="dni" value="<?= htmlspecialchars($u['dni'] ?? '') ?>" placeholder="Ej: 30111222" pattern="[0-9]{7,8}" inputmode="numeric">
            </div>
            <div style="flex:1;min-width:150px">
                <label>Teléfono</label>
                <input name="telefono" type="tel" value="<?= htmlspecialchars($u['telefono'] ?? '') ?>" placeholder="Ej: 351-1234567">
            </div>
            <div style="flex:1;min-width:150px">
                <label>Tipo de turno</label>
                <select name="tipo_turno">
                    <option value="">Sin especificar</option>
                    <?php foreach (['24/48','Diurno','Nocturno','Refuerzo mañana','Refuerzo tarde','Fin de semana'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($u['tipo_turno'] === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <br>
        <button type="submit" class="btn">Guardar datos laborales</button>
    </form>
</div>

<?php if ($turnos): ?>
<h2>Próximos turnos</h2>
<table class="no-datatable">
    <tr><th>Fecha</th><th>Horario</th><th>Nota</th></tr>
    <?php foreach ($turnos as $t): ?>
    <tr>
        <td><?= date('d/m/Y', strtotime($t['fecha'])) ?></td>
        <td><?= $t['hora_inicio'] ?> a <?= $t['hora_fin'] ?></td>
        <td><?= htmlspecialchars($t['nota']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($historial): ?>
<h2>Historial de estados</h2>
<table class="no-datatable">
    <tr><th>Fecha</th><th>Cambio</th><th>Realizado por</th></tr>
    <?php foreach ($historial as $h): ?>
    <tr>
        <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
        <td>
            <span class="pill estado-<?= $h['estado_anterior'] ?>"><?= $h['estado_anterior'] ?: 'alta' ?></span>
            → <span class="pill estado-<?= $h['estado_nuevo'] ?>"><?= $h['estado_nuevo'] ?></span>
        </td>
        <td><?= htmlspecialchars($h['quien'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<br>
<a class="btn gray" href="listar.php">Volver al listado</a>

<?php include '../config/footer.php'; exit; }

// ============================================================
// LISTADO PRINCIPAL — personal activo y pausado
// ============================================================
$rows = $pdo->query("SELECT * FROM usuarios
                     WHERE rol IN ('cuidadora','asistente') AND estado != 'baja'
                     ORDER BY rol, apellido, nombre")->fetchAll();
?>

<h1>Cuidadoras / Personal de cuidado</h1>

<?php if (isset($_GET['ver_bajas'])): ?>
    <?php $bajas = $pdo->query("SELECT * FROM usuarios WHERE rol IN ('cuidadora','asistente') AND estado='baja' ORDER BY apellido,nombre")->fetchAll(); ?>
    <h2>Personal dado de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (empty($bajas)): ?>
        <div class="card"><p>No hay personal dado de baja.</p></div>
    <?php else: ?>
    <table class="datatable">
        <thead><tr><th>Color</th><th>Nombre</th><th>Rol</th><th>DNI</th><th>Turno</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td><span style="display:inline-block;width:18px;height:18px;background:<?= $b['color'] ?>;border-radius:50%"></span></td>
                <td><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
                <td><span class="badge"><?= $b['rol'] ?></span></td>
                <td><?= htmlspecialchars($b['dni'] ?? '—') ?></td>
                <td><?= htmlspecialchars($b['tipo_turno'] ?? '—') ?></td>
                <td>
                    <a class="btn blue" href="?ver=<?= $b['id_usuario'] ?>">Ver</a>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_usuario'] ?>"
                       onclick="return confirmarLink(this,'¿Reincorporar a este personal?','Reincorporar','green')">
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

<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn" onclick="abrirModalCuidadora()">+ Crear cuidadora</button>
    <a class="btn gray" href="?ver_bajas=1">Ver personal dado de baja</a>
</div>

<table class="datatable">
    <thead>
        <tr>
            <th>Color</th><th>Nombre</th><th>Rol</th><th>DNI</th>
            <th>Teléfono</th><th>Tipo turno</th><th>Estado</th><th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><span style="display:inline-block;width:18px;height:18px;background:<?= $r['color'] ?>;border-radius:50%"></span></td>
        <td>
            <b><?= htmlspecialchars($r['nombre'] . ' ' . ($r['apellido'] ?? '')) ?></b>
        </td>
        <td><span class="badge"><?= $r['rol'] ?></span></td>
        <td><?= htmlspecialchars($r['dni'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['telefono'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['tipo_turno'] ?? '—') ?></td>
        <td><span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span></td>
        <td>
            <a class="btn blue" href="?ver=<?= $r['id_usuario'] ?>">Ver</a>
            <button class="btn" onclick="abrirModalEditarCuidadora(<?= $r['id_usuario'] ?>, <?= htmlspecialchars(json_encode($r)) ?>)">Editar</button>

            <?php if ($r['estado'] === 'activo'): ?>
                <a class="btn orange"
                   href="?accion=pausado&id=<?= $r['id_usuario'] ?>"
                   onclick="return confirmarLink(this,'¿Pausar a este usuario? Podés reactivarlo cuando quieras.','Pausar','orange')">Pausar</a>
                <a class="btn red"
                   href="?accion=baja&id=<?= $r['id_usuario'] ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja? Ya no podrá acceder al sistema y sus turnos futuros serán cancelados.','Dar de baja','red')">Dar de baja</a>
            <?php elseif ($r['estado'] === 'pausado'): ?>
                <a class="btn green" href="?accion=activo&id=<?= $r['id_usuario'] ?>">Activar</a>
                <a class="btn red"
                   href="?accion=baja&id=<?= $r['id_usuario'] ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja este usuario del sistema?','Dar de baja','red')">Dar de baja</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
// Repoblar modal si hubo error de validación
$fd = $_SESSION['form_data'] ?? null;
unset($_SESSION['form_data']);
?>

<!-- ============================================================
     MODAL CREAR CUIDADORA
     Mismo estilo que modal de usuarios
     Opera sobre tabla usuarios con rol cuidadora/asistente
     ============================================================ -->
<div id="modal-cuidadora" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:28px;width:100%;max-width:520px;position:relative;max-height:90vh;overflow-y:auto">

        <button onclick="cerrarModalCuidadora()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">✕</button>

        <h2 id="modal-cuid-titulo">Crear cuidadora</h2>

        <?php if (!empty($_SESSION['error_form'])): ?>
            <div class="alert" style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
                ⚠️ <?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="crear.php" id="form-cuidadora">

            <label>Nombre <span style="color:red">*</span></label>
            <input name="nombre" id="f-cuid-nombre"
                   value="<?= htmlspecialchars($fd['nombre'] ?? '') ?>"
                   placeholder="Nombre" required minlength="2">

            <label>Apellido <span style="color:red">*</span></label>
            <input name="apellido" id="f-cuid-apellido"
                   value="<?= htmlspecialchars($fd['apellido'] ?? '') ?>"
                   placeholder="Apellido" required minlength="2">

            <label>Email (para iniciar sesión) <span style="color:red">*</span></label>
            <input name="email" type="email" id="f-cuid-email"
                   value="<?= htmlspecialchars($fd['email'] ?? '') ?>"
                   placeholder="email@ejemplo.com" required>

            <label>Contraseña <span style="color:red">*</span></label>
            <input name="password" type="password" id="f-cuid-pass"
                   placeholder="Mínimo 6 caracteres" minlength="6" required>
            <small style="color:#888">Solo se define al crear la cuidadora.</small>

            <label>Rol</label>
            <select name="rol" id="f-cuid-rol">
                <option value="cuidadora" <?= ($fd['rol'] ?? '') === 'cuidadora' ? 'selected' : '' ?>>Cuidadora</option>
                <option value="asistente" <?= ($fd['rol'] ?? '') === 'asistente' ? 'selected' : '' ?>>Asistente</option>
            </select>

            <label>DNI <span style="color:red">*</span></label>
            <input name="dni" id="f-cuid-dni"
                   value="<?= htmlspecialchars($fd['dni'] ?? '') ?>"
                   placeholder="Ej: 30111222"
                   required pattern="[0-9]{7,8}" inputmode="numeric"
                   title="Entre 7 y 8 dígitos numéricos">

            <label>Teléfono</label>
            <input name="telefono" type="tel" id="f-cuid-telefono"
                   value="<?= htmlspecialchars($fd['telefono'] ?? '') ?>"
                   placeholder="Ej: 351-1234567">

            <label>Tipo de turno</label>
            <select name="tipo_turno" id="f-cuid-turno">
                <option value="">Sin especificar</option>
                <?php foreach (['24/48','Diurno','Nocturno','Refuerzo mañana','Refuerzo tarde','Fin de semana'] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($fd['tipo_turno'] ?? '') === $opt ? 'selected' : '' ?>>
                        <?= $opt ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Color para el calendario</label>
            <input name="color" type="color" id="f-cuid-color"
                   value="<?= htmlspecialchars($fd['color'] ?? '#087f7a') ?>">

            <br><br>
            <button type="submit" class="btn">Guardar cuidadora</button>
            <button type="button" class="btn gray" onclick="cerrarModalCuidadora()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModalCuidadora() {
    document.getElementById('modal-cuidadora').style.display = 'flex';
}

function cerrarModalCuidadora() {
    document.getElementById('modal-cuidadora').style.display = 'none';
}

window.onclick = function(e) {
    const m = document.getElementById('modal-cuidadora');
    if (e.target === m) cerrarModalCuidadora();
}

// Abrir automáticamente si hubo error de validación
<?php if (isset($_GET['abrir_modal'])): ?>
abrirModalCuidadora();
<?php endif; ?>
</script>

<!-- ============================================================
     MODAL EDITAR CUIDADORA — mismo estilo que modal de usuarios
     ============================================================ -->
<div id="modal-editar-cuidadora" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:28px;width:100%;max-width:520px;position:relative;max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModalEditarCuidadora()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">✕</button>
        <h2 id="modal-editar-cuid-titulo">Modificar cuidadora</h2>
        <form method="post" id="form-editar-cuidadora">
            <input type="hidden" name="id_usuario" id="fec-id">
            <label>Nombre</label>
            <input name="nombre" id="fec-nombre" placeholder="Nombre" required>
            <label>Apellido</label>
            <input name="apellido" id="fec-apellido" placeholder="Apellido" required>
            <label>Email (para iniciar sesión)</label>
            <input name="email" type="email" id="fec-email" placeholder="email@ejemplo.com" required>
            <label>Rol</label>
            <select name="rol" id="fec-rol">
                <option value="cuidadora">Cuidadora</option>
                <option value="asistente">Asistente</option>
            </select>
            <label>DNI <span style="color:red">*</span></label>
            <input name="dni" id="fec-dni" placeholder="Ej: 30111222" required pattern="[0-9]{7,8}" inputmode="numeric">
            <label>Teléfono</label>
            <input name="telefono" type="tel" id="fec-telefono" placeholder="Ej: 351-1234567">
            <label>Tipo de turno</label>
            <select name="tipo_turno" id="fec-turno">
                <option value="">Sin especificar</option>
                <?php foreach (['24/48','Diurno','Nocturno','Refuerzo mañana','Refuerzo tarde','Fin de semana'] as $opt): ?>
                    <option value="<?= $opt ?>"><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <label>Estado</label>
            <select name="estado" id="fec-estado">
                <option value="activo">Activo</option>
                <option value="pausado">Pausado</option>
                <option value="baja">Baja</option>
            </select>
            <label>Color para el calendario</label>
            <input name="color" type="color" id="fec-color" value="#087f7a">
            <br><br>
            <button type="submit" class="btn">Guardar cambios</button>
            <button type="button" class="btn gray" onclick="cerrarModalEditarCuidadora()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModalEditarCuidadora(id, datos) {
    document.getElementById('modal-editar-cuidadora').style.display = 'flex';
    document.getElementById('fec-id').value       = id || '';
    document.getElementById('fec-nombre').value   = datos ? datos.nombre            : '';
    document.getElementById('fec-apellido').value = datos ? (datos.apellido  || '') : '';
    document.getElementById('fec-email').value    = datos ? datos.email             : '';
    document.getElementById('fec-rol').value      = datos ? datos.rol               : 'cuidadora';
    document.getElementById('fec-dni').value      = datos ? (datos.dni       || '') : '';
    document.getElementById('fec-telefono').value = datos ? (datos.telefono  || '') : '';
    document.getElementById('fec-turno').value    = datos ? (datos.tipo_turno|| '') : '';
    document.getElementById('fec-estado').value   = datos ? datos.estado            : 'activo';
    document.getElementById('fec-color').value    = datos ? datos.color             : '#087f7a';
    document.getElementById('modal-editar-cuid-titulo').textContent = 'Modificar — ' + (datos ? datos.nombre : '');
}
function cerrarModalEditarCuidadora() {
    document.getElementById('modal-editar-cuidadora').style.display = 'none';
}
window.onclick = function(e) {
    const m = document.getElementById('modal-editar-cuidadora');
    if (e.target === m) cerrarModalEditarCuidadora();
}
</script>

<?php include '../config/footer.php'; ?>
