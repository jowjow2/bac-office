<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Welcome to BAC Office</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; background:linear-gradient(135deg, #fff7ed 0%, #ffffff 58%, #eff6ff 100%); border-bottom:1px solid #e5e7eb;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#b45309;">BAC Office</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#0f172a;">Welcome!</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $user->name ?? $user->company }},
            </p>

            <p style="margin:0 0 20px; font-size:15px; line-height:1.7;">
                Welcome to BAC-SAN JOSE OCCIDENTAL MINDORO, you are now register as a Bidder, Can u access your account now, THANK YOUU!!
            </p>

            <p style="margin:0 0 24px; font-size:15px; line-height:1.7;">
                You can now sign in to your account using your email and password.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px;">
                <tr>
                    <td style="padding:0 6px 0 0;">
                        <a href="{{ config('app.url') }}" style="display:block; padding:12px 16px; border-radius:12px; background:#ea580c; color:#ffffff; text-decoration:none; text-align:center; font-size:14px; font-weight:700;">
                            Sign In
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0; font-size:14px; line-height:1.7;">
                Thank you,<br>
                BAC Office
            </p>
        </div>
    </div>
</body>
</html>
