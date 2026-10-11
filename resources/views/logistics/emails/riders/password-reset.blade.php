<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>INVOIZ Rider Account – Password Reset Request</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1B1B1E; background: #F7F6F2; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E8E6E0; border-radius: 12px; padding: 32px;">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 15px; letter-spacing: 2px; font-weight: 700; color: #16697A;">INVOIZ LOGISTICS</div>
        <div style="font-size: 12px; color: #6B7280; margin-top: 4px; letter-spacing: 0.5px;">Password Reset Request</div>
    </div>

    <p>Hello {{ $riderName }},</p>

    <p>We received a request to reset the password for your INVOIZ Rider account ({{ $riderEmail }}). Tap the button below to open the Rider App and choose a new password. The link expires in 60 minutes and works only once.</p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $resetDeepLink }}" style="display: inline-block; background: #16697A; color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 15px; padding: 14px 32px; border-radius: 10px;">Reset My Password</a>
    </div>

    <p>If the button does not work, open the INVOIZ Rider App, choose <strong>Forgot password?</strong> → <strong>I have a reset code</strong>, and enter this code:</p>

    <div style="background: #EAF4F3; border: 1px solid #16697A; border-radius: 12px; padding: 16px 24px; margin: 18px 0; text-align: center;">
        <strong style="font-size: 15px; letter-spacing: 1px; word-break: break-all;">{{ $resetToken }}</strong>
    </div>

    <p style="background: #F0EEE9; border-radius: 8px; padding: 12px 16px; font-size: 13px; margin: 18px 0;">
        <strong>Important:</strong> Your current password keeps working until the reset is completed. Never share this code with anyone.
    </p>

    <p style="font-size: 13px;">If you did not request a password reset, you can safely ignore this email — your password will not change. Please contact the INVOIZ Logistics Team if you are concerned.</p>

    <p style="margin-bottom: 0;">
        Thank you,<br><br>
        <strong>INVOIZ Logistics Team</strong><br>
        <span style="color: #16697A;">invoizecommerce@gmail.com</span>
    </p>
</div>
</body>
</html>
