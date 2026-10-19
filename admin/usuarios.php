<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';
$yo = uid();

function validar_identidad(PDO $pdo, array $post, $id_excluir = null) {
    $apellido = trim($post['apellido'] ?? '');
    $dni      = trim($post['dni'] ?? '');

    if ($apellido === '') return 'El apellido es obligatorio.';
    if (mb_strlen($apellido) < 2) return 'El apellido tiene que tener al menos 2 letras.';

    $roles = ['admin','administrador','administradora','cuidadora','cuidador',
              'cocinera','cocinero','asistente','usuario','test','prueba'];
    if (in_array(mb_strtolower($apellido), $roles, true)) {
        return 'El apellido no puede ser el rol ni un texto de prueba. Poné el apellido real.';
    }
    if (preg_match('/\d/', $apellido)) return 'El apellido no puede tener números.';

    if ($dni === '') return 'El DNI es obligatorio.';
    if (!preg_match('/^[0-9]{7,8}$/', $dni)) {
        return 'El DNI tiene que ser de 7 u 8 dígitos, sin puntos ni letras.';
    }

    $sql = 'SELECT nombre, apellido FROM usuarios WHERE dni = ?';
    $params = [$dni];
    if ($id_excluir !== null) { $sql .= ' AND id_usuario != ?'; $params[] = $id_excluir; }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ($otro = $st->fetch()) {
        return 'El DNI ' . htmlspecialchars($dni) . ' ya está cargado en '
             . htmlspecialchars(trim($otro['nombre'] . ' ' . $otro['apellido'])) . '.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_pass'])) {
    $pass_actual = $_POST['pass_actual'] ?? '';
    $pass_nueva  = $_POST['pass_nueva']  ?? '';
    $pass_rep    = $_POST['pass_rep']    ?? '';

    $u = $pdo->prepare('SELECT password FROM usuarios WHERE id_usuario=?');
    $u->execute([$yo]);
    $hash_actual = $u->fetchColumn();

    if (!password_verify($pass_actual, $hash_actual)) {
        $_SESSION['error_pass'] = 'La contraseña actual no es correcta.';
    } elseif (strlen($pass_nueva) < 6) {
        $_SESSION['error_pass'] = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif ($pass_nueva !== $pass_rep) {
        $_SESSION['error_pass'] = 'Las contraseñas nuevas no coinciden.';
    } else {
        $pdo->prepare('UPDATE usuarios SET password=? WHERE id_usuario=?')
            ->execute([password_hash($pass_nueva, PASSWORD_DEFAULT), $yo]);
        header('Location: usuarios.php?toast='.urlencode('Contraseña actualizada correctamente').'&tipo=ok'); exit;
    }
    header('Location: usuarios.php?cambiar_pass=1'); exit;
}

if(isset($_GET['accion']) && isset($_GET['id'])){
    $id     = (int)$_GET['id'];
    $accion = $_GET['accion'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }

    $actual = $pdo->prepare('SELECT estado FROM usuarios WHERE id_usuario=?');
    $actual->execute([$id]);
    $estado_anterior = $actual->fetchColumn();

    $pdo->prepare('UPDATE usuarios SET estado=?, updated_by=? WHERE id_usuario=?')
        ->execute([$accion, $yo, $id]);

    $motivo    = trim($_GET['motivo'] ?? '') ?: null;
    $fecha_fin = trim($_GET['fecha_fin'] ?? '') ?: null;

    $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por,motivo,fecha_fin) VALUES(?,?,?,?,?,?,?)')
        ->execute(['usuario', $id, $estado_anterior, $accion, $yo, $motivo, $fecha_fin]);

    if (in_array($accion, ['pausado', 'baja'])) {
        $rol_usu = $pdo->prepare('SELECT rol FROM usuarios WHERE id_usuario=?');
        $rol_usu->execute([$id]);
        $rol_val = $rol_usu->fetchColumn();
        if (in_array($rol_val, ['cuidadora', 'asistente'])) {
            $pdo->prepare("UPDATE turnos_programados SET estado='baja', updated_by=? WHERE id_cuidadora_usuario=? AND fecha >= CURDATE() AND estado='activo'")
                ->execute([$yo, $id]);
        }
    }

    $labels = ['activo' => 'activado', 'pausado' => ($accion === 'pausado' && in_array($pdo->query("SELECT rol FROM usuarios WHERE id_usuario=$id")->fetchColumn(), ['cuidadora','asistente']) ? 'puesto+en+licencia' : 'inhabilitado'), 'baja' => 'dado de baja'];
    $label  = $labels[$accion] ?? 'actualizado';
    header('Location: usuarios.php?toast=Usuario+' . $label . '+correctamente&tipo=ok'); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = (int)($_POST['id_usuario'] ?? 0);

    if($id === 0){
        if(empty($_POST['password'])){
            $_SESSION['error_form'] = 'La contraseña es obligatoria al crear un usuario.';
            header('Location: usuarios.php'); exit;
        }
        if(strlen($_POST['password']) < 6){
            $_SESSION['error_form'] = 'La contraseña debe tener al menos 6 caracteres.';
            header('Location: usuarios.php'); exit;
        }
        if(empty(trim($_POST['email']))){
            $_SESSION['error_form'] = 'El email es obligatorio.';
            header('Location: usuarios.php'); exit;
        }
        if($err = validar_identidad($pdo, $_POST)){
            $_SESSION['error_form'] = $err;
            header('Location: usuarios.php'); exit;
        }
        $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email=?');
        $existe->execute([trim($_POST['email'])]);
        if($existe->fetchColumn() > 0){
            $_SESSION['error_form'] = 'Ya existe un usuario con el email ' . htmlspecialchars($_POST['email']) . '. Usá otro email.';
            header('Location: usuarios.php'); exit;
        }

        try {
            $hash  = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $color = $_POST['color'] ?: '#087f7a';
            $rol   = $_POST['rol'];

            $tipo_turno = in_array($rol, ['cuidadora', 'asistente'])
                ? trim($_POST['tipo_turno'] ?? '')
                : null;

            $pdo->prepare('INSERT INTO usuarios(nombre,apellido,email,password,rol,dni,telefono,tipo_turno,estado,color,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    trim($_POST['nombre']),
                    trim($_POST['apellido'] ?? ''),
                    trim($_POST['email']),
                    $hash,
                    $rol,
                    trim($_POST['dni']),
                    trim($_POST['telefono'] ?? '') ?: null,
                    $tipo_turno ?: null,
                    $_POST['estado'],
                    $color,
                    $yo
                ]);

            $nuevo_id = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por) VALUES(?,?,?,?,?)')
                ->execute(['usuario', $nuevo_id, '', $_POST['estado'], $yo]);

            header('Location: usuarios.php?toast=Usuario+creado+correctamente&tipo=ok'); exit;

        } catch(PDOException $e) {
            if($e->getCode() === '23000'){
                $_SESSION['error_form'] = 'Ya existe un usuario con ese email. Verificá los datos e intentá de nuevo.';
            } else {
                $_SESSION['error_form'] = 'Error al guardar el usuario. Verificá los datos e intentá de nuevo.';
            }
            header('Location: usuarios.php'); exit;
        }
    }

    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }

    $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email=? AND id_usuario!=?');
    $existe->execute([trim($_POST['email']), $id]);
    if($existe->fetchColumn() > 0){
        $_SESSION['error_form'] = 'Ya existe otro usuario con el email ' . htmlspecialchars($_POST['email']) . '.';
        header('Location: usuarios.php'); exit;
    }
    if($err = validar_identidad($pdo, $_POST, $id)){
        $_SESSION['error_form'] = $err;
        header('Location: usuarios.php'); exit;
    }

    try {
        $color = $_POST['color'] ?: '#087f7a';
        $rol   = $_POST['rol'];
        $tipo_turno = in_array($rol, ['cuidadora', 'asistente'])
            ? trim($_POST['tipo_turno'] ?? '')
            : null;

        $pdo->prepare('UPDATE usuarios SET nombre=?,apellido=?,email=?,rol=?,dni=?,telefono=?,tipo_turno=?,estado=?,color=?,updated_by=? WHERE id_usuario=?')
            ->execute([
                trim($_POST['nombre']),
                trim($_POST['apellido'] ?? ''),
                trim($_POST['email']),
                $rol,
                trim($_POST['dni']),
                trim($_POST['telefono'] ?? '') ?: null,
                $tipo_turno ?: null,
                $_POST['estado'],
                $color,
                $yo,
                $id
            ]);
        header('Location: usuarios.php?toast=Usuario+modificado+correctamente&tipo=ok'); exit;
    } catch(PDOException $e) {
        $_SESSION['error_form'] = 'Error al modificar el usuario. Verificá los datos e intentá de nuevo.';
        header('Location: usuarios.php'); exit;
    }
}

