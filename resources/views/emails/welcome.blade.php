<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to SJBAC</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f6f5; font-family:Arial, Helvetica, sans-serif; color:#25352e;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:520px; margin:0 auto; background:#ffffff; border:1px solid #dfe5e1; border-radius:10px;">
        <tr>
            <td style="padding:24px 24px 16px; border-bottom:1px solid #e8ece9;">
                <p style="margin:0 0 4px; color:#1d4f40; font-size:14px; font-weight:700;">SJBAC Procurement Portal</p>
                <p style="margin:0; color:#66756d; font-size:12px; line-height:1.5;">San Jose, Occidental Mindoro</p>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <h1 style="margin:0 0 20px; font-size:22px; line-height:1.3; font-weight:700;">Your account is ready</h1>
                <p style="margin:0 0 12px; font-size:14px; line-height:1.7;">Hello {{ $user->name ?: $user->company }},</p>
                <p style="margin:0 0 20px; font-size:14px; line-height:1.7;">Welcome to SJBAC. Your account is now active. Sign in with your registered email address and password to get started.</p>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                    <tr>
                        <td style="background:#1d4f40; border-radius:6px; text-align:center;">
                            <a href="{{ config('app.url') }}" style="display:inline-block; padding:12px 28px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700; line-height:1.4;">Sign in</a>
                        </td>
                    </tr>
                </table>
                <p style="margin:0; color:#66756d; font-size:13px; line-height:1.6;">Thank you,<br>SJBAC Secretariat</p>
            </td>
        </tr>
    </table>
</body>
</html>
