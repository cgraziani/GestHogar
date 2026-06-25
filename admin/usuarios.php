<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';
$yo = uid();

if(isset($_GET['accion']) && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }
    $accion = $_GET['accion'];
    $pdo->prepare('UPDATE usuarios SET estado=? WHERE id_usuario=?')
        ->execute([$accion, $id]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('usuarios',?,?,?)")
        ->execute([$id, 'estado_' . $accion, $_SESSION['usuario']['email']]);
    $labels = ['activo' => 'activado', 'pausado' => 'pausado', 'baja' => 'dado de baja'];
    $label  = $labels[$accion] ?? 'actualizado';
    header('Location: usuarios.php?toast=Usuario+' . $label . '+correctamente&tipo=ok'); exit;
}

if(isset($_GET['eliminar'])){
    $id = (int)$_GET['eliminar'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }
    $pdo->prepare('UPDATE usuarios SET eliminado=1 WHERE id_usuario=?')
        ->execute([$id]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('usuarios',?,'eliminado',?)")
        ->execute([$id, $_SESSION['usuario']['email']]);
    header('Location: usuarios.php'); exit;
}


if(isset($_GET['eliminar_baja'])){
    $id = (int)$_GET['eliminar_baja'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }
    try{
        $pdo->prepare('DELETE FROM cuidadoras WHERE id_usuario=?')->execute([$id]);
        $pdo->prepare('DELETE FROM usuarios WHERE id_usuario=?')->execute([$id]);
    }catch(Exception $e){
        try{ $pdo->prepare("UPDATE cuidadoras SET eliminado=1, estado='baja' WHERE id_usuario=?")->execute([$id]); }catch(Exception $e2){}
        $pdo->prepare("UPDATE usuarios SET eliminado=1, estado='baja' WHERE id_usuario=?")->execute([$id]);
    }
    $pdo->prepare("DELETE FROM papelera WHERE tabla='usuarios' AND registro_id=?")->execute([$id]);
    header('Location: usuarios.php?ver_bajas=1&toast=Usuario+eliminado+definitivamente&tipo=warn'); exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = (int)($_POST['id_usuario'] ?? 0);

    if($id === 0){
        if(empty($_POST['password'])){
            header('Location: usuarios.php?error=pass_required'); exit;
        }
        $hash  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $color = $_POST['color'] ?: '#087f7a';
        $pdo->prepare('INSERT INTO usuarios(nombre,email,password,rol,estado,color) VALUES(?,?,?,?,?,?)')
            ->execute([$_POST['nombre'], $_POST['email'], $hash, $_POST['rol'], $_POST['estado'], $color]);
        $nuevo_id = $pdo->lastInsertId();

        if(in_array($_POST['rol'], ['cuidadora', 'asistente'])){
            $partes   = explode(' ', trim($_POST['nombre']), 2);
            $nombre   = $partes[0];
            $apellido = $partes[1] ?? '';
            $pdo->prepare('INSERT INTO cuidadoras(id_usuario,nombre,apellido,estado) VALUES(?,?,?,?)')
                ->execute([$nuevo_id, $nombre, $apellido, $_POST['estado']]);
        }

        header('Location: usuarios.php?toast=Usuario+creado+correctamente&tipo=ok'); exit;
    }

    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }

    $color = $_POST['color'] ?: '#087f7a';
    $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,rol=?,estado=?,color=? WHERE id_usuario=?')
        ->execute([$_POST['nombre'], $_POST['email'], $_POST['rol'], $_POST['estado'], $color, $id]);
    header('Location: usuarios.php?toast=Usuario+modificado+correctamente&tipo=ok'); exit;
}

include '../config/menu.php';

