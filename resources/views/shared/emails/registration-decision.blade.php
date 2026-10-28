<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Registration Decision</title>
</head>
<body>
    <h2>{{ $approved ? 'Your registration has been approved' : 'Your registration has been declined' }}</h2>
    @if (!$approved)
        <p>Reason: {{ $reason }}</p>
    @endif
    <p>Thank you,
    <br/>Platform Team</p>
</body>
</html>
