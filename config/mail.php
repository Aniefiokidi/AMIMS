<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Edit only these constants to configure outgoing mail
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_USERNAME',   'your_email@gmail.com');   // your Gmail address
define('MAIL_PASSWORD',   'your_app_password');       // Gmail App Password (not account password)
define('MAIL_PORT',       587);
define('MAIL_FROM_EMAIL', 'your_email@gmail.com');
define('MAIL_FROM_NAME',  'AMIMS System');
define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);

function getMailer(): PHPMailer {
    $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new RuntimeException('Composer autoload not found. Run: composer install');
    }
    require_once $autoloadPath;

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    $mail->Port       = MAIL_PORT;
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

function sendEmail(array $recipients, string $subject, string $htmlBody, string $attachment = ''): bool {
    try {
        $mail = getMailer();
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        foreach ($recipients as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }
        if ($attachment && file_exists($attachment)) {
            $mail->addAttachment($attachment, basename($attachment));
        }
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('AMIMS Mail Error: ' . $e->getMessage());
        return false;
    }
}