if(isset($_GET['ver'])){
    $u = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
    $u->execute([$_GET['ver']]); $u = $u->fetch();

    $turnos = $pdo->prepare('SELECT * FROM turnos_programados WHERE id_cuidadora_usuario=? AND eliminado=0 ORDER BY fecha,hora_inicio LIMIT 20');
    $turnos->execute([$u['id_usuario']]); $turnos = $turnos->fetchAll();

    $es_yo = ($u['id_usuario'] === $yo);
?>

<h1>Detalle de usuario</h1>

<div class="card">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">
        <span style="display:inline-block;width:44px;height:44px;background:<?=$u['color']?>;border-radius:50%"></span>
        <div>
            <h2 style="margin:0"><?=htmlspecialchars($u['nombre'])?></h2>
            <span class="pill estado-<?=$u['estado']?>"><?=$u['estado']?></span>
            <span class="badge"><?=$u['rol']?></span>
        </div>
    </div>

    <table class="no-datatable">
        <tr>
            <th>Email</th>
            <th>Rol</th>
            <th>Color calendario</th>
            <th>Creado</th>
        </tr>
        <tr>
            <td><?=htmlspecialchars($u['email'])?></td>
            <td><?=$u['rol']?></td>
            <td>
                <span style="display:inline-block;width:35px;height:20px;background:<?=$u['color']?>;border-radius:5px"></span>
                <?=$u['color']?>
            </td>
            <td><?=$u['created_at']?></td>
        </tr>
    </table>
</div>

<?php if($turnos): ?>
<h2>Próximos turnos cargados</h2>
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

<br>
<?php if(!$es_yo): ?>
    <button onclick="abrirModal(<?=$u['id_usuario']?>, <?=htmlspecialchars(json_encode($u))?>)" class="btn">
        Modificar
    </button>
<?php endif; ?>
<a class="btn gray" href="usuarios.php">Volver</a>

<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:10px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main)">
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">×</button>
        <h2 id="modal-titulo">Modificar usuario</h2>
        <form method="post" id="form-usuario">
            <input type="hidden" name="id_usuario" id="f-id">
            <label>Nombre completo</label>
            <input name="nombre" id="f-nombre" placeholder="Nombre y apellido" required>
            <label>Email (se usa para iniciar sesión)</label>
            <input name="email" type="email" id="f-email" placeholder="email@ejemplo.com" required>
            <label>Rol</label>
            <select name="rol" id="f-rol">
                <option value="cuidadora">Cuidadora</option>
                <option value="asistente">Asistente</option>
                <option value="cocinera">Cocinera</option>
                <option value="admin">Administradora</option>
            </select>
            <label>Estado</label>
            <select name="estado" id="f-estado">
                <option value="activo">Activo</option>
                <option value="pausado">Pausado</option>
                <option value="baja">Baja</option>
            </select>
            <label>Color para el calendario de turnos</label>
            <input name="color" type="color" id="f-color" value="#087f7a">
            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>
<script>
function abrirModal(id, datos){
    const modal = document.getElementById('modal-usuario');
    modal.style.display = 'flex';
    document.getElementById('f-id').value     = id || '';
    document.getElementById('f-nombre').value = datos ? datos.nombre : '';
    document.getElementById('f-email').value  = datos ? datos.email  : '';
    document.getElementById('f-rol').value    = datos ? datos.rol    : 'cuidadora';
    document.getElementById('f-estado').value = datos ? datos.estado : 'activo';
    document.getElementById('f-color').value  = datos ? datos.color  : '#087f7a';
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

$rows = $pdo->query("SELECT * FROM usuarios WHERE eliminado=0 AND estado!='baja' ORDER BY rol,nombre")->fetchAll();
$error = $_GET['error'] ?? '';
$ok    = $_GET['ok'] ?? '';
?>

<h1>Usuarios del sistema</h1>

<?php if($ok): ?>
    <div class="alert alert-ok">Usuario <?=$ok==='creado' ? 'creado' : 'modificado'?> correctamente.</div>
<?php endif; ?>
<?php if($error === 'no_self'): ?>
    <div class="alert">No podés modificar o eliminar tu propio usuario.</div>
<?php endif; ?>

<?php if (isset($_GET['ver_bajas'])): ?>
    <?php
    $bajas = $pdo->query("SELECT * FROM usuarios WHERE eliminado=0 AND estado='baja' ORDER BY rol,nombre")->fetchAll();
    ?>
    <h2>Usuarios dados de baja</h2>
    <p><a class="btn gray" href="usuarios.php">← Volver al listado activo</a></p>
    <?php if (!empty($bajas)): ?>
    <table class="datatable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['nombre']) ?></td>
                <td><?= htmlspecialchars($b['email']) ?></td>
                <td><span class="badge"><?= $b['rol'] ?></span></td>
                <td>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_usuario'] ?>"
                       onclick="return confirm('¿Reactivar a <?= htmlspecialchars($b['nombre']) ?>?')">
                       Reactivar
                    </a>
                    <a class="btn red"
                       href="?eliminar_baja=<?= $b['id_usuario'] ?>"
                       onclick="return confirm('¿Eliminar definitivamente a <?= htmlspecialchars($b['nombre']) ?>? Esta acción no se puede deshacer.')">
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

<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn" onclick="abrirModal(0, null)">+ Crear usuario</button>
    <a class="btn gray" href="?ver_bajas=1">Ver usuarios dados de baja</a>
</div>

<table id="tabla-usuarios">
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Color</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r):
        $es_yo = ($r['id_usuario'] === $yo);
    ?>
    <tr>
        <td><?=htmlspecialchars($r['nombre'])?></td>
        <td><?=htmlspecialchars($r['email'])?></td>
        <td><span class="badge"><?=$r['rol']?></span></td>
        <td>
            <span style="display:inline-block;width:35px;height:20px;background:<?=$r['color']?>;border-radius:5px;border:1px solid #ccc"></span>
        </td>
        <td><span class="pill estado-<?=$r['estado']?>"><?=$r['estado']?></span></td>
        <td>
            <a class="btn blue" href="?ver=<?=$r['id_usuario']?>">Ver</a>

            <?php if(!$es_yo): ?>
                <button class="btn" onclick="abrirModal(<?=$r['id_usuario']?>, <?=htmlspecialchars(json_encode($r))?>)">
                    Editar
                </button>

                <?php if($r['estado']==='activo'): ?>
                    <a class="btn orange" href="?accion=pausado&id=<?=$r['id_usuario']?>"
                       onclick="return confirm('¿Pausar usuario?')">Pausar</a>

                <?php elseif($r['estado']==='pausado'): ?>
                    <a class="btn green" href="?accion=activo&id=<?=$r['id_usuario']?>">Activar</a>
                <?php endif; ?>

                <?php if($r['estado']!=='baja'): ?>
                    <a class="btn red" href="?accion=baja&id=<?=$r['id_usuario']?>"
                       onclick="return confirm('¿Dar de baja este usuario? Ya no podrá acceder al sistema.')">
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

