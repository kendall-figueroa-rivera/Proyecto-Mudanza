<?php
/**
 * Archivo: Mailer.php
 * Modulo: Servicios
 * Responsable: Daniel Gómez Mora
 * Descripcion: Servicio de envio de correos por SMTP usando PHPMailer.
 *              Uso: Mailer::enviar($para, $asunto, $htmlCuerpo);
 */

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    // Envia un correo HTML devuelve true si se envio y false si fallo (sin romper la pagina)
    public static function enviar($para, $asunto, $htmlCuerpo)
    {
        $rutaConfig = __DIR__ . '/../../config/mail.php';

        if (!file_exists($rutaConfig)) {
            error_log('Mailer: falta config/mail.php (copiar mail.example.php).');
            return false;
        }

        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer: correo de destino invalido.');
            return false;
        }

        $cfg = require $rutaConfig;

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = $cfg['puerto'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['usuario'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            $remitente = !empty($cfg['remitente_correo']) ? $cfg['remitente_correo'] : $cfg['usuario'];
            $mail->setFrom($remitente, $cfg['remitente_nombre']);
            $mail->addAddress($para);

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $htmlCuerpo;
            $mail->AltBody = strip_tags($htmlCuerpo);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer: no se pudo enviar el correo. ' . $e->getMessage());
            return false;
        }
    }
}