include '../config/menu.php';

if(isset($_GET['ver'])){
    $u = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
    $u->execute([$_GET['ver']]); $u = $u->fetch();

    $turnos = $pdo->prepare("SELECT * FROM turnos_programados WHERE id_cuidadora_usuario=? AND estado='activo' ORDER BY fecha,hora_inicio LIMIT 20");
    $turnos->execute([$u['id_usuario']]); $turnos = $turnos->fetchAll();

    $historial = $pdo->prepare("SELECT h.*, u.nombre as quien FROM historial_estados h LEFT JOIN usuarios u ON u.id_usuario=h.cambiado_por WHERE h.entidad='usuario' AND h.id_registro=? ORDER BY h.created_at DESC");
    $historial->execute([$u['id_usuario']]); $historial = $historial->fetchAll();

    $es_yo = ($u['id_usuario'] === $yo);
?>

<h1>Detalle de usuario</h1>

<div class="card">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">
        <span style="display:inline-block;width:44px;height:44px;background:<?=$u['color']?>;border-radius:50%"></span>
        <div>
            <h2 style="margin:0"><?=htmlspecialchars($u['nombre'])?> <?=htmlspecialchars($u['apellido'] ?? '')?></h2>
            <span class="pill estado-<?=$u['estado']?>"><?=$u['estado']?></span>
            <span class="badge"><?=$u['rol']?></span>
        </div>
    </div>

    <table class="no-datatable">
        <tr>
            <th>Email</th><th>DNI</th><th>Teléfono</th><th>Tipo turno</th><th>Color</th><th>Creado</th>
        </tr>
        <tr>
            <td><?=htmlspecialchars($u['email'])?></td>
            <td><?=htmlspecialchars($u['dni'] ?? '')?></td>
            <td><?=htmlspecialchars($u['telefono'] ?? '')?></td>
            <td><?=htmlspecialchars($u['tipo_turno'] ?? '')?></td>
            <td>
                <span style="display:inline-block;width:35px;height:20px;background:<?=$u['color']?>;border-radius:5px"></span>
                <?=$u['color']?>
            </td>
            <td><?=$u['created_at']?></td>
        </tr>
    </table>
</div>

<?php if($turnos): ?>
<h2>Próximos turnos</h2>
<table>
    <tr><th>Fecha</th><th>Horario</th><th>Nota</th></tr>
    <?php foreach($turnos as $t): ?>
    <tr>
        <td><?=date('d/m/Y', strtotime($t['fecha']))?></td>
        <td><?=$t['hora_inicio']?> a <?=$t['hora_fin']?></td>
        <td><?=htmlspecialchars($t['nota'])?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if($historial): ?>
<h2>Historial de estados</h2>
<table class="no-datatable">
    <tr><th>Fecha</th><th>Cambio</th><th>Realizado por</th></tr>
    <?php foreach($historial as $h): ?>
    <tr>
        <td><?=date('d/m/Y H:i', strtotime($h['created_at']))?></td>
        <td>
            <span class="pill estado-<?=$h['estado_anterior']?>"><?=$h['estado_anterior'] ?: 'alta'?></span>
            → <span class="pill estado-<?=$h['estado_nuevo']?>"><?=$h['estado_nuevo']?></span>
        </td>
        <td><?=htmlspecialchars($h['quien'] ?? '')?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<br>
<?php if(!$es_yo): ?>
    <button onclick="abrirModal(<?=$u['id_usuario']?>, <?=htmlspecialchars(json_encode($u))?>)" class="btn">Modificar</button>
<?php endif; ?>
<a class="btn gray" href="usuarios.php">Volver</a>

<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:600px;position:relative">
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)"></button>
        <h2 id="modal-titulo" style="margin:0 0 16px">Modificar usuario</h2>
        <form method="post" id="form-usuario">
            <input type="hidden" name="id_usuario" id="f-id">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre *</label>
                    <input name="nombre" id="f-nombre" placeholder="Nombre" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Apellido</label>
                    <input name="apellido" id="f-apellido" placeholder="Apellido" required minlength="2" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Email (para iniciar sesión) *</label>
                <input name="email" type="email" id="f-email" placeholder="email@ejemplo.com" required style="width:100%;box-sizing:border-box">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Rol</label>
                    <select name="rol" id="f-rol" onchange="toggleTurno()" style="width:100%;box-sizing:border-box">
                        <option value="cuidadora">Cuidadora</option>
                        <option value="asistente">Asistente</option>
                        <option value="cocinera">Cocinera</option>
                        <option value="admin">Administradora</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Estado</label>
                    <select name="estado" id="f-estado" style="width:100%;box-sizing:border-box">
                        <option value="activo">Activo</option>
                        <option value="pausado">Inhabilitado</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">DNI</label>
                    <input name="dni" id="f-dni" inputmode="numeric" required pattern="[0-9]{7,8}" title="7 u 8 dígitos, sin puntos" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Teléfono</label>
                    <input name="telefono" id="f-telefono" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div id="bloque-turno">
                    <label style="font-size:13px;font-weight:600">Tipo de turno</label>
                    <input name="tipo_turno" id="f-tipo-turno" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Color en el calendario</label>
                    <input name="color" type="color" id="f-color" value="#087f7a" style="width:100%;height:38px;padding:2px;box-sizing:border-box;border:1px solid #ddd;border-radius:6px;cursor:pointer">
                </div>
            </div>

            <div id="bloque-pass" style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Contraseña <span id="pass-requerida" style="color:red">*</span></label>
                <input name="password" type="password" id="f-pass" placeholder="Mínimo 6 caracteres" style="width:100%;box-sizing:border-box">
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<script>
function toggleTurno(){
    const rol = document.getElementById('f-rol').value;
    document.getElementById('bloque-turno').style.display = (rol==='cuidadora'||rol==='asistente') ? 'block' : 'none';
}
function abrirModal(id, datos){
    document.getElementById('modal-usuario').style.display = 'flex';
    document.getElementById('f-id').value         = id || '';
    document.getElementById('f-nombre').value     = datos ? datos.nombre            : '';
    document.getElementById('f-apellido').value   = datos ? (datos.apellido  || '') : '';
    document.getElementById('f-email').value      = datos ? datos.email             : '';
    document.getElementById('f-rol').value        = datos ? datos.rol               : 'cuidadora';
    document.getElementById('f-estado').value     = datos ? datos.estado            : 'activo';
    document.getElementById('f-color').value      = datos ? datos.color             : '#087f7a';
    document.getElementById('f-dni').value        = datos ? (datos.dni       || '') : '';
    document.getElementById('f-telefono').value   = datos ? (datos.telefono  || '') : '';
    document.getElementById('f-tipo-turno').value = datos ? (datos.tipo_turno|| '') : '';
    document.getElementById('modal-titulo').textContent = id ? 'Modificar usuario' : 'Crear usuario';
    toggleTurno();
}
function cerrarModal(){
    document.getElementById('modal-usuario').style.display = 'none';
}
window.onclick = function(e){
    const m = document.getElementById('modal-usuario');
    if(e.target === m) cerrarModal();
}
</script>

