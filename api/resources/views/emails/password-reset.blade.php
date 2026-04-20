<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 24px; margin-bottom: 16px;">Reset Your Password</h1>

    <p>You requested a password reset. Click the link below to set a new password:</p>

    <p style="margin: 24px 0;">
        <a href="{{ $resetUrl }}" style="background-color: #4f46e5; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
            Reset Password
        </a>
    </p>

    <p>Or copy and paste this URL into your browser:</p>
    <p style="word-break: break-all; color: #4f46e5;">{{ $resetUrl }}</p>

    <p style="margin-top: 32px; font-size: 14px; color: #666;">
        This link will expire in 60 minutes. If you did not request a password reset, you can safely ignore this email.
    </p>
</body>
</html>
