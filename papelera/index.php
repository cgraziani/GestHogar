<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

$tablasPermitidas = [
    'usuarios' => ['pk' => 'id_usuario', 'nombre' => 'Usuarios'],
    'residentes' => ['pk' => 'id_residente', 'nombre' => 'Residentes'],
    'medicacion_residente' => ['pk' => 'id_plan', 'nombre' => 'Medicación'],
    'cocina' => ['pk' => 'id_cocina', 'nombre' => 'Cocina'],
    'cuidadoras' => ['pk' => 'id_cuidadora', 'nombre' => 'Cuidadoras'],
    'turnos' => ['pk' => 'id_turno', 'nombre' => 'Turnos / Novedades'],
    'tareas_residente' => ['pk' => 'id_tarea', 'nombre' => 'Tareas de cuidado'],
];

if (isset($_GET['restaurar'], $_GET['tabla'])) {
    $tabla = $_GET['tabla'];
    $id = (int)$_GET['restaurar'];

    if (isset($tablasPermitidas[$tabla]) && $id > 0) {
        $pk = $tablasPermitidas[$tabla]['pk'];
        $stmt = $pdo->prepare("UPDATE `$tabla` SET eliminado = 0 WHERE `$pk` = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("INSERT INTO papelera(tabla, registro_id, accion, usuario) VALUES(?, ?, 'restaurado', ?)");
        $stmt->execute([$tabla, $id, $_SESSION['usuario']['email']]);

        header('Location: index.php?ok=restaurado');
        exit;
    }
}

include '../config/menu.php';
$rows = $pdo->query("SELECT * FROM papelera ORDER BY fecha DESC")->fetchAll();
?>
<h1>Papelera de eliminados</h1>
<?php if(isset($_GET['ok'])): ?>
    <div class="alert">Registro restaurado correctamente. Ya vuelve a aparecer en su pantalla correspondiente.</div>
<?php endif; ?>
<table>
    <tr>
        <th>Módulo</th>
        <th>ID Registro</th>
        <th>Acción</th>
        <th>Usuario</th>
        <th>Fecha</th>
        <th>Reintegrar</th>
    </tr>
    <?php foreach($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars($tablasPermitidas[$r['tabla']]['nombre'] ?? $r['tabla']) ?></td>
            <td><?= (int)$r['registro_id'] ?></td>
            <td><?= htmlspecialchars($r['accion']) ?></td>
            <td><?= htmlspecialchars($r['usuario']) ?></td>
            <td><?= htmlspecialchars($r['fecha']) ?></td>
            <td>
                <?php if($r['accion'] === 'eliminado' && isset($tablasPermitidas[$r['tabla']])): ?>
                    <a class="btn blue" onclick="return confirm('¿Querés reintegrar este registro?')" href="?tabla=<?= urlencode($r['tabla']) ?>&restaurar=<?= (int)$r['registro_id'] ?>">Restaurar</a>
                <?php else: ?>
                    <span class="pill estado-activo">Restaurado</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php include '../config/footer.php'; ?>
