<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif; padding: 20px; color: #333;">
    <h2>Sign in to your account</h2>
    <p>Hi {{ $customerName }},</p>
    <p>Click the button below to sign in to your customer portal. This link expires in 24 hours.</p>
    <p style="margin: 24px 0;">
        <a href="{{ $url }}" style="background-color: #4f46e5; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">Sign in</a>
    </p>
    <p style="color: #666; font-size: 13px;">If you didn't request this email, you can safely ignore it.</p>
</body>
</html>
