<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Request {{ $procurementRequest->reference_no }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ece9; color: #111; font: 12.5px/1.45 "Times New Roman", Georgia, serif; }
        .bar { position: sticky; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 20px; background: #1e3a31; color: #fff; font: 13px/1.3 system-ui, sans-serif; }
        .bar button, .bar a { padding: 7px 14px; border: 1px solid rgba(255, 255, 255, .4); border-radius: 7px; background: transparent; color: #fff; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .bar button { background: #fff; color: #1e3a31; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 14mm 12mm; background: #fff; box-shadow: 0 2px 14px rgba(0, 0, 0, .15); }
        .head { text-align: center; }
        .head p { margin: 0; }
        .head .entity { font-size: 14px; font-weight: 700; text-transform: uppercase; }
        .head h1 { margin: 10px 0 2px; font-size: 18px; letter-spacing: .08em; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin-top: 12px; border: 1px solid #111; }
        .meta div { padding: 5px 8px; border-bottom: 1px solid #111; }
        .meta div:nth-child(odd) { border-right: 1px solid #111; }
        .meta div:nth-last-child(-n+2) { border-bottom: 0; }
        .meta b { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        table { width: 100%; margin-top: 12px; border-collapse: collapse; }
        th, td { padding: 5px 8px; border: 1px solid #111; vertical-align: top; }
        th { background: #f1f1f1; font-size: 11px; letter-spacing: .03em; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.ctr { text-align: center; }
        tfoot td { font-weight: 700; }
        .block { margin-top: 12px; padding: 6px 8px; border: 1px solid #111; }
        .block b { display: block; font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; }
        .block p { margin: 3px 0 0; white-space: pre-line; }
        .sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; margin-top: 14px; border: 1px solid #111; }
        .sign div { min-height: 104px; padding: 6px 8px; border-right: 1px solid #111; }
        .sign div:last-child { border-right: 0; }
        .sign b { display: block; font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase; }
        .sign .line { margin-top: 44px; padding-top: 3px; border-top: 1px solid #111; text-align: center; font-weight: 700; }
        .sign .role { text-align: center; font-size: 11px; }
        .foot { margin-top: 10px; color: #444; font-size: 10.5px; text-align: center; }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 14mm; box-shadow: none; }
        }
    </style>
</head>
<body>
@php
    $money = fn ($amount) => number_format((float) $amount, 2);
    $rows = collect($rows);
    $total = $rows->isNotEmpty() ? $rows->sum('total') : (float) $procurementRequest->estimated_cost;
    $categories = ['goods' => 'Goods', 'services' => 'General support services', 'infrastructure' => 'Infrastructure', 'consultancy' => 'Consulting services'];
    $filed = $procurementRequest->submitted_at ?? $procurementRequest->created_at;
    $tz = config('bac-office.display_timezone');
    $requesterName = $procurementRequest->requester?->name ?? auth()->user()->name;
    $requesterPosition = $procurementRequest->requester?->position ?? null;
@endphp

<div class="bar">
    <span>Purchase Request {{ $procurementRequest->reference_no }} · print or save as PDF</span>
    <span style="display:flex; gap:8px">
        <a href="{{ auth()->user()->role === 'staff' ? route('staff.requests') : route('admin.requests') }}">Back</a>
        <button type="button" onclick="window.print()">Print</button>
    </span>
</div>

<main class="sheet">
    <header class="head">
        <p class="entity">{{ config('bac-office.procuring_entity') }}</p>
        <p>{{ $procurementRequest->end_user_office }}</p>
        <h1>Purchase Request</h1>
    </header>

    <section class="meta" aria-label="Request details">
        <div><b>PR No.</b>{{ $procurementRequest->reference_no }}</div>
        <div><b>Date</b>{{ $filed?->timezone($tz)->format('F j, Y') }}</div>
        <div><b>Office / Section</b>{{ $procurementRequest->end_user_office }}</div>
        <div><b>Fund source</b>{{ $procurementRequest->fund_source ?: '—' }}</div>
        <div><b>Category</b>{{ $categories[$procurementRequest->category] ?? '—' }}</div>
        <div><b>Delivery / completion period</b>{{ $procurementRequest->delivery_period ?: '—' }}</div>
    </section>

    <table>
        <thead>
            <tr>
                <th class="ctr" style="width:34px">No.</th>
                <th>Item description and specifications</th>
                <th class="ctr" style="width:52px">Unit</th>
                <th class="num" style="width:56px">Qty</th>
                <th class="num" style="width:92px">Unit cost (₱)</th>
                <th class="num" style="width:104px">Total cost (₱)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="ctr">{{ $index + 1 }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="ctr">{{ $row['unit'] ?: '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($row['quantity'], 2), '0'), '.') }}</td>
                    <td class="num">{{ $money($row['unit_cost']) }}</td>
                    <td class="num">{{ $money($row['total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="ctr">1</td>
                    <td>{{ $procurementRequest->title }}</td>
                    <td class="ctr">{{ $procurementRequest->unit ?: '—' }}</td>
                    <td class="num">—</td>
                    <td class="num">—</td>
                    <td class="num">{{ $money($procurementRequest->estimated_cost) }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="num">Estimated total cost</td>
                <td class="num">₱ {{ $money($total) }}</td>
            </tr>
        </tfoot>
    </table>

    @if(filled($procurementRequest->specifications))
        <section class="block"><b>Specifications / Terms of Reference</b><p>{{ $procurementRequest->specifications }}</p></section>
    @endif
    @if(filled($procurementRequest->justification))
        <section class="block"><b>Purpose / Justification</b><p>{{ $procurementRequest->justification }}</p></section>
    @endif

    <section class="sign" aria-label="Signatures">
        <div>
            <b>Requested by</b>
            <p class="line">{{ $requesterName }}</p>
            <p class="role">{{ $requesterPosition ?: 'Requesting officer' }}<br>{{ $procurementRequest->end_user_office }}</p>
        </div>
        <div>
            <b>Funds / PPMP-APP checked by</b>
            <p class="line">&nbsp;</p>
            <p class="role">Budget / Procurement Office</p>
        </div>
        <div>
            <b>Approved by</b>
            <p class="line">&nbsp;</p>
            <p class="role">Head of the Procuring Entity or authorized representative</p>
        </div>
    </section>

    <p class="foot">Generated from the SJBAC Procurement Portal · {{ now()->timezone($tz)->format('M j, Y g:i A') }} · {{ $procurementRequest->statusLabel() }}</p>
</main>
</body>
</html>
