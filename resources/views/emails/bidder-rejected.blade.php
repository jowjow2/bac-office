<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BAC Office Registration Update</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:620px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; background:linear-gradient(135deg, #fef2f2 0%, #ffffff 70%); border-bottom:1px solid #e5e7eb;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#b91c1c;">BAC Office</p>
            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#0f172a;">Registration Update</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $user->name }},
            </p>

            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Your bidder registration for <strong>{{ $bidder->company_name }}</strong> was reviewed by the BAC Office and was not approved at this time.
            </p>

            @if(filled($reason))
                <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                    Reason: {{ $reason }}
                </p>
            @endif

            <p style="margin:0 0 18px; font-size:15px; line-height:1.7;">
                Please contact the BAC Office if you need clarification or if you would like to submit an updated registration in the future.
            </p>

            <p style="margin:0; font-size:14px; line-height:1.7;">
                Thank you,<br>
                BAC Office
            </p>
        </div>
    </div>
</body>
</html>