<?php include '../config/footer.php'; exit; }

$rows  = $pdo->query("SELECT * FROM usuarios WHERE estado!='baja' ORDER BY rol,nombre")->fetchAll();
$error = $_GET['error'] ?? '';

$incompletos = [];
foreach ($rows as $u) {
    $falta = [];
    if (trim((string)$u['dni']) === '')      $falta[] = 'DNI';
    if (trim((string)$u['apellido']) === '') $falta[] = 'apellido';
    if ($falta) $incompletos[] = ['u' => $u, 'falta' => $falta];
}
?>

<?php if (!empty($_SESSION['error_pass'])): ?>
<div style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= htmlspecialchars($_SESSION['error_pass']); unset($_SESSION['error_pass']); ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['cambiar_pass'])): ?>
<div class="card" style="max-width:440px;margin-bottom:24px">
    <h2 style="margin-top:0">Cambiar mi contraseña</h2>
    <form method="post">
        <input type="hidden" name="accion_pass" value="1">
        <label style="font-size:13px;font-weight:600;display:block">Contraseña actual <span style="color:red">*</span></label>
        <input type="password" name="pass_actual" required placeholder="Tu contraseña actual" style="margin-bottom:10px">
        <label style="font-size:13px;font-weight:600;display:block">Nueva contraseña <span style="color:red">*</span></label>
        <input type="password" name="pass_nueva" required placeholder="Mínimo 6 caracteres" minlength="6" style="margin-bottom:10px">
        <label style="font-size:13px;font-weight:600;display:block">Repetir nueva contraseña <span style="color:red">*</span></label>
        <input type="password" name="pass_rep" required placeholder="Repetí la nueva contraseña" style="margin-bottom:14px">
        <div style="display:flex;gap:10px">
            <button type="submit" class="btn">Actualizar contraseña</button>
            <a href="usuarios.php" class="btn gray">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
    <h1 style="margin:0">Usuarios del sistema</h1>
    <a href="?cambiar_pass=1" class="btn gray">Cambiar mi contraseña</a>
