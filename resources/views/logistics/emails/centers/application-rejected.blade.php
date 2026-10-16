<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>INVOIZ Logistics Center Application Not Approved</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1B1B1E; background: #F7F6F2; margin: 0; padding: 24px;">
<div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E8E6E0; border-radius: 12px; padding: 32px;">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 15px; letter-spacing: 2px; font-weight: 700; color: #16697A;">INVOIZ LOGISTICS</div>
        <div style="font-size: 12px; color: #6B7280; margin-top: 4px; letter-spacing: 0.5px;">Center Application Update</div>
    </div>

    <p>Hello {{ $ownerName }},</p>

    <p>Thank you for your interest in opening <strong>{{ $centerName }}</strong> as an INVOIZ Logistics Center. After review, we are unable to approve your application at this time for the following reason:</p>

    <div style="background: #FDF3E3; border: 1px solid #F0A202; border-radius: 12px; padding: 16px 20px; margin: 18px 0;">
        <p style="margin: 0;">{{ $reason }}</p>
    </div>

    <p>You are welcome to correct the details above and submit a new application. Your previous reference details remain on file for our records.</p>

    <p style="margin-bottom: 0;">
        Thank you,<br><br>
        <strong>INVOIZ Logistics Team</strong><br>
        <span style="color: #16697A;">invoizecommerce@gmail.com</span>
    </p>
</div>
</body>
</html>
