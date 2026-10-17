{{--
    Printable acknowledgment that the BAC received a bid (Submission status → Print acknowledgment).
    It proves the time of receipt and what was filed; the bid price is left out because it stays sealed.
--}}
@php
    $tz = config('bac-office.display_timezone', 'Asia/Manila');
    $project = $bid->project;
    $bidder = $bid->user;
    $submittedAt = ($bid->submitted_at ?? $bid->created_at)?->copy()->timezone($tz);
    $opening = $project?->schedule?->bid_opening_date?->copy()->timezone($tz);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acknowledgment {{ $bid->receipt_no ?: '#'.$bid->id }} - SJBAC</title>
    <style>
        body { margin: 0; padding: 24px; background: #f4f7f5; font-family: Inter, Arial, sans-serif; color: #1b2420; }
        .sheet { max-width: 760px; margin: 0 auto; padding: 36px 40px; border: 1px solid #dfe6e1; border-radius: 14px; background: #fff; }
        .head { display: flex; align-items: center; gap: 14px; padding-bottom: 16px; border-bottom: 2px solid #1f5c45; }
        .head img { width: 54px; height: 54px; }
        .head p { margin: 0; color: #4b5b53; font-size: 13px; }
        .head h1 { margin: 2px 0 0; font-size: 19px; }
        h2 { margin: 26px 0 8px; font-size: 21px; text-align: center; letter-spacing: .02em; }
        .lead { margin: 0 0 20px; color: #4b5b53; font-size: 14px; text-align: center; }
        dl { display: grid; grid-template-columns: 200px 1fr; gap: 8px 14px; margin: 0; font-size: 14px; }
        dt { color: #5f6b65; font-weight: 600; }
        dd { margin: 0; }
        .mono { font-family: 'IBM Plex Mono', Consolas, monospace; }
        table { width: 100%; margin-top: 8px; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e3e8e4; text-align: left; }
        th { color: #5f6b65; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .note { margin-top: 22px; padding: 12px 14px; border-radius: 10px; background: #f1f7f4; color: #2d3a34; font-size: 12.5px; line-height: 1.6; }
        .foot { margin-top: 24px; color: #5f6b65; font-size: 12px; }
        .actions { max-width: 760px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 8px; }
        .actions button, .actions a { padding: 9px 16px; border: 1px solid #1f5c45; border-radius: 9px; background: #1f5c45; color: #fff; font: 600 14px Inter, Arial, sans-serif; text-decoration: none; cursor: pointer; }
        .actions a { background: #fff; color: #1f5c45; }
        @media print { body { padding: 0; background: #fff; } .sheet { border: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('bidder.bidding-track', ['bid' => $bid->id]) }}">Back</a>
        <button type="button" onclick="window.print()">Print or save as PDF</button>
    </div>
    <main class="sheet">
        <div class="head">
            <img src="{{ asset('favicon-bac-192.png') }}" alt="">
            <div>
                <p>Municipality of San Jose, Occidental Mindoro</p>
                <h1>Bids and Awards Committee</h1>
            </div>
        </div>

        <h2>Acknowledgment of bid submission</h2>
        <p class="lead">This confirms that the BAC received the bid below through the SJBAC online portal.</p>

        <dl>
            <dt>Receipt No.</dt><dd class="mono">{{ $bid->receipt_no ?: 'Recorded by the BAC' }}</dd>
            <dt>Received on</dt><dd>{{ $submittedAt?->format('F d, Y · h:i:s A') ?? '—' }} (Philippine time)</dd>
            <dt>Bidder</dt><dd>{{ $bidder?->company ?: $bidder?->name }}</dd>
            <dt>Project</dt><dd>{{ $project?->title }}</dd>
            <dt>Reference No.</dt><dd class="mono">{{ $project?->reference_no ?: '—' }}</dd>
            <dt>Mode of procurement</dt><dd>{{ $project?->mode()->label() }}</dd>
            <dt>Bid opening</dt><dd>{{ $opening?->format('F d, Y · h:i A') ?? 'To be announced' }}</dd>
        </dl>

        @if($bid->documents->isNotEmpty())
            <table>
                <thead><tr><th>Component</th><th>Document</th><th>File</th></tr></thead>
                <tbody>
                    @foreach($bid->documents->sortBy('component') as $document)
                        <tr>
                            <td>{{ ucfirst((string) $document->component) }}</td>
                            <td>{{ $document->label }}</td>
                            <td>{{ $document->original_name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="note">The bid price is not shown: the financial component stays sealed until the BAC opens it. This acknowledgment only records the receipt of the bid; it is not an evaluation of its contents.</p>
        <p class="foot">Printed {{ now($tz)->format('F d, Y · h:i A') }} · SJBAC Procurement Portal</p>
    </main>
</body>
</html>