</div>

<?php if ($incompletos): ?>
<div class="card" style="border-left:4px solid #e6a700">
    <b><?= count($incompletos) ?> usuario<?= count($incompletos) > 1 ? 's' : '' ?>
       cargado<?= count($incompletos) > 1 ? 's' : '' ?> antes de que el DNI fuera obligatorio</b>
    <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($incompletos as $i): ?>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <b><?= h(trim($i['u']['nombre'] . ' ' . $i['u']['apellido'])) ?></b>
            <span class="badge"><?= h($i['u']['rol']) ?></span>
            <span class="small">Falta: <?= h(implode(' y ', $i['falta'])) ?></span>
            <a class="btn" href="#" onclick="abrirModal(<?= (int)$i['u']['id_usuario'] ?>, <?= h(json_encode($i['u'])) ?>); return false;">Completar</a>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if(!empty($_SESSION['error_form'])): ?>
    <div class="alert" style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
    </div>
<?php endif; ?>
<?php if($error === 'no_self'): ?>
    <div class="alert">No podés modificar o eliminar tu propio usuario.</div>
<?php endif; ?>

<?php if(isset($_GET['ver_bajas'])): ?>
    <?php $bajas = $pdo->query("SELECT * FROM usuarios WHERE estado='baja' ORDER BY rol,nombre")->fetchAll(); ?>
    <h2>Usuarios dados de baja</h2>
    <p><a class="btn gray" href="usuarios.php">← Volver al listado activo</a></p>
    <?php if(!empty($bajas)): ?>
    <table class="datatable">
        <thead><tr><th>Nombre y apellido</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach($bajas as $b): ?>
            <tr>
                <td><?=htmlspecialchars(trim($b['nombre'].' '.($b['apellido'] ?? '')))?></td>
                <td><?=htmlspecialchars($b['email'])?></td>
                <td><span class="badge"><?=$b['rol']?></span></td>
                <td>
                    <a class="btn green"
                       href="?accion=activo&id=<?=$b['id_usuario']?>"
                       onclick="return confirm('¿Reactivar a <?=htmlspecialchars($b['nombre'])?>?')">
                       Reactivar
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include '../config/footer.php'; exit; ?>
<?php endif; ?>

