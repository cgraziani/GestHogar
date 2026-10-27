<?php
// ============================================================
// HABITACIONES
// Cambios v2:
// - Eliminado: columna 'cama' del formulario (ahora tabla camas)
// - Eliminado: eliminado=1 y papelera
// - Agregado: gestión de camas asociadas
// - Agregado: updated_by en cambios de estado
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

// Cambiar estado de habitación
if (isset($_GET['estado']) && isset($_GET['id'])) {
    $estado = $_GET['estado'];
    if (in_array($estado, ['disponible', 'mantenimiento', 'ocupada'])) {
        $pdo->prepare('UPDATE habitaciones SET estado=?, updated_by=? WHERE id_habitacion=?')
            ->execute([$estado, uid(), $_GET['id']]);
        $labels = ['disponible' => 'disponible', 'mantenimiento' => 'en+mantenimiento', 'ocupada' => 'ocupada'];
        header('Location: listar.php?toast=Habitaci%C3%B3n+marcada+como+' . $labels[$estado] . '&tipo=ok');
        exit;
    }
}

// Dar de baja habitación (reemplaza eliminado=1)
if (isset($_GET['eliminar'])) {
    $pdo->prepare("UPDATE habitaciones SET estado='mantenimiento', updated_by=? WHERE id_habitacion=?")
        ->execute([uid(), $_GET['eliminar']]);
    header('Location: listar.php?toast=Habitaci%C3%B3n+puesta+en+mantenimiento&tipo=warn');
    exit;
}

// Crear o modificar habitación
// Corregido: sin columna 'cama', agrega created_by/updated_by
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_hab'])) {
    if ($_POST['id']) {
        $pdo->prepare('UPDATE habitaciones SET numero=?,sector=?,piso=?,capacidad=?,estado=?,observaciones=?,updated_by=? WHERE id_habitacion=?')
            ->execute([$_POST['numero'], $_POST['sector'], $_POST['piso'], (int)$_POST['capacidad'], $_POST['estado'], $_POST['observaciones'], uid(), $_POST['id']]);
        header('Location: listar.php?toast=Habitaci%C3%B3n+modificada+correctamente&tipo=ok');
    } else {
        $pdo->prepare('INSERT INTO habitaciones(numero,sector,piso,capacidad,estado,observaciones,created_by) VALUES(?,?,?,?,?,?,?)')
            ->execute([$_POST['numero'], $_POST['sector'], $_POST['piso'], (int)$_POST['capacidad'], $_POST['estado'], $_POST['observaciones'], uid()]);
        $id_nueva = $pdo->lastInsertId();
        // Crear camas según la capacidad indicada
        $capacidad = max(1, (int)$_POST['capacidad']);
        for ($i = 1; $i <= $capacidad; $i++) {
            $letra = chr(64 + $i); // A, B, C...
            $pdo->prepare('INSERT INTO camas(id_habitacion, identificador, estado, created_by) VALUES(?,?,?,?)')
                ->execute([$id_nueva, 'Cama ' . $letra, 'disponible', uid()]);
        }
        header('Location: listar.php?toast=Habitaci%C3%B3n+y+camas+creadas+correctamente&tipo=ok');
    }
    exit;
}

// Agregar cama individual a una habitación existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cama'])) {
    $pdo->prepare('INSERT INTO camas(id_habitacion, identificador, estado, created_by) VALUES(?,?,?,?)')
        ->execute([$_POST['id_habitacion'], $_POST['identificador'], 'disponible', uid()]);
    header('Location: listar.php?toast=Cama+agregada+correctamente&tipo=ok');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$h  = ['numero' => '', 'sector' => '', 'piso' => '', 'capacidad' => 1, 'estado' => 'disponible', 'observaciones' => ''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM habitaciones WHERE id_habitacion=?');
    $st->execute([$id]);
    $h = $st->fetch();
}

include '../config/menu.php';

