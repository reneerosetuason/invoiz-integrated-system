<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>INVOIZ Rider Account – New Login Credentials</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1B1B1E; background: #F7F6F2; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E8E6E0; border-radius: 12px; padding: 32px;">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 15px; letter-spacing: 2px; font-weight: 700; color: #16697A;">INVOIZ LOGISTICS</div>
        <div style="font-size: 12px; color: #6B7280; margin-top: 4px; letter-spacing: 0.5px;">New Login Credentials</div>
    </div>

    <p>Hello {{ $riderName }},</p>

    <p>Your INVOIZ Logistics administrator has generated a new temporary password for your rider account. Your previous password no longer works.</p>

    <div style="background: #EAF4F3; border: 1px solid #16697A; border-radius: 12px; padding: 20px 24px; margin: 18px 0;">
        <div style="font-size: 12px; letter-spacing: 1.5px; font-weight: 700; color: #16697A; margin-bottom: 14px; text-align: center;">YOUR LOGIN CREDENTIALS</div>

        <p style="margin: 0 0 12px;">
            Email:<br>
            <strong style="font-size: 15px;">{{ $riderEmail }}</strong>
        </p>

        <p style="margin: 0;">
            New Temporary Password:<br>
            <strong style="font-size: 18px; letter-spacing: 1px;">{{ $temporaryPassword }}</strong>
        </p>
    </div>

    <p>You may now open the INVOIZ Rider App and log in using the credentials above.</p>

    <p style="background: #F0EEE9; border-radius: 8px; padding: 12px 16px; font-size: 13px; margin: 18px 0;">
        <strong>Important:</strong> For your security, please change your temporary password after your first successful login.
    </p>

    <p style="font-size: 13px;">If you did not request new credentials or believe this email was sent to you by mistake, please contact the INVOIZ Logistics Team.</p>

    <p style="margin-bottom: 0;">
        Thank you,<br><br>
        <strong>INVOIZ Logistics Team</strong><br>
        <span style="color: #16697A;">invoizecommerce@gmail.com</span>
    </p>
</div>
</body>
</html>
