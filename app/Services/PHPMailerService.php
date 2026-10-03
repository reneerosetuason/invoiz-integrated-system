<?php
namespace App\Services;

require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class PHPMailerService
{
    public static function sendOTP(string $recipient, string $otp): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'invoizecommerce@gmail.com';
            $mail->Password = 'mgbb awwa srrv hmoy';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('invoizecommerce@gmail.com', 'Invoiz');
            $mail->addAddress($recipient);

            $mail->isHTML(true);
            $mail->Subject = 'Invoiz Verification Code';
            $mail->Body = "
                <h2>Welcome to Invoiz</h2>
                <p>Your verification code is:</p>
                <h1>$otp</h1>
                <p>This code will expire in 5 minutes.</p>
                <p>If you did not create an Invoiz account, you can ignore this email.</p>
            ";

            $mail->send();
            return true;
        } catch (Exception $e) {
            \Log::error('PHPMailer OTP failed: ' . $e->getMessage());
            return false;
        }
    }
}
