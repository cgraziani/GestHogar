<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN INICIAL
// Solo el administrador puede acceder a esta página.
// Se guarda el ID del admin logueado en $yo para usarlo
// en todo el archivo y evitar que se modifique a sí mismo.
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';
$yo = uid(); // ID del usuario administrador actualmente logueado

// ============================================================
// SECCIÓN 2: CAMBIAR ESTADO (Pausar / Activar / Dar de baja)
// Llega por GET cuando el admin hace clic en los botones
// de la tabla. Verifica que no sea su propio usuario antes
// de ejecutar el cambio de estado.
// ============================================================
if(isset($_GET['accion']) && isset($_GET['id'])){
    $id = (int)$_GET['id'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }
    $accion = $_GET['accion'];
    $pdo->prepare('UPDATE usuarios SET estado=? WHERE id_usuario=?')
        ->execute([$accion, $id]);
    // Registra el cambio de estado en la papelera como auditoría
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('usuarios',?,?,?)")
        ->execute([$id, 'estado_' . $accion, $_SESSION['usuario']['email']]);
    $labels = ['activo' => 'activado', 'pausado' => 'pausado', 'baja' => 'dado de baja'];
    $label  = $labels[$accion] ?? 'actualizado';
    header('Location: usuarios.php?toast=Usuario+' . $label . '+correctamente&tipo=ok'); exit;
}

// ============================================================
// SECCIÓN 3: BAJA LÓGICA (Papelera)
// No elimina el registro de la base de datos.
// Solo pone eliminado=1 y registra la acción en la tabla
// papelera para auditoría. Bloquea si es el propio admin.
// ============================================================
if(isset($_GET['eliminar'])){
    $id = (int)$_GET['eliminar'];
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }
    $pdo->prepare('UPDATE usuarios SET eliminado=1 WHERE id_usuario=?')
        ->execute([$id]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('usuarios',?,'eliminado',?)")
        ->execute([$id, $_SESSION['usuario']['email']]);
    header('Location: usuarios.php'); exit;
}


// ============================================================
// ELIMINAR DEFINITIVAMENTE DESDE DADOS DE BAJA
// Quita el usuario de la vista de dados de baja. Primero intenta
// borrarlo de la base; si hay historial relacionado, lo deja oculto
// con eliminado=1 para que no vuelva a aparecer ni restaurarse.
// ============================================================
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

// ============================================================
// SECCIÓN 4: CREAR O MODIFICAR USUARIO (POST del modal)
// Al CREAR: requiere contraseña obligatoria.
// Al MODIFICAR: NO se puede cambiar la contraseña desde acá.
// La contraseña solo se define al crear el usuario.
// Para cambiarla se necesita un módulo de perfil separado.
// Bloquea si el admin intenta modificarse a sí mismo.
// ============================================================
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id = (int)($_POST['id_usuario'] ?? 0);

    // --- CREAR nuevo usuario (contraseña obligatoria) ---
    if($id === 0){
        if(empty($_POST['password'])){
            header('Location: usuarios.php?error=pass_required'); exit;
        }
        $hash  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $color = $_POST['color'] ?: '#087f7a';
        $pdo->prepare('INSERT INTO usuarios(nombre,email,password,rol,estado,color) VALUES(?,?,?,?,?,?)')
            ->execute([$_POST['nombre'], $_POST['email'], $hash, $_POST['rol'], $_POST['estado'], $color]);
        $nuevo_id = $pdo->lastInsertId();

        // Si el rol es cuidadora o asistente, crea automáticamente
        // el registro en la tabla cuidadoras para que aparezca
        // en el listado de cuidadoras y pueda ser asignada a turnos.
        // El nombre se divide en nombre/apellido por el primer espacio.
        if(in_array($_POST['rol'], ['cuidadora', 'asistente'])){
            $partes   = explode(' ', trim($_POST['nombre']), 2);
            $nombre   = $partes[0];
            $apellido = $partes[1] ?? '';
            $pdo->prepare('INSERT INTO cuidadoras(id_usuario,nombre,apellido,estado) VALUES(?,?,?,?)')
                ->execute([$nuevo_id, $nombre, $apellido, $_POST['estado']]);
        }

        header('Location: usuarios.php?toast=Usuario+creado+correctamente&tipo=ok'); exit;
    }

    // --- Bloquea si intenta modificarse a sí mismo ---
    if($id === $yo){ header('Location: usuarios.php?error=no_self'); exit; }

    // --- MODIFICAR: sin contraseña (no se toca) ---
    $color = $_POST['color'] ?: '#087f7a';
    $pdo->prepare('UPDATE usuarios SET nombre=?,email=?,rol=?,estado=?,color=? WHERE id_usuario=?')
        ->execute([$_POST['nombre'], $_POST['email'], $_POST['rol'], $_POST['estado'], $color, $id]);
    header('Location: usuarios.php?toast=Usuario+modificado+correctamente&tipo=ok'); exit;
}

