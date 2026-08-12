<?php
require_once '../config/db.php';

$token    = trim($_GET['token'] ?? '');
$enviado  = false;
$error    = '';

if (empty($token)) {
    die('<div style="font-family:Arial;text-align:center;padding:60px;color:#c0392b"><h2>Link inválido</h2><p>Este link no es válido. Pedile a la administradora un link actualizado.</p></div>');
}

$res = $pdo->prepare("SELECT id_residente, nombre, apellido, estado FROM residentes WHERE token_visita = ?");
$res->execute([$token]);
$residente = $res->fetch();

if (!$residente) {
    die('<div style="font-family:Arial;text-align:center;padding:60px;color:#c0392b"><h2>Link inválido</h2><p>Este link no corresponde a ningún residente. Pedile a la administradora un link actualizado.</p></div>');
}

if ($residente['estado'] === 'baja') {
    die('<div style="font-family:Arial;text-align:center;padding:60px;color:#555"><h2>Residente no disponible</h2><p>El residente ya no se encuentra en el hogar.</p></div>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $apellido    = trim($_POST['apellido'] ?? '');
    $lazo        = trim($_POST['lazo'] ?? '');
    $telefono    = trim($_POST['telefono'] ?? '');
    $fecha_hora  = trim($_POST['fecha_hora'] ?? '');
    $obs         = trim($_POST['observaciones'] ?? '');

    if (!$nombre || !$apellido || !$lazo || !$telefono || !$fecha_hora) {
        $error = 'Por favor completá todos los campos obligatorios.';
    } elseif (strtotime($fecha_hora) <= time()) {
        $error = 'La fecha y hora de la visita debe ser futura.';
    } else {
        $pdo->prepare("
            INSERT INTO visitas (id_residente, nombre_visitante, apellido_visitante, parentesco,
                                 telefono_visitante, hora_ingreso, observaciones, estado, fecha_solicitada,
                                 created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'solicitada', NOW(), NOW())
        ")->execute([
            $residente['id_residente'],
            $nombre, $apellido, $lazo, $telefono,
            $fecha_hora, $obs ?: null
        ]);
        $enviado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitar visita al Hogar Ñongo</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0 }
        body { font-family: Arial, sans-serif; background: #f0f4f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px }
        .card { background: #fff; border-radius: 12px; padding: 36px 32px; width: 100%; max-width: 480px; box-shadow: 0 4px 20px rgba(0,0,0,.1) }
        .logo  { text-align: center; margin-bottom: 24px }
        .logo h1 { color: #1F4E79; font-size: 22px; margin-bottom: 4px }
        .logo p  { color: #888; font-size: 14px }
        .residente-pill { background: #e8f4fd; border-left: 4px solid #1F4E79; padding: 12px 16px; border-radius: 8px; margin-bottom: 24px }
        .residente-pill b { color: #1F4E79; font-size: 16px }
        .residente-pill small { display: block; color: #666; font-size: 13px; margin-top: 2px }
        label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 4px; margin-top: 14px }
        input, select, textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; font-family: Arial; outline: none; transition: border .2s
        }
        input:focus, select:focus, textarea:focus { border-color: #1F4E79 }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px }
        .btn-enviar {
            width: 100%; margin-top: 24px; padding: 13px; background: #1F4E79; color: #fff;
            border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer
        }
        .btn-enviar:hover { background: #163a5c }
        .error  { background: #fdecea; border-left: 4px solid #c0392b; padding: 12px 16px; border-radius: 8px; color: #c0392b; font-size: 14px; margin-bottom: 16px }
        .ok     { text-align: center; padding: 32px 0 }
        .ok h2  { color: #27ae60; margin-bottom: 8px; font-size: 22px }
        .ok p   { color: #555; font-size: 14px; line-height: 1.6 }
        .req    { color: #c0392b }
        small.hint { color: #aaa; font-size: 11px; margin-top: 3px; display: block }
    </style>
</head>
<body>
<div class="card">

    <div class="logo">
        <h1>Hogar Ñongo</h1>
        <p>Solicitud de visita</p>
    </div>

    <?php if ($enviado): ?>
    <div class="ok">
        <h2>¡Solicitud enviada!</h2>
        <p>Tu solicitud fue recibida. Una cuidadora o la administradora la revisará y te confirmará por teléfono.</p>
        <p style="margin-top:16px;color:#aaa;font-size:13px">Podés cerrar esta página.</p>
    </div>

    <?php else: ?>

    <div class="residente-pill">
        <b><?= htmlspecialchars($residente['nombre'] . ' ' . $residente['apellido']) ?></b>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="grid2">
            <div>
                <label>Nombre <span class="req">*</span></label>
                <input name="nombre" placeholder="Tu nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
            </div>
            <div>
                <label>Apellido <span class="req">*</span></label>
                <input name="apellido" placeholder="Tu apellido" required value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>">
            </div>
        </div>

        <div class="grid2">
            <div>
                <label>Lazo con el residente <span class="req">*</span></label>
                <select name="lazo" required>
                    <option value="">Seleccioná...</option>
                    <?php foreach (['Hijo/a','Cónyuge','Hermano/a','Nieto/a','Sobrino/a','Amigo/a','Otro'] as $l): ?>
                        <option value="<?= $l ?>" <?= (($_POST['lazo'] ?? '') === $l) ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Teléfono de contacto <span class="req">*</span></label>
                <input name="telefono" type="tel" required value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
            </div>
        </div>

        <div>
            <label>Fecha y hora preferida de visita <span class="req">*</span></label>
            <input type="datetime-local" name="fecha_hora" required
                   min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
                   value="<?= htmlspecialchars($_POST['fecha_hora'] ?? '') ?>">
        </div>

        <div>
            <label>Observaciones <span style="color:#aaa;font-weight:400">(opcional)</span></label>
            <textarea name="observaciones" rows="3"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-enviar">Enviar solicitud</button>
    </form>

    <?php endif; ?>

</div>
</body>
</html>
