<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email — INVOIZ Logistics Center Application</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1B1B1E; background: #F7F6F2; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E8E6E0; border-radius: 12px; padding: 32px;">
    <h2 style="color: #16697A; margin-top: 0;">INVOIZ LOGISTICS</h2>
    <h3 style="color: #16697A;">Verify Your Email</h3>

    <p>Hello {{ $applicantName }},</p>

    <p>Use the verification code below to verify the email address for your INVOIZ Logistics Center application:</p>

    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #16697A;">{{ $code }}</p>

    <p>This code expires in 5 minutes.</p>

    <p>If you did not request this verification code, you can safely ignore this email.</p>

    <p>INVOIZ Logistics Team<br>invoizecommerce@gmail.com</p>
</div>
</body>
</html>