// ============================================================
// SECCIÓN 5: MENÚ DE NAVEGACIÓN
// Se incluye acá para que aparezca en todas las vistas
// de esta página (detalle y listado).
// ============================================================
include '../config/menu.php';

// ============================================================
// SECCIÓN 6: VISTA DE DETALLE DE UN USUARIO (?ver=ID)
// Muestra los datos completos del usuario y sus turnos.
// Si es el propio admin logueado, no muestra botón Modificar.
// La tabla de datos usa no-datatable para que DataTables
// no la convierta (es de un solo registro).
// ============================================================
if(isset($_GET['ver'])){
    $u = $pdo->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
    $u->execute([$_GET['ver']]); $u = $u->fetch();

    // Trae los turnos programados del usuario
    $turnos = $pdo->prepare('SELECT * FROM turnos_programados WHERE id_cuidadora_usuario=? AND eliminado=0 ORDER BY fecha,hora_inicio LIMIT 20');
    $turnos->execute([$u['id_usuario']]); $turnos = $turnos->fetchAll();

    // Verifica si el usuario que se está viendo es el admin logueado
    $es_yo = ($u['id_usuario'] === $yo);
?>

<h1>Detalle de usuario</h1>

<!-- Encabezado con círculo de color, nombre, estado y rol -->
<div class="card">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px">
        <!-- Círculo del color asignado al usuario para el calendario -->
        <span style="display:inline-block;width:44px;height:44px;background:<?=$u['color']?>;border-radius:50%"></span>
        <div>
            <h2 style="margin:0"><?=htmlspecialchars($u['nombre'])?></h2>
            <span class="pill estado-<?=$u['estado']?>"><?=$u['estado']?></span>
            <span class="badge"><?=$u['rol']?></span>
        </div>
    </div>

    <!-- Tabla horizontal con datos secundarios. no-datatable evita que DataTables la procese -->
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

<!-- Turnos del usuario (solo si tiene cargados) -->
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
<!-- Botón Modificar: solo aparece si NO es el propio admin logueado -->
<?php if(!$es_yo): ?>
    <button onclick="abrirModal(<?=$u['id_usuario']?>, <?=htmlspecialchars(json_encode($u))?>)" class="btn">
        Modificar
    </button>
<?php else: ?>
    <span class="small" style="color:#888">No podés modificarte a vos misma desde acá.</span>
<?php endif; ?>
<a class="btn gray" href="usuarios.php">Volver</a>

<!-- ============================================================
     MODAL DE EDICIÓN — incluido en la vista de detalle
     Es necesario tenerlo acá porque el footer cierra la página
     con exit y el modal del listado no llegaría a cargarse.
     ============================================================ -->
<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:10px;padding:28px;width:100%;max-width:480px;position:relative;color:var(--text-main)">
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">✕</button>
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

// ============================================================
// SECCIÓN 7: LISTADO PRINCIPAL DE USUARIOS
// Trae usuarios NO eliminados y NO dados de baja.
// Los usuarios de baja solo aparecen en la papelera
// (auditoría), no en el listado activo.
// ============================================================
$rows = $pdo->query("SELECT * FROM usuarios WHERE eliminado=0 AND estado!='baja' ORDER BY rol,nombre")->fetchAll();
$error = $_GET['error'] ?? '';
$ok    = $_GET['ok'] ?? '';
?>

<h1>Usuarios del sistema</h1>

<!-- Mensajes de éxito o error -->
<?php if($ok): ?>
    <div class="alert alert-ok">✅ Usuario <?=$ok==='creado' ? 'creado' : 'modificado'?> correctamente.</div>
<?php endif; ?>
<?php if($error === 'no_self'): ?>
    <div class="alert">⚠️ No podés modificar o eliminar tu propio usuario.</div>
<?php endif; ?>

<!-- ============================================================
     Vista de usuarios dados de baja (?ver_bajas=1)
     Muestra solo los usuarios con estado="baja" con botón
     para reactivarlos. Accesible desde el botón de arriba.
     ============================================================ -->
<?php if (isset($_GET['ver_bajas'])): ?>
    <?php
    $bajas = $pdo->query("SELECT * FROM usuarios WHERE eliminado=0 AND estado='baja' ORDER BY rol,nombre")->fetchAll();
    ?>
    <h2>Usuarios dados de baja</h2>
    <p><a class="btn gray" href="usuarios.php">← Volver al listado activo</a></p>
    <?php if (empty($bajas)): ?>
        <div class="card"><p>No hay usuarios dados de baja.</p></div>
    <?php else: ?>
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

<!-- Botones de acción principales -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn" onclick="abrirModal(0, null)">+ Crear usuario</button>
    <a class="btn gray" href="?ver_bajas=1">Ver usuarios dados de baja</a>
</div>

<!-- ============================================================
     SECCIÓN 8: TABLA DE USUARIOS CON DATATABLES
     Cada fila muestra los botones según el estado actual
     del usuario. El admin logueado ve "Tu cuenta" sin botones.
     DataTables se inicializa automáticamente desde footer.php
     porque la tabla tiene más de 2 filas.
     ============================================================ -->
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
        // Verifica si esta fila es el propio admin logueado
        $es_yo = ($r['id_usuario'] === $yo);
    ?>
    <tr>
        <td><?=htmlspecialchars($r['nombre'])?></td>
        <td><?=htmlspecialchars($r['email'])?></td>
        <td><span class="badge"><?=$r['rol']?></span></td>
        <!-- Círculo de color del usuario para el calendario -->
        <td>
            <span style="display:inline-block;width:35px;height:20px;background:<?=$r['color']?>;border-radius:5px;border:1px solid #ccc"></span>
        </td>
        <td><span class="pill estado-<?=$r['estado']?>"><?=$r['estado']?></span></td>
        <td>
            <!-- Ver detalle: siempre disponible -->
            <a class="btn blue" href="?ver=<?=$r['id_usuario']?>">Ver</a>

            <?php if(!$es_yo): ?>
                <!-- Editar: abre el modal con los datos del usuario en JSON -->
                <button class="btn" onclick="abrirModal(<?=$r['id_usuario']?>, <?=htmlspecialchars(json_encode($r))?>)">
                    Editar
                </button>

                <!-- Pausar: solo si está activo -->
                <?php if($r['estado']==='activo'): ?>
                    <a class="btn orange" href="?accion=pausado&id=<?=$r['id_usuario']?>"
                       onclick="return confirm('¿Pausar usuario?')">Pausar</a>

                <!-- Activar: solo si está pausado -->
                <?php elseif($r['estado']==='pausado'): ?>
                    <a class="btn green" href="?accion=activo&id=<?=$r['id_usuario']?>">Activar</a>
                <?php endif; ?>

                <!-- Dar de baja: si no está ya de baja -->
                <?php if($r['estado']!=='baja'): ?>
                    <a class="btn red" href="?accion=baja&id=<?=$r['id_usuario']?>"
                       onclick="return confirm('¿Dar de baja este usuario? Ya no podrá acceder al sistema.')">
                       Dar de baja
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <!-- Si es el propio admin logueado no muestra botones -->
                <span class="small" style="color:#aaa">Tu cuenta</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- ============================================================
     SECCIÓN 9: MODAL DE CREAR / EDITAR USUARIO
     Está oculto por defecto (display:none).
     Se abre con abrirModal() y se cierra con cerrarModal().
     El campo oculto id_usuario determina si es creación (vacío)
     o edición (con ID). Al hacer clic fuera del recuadro
     blanco también se cierra.
     ============================================================ -->
<div id="modal-usuario" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:white;border-radius:10px;padding:28px;width:100%;max-width:480px;position:relative">

        <!-- Botón X para cerrar el modal -->
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">✕</button>

        <h2 id="modal-titulo">Crear usuario</h2>

        <form method="post" id="form-usuario">
            <!-- Campo oculto: vacío = crear, con valor = editar -->
            <input type="hidden" name="id_usuario" id="f-id">

            <label>Nombre completo</label>
            <input name="nombre" id="f-nombre" placeholder="Nombre y apellido" required>

            <label>Email (se usa para iniciar sesión)</label>
            <input name="email" type="email" id="f-email" placeholder="email@ejemplo.com" required>

            <!-- Contraseña: SOLO visible al crear un usuario nuevo.
                 Al editar se oculta porque la contraseña no se puede
                 cambiar desde este módulo. -->
            <div id="bloque-password">
                <label>Contraseña</label>
                <input name="password" type="password" id="f-pass" placeholder="Mínimo 6 caracteres">
                <small style="color:#888">La contraseña solo se define al crear el usuario.</small>
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

<!-- ============================================================
     SECCIÓN 10: JAVASCRIPT DEL MODAL
     abrirModal(id, datos): recibe el ID y los datos del usuario
     en formato JSON (pasados desde PHP con json_encode).
     Pre-carga todos los campos, ajusta el título y controla
     si la contraseña es requerida o no.
     cerrarModal(): oculta el modal.
     window.onclick: cierra el modal si se hace clic fuera.
     ============================================================ -->
<script>
function abrirModal(id, datos){
    const modal = document.getElementById('modal-usuario');
    modal.style.display = 'flex';

    // Pre-carga los campos con los datos del usuario
    document.getElementById('f-id').value     = id || '';
    document.getElementById('f-nombre').value = datos ? datos.nombre : '';
    document.getElementById('f-email').value  = datos ? datos.email  : '';
    document.getElementById('f-rol').value    = datos ? datos.rol    : 'cuidadora';
    document.getElementById('f-estado').value = datos ? datos.estado : 'activo';
    document.getElementById('f-color').value  = datos ? datos.color  : '#087f7a';

    // Ajusta el título según si es crear o editar
    document.getElementById('modal-titulo').textContent = id ? 'Modificar usuario' : 'Crear usuario';

    // Muestra el campo contraseña solo al crear, lo oculta al editar
    // La contraseña no se puede cambiar desde este módulo
    const bloquePass = document.getElementById('bloque-password');
    const inputPass  = document.getElementById('f-pass');
    if(id){
        // Editando: oculta contraseña y la hace no requerida
        bloquePass.style.display = 'none';
        inputPass.required       = false;
        inputPass.value          = '';
    } else {
        // Creando: muestra contraseña y la hace requerida
        bloquePass.style.display = 'block';
        inputPass.required       = true;
        inputPass.value          = '';
    }
}

function cerrarModal(){
    document.getElementById('modal-usuario').style.display = 'none';
}

// Cierra el modal al hacer clic en el fondo oscuro
window.onclick = function(e){
    const m = document.getElementById('modal-usuario');
    if(e.target === m) cerrarModal();
}
</script>

<?php include '../config/footer.php'; ?>
