<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email — INVOIZ Logistics Hub Application</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1B1B1E; background: #F7F6F2; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E8E6E0; border-radius: 12px; padding: 32px;">
    <h2 style="color: #16697A; margin-top: 0;">INVOIZ LOGISTICS</h2>
    <h3 style="color: #16697A;">Verify Your Email</h3>

    <p>Hello {{ $applicantName }},</p>

    <p>Use the verification code below to verify the email address for your INVOIZ Logistics Hub application:</p>

    <div style="text-align: center; margin: 24px 0;">
        <span style="display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #16697A; background: #E8F4F8; padding: 14px 28px; border-radius: 8px; font-family: monospace; user-select: all;">{{ $code }}</span>
        <p style="margin-top: 10px; font-size: 13px; color: #555;">Enter this 6-digit code on the application page and click <strong>Verify</strong> to proceed.</p>
    </div>

    <p>This code expires in 5 minutes.</p>

    <p>If you did not request this verification code, you can safely ignore this email.</p>

    <p>INVOIZ Logistics Team<br>invoizecommerce@gmail.com</p>
</div>
</body>
</html>