// Corregido: sin eliminado=0, JOIN con camas (nueva tabla)
// Residentes: solo activos y pausados (no dados de baja), sin duplicar por camas
$rows = $pdo->query("SELECT h.*,
                            GROUP_CONCAT(DISTINCT r.nombre,' ',r.apellido SEPARATOR ', ') residentes,
                            COUNT(DISTINCT c.id_cama) total_camas,
                            SUM(c.estado='disponible') camas_disponibles
                     FROM habitaciones h
                     LEFT JOIN residentes r ON r.id_habitacion=h.id_habitacion AND r.estado IN ('activo','pausado')
                     LEFT JOIN camas c ON c.id_habitacion=h.id_habitacion
                     GROUP BY h.id_habitacion
                     ORDER BY h.numero")->fetchAll();

// Camas por habitación para el panel de detalle
$camas_por_hab = [];
$camas_all = $pdo->query("SELECT c.*, r.nombre res_nombre, r.apellido res_apellido, r.estado res_estado
                           FROM camas c
                           LEFT JOIN residentes r ON r.id_cama=c.id_cama AND r.estado IN ('activo','pausado')
                           ORDER BY c.id_habitacion, c.identificador")->fetchAll();
foreach ($camas_all as $c) {
    $camas_por_hab[$c['id_habitacion']][] = $c;
}
?>

<h1>Habitaciones</h1>

<div style="margin-bottom:16px">
    <button class="btn" onclick="abrirModal(0, null)">+ Crear habitación</button>
</div>

<table class="datatable">
    <thead>
        <tr>
            <th>Habitación</th>
            <th>Sector / Piso</th>
            <th>Camas</th>
            <th>Residentes</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><b><?= htmlspecialchars($r['numero']) ?></b></td>
            <td><?= htmlspecialchars($r['sector']) ?> · <?= htmlspecialchars($r['piso']) ?></td>
            <td>
                <?= $r['camas_disponibles'] ?> disponible(s) / <?= $r['total_camas'] ?> total
                <!-- Detalle de camas expandible -->
                <?php if (!empty($camas_por_hab[$r['id_habitacion']])): ?>
                <br>
                <?php foreach ($camas_por_hab[$r['id_habitacion']] as $c): ?>
                    <span class="badge badge-cama-<?= $c['estado'] === 'disponible' ? 'disponible' : ($c['estado'] === 'mantenimiento' ? 'mantenimiento' : 'ocupada') ?>">
                        <?= htmlspecialchars($c['identificador']) ?>
                        <?php if ($c['res_nombre']): ?>
                            — <?= htmlspecialchars($c['res_nombre']) ?>
                            <?php if ($c['res_estado'] === 'pausado'): ?>
                                <em style="color:#e67e22">(serv. suspendido)</em>
                            <?php endif; ?>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['residentes'] ?: 'Sin residente') ?></td>
            <td>
                <span class="pill estado-<?= $r['estado'] === 'disponible' ? 'activo' : ($r['estado'] === 'mantenimiento' ? 'pausado' : 'baja') ?>">
                    <?= ucfirst($r['estado']) ?>
                </span>
            </td>
            <td><span class="small"><?= htmlspecialchars($r['observaciones']) ?></span></td>
            <td>
                <button class="btn" onclick="abrirModal(<?= $r['id_habitacion'] ?>, <?= htmlspecialchars(json_encode($r)) ?>)">
                    Modificar
                </button>
                <?php if ($r['estado'] === 'disponible'): ?>
                    <a class="btn orange"
                       href="?estado=mantenimiento&id=<?= $r['id_habitacion'] ?>"
                       onclick="return confirm('¿Marcar la habitación <?= htmlspecialchars($r['numero']) ?> como en mantenimiento?')">
                       Mantenimiento
                    </a>
                <?php elseif ($r['estado'] === 'mantenimiento'): ?>
                    <a class="btn green" href="?estado=disponible&id=<?= $r['id_habitacion'] ?>">
                       Marcar disponible
                    </a>
                <?php endif; ?>
                <!-- Agregar cama -->
                <button class="btn gray" onclick="abrirModalCama(<?= $r['id_habitacion'] ?>, '<?= htmlspecialchars($r['numero']) ?>')">
                    + Cama
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- MODAL: Crear / Modificar habitación -->
<!-- Corregido: sin campo 'cama', agrega campo 'capacidad' -->
<div id="modal-habitacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;
                width:100%;max-width:480px;position:relative;color:var(--text-main)">
        <button onclick="cerrarModal()"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">✕</button>
        <h2 id="modal-titulo" style="margin-top:0">Crear habitación</h2>
        <form method="post" id="form-habitacion">
            <input type="hidden" name="accion_hab" value="1">
            <input type="hidden" name="id" id="f-id">
            <label>Número de habitación <span style="color:red">*</span></label>
            <input name="numero" id="f-numero" placeholder="Ej: 101" required>
            <label>Sector</label>
            <input name="sector" id="f-sector" placeholder="Ej: Ala Norte">
            <label>Piso</label>
            <input name="piso" id="f-piso" placeholder="Ej: Planta Baja">
            <label>Capacidad (cantidad de camas)</label>
            <input name="capacidad" id="f-capacidad" type="number" min="1" max="10" value="1">
            <small style="color:#888">Al crear, se generan las camas automáticamente (A, B, C...)</small>
            <label>Estado</label>
            <select name="estado" id="f-estado">
                <option value="disponible">Disponible</option>
                <option value="ocupada">Ocupada</option>
                <option value="mantenimiento">Mantenimiento</option>
            </select>
            <label>Observaciones</label>
            <input name="observaciones" id="f-obs" placeholder="Observaciones opcionales">
            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<!-- MODAL: Agregar cama a habitación existente -->
<div id="modal-cama"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;
                width:100%;max-width:400px;position:relative;color:var(--text-main)">
        <button onclick="document.getElementById('modal-cama').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer">✕</button>
        <h2 id="modal-cama-titulo" style="margin-top:0">Agregar cama</h2>
        <form method="post">
            <input type="hidden" name="accion_cama" value="1">
            <input type="hidden" name="id_habitacion" id="f-cama-hab">
            <label>Identificador de cama</label>
            <input name="identificador" placeholder="Ej: Cama C, Cama 3" required>
            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="document.getElementById('modal-cama').style.display='none'">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id, datos) {
    document.getElementById('modal-habitacion').style.display = 'flex';
    document.getElementById('f-id').value         = id || '';
    document.getElementById('f-numero').value     = datos ? datos.numero       : '';
    document.getElementById('f-sector').value     = datos ? datos.sector       : '';
    document.getElementById('f-piso').value       = datos ? datos.piso         : '';
    document.getElementById('f-capacidad').value  = datos ? datos.capacidad    : 1;
    document.getElementById('f-estado').value     = datos ? datos.estado       : 'disponible';
    document.getElementById('f-obs').value        = datos ? datos.observaciones: '';
    document.getElementById('modal-titulo').textContent = id ? 'Modificar habitación' : 'Crear habitación';
}
function cerrarModal() {
    document.getElementById('modal-habitacion').style.display = 'none';
}
function abrirModalCama(id_hab, numero) {
    document.getElementById('modal-cama').style.display = 'flex';
    document.getElementById('f-cama-hab').value = id_hab;
    document.getElementById('modal-cama-titulo').textContent = 'Agregar cama — Hab. ' + numero;
}
document.getElementById('modal-habitacion').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
document.getElementById('modal-cama').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
<?php if ($id && $h): ?>
abrirModal(<?= $id ?>, <?= json_encode($h) ?>);
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
