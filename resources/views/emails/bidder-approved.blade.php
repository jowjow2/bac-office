<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BAC Office Registration Approved</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; background:linear-gradient(135deg, #fff7ed 0%, #ffffff 60%, #eff6ff 100%); border-bottom:1px solid #e5e7eb;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#b45309;">BAC Office</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#0f172a;">Registration Approved</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $user->name }},
            </p>

            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Your bidder registration for <strong>{{ $bidder->company_name }}</strong> has been approved by the BAC Office.
            </p>

            <p style="margin:0 0 24px; font-size:15px; line-height:1.7;">
                You may now access your bidder dashboard using your registered email and password.
            </p>

            <div style="padding:20px; border:1px solid #e2e8f0; border-radius:18px; background:#f8fafc; margin-bottom:24px;">
                <p style="margin:0 0 14px; font-size:14px; font-weight:700; color:#0f172a;">How to login:</p>
                <ol style="margin:0; padding-left:18px; font-size:14px; line-height:1.8; color:#475569;">
                    <li>Go to the BAC Office website.</li>
                    <li>Click <strong>Sign In</strong>.</li>
                    <li>Enter your registered email/username and password.</li>
                    <li>Once verified, you will be automatically redirected to your bidder dashboard.</li>
                </ol>
            </div>

            <p style="margin:0 0 10px; font-size:14px; line-height:1.7;">
                Login page: <a href="{{ $loginUrl }}" style="color:#c2410c;">{{ $loginUrl }}</a>
            </p>

            <p style="margin:0; font-size:14px; line-height:1.7;">
                Thank you,<br>
                BAC Office
            </p>
        </div>
    </div>
</body>
</html>
