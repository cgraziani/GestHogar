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
if(!in_array($_POST['sexo'] ?? '', ['F','M'], true)) $errores[] = 'Hay que indicar el sexo del residente.';

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
$sexo       = $_POST['sexo'];
$id_conyuge = $_POST['id_conyuge'] ?: null;

if ($habitacion) {
    $hq = $pdo->prepare('SELECT numero, sexo_asignado, admite_pareja, habilitada, estado FROM habitaciones WHERE id_habitacion=?');
    $hq->execute([$habitacion]);
    $hab = $hq->fetch();

    $rechazo = null;
    if (!$hab) {
        $rechazo = 'La habitación elegida no existe.';
    } elseif (!$hab['habilitada']) {
        $rechazo = 'La habitación ' . $hab['numero'] . ' está deshabilitada y no admite residentes.';
    } elseif ($hab['estado'] === 'mantenimiento') {
        $rechazo = 'La habitación ' . $hab['numero'] . ' está en mantenimiento.';
    } elseif ($hab['sexo_asignado'] !== 'mixta' && $hab['sexo_asignado'] !== $sexo && !$hab['admite_pareja']) {
        $rechazo = 'La habitación ' . $hab['numero'] . ' es solo para '
                 . ($hab['sexo_asignado'] === 'F' ? 'mujeres' : 'varones') . '.';
    }

    if (!$rechazo) {
        $oq = $pdo->prepare("SELECT id_residente, nombre, apellido, sexo FROM residentes
                             WHERE id_habitacion=? AND estado IN('activo','pausado') AND id_residente != ?");
        $oq->execute([$habitacion, (int)($_POST['id'] ?: 0)]);
        foreach ($oq->fetchAll() as $otro) {
            if ($otro['sexo'] && $otro['sexo'] !== $sexo) {
                $es_pareja = $hab['admite_pareja'] && $id_conyuge && $id_conyuge == $otro['id_residente'];
                if (!$es_pareja) {
                    $rechazo = 'En la habitación ' . $hab['numero'] . ' ya está alojado/a '
                             . $otro['nombre'] . ' ' . $otro['apellido'] . ', de distinto sexo. '
                             . 'Si es un matrimonio, marcá la habitación como que admite pareja '
                             . 'y elegí a esa persona como cónyuge.';
                    break;
                }
            }
        }
    }

    if ($rechazo) {
        $_SESSION['error_form'] = $rechazo;
        $_SESSION['form_data']  = $_POST;
        header('Location: listar.php' . ($_POST['id'] ? '?abrir_modal=' . $_POST['id'] : '?abrir_modal=nuevo'));
        exit;
    }
}

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
        $nivel_act = in_array($_POST['nivel_actividad']??'', ['activo','asistido','reducido','postrado'])
            ? $_POST['nivel_actividad'] : null;
        $pdo->prepare('UPDATE residentes SET nombre=?,apellido=?,sexo=?,dni=?,fecha_nacimiento=?,fecha_ingreso=?,movilidad_autonoma=?,autorizado_foto=?,diagnostico=?,contacto_familiar=?,parentesco_familiar=?,telefono_familiar=?,id_habitacion=?,id_cama=?,id_conyuge=?,foto=?,nivel_actividad=?,estado=?,updated_by=? WHERE id_residente=?')
            ->execute([
                trim($_POST['nombre']),
                trim($_POST['apellido']),
                $sexo,
                trim($_POST['dni']),
                $_POST['fecha_nacimiento'] ?: null,
                $_POST['fecha_ingreso']    ?: null,
                isset($_POST['movilidad_autonoma']) ? 1 : 0,
                isset($_POST['autorizado_foto'])    ? 1 : 0,
                $_POST['diagnostico'],
                $_POST['contacto_familiar'],
                trim($_POST['parentesco_familiar'] ?? '') ?: null,
                $_POST['telefono_familiar'],
                $habitacion, $cama, $id_conyuge, $foto,
                $nivel_act,
                $_POST['estado'],
                uid(), $_POST['id']
            ]);
        header('Location: listar.php?toast=Residente+modificado+correctamente&tipo=ok');
    } else {
        $nivel_act = in_array($_POST['nivel_actividad']??'', ['activo','asistido','reducido','postrado'])
            ? $_POST['nivel_actividad'] : null;
        $pdo->prepare('INSERT INTO residentes(nombre,apellido,sexo,dni,fecha_nacimiento,fecha_ingreso,movilidad_autonoma,autorizado_foto,diagnostico,contacto_familiar,parentesco_familiar,telefono_familiar,id_habitacion,id_cama,id_conyuge,foto,nivel_actividad,estado,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                trim($_POST['nombre']),
                trim($_POST['apellido']),
                $sexo,
                trim($_POST['dni']),
                $_POST['fecha_nacimiento'] ?: null,
                $_POST['fecha_ingreso']    ?: null,
                isset($_POST['movilidad_autonoma']) ? 1 : 0,
                isset($_POST['autorizado_foto'])    ? 1 : 0,
                $_POST['diagnostico'],
                $_POST['contacto_familiar'],
                trim($_POST['parentesco_familiar'] ?? '') ?: null,
                $_POST['telefono_familiar'],
                $habitacion, $cama, $id_conyuge, $foto,
                $nivel_act, 'activo', uid()
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

