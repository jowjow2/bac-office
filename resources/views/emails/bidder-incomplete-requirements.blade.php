<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SJBAC Registration Requirements Incomplete</title>
</head>
<body style="margin:0; padding:24px; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <div style="max-width:620px; margin:0 auto; background:#ffffff; border:1px solid #e5e7eb; border-radius:20px; overflow:hidden;">
        <div style="padding:24px 28px; background:linear-gradient(135deg, #fff7ed 0%, #ffffff 70%); border-bottom:1px solid #e5e7eb;">
            <p style="margin:0 0 8px; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#b45309;">SJBAC</p>
            <h1 style="margin:0; font-size:26px; line-height:1.2; color:#0f172a;">Registration Incomplete</h1>
        </div>

        <div style="padding:28px;">
            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                Hello {{ $bidderName }},
            </p>

            <p style="margin:0 0 16px; font-size:15px; line-height:1.7;">
                We received your bidder registration for <strong>{{ $companyName }}</strong>, but we could not complete the submission because some required documents were not included.
            </p>

            <p style="margin:0 0 10px; font-size:15px; line-height:1.7; font-weight:700; color:#0f172a;">
                Please prepare the following:
            </p>

            <ul style="margin:0 0 22px; padding-left:22px; font-size:15px; line-height:1.8;">
                @foreach($missingRequirements as $requirement)
                    <li>{{ $requirement }}</li>
                @endforeach
            </ul>

            <p style="margin:0 0 18px; font-size:15px; line-height:1.7;">
                Please return to the SJBAC registration form and submit the missing requirements using the accepted file formats.
            </p>

            <p style="margin:0; font-size:14px; line-height:1.7;">
                Thank you,<br>
                SJBAC
            </p>
        </div>
    </div>
</body>
</html>
