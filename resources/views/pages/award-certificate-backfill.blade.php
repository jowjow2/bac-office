<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 34px 42px; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; margin: 0; }
        .header { border-bottom: 3px solid #1d4ed8; padding-bottom: 16px; }
        .office { color: #1d4ed8; font-size: 12px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 24px; margin: 8px 0 4px; }
        .subtitle { color: #475569; font-size: 11px; }
        .reference { background: #eff6ff; color: #1e3a8a; font-size: 13px; font-weight: bold; margin-top: 24px; padding: 12px 14px; }
        .intro { font-size: 12px; line-height: 1.6; margin: 24px 0 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #cbd5e1; padding: 10px 8px; text-align: left; vertical-align: top; }
        th { color: #475569; font-size: 10px; text-transform: uppercase; width: 36%; }
        td { font-size: 12px; }
        .status { color: #047857; font-weight: bold; text-transform: uppercase; }
        .footer { color: #64748b; font-size: 9px; line-height: 1.5; margin-top: 28px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="office">SJBAC</div>
        <h1>Certificate of Award</h1>
        <div class="subtitle">System-generated certificate from the official procurement award record</div>
    </div>

    <div class="reference">BAC Award Reference: {{ $award->certificate_number }}</div>

    <p class="intro">
        This certifies that the award below is recorded in the SJBAC procurement system.
        Its current status must be confirmed through the live public verification page.
    </p>

    <table>
        <tr><th>Project</th><td>{{ $award->project?->title ?: 'N/A' }}</td></tr>
        <tr><th>Project Number</th><td>{{ $award->project?->project_number ?: ('Project #' . $award->project_id) }}</td></tr>
        <tr><th>Winning Bidder</th><td>{{ $award->bid?->user?->company ?: ($award->bid?->user?->name ?: 'N/A') }}</td></tr>
        <tr><th>Approved Budget (ABC)</th><td>₱{{ number_format((float) ($award->project?->approved_budget ?: $award->project?->budget ?: 0), 2) }}</td></tr>
        <tr><th>Contract Amount</th><td>₱{{ number_format((float) $award->contract_amount, 2) }}</td></tr>
        <tr><th>Award Date</th><td>{{ optional($award->contract_date)->format('F j, Y') ?: 'N/A' }}</td></tr>
        <tr><th>Status</th><td class="status">{{ ucfirst($award->certificate_status ?: $award->status ?: 'unknown') }}</td></tr>
    </table>

    <div class="footer">
        This document contains business-relevant award information only. Scan the QR code or visit the official
        verification URL to confirm the current status and live record details.
    </div>
</body>
</html>
