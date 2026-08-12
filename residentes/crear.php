<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php'); exit;
}

$foto    = $_POST['foto_actual'] ?? '';
$errores = [];

if(empty(trim($_POST['nombre'])))   $errores[] = 'El nombre es obligatorio.';
if(empty(trim($_POST['apellido']))) $errores[] = 'El apellido es obligatorio.';
if(empty(trim($_POST['dni'])))      $errores[] = 'El DNI es obligatorio.';

if(!empty($_POST['dni']) && !preg_match('/^[0-9]{7,8}$/', trim($_POST['dni']))){
    $errores[] = 'El DNI debe tener entre 7 y 8 dígitos numéricos.';
}

if(!empty($_POST['dni']) && empty($errores)){
    $q = $pdo->prepare('SELECT COUNT(*) FROM residentes WHERE dni=?' . ($_POST['id'] ? ' AND id_residente!=?' : ''));
    $params = [$_POST['dni']];
    if($_POST['id']) $params[] = $_POST['id'];
    $q->execute($params);
    if($q->fetchColumn() > 0)
        $errores[] = 'Ya existe un residente con el DNI ' . htmlspecialchars($_POST['dni']) . '.';
}

if(!empty($errores)){
    $_SESSION['error_form'] = implode(' | ', $errores);
    $_SESSION['form_data']  = $_POST;
    header('Location: listar.php' . ($_POST['id'] ? '?abrir_modal=' . $_POST['id'] : '?abrir_modal=nuevo'));
    exit;
}

if (!empty($_FILES['foto']['name'])) {
    $ext  = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
    $name = 'residente_' . time() . '_' . rand(100,999) . '.' . $ext;
    move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . '/../uploads/residentes/' . $name);
    $foto = 'uploads/residentes/' . $name;
}

$habitacion = $_POST['id_habitacion'] ?: null;
$cama       = $_POST['id_cama']       ?: null;

if ($cama) {
    $cama_check = $pdo->prepare("SELECT estado FROM camas WHERE id_cama=?");
    $cama_check->execute([$cama]);
    $cama_data = $cama_check->fetch();

    $cama_anterior = null;
    if ($_POST['id']) {
        $ant_q = $pdo->prepare("SELECT id_cama FROM residentes WHERE id_residente=?");
        $ant_q->execute([$_POST['id']]);
        $cama_anterior = $ant_q->fetchColumn();
    }

    if ($cama_data && $cama_data['estado'] !== 'disponible' && $cama != $cama_anterior) {
        $_SESSION['error_form'] = 'La cama seleccionada ya está ocupada. Elegí una cama disponible.';
        $_SESSION['form_data']  = $_POST;
        header('Location: listar.php' . ($_POST['id'] ? '?abrir_modal=' . $_POST['id'] : '?abrir_modal=nuevo'));
        exit;
    }
}

try {
    if ($_POST['id']) {
        $anterior = $pdo->prepare('SELECT id_cama, id_habitacion FROM residentes WHERE id_residente=?');
        $anterior->execute([$_POST['id']]);
        $ant = $anterior->fetch();
        if ($ant['id_cama'] && $ant['id_cama'] != $cama) {
            $pdo->prepare("UPDATE camas SET estado='disponible', updated_by=? WHERE id_cama=?")
                ->execute([uid(), $ant['id_cama']]);
        }
        $pdo->prepare('UPDATE residentes SET nombre=?,apellido=?,dni=?,fecha_nacimiento=?,fecha_ingreso=?,movilidad_autonoma=?,autorizado_foto=?,diagnostico=?,contacto_familiar=?,telefono_familiar=?,id_habitacion=?,id_cama=?,foto=?,estado=?,updated_by=? WHERE id_residente=?')
            ->execute([
                trim($_POST['nombre']),
                trim($_POST['apellido']),
                trim($_POST['dni']),
                $_POST['fecha_nacimiento'] ?: null,
                $_POST['fecha_ingreso']    ?: null,
                isset($_POST['movilidad_autonoma']) ? 1 : 0,
                isset($_POST['autorizado_foto'])    ? 1 : 0,
                $_POST['diagnostico'],
                $_POST['contacto_familiar'],
                $_POST['telefono_familiar'],
                $habitacion, $cama, $foto,
                $_POST['estado'],
                uid(), $_POST['id']
            ]);
        header('Location: listar.php?toast=Residente+modificado+correctamente&tipo=ok');
    } else {
        $pdo->prepare('INSERT INTO residentes(nombre,apellido,dni,fecha_nacimiento,fecha_ingreso,movilidad_autonoma,autorizado_foto,diagnostico,contacto_familiar,telefono_familiar,id_habitacion,id_cama,foto,estado,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                trim($_POST['nombre']),
                trim($_POST['apellido']),
                trim($_POST['dni']),
                $_POST['fecha_nacimiento'] ?: null,
                $_POST['fecha_ingreso']    ?: null,
                isset($_POST['movilidad_autonoma']) ? 1 : 0,
                isset($_POST['autorizado_foto'])    ? 1 : 0,
                $_POST['diagnostico'],
                $_POST['contacto_familiar'],
                $_POST['telefono_familiar'],
                $habitacion, $cama, $foto,
                'activo', uid()
            ]);
        $nuevo_id = $pdo->lastInsertId();

        $token = md5($nuevo_id . 'gesthogar' . uniqid('', true));
        $pdo->prepare('UPDATE residentes SET token_visita=? WHERE id_residente=?')
            ->execute([$token, $nuevo_id]);

        $pdo->prepare('INSERT INTO historial_estados(entidad,id_registro,estado_anterior,estado_nuevo,cambiado_por) VALUES(?,?,?,?,?)')
            ->execute(['residente', $nuevo_id, '', 'activo', uid()]);
        header('Location: listar.php?toast=Residente+creado+correctamente&tipo=ok');
    }

    if ($cama) {
        $pdo->prepare("UPDATE camas SET estado='ocupada', updated_by=? WHERE id_cama=?")
            ->execute([uid(), $cama]);
    }
    if ($habitacion) {
        $pdo->prepare("UPDATE habitaciones SET estado='ocupada', updated_by=? WHERE id_habitacion=?")
            ->execute([uid(), $habitacion]);
    }
    exit;

} catch(PDOException $e) {
    $_SESSION['error_form'] = $e->getCode() === '23000'
        ? 'Ya existe un residente con ese DNI.'
        : 'Error al guardar el residente. Verificá los datos e intentá de nuevo.';
    $_SESSION['form_data'] = $_POST;
    header('Location: listar.php' . ($_POST['id'] ? '?abrir_modal=' . $_POST['id'] : '?abrir_modal=nuevo'));
    exit;
}

