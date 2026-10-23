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
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');
            $encryption = config('mail.mailers.smtp.encryption');
            $username = config('mail.mailers.smtp.username');
            $password = config('mail.mailers.smtp.password');
            $fromAddress = config('mail.from.address');
            $fromName = config('mail.from.name');

            $requiredConfig = [
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => $port,
                'mail.mailers.smtp.encryption' => $encryption,
                'mail.mailers.smtp.username' => $username,
                'mail.mailers.smtp.password' => $password,
                'mail.from.address' => $fromAddress,
                'mail.from.name' => $fromName,
            ];

            $missingConfig = [];

            foreach ($requiredConfig as $key => $value) {
                if ($value === null || $value === '') {
                    $missingConfig[] = $key;
                }
            }

            if (!is_numeric($port)) {
                $missingConfig[] = 'mail.mailers.smtp.port';
            }

            if ($missingConfig !== []) {
                \Log::error('PHPMailerService missing required mail configuration: ' . implode(', ', array_unique($missingConfig)));
                return false;
            }

            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $encryption;
            $mail->Port = (int) $port;

            $mail->setFrom($fromAddress, $fromName);
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
