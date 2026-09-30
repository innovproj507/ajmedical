<?php

namespace AJM\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Mailer — wrapper delgado sobre PHPMailer.
 * Reemplaza el envío SMTP por socket crudo de diplomado-adolescencia/api/config.php
 * (misma configuración: SMTP_HOST/PORT/SECURITY/USER/PASS vía .env + settings).
 */
class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, array $attachments = []): bool
    {
        if (!SMTP_ENABLED) {
            error_log("[Mailer] SMTP deshabilitado — correo a {$toEmail} no enviado: {$subject}");
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = (int) SMTP_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURITY === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo(EMAIL_ADMIN);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;

            foreach ($attachments as $attachment) {
                $mail->addStringAttachment($attachment['content'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['type'] ?? 'application/octet-stream');
            }

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[Mailer] ' . $mail->ErrorInfo);
            return false;
        }
    }

    public static function notifyAdmin(string $subject, string $htmlBody): bool
    {
        return self::send(EMAIL_ADMIN, 'Admin', $subject, $htmlBody);
    }
}
