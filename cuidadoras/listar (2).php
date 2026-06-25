<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN
// Solo el administrador puede acceder a este módulo.
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

// ============================================================
// SECCIÓN 2: CAMBIAR ESTADO (Activar / Pausar / Dar de baja)
// Actualiza el estado tanto en la tabla cuidadoras como en
// la tabla usuarios (ambas deben estar sincronizadas).
// Redirige con toast para informar el resultado.
// ============================================================
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $estado = $_GET['accion'];
    if (in_array($estado, ['activo', 'pausado', 'baja'])) {
        // Actualiza estado en cuidadoras
        $pdo->prepare('UPDATE cuidadoras SET estado=? WHERE id_cuidadora=?')
            ->execute([$estado, $_GET['id']]);
        // Sincroniza el mismo estado en el usuario vinculado
        $pdo->prepare('UPDATE usuarios u JOIN cuidadoras c ON c.id_usuario=u.id_usuario SET u.estado=? WHERE c.id_cuidadora=?')
            ->execute([$estado, $_GET['id']]);
        // Registra el cambio en papelera como auditoría
        $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('cuidadoras',?,?,?)")
            ->execute([$_GET['id'], 'estado_' . $estado, $_SESSION['usuario']['email']]);
    }
    $labels = ['activo' => 'activada', 'pausado' => 'pausada', 'baja' => 'dada de baja'];
    $label  = $labels[$estado] ?? 'actualizada';
    header('Location: listar.php?toast=Cuidadora+' . $label . '+correctamente&tipo=ok');
    exit;
}


// ============================================================
// ELIMINAR DEFINITIVAMENTE DESDE DADOS DE BAJA
// Quita la cuidadora de esa vista. Primero intenta borrar el registro;
// si hay historial relacionado, lo deja oculto con eliminado=1.
// También sincroniza el usuario vinculado para que no aparezca activo.
// ============================================================
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

// ============================================================
// SECCIÓN 3: SIN PAPELERA PARA CUIDADORAS
// Las cuidadoras no se eliminan del sistema porque sus
// registros históricos (medicaciones, turnos, incidentes)
// deben mantener la referencia a quién los registró.
// La baja lógica (estado="baja") es suficiente para
// impedir el acceso sin perder el historial.
// ============================================================

// ============================================================
// SECCIÓN 4: CARGA DE DATOS
// Trae todas las cuidadoras activas con su usuario vinculado.
// ============================================================
include '../config/menu.php';

$rows = $pdo->query("SELECT c.*,u.email,u.rol,u.color,u.estado estado_usuario
                     FROM cuidadoras c
                     LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario
                     WHERE c.eliminado=0 AND c.estado != 'baja'
                     ORDER BY c.apellido,c.nombre")->fetchAll();
?>

<h1>Cuidadoras / Personal de cuidado</h1>

<!-- ============================================================
     Vista de cuidadoras dadas de baja (?ver_bajas=1)
     Muestra solo las cuidadoras con estado="baja" con botón
     para reincorporarlas. Accesible desde el botón de arriba.
     ============================================================ -->
<?php if (isset($_GET['ver_bajas'])): ?>
    <?php
    $bajas = $pdo->query("SELECT c.*,u.email,u.rol,u.color FROM cuidadoras c
                          LEFT JOIN usuarios u ON c.id_usuario=u.id_usuario
                          WHERE c.eliminado=0 AND c.estado='baja'
                          ORDER BY c.apellido,c.nombre")->fetchAll();
    ?>
    <h2>Cuidadoras dadas de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (empty($bajas)): ?>
        <div class="card"><p>No hay cuidadoras dadas de baja.</p></div>
    <?php else: ?>
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

<!-- Botones de acción principales -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <a class="btn" href="crear.php">+ Crear cuidadora</a>
    <a class="btn gray" href="?ver_bajas=1">Ver cuidadoras dadas de baja</a>
</div>

<!-- ============================================================
     SECCIÓN 5: TABLA DE CUIDADORAS CON DATATABLES
     Botones con política unificada según estado actual:
     - Activo   → solo puede Pausar o Dar de baja
     - Pausado  → solo puede Activar o Dar de baja
     - Baja     → solo puede Activar (reincorporar)
     El botón Papelera aparece siempre como última opción
     destructiva, con confirmación obligatoria.
     ============================================================ -->
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
        <!-- Círculo de color asignado para el calendario de turnos -->
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
            <!-- Modificar: siempre disponible -->
            <a class="btn blue" href="crear.php?id=<?= $r['id_cuidadora'] ?>">Modificar</a>

            <!-- Botones de estado: solo muestra los que tienen sentido según estado actual -->
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

            <!-- Sin botón papelera: las cuidadoras no se eliminan,
                 solo se dan de baja para preservar el historial. -->
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
