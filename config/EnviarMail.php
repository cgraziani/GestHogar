<?php
require_once __DIR__ . '/config_mail.php';

function enviar_email($destinatario, $asunto, $cuerpo_html) {
    $autoload = __DIR__ . '/../vendor/autoload.php';

    if (file_exists($autoload)) {
        require_once $autoload;
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($destinatario);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $cuerpo_html;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Error enviando email: ' . $e->getMessage());
            return false;
        }
    }

    $log = __DIR__ . '/../logs/emails_enviados.log';
    if (!is_dir(dirname($log))) mkdir(dirname($log), 0755, true);
    file_put_contents($log,
        "\n=== " . date('Y-m-d H:i:s') . " ===\n" .
        "Para: $destinatario\nAsunto: $asunto\n$cuerpo_html\n",
        FILE_APPEND
    );
    return true;
}
