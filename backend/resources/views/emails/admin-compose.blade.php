<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #111827;">
    <p>Pesan dari {{ $senderName }}:</p>
    <div style="white-space: pre-wrap;">{{ $messageBody }}</div>
    <p style="margin-top: 24px; color: #6b7280; font-size: 12px;">Dikirim dari UI compose Arkav.</p>
</body>
</html>