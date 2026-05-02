<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BAC Office QR Quick Access</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:640px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; background:linear-gradient(135deg, #fff7ed 0%, #ffffff 58%, #eff6ff 100%); border-bottom:1px solid #e5e7eb;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#b45309;">BAC Office</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#0f172a;">Bidder Quick Access</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $user->name }},
            </p>

            <p style="margin:0 0 20px; font-size:15px; line-height:1.7;">
                Approved bidders can scan their QR Code to securely access their bidder dashboard.
            </p>

            <div style="margin:0 0 22px; padding:22px; border:1px solid #fed7aa; border-radius:18px; background:linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); text-align:center;">
                @if(!empty($qrDataUri))
                    <img
                        src="{{ $qrDataUri }}"
                        alt="BAC Office QR login code"
                        style="width:220px; max-width:100%; height:auto; margin:0 auto 16px; display:block;"
                    >
                @endif

                <p style="margin:0; font-size:13px; line-height:1.7; color:#7c2d12;">
                    This QR login code can be used directly by your approved bidder account on the secure BAC Office login page.
                </p>
            </div>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px;">
                <tr>
                    <td style="padding:0 6px 0 0;">
                        <a href="{{ $loginUrl }}" style="display:block; padding:12px 16px; border-radius:12px; background:#ea580c; color:#ffffff; text-decoration:none; text-align:center; font-size:14px; font-weight:700;">
                            Scan QR Code
                        </a>
                    </td>
                    <td style="padding:0 0 0 6px;">
                        <a href="{{ $loginUrl }}" style="display:block; padding:12px 16px; border-radius:12px; background:#ffffff; border:1px solid #d1d5db; color:#334155; text-decoration:none; text-align:center; font-size:14px; font-weight:700;">
                            Login Manually
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 10px; font-size:14px; line-height:1.7;">
                Company: <strong>{{ $bidder->company_name }}</strong>
            </p>

            <p style="margin:0 0 20px; font-size:14px; line-height:1.7;">
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
