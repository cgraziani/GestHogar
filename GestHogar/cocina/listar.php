<?php
// ============================================================
// COCINA — Recetas y gastos
// Cambios v2:
// - Eliminado: eliminado=1 y papelera
// - Corregido: monto NULL para recetas (era 0)
// - Corregido: estado='baja' en lugar de eliminado=1
// - Agregado: created_by en INSERT
// ============================================================
require_once '../config/session.php';
puede_cocina();
require_once '../config/db.php';

// Guardar receta o gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (es_admin() || es_cocinera())) {
    $foto = '';
    if (!empty($_FILES['foto']['name'])) {
        if (!is_dir('../uploads/cocina')) mkdir('../uploads/cocina', 0777, true);
        $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
        $name = 'cocina_' . time() . '_' . rand(100,999) . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/cocina/' . $name);
        $foto = 'uploads/cocina/' . $name;
    }
    // Corregido: monto NULL para recetas, created_by agregado
    $tipo  = $_POST['tipo'];
    $monto = ($tipo === 'gasto' && $_POST['monto'] !== '') ? $_POST['monto'] : null;

    $pdo->prepare('INSERT INTO cocina(id_usuario,tipo,titulo,descripcion,monto,foto,fecha,created_by) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([uid(), $tipo, $_POST['titulo'], $_POST['descripcion'], $monto, $foto, $_POST['fecha'], uid()]);
    header('Location: listar.php?toast=Registro+guardado+correctamente&tipo=ok');
    exit;
}

// Dar de baja (reemplaza eliminado=1 + papelera)
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare("UPDATE cocina SET estado='baja', updated_by=? WHERE id_cocina=?")
        ->execute([uid(), $_GET['eliminar']]);
    header('Location: listar.php?toast=Registro+dado+de+baja&tipo=warn');
    exit;
}

include '../config/menu.php';

// Corregido: sin eliminado=0, filtra por estado activo
$rows = $pdo->query("SELECT c.*, u.nombre usuario FROM cocina c
                     JOIN usuarios u ON c.id_usuario=u.id_usuario
                     WHERE c.estado='activo'
                     ORDER BY c.fecha DESC")->fetchAll();

// Totales para resumen del mes actual
$mes_actual = date('Y-m');
$total_gastos = $pdo->query("SELECT COALESCE(SUM(monto),0) FROM cocina WHERE tipo='gasto' AND estado='activo' AND DATE_FORMAT(fecha,'%Y-%m')='$mes_actual'")->fetchColumn();
$total_recetas = $pdo->query("SELECT COUNT(*) FROM cocina WHERE tipo='receta' AND estado='activo' AND DATE_FORMAT(fecha,'%Y-%m')='$mes_actual'")->fetchColumn();
?>

<h1>Cocina — Recetas y gastos diarios</h1>

<!-- Resumen del mes -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
    <div class="card" style="flex:1;text-align:center;min-width:140px">
        <div class="stat">$<?= number_format($total_gastos, 2, ',', '.') ?></div>
        <b>Gastos del mes</b>
    </div>
    <div class="card" style="flex:1;text-align:center;min-width:140px">
        <div class="stat"><?= $total_recetas ?></div>
        <b>Recetas del mes</b>
    </div>
</div>

<?php if (es_admin() || es_cocinera()): ?>
<div class="card">
    <h2>Cargar receta, menú o gasto diario</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Tipo de registro</label>
        <select name="tipo" id="sel-tipo" onchange="toggleMonto()">
            <option value="receta">Receta / Menú del día</option>
            <option value="gasto">Gasto diario</option>
        </select>

        <label>Título <span style="color:red">*</span></label>
        <input name="titulo" placeholder="Ej: Almuerzo — Pollo al horno con puré" required>

        <label>Descripción / Ingredientes / Detalle</label>
        <textarea name="descripcion" placeholder="Ingredientes, preparación o detalle del gasto" rows="3"></textarea>

        <!-- Monto: solo requerido para gastos -->
        <div id="bloque-monto">
            <label>Monto / Costo <span id="monto-req" style="color:red;display:none">*</span></label>
            <input name="monto" type="number" step="0.01" min="0" placeholder="Ej: 4500.00" id="input-monto">
        </div>

        <label>Fecha <span style="color:red">*</span></label>
        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>

        <label>Foto de la comida o comprobante (opcional)</label>
        <input type="file" name="foto" accept="image/*">

        <br>
        <button class="btn">Guardar</button>
    </form>
</div>
<?php else: ?>
    <div class="card">Solo lectura — no tenés permisos para cargar registros.</div>
<?php endif; ?>

<h2>Registros</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Foto</th><th>Tipo</th><th>Título</th>
            <th>Monto</th><th>Fecha</th><th>Cargó</th>
            <?php if (es_admin()): ?><th>Acciones</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <?php if ($r['foto']): ?>
                    <a href="<?= BASE_URL . '/' . $r['foto'] ?>" target="_blank">
                        <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                             style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                    </a>
                <?php else: ?>
                    <span style="color:#ccc">—</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="pill estado-<?= $r['tipo'] === 'receta' ? 'activo' : 'pendiente' ?>">
                    <?= ucfirst($r['tipo']) ?>
                </span>
            </td>
            <td>
                <b><?= htmlspecialchars($r['titulo']) ?></b>
                <?php if ($r['descripcion']): ?>
                    <br><span class="small"><?= htmlspecialchars($r['descripcion']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?= $r['monto'] !== null
                    ? '$' . number_format($r['monto'], 2, ',', '.')
                    : '<span style="color:#ccc">—</span>' ?>
            </td>
            <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td><?= htmlspecialchars($r['usuario']) ?></td>
            <?php if (es_admin()): ?>
            <td>
                <a class="btn red"
                   href="?eliminar=<?= $r['id_cocina'] ?>"
                   onclick="return confirm('¿Dar de baja este registro?')">
                   Dar de baja
                </a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script>
function toggleMonto() {
    var tipo  = document.getElementById('sel-tipo').value;
    var input = document.getElementById('input-monto');
    var req   = document.getElementById('monto-req');
    input.required = (tipo === 'gasto');
    req.style.display = (tipo === 'gasto') ? 'inline' : 'none';
}
window.addEventListener('DOMContentLoaded', toggleMonto);
</script>

<?php include '../config/footer.php'; ?>
