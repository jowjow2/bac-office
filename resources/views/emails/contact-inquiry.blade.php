<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>SJBAC inquiry</title></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
        <div style="padding:22px 28px;background:#eff6ff;border-bottom:1px solid #dbeafe;">
            <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">SJBAC &middot; Contact page</p>
            <h1 style="margin:0;font-size:22px;line-height:1.25;color:#0f172a;">{{ $subjectLine }}</h1>
        </div>
        <div style="padding:26px 28px;">
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;font-size:14px;line-height:1.7;">
                <tr><td style="padding-right:14px;color:#64748b;">From</td><td><strong>{{ $senderName }}</strong> &lt;<a href="mailto:{{ $senderEmail }}" style="color:#2563eb;">{{ $senderEmail }}</a>&gt;</td></tr>
                <tr><td style="padding-right:14px;color:#64748b;">About</td><td>{{ $topic }}</td></tr>
            </table>
            <div style="padding:14px 16px;border-left:4px solid #2563eb;background:#f8fafc;font-size:15px;line-height:1.7;white-space:pre-line;">{{ $body }}</div>
            <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#64748b;">Reply to this email to answer {{ $senderName }} directly.</p>
        </div>
    </div>
</body>
</html>
