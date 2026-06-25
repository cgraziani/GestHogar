<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if (isset($_GET['accion']) && isset($_GET['id'])) {
    $estado = $_GET['accion'];
    if (in_array($estado, ['activo', 'pausado', 'baja'])) {
        $pdo->prepare('UPDATE cuidadoras SET estado=? WHERE id_cuidadora=?')
            ->execute([$estado, $_GET['id']]);
        $pdo->prepare('UPDATE usuarios u JOIN cuidadoras c ON c.id_usuario=u.id_usuario SET u.estado=? WHERE c.id_cuidadora=?')
            ->execute([$estado, $_GET['id']]);
        $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('cuidadoras',?,?,?)")
            ->execute([$_GET['id'], 'estado_' . $estado, $_SESSION['usuario']['email']]);
    }
    $labels = ['activo' => 'activada', 'pausado' => 'pausada', 'baja' => 'dada de baja'];
    $label  = $labels[$estado] ?? 'actualizada';
    header('Location: listar.php?toast=Cuidadora+' . $label . '+correctamente&tipo=ok');
    exit;
}


if (isset($_GET['eliminar_baja'])) {
    $id = (int)$_GET['eliminar_baja'];
    if ($id > 0) {
        $st = $pdo->prepare('SELECT id_usuario FROM cuidadoras WHERE id_cuidadora=?');
        $st->execute([$id]);
        $id_usuario = $st->fetchColumn();
        try {
            $pdo->prepare('DELETE FROM cuidadoras WHERE id_cuidadora=?')->execute([$id]);
            if ($id_usuario) {
                try { $pdo->prepare('DELETE FROM usuarios WHERE id_usuario=?')->execute([$id_usuario]); } catch(Exception $e2) {
                    $pdo->prepare("UPDATE usuarios SET eliminado=1, estado='baja' WHERE id_usuario=?")->execute([$id_usuario]);
                }
            }
        } catch(Exception $e) {
            $pdo->prepare("UPDATE cuidadoras SET eliminado=1, estado='baja' WHERE id_cuidadora=?")->execute([$id]);
            if ($id_usuario) {
                $pdo->prepare("UPDATE usuarios SET eliminado=1, estado='baja' WHERE id_usuario=?")->execute([$id_usuario]);
            }
        }
        $pdo->prepare("DELETE FROM papelera WHERE tabla='cuidadoras' AND registro_id=?")->execute([$id]);
        header('Location: listar.php?ver_bajas=1&toast=Cuidadora+eliminada+definitivamente&tipo=warn');
        exit;
    }
}

include '../config/menu.php';

$rows = $pdo->query("SELECT c.*,u.email,u.rol,u.color,u.estado estado_usuario
                     FROM cuidadoras c
                     LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario
                     WHERE c.eliminado=0 AND c.estado != 'baja'
                     ORDER BY c.apellido,c.nombre")->fetchAll();
?>

<h1>Cuidadoras / Personal de cuidado</h1>

<?php if (isset($_GET['ver_bajas'])): ?>
    <?php
    $bajas = $pdo->query("SELECT c.*,u.email,u.rol,u.color FROM cuidadoras c
                          LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario
                          WHERE c.eliminado=0 AND c.estado='baja'
                          ORDER BY c.apellido,c.nombre")->fetchAll();
    ?>
    <h2>Cuidadoras dadas de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (!empty($bajas)): ?>
    <table class="datatable">
        <thead>
            <tr>
                <th>Color</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Email</th>
                <th>Turno</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td>
                    <span style="display:inline-block;width:18px;height:18px;
                                 background:<?= $b['color'] ?: '#087f7a' ?>;
                                 border-radius:50%"></span>
                </td>
                <td><b><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></b></td>
                <td><?= htmlspecialchars($b['dni']) ?></td>
                <td><?= htmlspecialchars($b['email']) ?></td>
                <td><?= htmlspecialchars($b['tipo_turno']) ?></td>
                <td>
                    <a class="btn blue" href="crear.php?id=<?= $b['id_cuidadora'] ?>">Ver datos</a>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_cuidadora'] ?>"
                       onclick="return confirm('¿Reincorporar a <?= htmlspecialchars($b['nombre']) ?>?')">
                       Reincorporar
                    </a>
                    <a class="btn red"
                       href="?eliminar_baja=<?= $b['id_cuidadora'] ?>"
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

<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <a class="btn" href="crear.php">+ Crear cuidadora</a>
    <a class="btn gray" href="?ver_bajas=1">Ver cuidadoras dadas de baja</a>
</div>

<table class="datatable">
    <thead>
        <tr>
            <th>Color</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Teléfono</th>
            <th>Turno</th>
            <th>Email</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td>
            <span class="color-dot"
                  style="--c:<?= $r['color'] ?: '#087f7a' ?>"></span>
        </td>
        <td>
            <b><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></b><br>
            <span class="small">Rol: <?= htmlspecialchars($r['rol'] ?: 'cuidadora') ?></span>
        </td>
        <td><?= htmlspecialchars($r['dni']) ?></td>
        <td><?= htmlspecialchars($r['telefono']) ?></td>
        <td><?= htmlspecialchars($r['tipo_turno']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td>
            <span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span>
        </td>
        <td>
            <a class="btn blue" href="crear.php?id=<?= $r['id_cuidadora'] ?>">Modificar</a>

            <?php if ($r['estado'] === 'activo'): ?>
                <a class="btn orange"
                   href="?accion=pausado&id=<?= $r['id_cuidadora'] ?>"
                   onclick="return confirm('¿Pausar a <?= htmlspecialchars($r['nombre']) ?>? No podrá iniciar sesión mientras esté pausada.')">
                   Pausar
                </a>
                <a class="btn red"
                   href="?accion=baja&id=<?= $r['id_cuidadora'] ?>"
                   onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>?')">
                   Dar de baja
                </a>

            <?php elseif ($r['estado'] === 'pausado'): ?>
                <a class="btn green"
                   href="?accion=activo&id=<?= $r['id_cuidadora'] ?>">
                   Activar
                </a>
                <a class="btn red"
                   href="?accion=baja&id=<?= $r['id_cuidadora'] ?>"
                   onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre']) ?>?')">
                   Dar de baja
                </a>
            <?php endif; ?>

        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
