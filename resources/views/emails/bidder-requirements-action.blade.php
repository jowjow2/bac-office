<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>SJBAC Portal requirements</title></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
        <div style="padding:24px 28px;background:#eff6ff;border-bottom:1px solid #dbeafe;">
            <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#1d4ed8;">SJBAC</p>
            <h1 style="margin:0;font-size:25px;line-height:1.2;color:#0f172a;">Registration needs your attention</h1>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Hello {{ $bidderName }},</p>
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">The SJBAC reviewed the registration for <strong>{{ $companyName }}</strong> on <strong>{{ $requestedAt->format('F j, Y \a\t g:i A') }}</strong> and needs you to correct or submit the following documents:</p>
            <ul style="margin:0 0 20px;padding-left:22px;font-size:15px;line-height:1.8;">
                @foreach($requirements as $requirement)<li>{{ $requirement }}</li>@endforeach
            </ul>
            <div style="margin:0 0 20px;padding:14px 16px;border-left:4px solid #2563eb;background:#f8fafc;font-size:15px;line-height:1.7;"><strong>Message from SJBAC:</strong><br>{{ $reason }}</div>
            @php($replyTo = $adminReplyToEmail ?: config('mail.from.address'))
            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;"><strong>How to send the corrections:</strong> simply <strong>reply to this email</strong> and attach the corrected documents (PDF, JPG or PNG, up to 10 MB each). Your reply goes straight to the SJBAC.</p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:0 0 20px;">
                <tr>
                    <td>
                        <a href="mailto:{{ $replyTo }}?subject={{ rawurlencode('Corrected documents - '.$companyName) }}" style="display:block;padding:13px 18px;border-radius:12px;background:#1d4ed8;color:#ffffff;text-decoration:none;text-align:center;font-size:15px;font-weight:700;">
                            Reply with the corrected documents
                        </a>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 18px;font-size:13px;line-height:1.6;color:#64748b;">Prefer the portal? You can also sign in and upload them yourself:
                <a href="{{ $resubmissionUrl }}" style="color:#2563eb;word-break:break-all;">{{ $resubmissionUrl }}</a>
            </p>
            <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 18px;">

            <p style="margin:0;font-size:13px;line-height:1.7;color:#475569;">
                Questions about this request? Reply to this email or contact the SJBAC at
                <a href="mailto:{{ $replyTo }}" style="color:#2563eb;">{{ $replyTo }}</a>.
            </p>
            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;">Thank you,<br>SJBAC</p>
        </div>
    </div>
</body>
</html>
