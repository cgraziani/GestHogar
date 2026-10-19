<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

const TIPOS_DOC = [
    'dni'                => 'DNI / documento',
    'carnet_obra_social' => 'Carnet de obra social',
    'historia_clinica'   => 'Historia clínica',
    'autorizacion'       => 'Autorización firmada',
    'contrato'           => 'Contrato de ingreso',
    'certificado_defuncion' => 'Certificado de defunción',
    'otro'               => 'Otro',
];

function volver($id_residente, $mensaje, $tipo = 'ok') {
    header('Location: listar.php?ver=' . (int)$id_residente
         . '&toast=' . urlencode($mensaje) . '&tipo=' . $tipo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_documento'])) {
    if (empty($_POST['tipo']))        { header('Location: documentos.php?id='.$id_residente.'&toast='.urlencode('El tipo de documento es obligatorio.').'&tipo=error'); exit; }
    if (empty($_FILES['archivo']['name'])) { header('Location: documentos.php?id='.$id_residente.'&toast='.urlencode('Seleccioná un archivo para subir.').'&tipo=error'); exit; }
    $id_residente = (int)$_POST['id_residente'];
    $tipo         = $_POST['tipo'] ?? 'otro';
    $descripcion  = trim($_POST['descripcion'] ?? '');

    if (!array_key_exists($tipo, TIPOS_DOC)) $tipo = 'otro';

    if (empty($_FILES['archivo']['name'])) {
        volver($id_residente, 'Tenés que elegir un archivo para adjuntar.', 'error');
    }

    $permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        volver($id_residente, 'Formato no permitido. Se aceptan PDF, JPG, PNG o WEBP.', 'error');
    }
    if ($_FILES['archivo']['size'] > 8 * 1024 * 1024) {
        volver($id_residente, 'El archivo supera los 8 MB.', 'error');
    }

    $dir = __DIR__ . '/../uploads/documentos';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $nombre = 'doc_' . $id_residente . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . '/' . $nombre)) {
        volver($id_residente, 'No se pudo guardar el archivo.', 'error');
    }

    try {
        $pdo->prepare('INSERT INTO documentos_residente(id_residente,tipo,descripcion,archivo,created_by) VALUES(?,?,?,?,?)')
            ->execute([$id_residente, $tipo, $descripcion ?: null, 'uploads/documentos/' . $nombre, uid()]);
        volver($id_residente, 'Documento adjuntado correctamente');
    } catch (PDOException $e) {
        volver($id_residente, 'Error al guardar el documento. Intentá de nuevo.', 'error');
    }
}

if (isset($_GET['baja'])) {
    $st = $pdo->prepare('SELECT id_residente FROM documentos_residente WHERE id_documento=?');
    $st->execute([(int)$_GET['baja']]);
    $id_residente = (int)$st->fetchColumn();

    $pdo->prepare("UPDATE documentos_residente SET estado='baja' WHERE id_documento=?")
        ->execute([(int)$_GET['baja']]);

    volver($id_residente, 'Documento dado de baja', 'warn');
}

header('Location: listar.php');
exit;
