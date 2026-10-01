<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentType === 'resolution' ? 'BAC Resolution' : 'Post-Qualification Report' }} - {{ $project->reference_no }}</title>
    <style>
        @page { margin: 22mm 20mm 20mm; }
        body { color: #1c2823; font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; line-height: 1.5; }
        .header { text-align: center; border-bottom: 2px solid #174f40; padding-bottom: 12px; margin-bottom: 22px; }
        .header p { margin: 0; }
        .eyebrow { color: #50645c; font-size: 9pt; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: 16pt; margin: 5px 0 2px; text-transform: uppercase; }
        h2 { color: #174f40; font-size: 12pt; margin: 20px 0 7px; }
        p { margin: 8px 0; }
        .meta { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        .meta td { border: 1px solid #d6dfd9; padding: 7px 9px; vertical-align: top; }
        .meta td:first-child { background: #f3f6f3; color: #52645c; font-weight: bold; width: 31%; }
        .signature-grid { width: 100%; margin-top: 44px; border-collapse: separate; border-spacing: 18px 0; }
        .signature-grid td { width: 50%; text-align: center; vertical-align: bottom; padding-top: 34px; }
        .signature-line { border-top: 1px solid #28352f; padding-top: 5px; }
        .muted { color: #64736b; font-size: 9pt; }
        .callout { background: #f4f7f4; border-left: 3px solid #174f40; padding: 9px 12px; margin: 12px 0; }
        ul { margin: 5px 0 10px; padding-left: 20px; }
        li { margin: 4px 0; }
        .footer { color: #64736b; border-top: 1px solid #d6dfd9; font-size: 8pt; margin-top: 28px; padding-top: 7px; }
    </style>
</head>
<body>
    <header class="header">
        <p>Republic of the Philippines</p>
        <p class="eyebrow">Bids and Awards Committee</p>
        <h1>{{ $documentType === 'resolution' ? 'BAC Resolution' : 'Post-Qualification Report' }}</h1>
        <p>{{ $project->title }}</p>
    </header>

    <table class="meta">
        <tr><td>Project reference</td><td>{{ $project->reference_no ?: 'Not recorded' }}</td></tr>
        <tr><td>Procurement mode</td><td>{{ $modeLabel }}</td></tr>
        <tr><td>Approved Budget for the Contract</td><td>₱{{ number_format((float) $project->budget, 2) }}</td></tr>
        <tr><td>Winning bidder under review</td><td>{{ $bidderName }}</td></tr>
        <tr><td>Bid amount</td><td>₱{{ number_format((float) $bid->bid_amount, 2) }}</td></tr>
        <tr><td>Award criterion</td><td>{{ $awardCriterion }}</td></tr>
        <tr><td>BAC Resolution reference</td><td>{{ $resolutionNo }} · {{ $resolutionDate }}</td></tr>
    </table>

    @if($documentType === 'resolution')
        
        <p><strong>A RESOLUTION RECOMMENDING THE AWARD OF THE CONTRACT FOR {{ mb_strtoupper($project->title) }}</strong></p>

        <h2>Whereas</h2>
        <p>The Municipality, through its Bids and Awards Committee, conducted the procurement for the project identified above under the procurement mode and approved budget stated in this resolution;</p>
        <p>The bids were evaluated using the award criterion stated in the Bidding Documents, and {{ $bidderName }} was identified as the bidder for post-qualification based on the recorded evaluation;</p>
        <p>The post-qualification record for {{ $bidderName }} shows a passed result, subject to the findings and supporting records kept in the procurement file;</p>

        <h2>Now, therefore, the BAC resolves</h2>
        <p>To recommend to the Head of the Procuring Entity the award of the contract for <strong>{{ $project->title }}</strong> to <strong>{{ $bidderName }}</strong> in the amount of <strong>₱{{ number_format((float) $bid->bid_amount, 2) }}</strong>, subject to the Head of the Procuring Entity’s review and approval and completion of the required procurement records.</p>
        <p>Approved this {{ $resolutionDate }}.</p>

        <table class="signature-grid">
            <tr>
                <td><div class="signature-line"><strong>BAC Chairperson</strong><br><span class="muted">Signature over printed name</span></div></td>
                <td><div class="signature-line"><strong>BAC Member</strong><br><span class="muted">Signature over printed name</span></div></td>
            </tr>
            <tr>
                <td><div class="signature-line"><strong>BAC Member</strong><br><span class="muted">Signature over printed name</span></div></td>
                <td><div class="signature-line"><strong>BAC Member</strong><br><span class="muted">Signature over printed name</span></div></td>
            </tr>
        </table>
        <p class="callout"><strong>For BAC review and signatures.</strong> This generated document records a recommendation only. It is not the HoPE’s approval, Notice of Award, contract, or Notice to Proceed.</p>
    @else
        <div class="callout"><strong>Purpose.</strong> This report summarizes the recorded bid evaluation and post-qualification findings supporting the BAC recommendation. Refer to the official procurement file and original bidding documents for source records.</div>

        @if(filled($evaluationFindings))
            <h2>Detailed bid evaluation</h2>
            <p>{{ $evaluationFindings }}</p>
            <p class="muted">Recorded on {{ $evaluation?->created_at?->timezone($timezone)?->format('F d, Y h:i A') ?? 'Not recorded' }} by {{ $evaluation?->creator?->name ?? 'Not recorded' }}.</p>
        @endif

        <h2>Post-qualification result</h2>
        <p><strong>Result:</strong> Passed</p>
        <p><strong>Recorded on:</strong> {{ $postQualification?->created_at?->timezone($timezone)?->format('F d, Y h:i A') ?? 'Not recorded' }} ({{ $timezone }})</p>
        <p><strong>Recorded by:</strong> {{ $postQualification?->creator?->name ?? 'Not recorded' }}</p>

        @if(filled($qualificationBasis))
            <h2>Qualification basis</h2>
            <p>{{ $qualificationBasis }}</p>
        @endif

        @if($criteria->isNotEmpty())
            <h2>Recorded evaluation criteria</h2>
            <ul>@foreach($criteria as $criterion)<li>{{ is_array($criterion) ? ($criterion['label'] ?? $criterion['name'] ?? json_encode($criterion)) : $criterion }}</li>@endforeach</ul>
        @endif

        @if($criterionResults->isNotEmpty())
            <h2>Recorded criterion findings</h2>
            <ul>@foreach($criterionResults as $row)<li>{{ $row['key'] ?? 'Criterion' }}: {{ $row['result'] }}</li>@endforeach</ul>
        @endif

        <h2>BAC post-qualification findings</h2>
        <p>{{ filled($postQualificationFindings) ? $postQualificationFindings : 'No detailed findings were recorded.' }}</p>

        <h2>Recommendation record</h2>
        <p>The BAC recorded a recommendation for award to {{ $bidderName }} at ₱{{ number_format((float) $bid->bid_amount, 2) }} under {{ $resolutionNo }}, dated {{ $resolutionDate }}. The HoPE’s decision is recorded separately.</p>
        <p class="muted">This report is generated from information recorded in the system on {{ $generatedAt }} ({{ $timezone }}). It does not replace signed evaluation records, original supporting documents, or the BAC’s official resolution.</p>
    @endif

    <div class="footer">SJ BAC Procurement System · Generated {{ $generatedAt }} ({{ $timezone }}) · Internal procurement record</div>
</body>
</html>
