<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>SJBAC Login Verification Code</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f3f6f4; font-family:Arial, Helvetica, sans-serif; color:#1b2420; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; font-size:1px; line-height:1px; color:#f3f6f4; mso-hide:all;">Your SJBAC sign-in code is ready. It expires in {{ $minutes }} {{ \Illuminate\Support\Str::plural('minute', $minutes) }}.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#f3f6f4;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <!--[if mso]><table role="presentation" width="520" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:520px; background-color:#ffffff; border:1px solid #dfe7e2; border-top:4px solid #1d4f40; border-radius:14px; border-spacing:0;">
                    <tr>
                        <td style="padding:24px; border-bottom:1px solid #ecf0ee;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="48" valign="middle" style="width:48px;">
                                        <img src="{{ asset('favicon-bac-192.png') }}" width="44" height="44" alt="SJBAC seal" style="display:block; width:44px; height:44px; border:0;">
                                    </td>
                                    <td valign="middle" style="padding-left:10px;">
                                        <p style="margin:0 0 4px; font-size:15px; line-height:20px; font-weight:700; color:#1d4f40;">SJBAC Procurement</p>
                                        <p style="margin:0; font-size:11px; line-height:16px; color:#66716b;">Bids &amp; Awards Committee<br>San Jose, Occidental Mindoro</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px 24px;">
                            <p style="margin:0 0 10px; font-size:11px; line-height:16px; font-weight:700; letter-spacing:1.5px; color:#527264;">ACCOUNT VERIFICATION</p>
                            <h1 style="margin:0 0 20px; font-size:26px; line-height:34px; font-weight:700; letter-spacing:-0.5px; color:#1b2420;">Confirm your sign-in</h1>
                            <p style="margin:0 0 8px; font-size:14px; line-height:23px; color:#33403a;">Hello <strong>{{ $user->name ?? $user->company }}</strong>,</p>
                            <p style="margin:0 0 24px; font-size:14px; line-height:23px; color:#4f5b55;">Enter this code in the portal to finish signing in to your {{ $user->role === 'bidder' ? 'bidder' : 'SJBAC' }} account.</p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#edf5f0; border:1px solid #cfe2d7; border-radius:10px;">
                                <tr>
                                    <td align="center" style="padding:20px 12px 8px; font-size:10px; line-height:16px; font-weight:700; letter-spacing:1.5px; color:#527264;">YOUR VERIFICATION CODE</td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:0 12px 12px; font-family:'Courier New', Courier, monospace; font-size:36px; line-height:44px; font-weight:700; letter-spacing:6px; white-space:nowrap; color:#1d4f40;">{{ $code }}</td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:0 12px 20px; font-size:12px; line-height:18px; color:#527264;">Expires in <strong>{{ $minutes }} {{ \Illuminate\Support\Str::plural('minute', $minutes) }}</strong> &nbsp;&middot;&nbsp; One-time use</td>
                                </tr>
                            </table>
                            <p style="margin:18px 0 0; font-size:12px; line-height:20px; color:#66716b;">Keep this code private. Do not share it with anyone.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 24px; border-top:1px solid #ecf0ee;">
                            <p style="margin:0 0 5px; font-size:12px; line-height:19px; font-weight:700; color:#33403a;">Didn't try to sign in?</p>
                            <p style="margin:0; font-size:12px; line-height:20px; color:#66716b;">You can ignore this email. This sign-in cannot finish without the code.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
                <p style="margin:20px 12px 0; font-size:11px; line-height:18px; color:#7b8780;">SJBAC Procurement Portal<br>This is an automated email. Please do not reply.</p>
            </td>
        </tr>
    </table>
</body>
</html>