<?php
$rows_cuidadoras = array_filter($rows, fn($r) => in_array($r['rol'], ['cuidadora','asistente']));
$rows_otros      = array_filter($rows, fn($r) => !in_array($r['rol'], ['cuidadora','asistente']));
$tab_activa      = $_GET['tab'] ?? 'usuarios';
?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <button class="tab-btn <?= $tab_activa==='usuarios'?'active':'' ?>"
            onclick="mostrarTab('tab-usuarios',this)">
         Todos los usuarios
    </button>
    <button class="tab-btn <?= $tab_activa==='cuidadoras'?'active':'' ?>"
            onclick="mostrarTab('tab-cuidadoras',this)">
Cuidadoras / Personal de cuidado
        <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px">
            <?= count($rows_cuidadoras) ?>
        </span>
    </button>
</div>

<style>
.tab-btn { padding:8px 18px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;transition:.2s }
.tab-btn.active { background:var(--color-primary,#2D9B8A);color:#fff }
.tab-content { display:none }
.tab-content.active { display:block }
</style>

<div id="tab-usuarios" class="tab-content <?= $tab_activa==='usuarios'?'active':'' ?>">
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <button class="btn" onclick="abrirModal(0, null)">+ Crear usuario</button>
        <a class="btn gray" href="?ver_bajas=1">Ver usuarios dados de baja</a>
    </div>

    <table class="datatable">
        <thead>
            <tr>
                <th>Nombre y apellido</th><th>Email</th><th>Rol</th><th>DNI</th><th>Color</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r):
            $es_yo = ($r['id_usuario'] === $yo);
        ?>
        <tr>
            <td><?=htmlspecialchars(trim($r['nombre'].' '.($r['apellido'] ?? '')))?></td>
            <td><?=htmlspecialchars($r['email'])?></td>
            <td><span class="badge"><?=$r['rol']?></span></td>
            <td><?=htmlspecialchars($r['dni'] ?? '')?></td>
            <td><span style="display:inline-block;width:35px;height:20px;background:<?=$r['color']?>;border-radius:5px;border:1px solid #ccc"></span></td>
            <td><span class="pill estado-<?=$r['estado']?>"><?= ['activo'=>'Activo','pausado'=>'Inhabilitado','baja'=>'Baja'][$r['estado']] ?? $r['estado'] ?></span></td>
            <td>
                <a class="btn blue" href="?ver=<?=$r['id_usuario']?>">Ver</a>
                <?php if(!$es_yo): ?>
                    <a class="btn" onclick="abrirModal(<?=$r['id_usuario']?>, <?=htmlspecialchars(json_encode($r))?>)" href="#">Modificar</a>
                    <?php if($r['estado']==='activo'): ?>
                        <button class="btn orange"
                            onclick="abrirModalInhabilitar(<?=$r['id_usuario']?>, '<?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>', '<?= $r['rol'] ?>')">
                            Inhabilitar
                        </button>
                    <?php elseif($r['estado']==='pausado'): ?>
                        <a class="btn green" href="?accion=activo&id=<?=$r['id_usuario']?>"
                           onclick="return confirmarLink(this,'¿Reactivar a <?= htmlspecialchars($r['nombre']) ?>?','Reactivar','green')">
                           Reactivar
                        </a>
                    <?php endif; ?>
                    <?php if($r['estado']!=='baja'): ?>
                        <a class="btn red" href="?accion=baja&id=<?=$r['id_usuario']?>"
                           onclick="return confirmarLink(this,'¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>? No podrá acceder al sistema<?= in_array($r['rol'],['cuidadora','asistente']) ? " y sus turnos futuros serán cancelados." : "." ?>','Dar de baja','red')">
                           Dar de baja
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="small" style="color:#aaa">Tu cuenta</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="tab-cuidadoras" class="tab-content <?= $tab_activa==='cuidadoras'?'active':'' ?>">
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <button class="btn" onclick="abrirModal(0, null)">+ Crear cuidadora</button>
        <a class="btn gray" href="?ver_bajas=1&tab=cuidadoras">Ver personal dado de baja</a>
    </div>

    <?php if (empty($rows_cuidadoras)): ?>
        <div class="card" style="text-align:center;padding:40px;color:var(--text-muted)">
            <b>No hay cuidadoras activas.</b>
        </div>
    <?php else: ?>
    <table class="datatable">
        <thead>
            <tr>
                <th>Color</th><th>Nombre</th><th>Rol</th><th>DNI</th>
                <th>Teléfono</th><th>Tipo turno</th><th>Estado</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($rows_cuidadoras as $r):
            $es_yo = ($r['id_usuario'] === $yo);
        ?>
        <tr>
            <td><span style="display:inline-block;width:18px;height:18px;background:<?=$r['color']?>;border-radius:50%"></span></td>
            <td><b><?=htmlspecialchars(trim($r['nombre'].' '.($r['apellido'] ?? '')))?></b></td>
            <td><span class="badge"><?=$r['rol']?></span></td>
            <td><?=htmlspecialchars($r['dni'] ?? '')?></td>
            <td><?=htmlspecialchars($r['telefono'] ?? '')?></td>
            <td><?=htmlspecialchars($r['tipo_turno'] ?? '')?></td>
            <td><span class="pill estado-<?=$r['estado']?>"><?= ['activo'=>'Activo','pausado'=>'Inhabilitado','baja'=>'Baja'][$r['estado']] ?? $r['estado'] ?></span></td>
            <td>
                <a class="btn blue" href="?ver=<?=$r['id_usuario']?>">Ver</a>
                <a class="btn" onclick="abrirModal(<?=$r['id_usuario']?>, <?=htmlspecialchars(json_encode($r))?>)" href="#">Modificar</a>
                <?php if($r['estado']==='activo'): ?>
                    <button class="btn orange"
                        onclick="abrirModalInhabilitar(<?=$r['id_usuario']?>, '<?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>', '<?= $r['rol'] ?>')">
                        Inhabilitar
                    </button>
                    <a class="btn red" href="?accion=baja&id=<?=$r['id_usuario']?>"
                       onclick="return confirmarLink(this,'¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>? Sus turnos futuros serán cancelados.','Dar de baja','red')">
                       Dar de baja
                    </a>
                <?php elseif($r['estado']==='pausado'): ?>
                    <a class="btn green" href="?accion=activo&id=<?=$r['id_usuario']?>"
                       onclick="return confirmarLink(this,'¿Reactivar a <?= htmlspecialchars($r['nombre']) ?>?','Reactivar','green')">
                       Reactivar
                    </a>
                    <a class="btn red" href="?accion=baja&id=<?=$r['id_usuario']?>"
                       onclick="return confirmarLink(this,'¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>?','Dar de baja','red')">
                       Dar de baja
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function mostrarTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(function(el){ el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(el){ el.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
    setTimeout(function(){ $.fn.dataTable && $.fn.dataTable.tables({visible:true,api:true}).columns.adjust(); }, 10);
}
<?php if ($tab_activa === 'cuidadoras'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelectorAll('.tab-btn')[1];
    if (btn) mostrarTab('tab-cuidadoras', btn);
});
<?php endif; ?>
</script>

<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:620px;position:relative">
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)"></button>
        <h2 id="modal-titulo" style="margin:0 0 16px">Crear usuario</h2>

        <form method="post" id="form-usuario">
            <input type="hidden" name="id_usuario" id="f-id">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre *</label>
                    <input name="nombre" id="f-nombre" placeholder="Nombre" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Apellido</label>
                    <input name="apellido" id="f-apellido" placeholder="Apellido" required minlength="2" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Email (para iniciar sesión) *</label>
                <input name="email" type="email" id="f-email" placeholder="email@ejemplo.com" required style="width:100%;box-sizing:border-box">
            </div>

            <div id="bloque-password" style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Contraseña <span id="pass-req" style="color:red">*</span></label>
                <input name="password" type="password" id="f-pass" placeholder="Mínimo 6 caracteres" style="width:100%;box-sizing:border-box">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Rol</label>
                    <select name="rol" id="f-rol" onchange="toggleTurno()" style="width:100%;box-sizing:border-box">
                        <option value="cuidadora">Cuidadora</option>
                        <option value="asistente">Asistente</option>
                        <option value="cocinera">Cocinera</option>
                        <option value="admin">Administradora</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Estado</label>
                    <select name="estado" id="f-estado" style="width:100%;box-sizing:border-box">
                        <option value="activo">Activo</option>
                        <option value="pausado">Inhabilitado</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">DNI</label>
                    <input name="dni" id="f-dni" inputmode="numeric" required pattern="[0-9]{7,8}" title="7 u 8 dígitos, sin puntos" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Teléfono</label>
                    <input name="telefono" id="f-telefono" style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div id="bloque-turno">
                    <label style="font-size:13px;font-weight:600">Tipo de turno</label>
                    <input name="tipo_turno" id="f-tipo-turno" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Color en el calendario</label>
                    <input name="color" type="color" id="f-color" value="#087f7a" style="width:100%;height:38px;padding:2px;box-sizing:border-box;border:1px solid #ddd;border-radius:6px;cursor:pointer">
                </div>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTurno(){
    const rol = document.getElementById('f-rol').value;
    const bloque = document.getElementById('bloque-turno');
    bloque.style.display = (rol === 'cuidadora' || rol === 'asistente') ? 'block' : 'none';
}

function abrirModal(id, datos){
    const modal = document.getElementById('modal-usuario');
    modal.style.display = 'flex';

    document.getElementById('f-id').value         = id || '';
    document.getElementById('f-nombre').value     = datos ? datos.nombre     : '';
    document.getElementById('f-apellido').value   = datos ? (datos.apellido || '') : '';
    document.getElementById('f-email').value      = datos ? datos.email      : '';
    document.getElementById('f-rol').value        = datos ? datos.rol        : 'cuidadora';
    document.getElementById('f-estado').value     = datos ? datos.estado     : 'activo';
    document.getElementById('f-color').value      = datos ? datos.color      : '#087f7a';
    document.getElementById('f-dni').value        = datos ? (datos.dni || '') : '';
    document.getElementById('f-telefono').value   = datos ? (datos.telefono || '') : '';
    document.getElementById('f-tipo-turno').value = datos ? (datos.tipo_turno || '') : '';

    document.getElementById('modal-titulo').textContent = id ? 'Modificar usuario' : 'Crear usuario';

    const bloquePass = document.getElementById('bloque-password');
    const inputPass  = document.getElementById('f-pass');
    if(id){
        bloquePass.style.display = 'none';
        inputPass.required = false;
        inputPass.value = '';
    } else {
        bloquePass.style.display = 'block';
        inputPass.required = true;
        inputPass.value = '';
    }

    toggleTurno();
}

function cerrarModal(){
    document.getElementById('modal-usuario').style.display = 'none';
}

window.onclick = function(e){
    const m = document.getElementById('modal-usuario');
    if(e.target === m) cerrarModal();
}
</script>

<div id="modal-inhabilitar" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:28px;width:100%;max-width:460px;position:relative">
        <button onclick="cerrarModalInhabilitar()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)"></button>
        <h2 style="margin-top:0">Inhabilitar usuario</h2>
        <p id="inhabilitar-nombre" style="color:var(--text-muted);margin-bottom:8px"></p>
        <p id="inhabilitar-aviso-turno" style="display:none;font-size:13px;background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;border-radius:6px;margin-bottom:16px">
El usuario es <b>cuidadora/asistente</b>. Sus turnos futuros serán cancelados automáticamente.
        </p>
        <form id="form-inhabilitar" method="get" action="usuarios.php">
            <input type="hidden" name="accion" value="pausado">
            <input type="hidden" name="id" id="inhabilitar-id">
            <label>Motivo <span style="color:#aaa">(opcional)</span></label>
            <input name="motivo" id="inhabilitar-motivo">
            <label>Fecha estimada de regreso <span style="color:#aaa">(opcional)</span></label>
            <input type="date" name="fecha_fin" id="inhabilitar-fecha" min="<?= date('Y-m-d') ?>">
            <br><br>
            <button type="submit" class="btn orange">Inhabilitar</button>
            <button type="button" class="btn gray" onclick="cerrarModalInhabilitar()">Cancelar</button>
        </form>
    </div>
</div>
<script>
function abrirModalInhabilitar(id, nombre, rol) {
    document.getElementById('inhabilitar-id').value    = id;
    document.getElementById('inhabilitar-nombre').textContent = 'Usuario: ' + nombre;
    document.getElementById('inhabilitar-motivo').value = '';
    document.getElementById('inhabilitar-fecha').value  = '';
    const aviso = document.getElementById('inhabilitar-aviso-turno');
    aviso.style.display = (rol === 'cuidadora' || rol === 'asistente') ? 'block' : 'none';
    document.getElementById('modal-inhabilitar').style.display = 'flex';
}
function cerrarModalInhabilitar() {
    document.getElementById('modal-inhabilitar').style.display = 'none';
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('modal-inhabilitar');
    if (e.target === m) cerrarModalInhabilitar();
});
</script>

<?php include '../config/footer.php'; ?>

