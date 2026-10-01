<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Bid document review</title></head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <main style="max-width:620px;margin:0 auto;padding:28px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;">
        <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.08em;color:#1d4ed8;">SJBAC PROCUREMENT PORTAL</p>
        <h1 style="margin:0 0 18px;font-size:24px;">{{ $status === 'accepted' ? 'Document accepted' : 'Document needs revision' }}</h1>
        <p style="font-size:15px;line-height:1.7;"><strong>{{ $documentLabel }}</strong> for <strong>{{ $projectTitle }}</strong> was reviewed. Current version: {{ $version }}.</p>
        @if($status === 'needs_revision' && $comment)
            <div style="margin:18px 0;padding:14px 16px;border-left:4px solid #b42318;background:#fff5f4;font-size:15px;line-height:1.7;"><strong>Reviewer’s comment:</strong><br>{{ $comment }}</div>
            <p style="font-size:15px;line-height:1.7;">Sign in to the portal to upload a corrected document before the bid submission deadline. Your previous version remains in the revision history.</p>
        @else
            <p style="font-size:15px;line-height:1.7;">The BAC accepted this document. Sign in to view its review status and history.</p>
        @endif
        <p><a href="{{ $myBidsUrl }}" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#145c49;color:#fff;text-decoration:none;font-weight:700;">View My Bids</a></p>
        <p style="margin-top:24px;color:#64748b;font-size:13px;">SJBAC Procurement Portal</p>
    </main>
</body>
</html>