<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:white;border-radius:10px;padding:28px;width:100%;max-width:480px;position:relative">

        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">×</button>

        <h2 id="modal-titulo">Crear usuario</h2>

        <form method="post" id="form-usuario">
            <input type="hidden" name="id_usuario" id="f-id">

            <label>Nombre completo</label>
            <input name="nombre" id="f-nombre" placeholder="Nombre y apellido" required>

            <label>Email (se usa para iniciar sesión)</label>
            <input name="email" type="email" id="f-email" placeholder="email@ejemplo.com" required>

            <div id="bloque-password">
                <label>Contraseña</label>
                <input name="password" type="password" id="f-pass" placeholder="Mínimo 6 caracteres">
            </div>

            <label>Rol</label>
            <select name="rol" id="f-rol">
                <option value="cuidadora">Cuidadora</option>
                <option value="asistente">Asistente</option>
                <option value="cocinera">Cocinera</option>
                <option value="admin">Administradora</option>
            </select>

            <label>Estado</label>
            <select name="estado" id="f-estado">
                <option value="activo">Activo</option>
                <option value="pausado">Pausado</option>
                <option value="baja">Baja</option>
            </select>

            <label>Color para el calendario de turnos</label>
            <input name="color" type="color" id="f-color" value="#087f7a">

            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id, datos){
    const modal = document.getElementById('modal-usuario');
    modal.style.display = 'flex';

    document.getElementById('f-id').value     = id || '';
    document.getElementById('f-nombre').value = datos ? datos.nombre : '';
    document.getElementById('f-email').value  = datos ? datos.email  : '';
    document.getElementById('f-rol').value    = datos ? datos.rol    : 'cuidadora';
    document.getElementById('f-estado').value = datos ? datos.estado : 'activo';
    document.getElementById('f-color').value  = datos ? datos.color  : '#087f7a';

    document.getElementById('modal-titulo').textContent = id ? 'Modificar usuario' : 'Crear usuario';

    const bloquePass = document.getElementById('bloque-password');
    const inputPass  = document.getElementById('f-pass');
    if(id){
        bloquePass.style.display = 'none';
        inputPass.required       = false;
        inputPass.value          = '';
    } else {
        bloquePass.style.display = 'block';
        inputPass.required       = true;
        inputPass.value          = '';
    }
}

function cerrarModal(){
    document.getElementById('modal-usuario').style.display = 'none';
}

window.onclick = function(e){
    const m = document.getElementById('modal-usuario');
    if(e.target === m) cerrarModal();
}
</script>

<?php include '../config/footer.php'; ?>
