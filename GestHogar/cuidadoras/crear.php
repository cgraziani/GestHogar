<?php
// ============================================================
// CUIDADORAS / CREAR — SOLO BACKEND
// El formulario ahora vive como modal en listar.php
// Inserta en tabla usuarios con rol cuidadora/asistente
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php'); exit;
}

$errores = [];

if (empty(trim($_POST['nombre'])))   $errores[] = 'El nombre es obligatorio.';
if (empty(trim($_POST['apellido']))) $errores[] = 'El apellido es obligatorio.';
if (empty(trim($_POST['email'])))    $errores[] = 'El email es obligatorio.';
if (empty(trim($_POST['dni'])))      $errores[] = 'El DNI es obligatorio.';
if (empty($_POST['password']))       $errores[] = 'La contraseña es obligatoria.';
if (!empty($_POST['password']) && strlen($_POST['password']) < 6)
    $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
if (!empty($_POST['dni']) && !preg_match('/^[0-9]{7,8}$/', trim($_POST['dni'])))
    $errores[] = 'El DNI debe tener entre 7 y 8 dígitos numéricos.';

if (empty($errores) && !empty(trim($_POST['email']))) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email=?');
    $q->execute([trim($_POST['email'])]);
    if ($q->fetchColumn() > 0)
        $errores[] = 'Ya existe un usuario con el email ' . htmlspecialchars($_POST['email']) . '.';
}

if (empty($errores) && !empty(trim($_POST['dni']))) {
    $q = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE dni=?');
    $q->execute([trim($_POST['dni'])]);
    if ($q->fetchColumn() > 0)
        $errores[] = 'Ya existe un usuario con ese DNI.';
}

if (!empty($errores)) {
    $_SESSION['error_form'] = implode(' | ', $errores);
    $_SESSION['form_data']  = $_POST;
    header('Location: listar.php?abrir_modal=nuevo'); exit;
}

try {
    $hash  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $color = $_POST['color'] ?: '#087f7a';
    $rol   = in_array($_POST['rol'], ['cuidadora','asistente']) ? $_POST['rol'] : 'cuidadora';

    $pdo->prepare('INSERT INTO usuarios(nombre,apellido,email,password,rol,dni,telefono,tipo_turno,estado,color,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            trim($_POST['nombre']),
            trim($_POST['apellido']),
            trim($_POST['email']),
            $hash,
            $rol,
            trim($_POST['dni'])        ?: null,
            trim($_POST['telefono'])   ?: null,
            trim($_POST['tipo_turno']) ?: null,
            'activo',
            $color,
            uid()
        ]);

    $nuevo_id = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por) VALUES(?,?,?,?,?)')
        ->execute(['usuario', $nuevo_id, '', 'activo', uid()]);

    header('Location: listar.php?toast=Cuidadora+creada+correctamente&tipo=ok'); exit;

} catch (PDOException $e) {
    $_SESSION['error_form'] = $e->getCode() === '23000'
        ? 'Ya existe un usuario con ese email o DNI.'
        : 'Error al guardar. Verificá los datos e intentá de nuevo.';
    $_SESSION['form_data'] = $_POST;
    header('Location: listar.php?abrir_modal=nuevo'); exit;
}
