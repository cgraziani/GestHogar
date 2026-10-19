<?php
require_once __DIR__ . '/EnviarMail.php';

function plantilla_email(string $titulo, string $contenido): string {
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>' . htmlspecialchars($titulo) . '</title></head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 0">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08)">
        <tr><td style="background:#2D9B8A;padding:24px 32px;border-radius:10px 10px 0 0">
          <h1 style="margin:0;color:#fff;font-size:22px">GestHogar</h1>
          <p style="margin:4px 0 0;color:rgba(255,255,255,.8);font-size:13px">Hogar Ñongo, Alta Gracia, Córdoba</p>
        </td></tr>
        <tr><td style="padding:28px 32px;color:#333;font-size:15px;line-height:1.6">
          ' . $contenido . '
        </td></tr>
        <tr><td style="background:#f5f6fa;padding:16px 32px;text-align:center;
                       font-size:12px;color:#aaa;border-radius:0 0 10px 10px">
          Este email fue generado automáticamente por GestHogar. No respondas este mensaje.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>';
}

function email_acceso_profesional(string $email, string $nombre, string $password): bool {
    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . BASE_URL . '/auth/login.php';

    $cuerpo = plantilla_email('Acceso al sistema GestHogar', '
        <p>Hola <b>' . htmlspecialchars($nombre) . '</b>,</p>
        <p>Se creó tu acceso al sistema <b>GestHogar</b> del Hogar Ñongo.
           Con él podés registrar tus atenciones a los residentes directamente en el sistema.</p>
        <table width="100%" cellpadding="0" cellspacing="0"
               style="background:#f0faf8;border-radius:8px;padding:20px;margin:20px 0">
          <tr><td>
            <p style="margin:0 0 10px"><b>Tus datos de acceso:</b></p>
            <p style="margin:4px 0"><b>URL del sistema:</b><br>
              <a href="' . $url . '" style="color:#2D9B8A">' . $url . '</a></p>
            <p style="margin:10px 0 4px"><b>Email:</b> ' . htmlspecialchars($email) . '</p>
            <p style="margin:4px 0"><b>Contraseña temporal:</b>
              <span style="background:#fff;border:1px solid #2D9B8A;border-radius:4px;
                           padding:3px 12px;font-family:monospace;font-size:16px;
                           font-weight:700;color:#2D9B8A">'
              . htmlspecialchars($password) . '</span></p>
          </td></tr>
        </table>
        <p style="color:#e67e22;font-size:13px">
          Te recomendamos cambiar tu contraseña la primera vez que ingreses
          desde el menú superior → Cambiar contraseña.
        </p>
        <p>Si tenés algún problema para acceder, contactá a la administradora.</p>
    ');

    return (bool) enviar_email($email, 'Acceso al sistema GestHogar', $cuerpo);
}

function email_recuperar_password(string $email, string $nombre, string $token): bool {
    $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . BASE_URL . '/auth/nueva_password.php?token=' . $token;

    $cuerpo = plantilla_email('Restablecer contraseña de GestHogar', '
        <p>Hola <b>' . htmlspecialchars($nombre) . '</b>,</p>
        <p>Recibimos una solicitud para restablecer tu contraseña en <b>GestHogar</b>.</p>
        <p>Hacé clic en el botón para crear una nueva contraseña:</p>
        <p style="text-align:center;margin:28px 0">
          <a href="' . $url . '"
             style="background:#2D9B8A;color:#fff;padding:12px 28px;border-radius:8px;
                    text-decoration:none;font-weight:700;font-size:15px;display:inline-block">
            Restablecer contraseña
          </a>
        </p>
        <p style="font-size:13px;color:#888">
          O copiá este link en tu navegador:<br>
          <a href="' . $url . '" style="color:#2D9B8A;word-break:break-all">' . $url . '</a>
        </p>
        <p style="color:#e74c3c;font-size:13px">
          Este link vence en 1 hora. Si no solicitaste este cambio, ignorá este mensaje.
        </p>
    ');

    return (bool) enviar_email($email, 'Restablecer contraseña de GestHogar', $cuerpo);
}
