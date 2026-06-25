<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN
// Solo admin y cocinera pueden acceder.
// ============================================================
require_once '../config/session.php';
puede_cocina();
require_once '../config/db.php';

// ============================================================
// SECCIÓN 2: GUARDAR RECETA O GASTO (POST)
// Sube foto si viene una, guarda el registro y redirige
// con toast.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (es_admin() || es_cocinera())) {
    $foto = '';
    if (!empty($_FILES['foto']['name'])) {
        if (!is_dir('../uploads/cocina')) mkdir('../uploads/cocina', 0777, true);
        $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
        $name = 'cocina_' . time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/cocina/' . $name);
        $foto = 'uploads/cocina/' . $name;
    }
    $pdo->prepare('INSERT INTO cocina(id_usuario,tipo,titulo,descripcion,monto,foto,fecha) VALUES(?,?,?,?,?,?,?)')
        ->execute([uid(), $_POST['tipo'], $_POST['titulo'], $_POST['descripcion'], $_POST['monto'] ?: 0, $foto, $_POST['fecha']]);
    header('Location: listar.php?toast=Registro+guardado+correctamente&tipo=ok');
    exit;
}

// ============================================================
// SECCIÓN 3: ELIMINAR (solo admin, baja lógica)
// ============================================================
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare('UPDATE cocina SET eliminado=1 WHERE id_cocina=?')->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('cocina',?,'eliminado',?)")
        ->execute([$_GET['eliminar'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Registro+enviado+a+la+papelera&tipo=warn');
    exit;
}

include '../config/menu.php';

$rows = $pdo->query("SELECT c.*,u.nombre usuario FROM cocina c
                     JOIN usuarios u ON c.id_usuario=u.id_usuario
                     WHERE c.eliminado=0
                     ORDER BY c.fecha DESC")->fetchAll();
?>

<h1>Cocina — Recetas y gastos diarios</h1>

<!-- ============================================================
     SECCIÓN 4: FORMULARIO CARGAR RECETA O GASTO
     Solo visible para admin y cocinera.
     Incluye validaciones front-end con required y type correcto.
     ============================================================ -->
<?php if (es_admin() || es_cocinera()): ?>
<div class="card">
    <h2>Cargar receta, menú o gasto diario</h2>
    <form method="post" enctype="multipart/form-data">

        <label>Tipo de registro</label>
        <select name="tipo">
            <option value="receta">Receta / Menú del día</option>
            <option value="gasto">Gasto diario</option>
        </select>

        <label>Título <span style="color:red">*</span></label>
        <input name="titulo"
               placeholder="Ej: Almuerzo — Pollo al horno con puré"
               required>

        <label>Descripción / Ingredientes / Detalle</label>
        <textarea name="descripcion"
                  placeholder="Ingredientes, preparación o detalle del gasto"
                  rows="3"></textarea>

        <label>Monto / Costo</label>
        <input name="monto"
               type="number"
               step="0.01"
               min="0"
               placeholder="Ej: 4500.00">

        <label>Fecha <span style="color:red">*</span></label>
        <input type="date"
               name="fecha"
               value="<?= date('Y-m-d') ?>"
               required>

        <label>Foto de la comida o comprobante (opcional)</label>
        <input type="file" name="foto" accept="image/*">

        <br>
        <button class="btn">Guardar</button>
    </form>
</div>
<?php else: ?>
    <div class="card">Solo lectura — no tenés permisos para cargar registros.</div>
<?php endif; ?>

<!-- ============================================================
     SECCIÓN 5: TABLA DE REGISTROS CON DATATABLES
     Muestra fotos en miniatura, tipo con badge de color,
     monto formateado y botón Papelera solo para admin.
     ============================================================ -->
<h2>Registros</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Foto</th>
            <th>Tipo</th>
            <th>Título</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Cargó</th>
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
                <!-- Verde para receta, azul para gasto -->
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
                <?= $r['monto'] > 0
                    ? '$' . number_format($r['monto'], 2, ',', '.')
                    : '<span style="color:#ccc">—</span>' ?>
            </td>
            <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td><?= htmlspecialchars($r['usuario']) ?></td>
            <?php if (es_admin()): ?>
            <td>
                <a class="btn red"
                   href="?eliminar=<?= $r['id_cocina'] ?>"
                   onclick="return confirm('¿Enviar este registro a la papelera?')">
                   Papelera
                </a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>