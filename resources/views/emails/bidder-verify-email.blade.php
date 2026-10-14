<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Confirm your email address</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; border-bottom:1px solid #e5e7eb; background:#f1f7f4;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#1f5c45;">SJBAC Bids and Awards Committee</p>
            <h1 style="margin:0; font-size:24px; line-height:1.25; color:#0f172a;">Confirm your email address</h1>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">Hello {{ $user->name ?: $user->company }},</p>
            <p style="margin:0 0 20px; font-size:15px; line-height:1.7;">
                We received a bidder registration for <strong>{{ $user->company }}</strong> with this email address.
                Please confirm that it is yours. The BAC Secretariat can approve a registration only after its email address is confirmed.
            </p>
            <p style="margin:0 0 24px;">
                <a href="{{ $link }}" style="display:inline-block; padding:12px 22px; border-radius:10px; background:#1f5c45; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">Confirm my email</a>
            </p>
            <p style="margin:0 0 16px; font-size:13px; line-height:1.7; color:#4b5563;">The link works for 3 days. If the button does not open, copy this address into your browser:<br><span style="word-break:break-all;">{{ $link }}</span></p>
            <p style="margin:0; font-size:13px; line-height:1.7; color:#4b5563;">If you did not register, you can ignore this email. Nothing happens to an unconfirmed registration.</p>
        </div>
    </div>
</body>
</html>
