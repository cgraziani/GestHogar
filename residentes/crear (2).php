<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN
// Solo el administrador puede crear o modificar residentes.
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

// ============================================================
// SECCIÓN 2: CARGA DE DATOS PARA EDICIÓN
// Si viene ?id= precarga los datos del residente a modificar.
// Si no, inicializa valores vacíos para el formulario nuevo.
// ============================================================
$id = (int)($_GET['id'] ?? 0);
$r  = [
    'nombre'            => '',
    'apellido'          => '',
    'dni'               => '',
    'fecha_nacimiento'  => '',
    'movilidad_autonoma'=> 1,
    'obs_medicas'       => '',
    'contacto_familiar' => '',
    'telefono_familiar' => '',
    'id_habitacion'     => '',
    'foto'              => '',
    'estado'            => 'activo'
];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM residentes WHERE id_residente=?');
    $st->execute([$id]);
    $r  = $st->fetch();
}
$habitaciones = $pdo->query("SELECT * FROM habitaciones WHERE eliminado=0
    AND (estado='disponible'" . ($r['id_habitacion'] ? " OR id_habitacion=" . (int)$r['id_habitacion'] : "") . ")
    ORDER BY numero")->fetchAll();

// ============================================================
// SECCIÓN 3: GUARDAR (POST)
// Procesa la foto si viene una nueva, actualiza o crea el
// registro. Redirige con toast al terminar.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $foto = $_POST['foto_actual'] ?? '';

    // Subida de foto: valida extensión y guarda en uploads/residentes/
    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
        $name = 'residente_' . time() . '_' . rand(100, 999) . '.' . $ext;
        move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/residentes/' . $name);
        $foto = 'uploads/residentes/' . $name;
    }

    $habitacion = $_POST['id_habitacion'] ?: null;
    $data = [
        $_POST['nombre'],
        $_POST['apellido'],
        $_POST['dni'],
        $_POST['fecha_nacimiento'],
        isset($_POST['movilidad_autonoma']) ? 1 : 0,
        $_POST['obs_medicas'],
        $_POST['contacto_familiar'],
        $_POST['telefono_familiar'],
        $habitacion,
        $foto,
        $_POST['estado'],
        uid()
    ];

    if ($_POST['id']) {
        $pdo->prepare('UPDATE residentes SET nombre=?,apellido=?,dni=?,fecha_nacimiento=?,movilidad_autonoma=?,obs_medicas=?,contacto_familiar=?,telefono_familiar=?,id_habitacion=?,foto=?,estado=?,created_by=? WHERE id_residente=?')
            ->execute([...$data, $_POST['id']]);
        header('Location: listar.php?toast=Residente+modificado+correctamente&tipo=ok');
    } else {
        $pdo->prepare('INSERT INTO residentes(nombre,apellido,dni,fecha_nacimiento,movilidad_autonoma,obs_medicas,contacto_familiar,telefono_familiar,id_habitacion,foto,estado,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute($data);
        header('Location: listar.php?toast=Residente+creado+correctamente&tipo=ok');
    }
    exit;
}

include '../config/menu.php';
?>

<h1><?= $id ? 'Modificar' : 'Crear' ?> residente</h1>

<!-- ============================================================
     SECCIÓN 4: FORMULARIO CON VALIDACIONES FRONT-END
     Cada campo tiene:
     - label descriptivo
     - required en los campos obligatorios
     - type correcto (date, tel, email)
     - pattern donde corresponde (DNI solo números)
     - minlength donde aplica
     El atributo novalidate está ausente a propósito para que
     el navegador valide antes de enviar (doble capa: front+back).
     ============================================================ -->
