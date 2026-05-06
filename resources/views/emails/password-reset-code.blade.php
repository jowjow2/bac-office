<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BAC Office Password Reset Code</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:18px; overflow:hidden;">
        <div style="padding:24px 28px; background:#fff7ed; border-bottom:1px solid #fed7aa;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#c2410c;">BAC Office</p>
            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#0f172a;">Password Reset Code</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $user->name ?? $user->company }},
            </p>

            <p style="margin:0 0 18px; font-size:15px; line-height:1.7;">
                Use this verification code to reset your BAC Office password:
            </p>

            <p style="margin:0 0 20px; padding:18px 20px; background:#f1f5f9; border-radius:14px; text-align:center; font-size:32px; font-weight:800; letter-spacing:0.18em; color:#0f172a;">
                {{ $code }}
            </p>

            <p style="margin:0 0 24px; font-size:14px; line-height:1.7; color:#64748b;">
                This code expires in 10 minutes. If you did not request a password reset, you can ignore this email.
            </p>

            <p style="margin:0; font-size:14px; line-height:1.7;">
                Thank you,<br>
                BAC Office
            </p>
        </div>
    </div>
</body>
</html>