<div class="card">
    <form method="post" enctype="multipart/form-data" id="form-residente">
        <input type="hidden" name="id"          value="<?= $id ?>">
        <input type="hidden" name="foto_actual" value="<?= htmlspecialchars($r['foto']) ?>">

        <!-- Datos personales -->
        <h3>Datos personales</h3>

        <label>Nombre <span style="color:red">*</span></label>
        <input name="nombre"
               value="<?= htmlspecialchars($r['nombre']) ?>"
               placeholder="Nombre del residente"
               required
               minlength="2"
               title="Ingresá el nombre del residente">

        <label>Apellido <span style="color:red">*</span></label>
        <input name="apellido"
               value="<?= htmlspecialchars($r['apellido']) ?>"
               placeholder="Apellido del residente"
               required
               minlength="2"
               title="Ingresá el apellido del residente">

        <label>DNI <span style="color:red">*</span></label>
        <input name="dni"
               value="<?= htmlspecialchars($r['dni']) ?>"
               placeholder="Ej: 25123456"
               required
               pattern="[0-9]{7,8}"
               title="El DNI debe tener entre 7 y 8 dígitos numéricos"
               inputmode="numeric">

        <label>Fecha de nacimiento</label>
        <input type="date"
               name="fecha_nacimiento"
               value="<?= $r['fecha_nacimiento'] ?>"
               max="<?= date('Y-m-d') ?>"
               title="La fecha no puede ser futura">

        <label>
            <input type="checkbox"
                   name="movilidad_autonoma"
                   style="width:auto"
                   <?= $r['movilidad_autonoma'] ? 'checked' : '' ?>>
            Tiene movilidad autónoma
        </label>

        <!-- Datos de internación -->
        <h3>Internación</h3>

        <label>Habitación asignada</label>
        <select name="id_habitacion">
            <option value="">Sin asignar</option>
            <?php foreach ($habitaciones as $h): ?>
                <option value="<?= $h['id_habitacion'] ?>"
                    <?= $r['id_habitacion'] == $h['id_habitacion'] ? 'selected' : '' ?>>
                    Hab. <?= $h['numero'] ?> — <?= $h['sector'] ?> — <?= $h['piso'] ?> (<?= $h['estado'] ?>)
                </option>
            <?php endforeach; ?>
        </select>

        
     <input type="hidden" name="estado" value="<?= htmlspecialchars($r['estado'] ?: 'activo') ?>">


        <!-- Datos de contacto familiar -->
        <h3>Contacto familiar</h3>

        <label>Nombre del familiar</label>
        <input name="contacto_familiar"
               value="<?= htmlspecialchars($r['contacto_familiar']) ?>"
               placeholder="Nombre y apellido del familiar">

        <label>Teléfono del familiar</label>
        <input name="telefono_familiar"
               type="tel"
               value="<?= htmlspecialchars($r['telefono_familiar']) ?>"
               placeholder="Ej: 351-1234567"
               pattern="[0-9\-\+\s\(\)]{7,20}"
               title="Ingresá un número de teléfono válido">

        <!-- Observaciones médicas -->
        <h3>Observaciones médicas</h3>

        <label>Observaciones médicas</label>
        <textarea name="obs_medicas"
                  placeholder="Diagnósticos, alergias, condiciones especiales, indicaciones generales..."
                  rows="5"><?= htmlspecialchars($r['obs_medicas']) ?></textarea>

        <!-- Foto -->
        <h3>Foto del residente</h3>

        <?php if ($r['foto']): ?>
            <div style="margin-bottom:10px">
                <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                     style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #ddd">
                <span class="small" style="margin-left:8px">Foto actual</span>
            </div>
        <?php endif; ?>

        <label>
            <?= $r['foto'] ? 'Reemplazar foto (opcional)' : 'Subir foto (opcional)' ?>
        </label>
        <input type="file"
               name="foto"
               accept="image/jpeg,image/png,image/webp"
               title="Solo se aceptan imágenes JPG, PNG o WEBP">

        <br>
        <button type="submit" class="btn">Guardar residente</button>
        <a class="btn red" href="listar.php">Cancelar</a>
    </form>
</div>

<!-- ============================================================
     SECCIÓN 5: VALIDACIÓN FRONT-END ADICIONAL CON JAVASCRIPT
     Complementa las validaciones HTML5 nativas con mensajes
     más descriptivos y validaciones que HTML5 no cubre
     (como verificar que la fecha de nacimiento sea razonable).
     ============================================================ -->
<script>
document.getElementById('form-residente').addEventListener('submit', function(e) {
    var dni   = document.querySelector('[name="dni"]').value.trim();
    var fecha = document.querySelector('[name="fecha_nacimiento"]').value;

    // Valida que el DNI tenga exactamente 7 u 8 dígitos
    if (dni && !/^[0-9]{7,8}$/.test(dni)) {
        alert('El DNI debe tener entre 7 y 8 dígitos numéricos, sin puntos ni espacios.');
        e.preventDefault(); return;
    }

    // Valida que la fecha de nacimiento sea razonable (mayor de 50 años como mínimo)
    if (fecha) {
        var hoy  = new Date();
        var nac  = new Date(fecha);
        var edad = hoy.getFullYear() - nac.getFullYear();
        if (edad < 50) {
            var ok = confirm('La fecha indica que el residente tiene menos de 50 años (' + edad + '). ¿Es correcto?');
            if (!ok) { e.preventDefault(); return; }
        }
        if (nac > hoy) {
            alert('La fecha de nacimiento no puede ser futura.');
            e.preventDefault(); return;
        }
    }
});
</script>

<?php include '../config/footer.php'; ?>