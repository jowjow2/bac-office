<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidding fee collection report</title>
    <style>
        :root { --ink: #1b2420; --muted: #5d6b64; --line: #dfe6e1; --soft: #f5f8f6; --green: #1f5c45; --green-soft: #e8f3ee; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--soft); color: var(--ink); font: 14px/1.5 'Inter', system-ui, -apple-system, 'Segoe UI', Arial, sans-serif; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 24px; border-bottom: 1px solid var(--line); background: #fff; }
        .bar a, .bar button { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 1px solid #cfd8d3; border-radius: 9px; background: #fff; color: #2d3a34; font: 600 13px/1 inherit; text-decoration: none; cursor: pointer; }
        .bar .primary { border-color: var(--green); background: var(--green); color: #fff; }
        .bar .spacer { flex: 1; }
        .bar form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
        .bar label { color: var(--muted); font-size: 12px; font-weight: 600; }
        .bar input[type=date] { height: 36px; padding: 0 10px; border: 1px solid #cfd8d3; border-radius: 9px; font: inherit; color: var(--ink); }
        .chips a.is-on { border-color: var(--green); background: var(--green-soft); color: var(--green); }
        .sheet { max-width: 980px; margin: 24px auto; padding: 36px 40px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
        .head { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 16px; border-bottom: 2px solid var(--green); }
        .head h1 { margin: 0; font-size: 21px; letter-spacing: -.01em; }
        .head p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .head .right { text-align: right; color: var(--muted); font-size: 12.5px; }
        .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 20px 0; }
        .kpi { padding: 14px 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--soft); }
        .kpi small { display: block; color: var(--muted); font-size: 12px; font-weight: 600; }
        .kpi strong { display: block; margin-top: 4px; font-size: 22px; font-variant-numeric: tabular-nums; }
        .kpi.is-total { border-color: #bcd9cc; background: var(--green-soft); }
        h2 { margin: 26px 0 10px; font-size: 14px; letter-spacing: .02em; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 9px 10px; border-bottom: 1px solid var(--line); background: var(--soft); color: var(--muted); font-size: 11.5px; font-weight: 700; text-align: left; }
        td { padding: 9px 10px; border-bottom: 1px solid #eef2ef; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tfoot td { border-top: 2px solid var(--ink); border-bottom: 0; font-weight: 700; }
        .sub { display: block; color: var(--muted); font-size: 12px; }
        .empty { padding: 30px; border: 1px dashed #cfd8d3; border-radius: 12px; color: var(--muted); text-align: center; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 56px; }
        .sign div { padding-top: 6px; border-top: 1px solid var(--ink); font-size: 12.5px; color: var(--muted); }
        .sign strong { display: block; color: var(--ink); }
        @media (max-width: 700px) { .sheet { margin: 12px; padding: 20px 16px; } .kpis { grid-template-columns: 1fr; } .head { flex-direction: column; } .head .right { text-align: left; } .sign { gap: 24px; } table { display: block; overflow-x: auto; } }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $label = $all ? 'All dates' : ($from->isSameDay($to) ? $from->format('F j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y'));
    $today = now($zone)->startOfDay();
    $presets = [
        'Today' => ['from' => $today->toDateString(), 'to' => $today->toDateString()],
        'This month' => ['from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
        'This year' => ['from' => $today->copy()->startOfYear()->toDateString(), 'to' => $today->toDateString()],
    ];
@endphp

<div class="bar">
    <a href="{{ route($routePrefix.'.payments') }}">&larr; Back to payments</a>
    <span class="chips" style="display:inline-flex; gap:8px; flex-wrap:wrap;">
        @foreach($presets as $name => $range)
            <a href="{{ route($routePrefix.'.payments.report', $range) }}" class="{{ ! $all && $from->toDateString() === $range['from'] && $to->toDateString() === $range['to'] ? 'is-on' : '' }}">{{ $name }}</a>
        @endforeach
        <a href="{{ route($routePrefix.'.payments.report', ['all' => 1]) }}" class="{{ $all ? 'is-on' : '' }}">All time</a>
    </span>
    <form method="GET" action="{{ route($routePrefix.'.payments.report') }}">
        <label for="from">From</label><input type="date" id="from" name="from" value="{{ ($from ?? $today)->toDateString() }}">
        <label for="to">To</label><input type="date" id="to" name="to" value="{{ ($to ?? $today)->toDateString() }}">
        <button type="submit">Apply</button>
    </form>
    <span class="spacer"></span>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<main class="sheet">
    <header class="head">
        <div>
            <h1>Bidding documents fee collection report</h1>
            <p>Bids and Awards Committee &middot; San Jose, Occidental Mindoro</p>
            <p><strong>Period:</strong> {{ $label }}</p>
        </div>
        <div class="right">Generated {{ now($zone)->format('M j, Y g:i A') }}<br>by {{ $user->name }}</div>
    </header>

    <section class="kpis" aria-label="Summary">
        <div class="kpi is-total"><small>Total collected</small><strong>{{ $peso($total) }}</strong></div>
        <div class="kpi"><small>Official Receipts</small><strong>{{ number_format($payments->count()) }}</strong></div>
        <div class="kpi"><small>Projects with payments</small><strong>{{ number_format($byProject->count()) }}</strong></div>
    </section>

    @if($payments->isEmpty())
        <div class="empty">No payments were recorded in this period.</div>
    @else
        <h2>Collection by project</h2>
        <table>
            <thead><tr><th>Project</th><th class="num">Fee</th><th class="num">Payments</th><th class="num">Collected</th></tr></thead>
            <tbody>
                @foreach($byProject as $row)
                    <tr>
                        <td>{{ $row['title'] }}<span class="sub">{{ $row['reference'] }}</span></td>
                        <td class="num">{{ $row['fee'] !== null ? $peso($row['fee']) : '—' }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $peso($row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="2">Total</td><td class="num">{{ $payments->count() }}</td><td class="num">{{ $peso($total) }}</td></tr></tfoot>
        </table>

        @if($byDay->count() > 1)
            <h2>Collection by day</h2>
            <table>
                <thead><tr><th>Date</th><th class="num">Payments</th><th class="num">Collected</th></tr></thead>
                <tbody>
                    @foreach($byDay as $row)
                        <tr><td>{{ $row['date']->format('M j, Y (D)') }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $peso($row['amount']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Official Receipts</h2>
        <table>
            <thead><tr><th>OR No.</th><th>Date paid</th><th>Bidder</th><th>Project</th><th>Recorded by</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach($payments as $payment)
                    <tr>
                        <td><strong>{{ $payment->or_number }}</strong></td>
                        <td>{{ $payment->paid_at->format('M j, Y') }}</td>
                        <td>{{ $payment->bidder?->company ?: ($payment->bidder?->name ?? 'Deleted bidder') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($payment->project?->title ?? 'Deleted project', 50) }}<span class="sub">{{ $payment->project?->reference_no }}</span></td>
                        <td>{{ $payment->recorder?->name ?? '—' }}</td>
                        <td class="num">{{ $peso($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="5">Total</td><td class="num">{{ $peso($total) }}</td></tr></tfoot>
        </table>
    @endif

    <div class="sign">
        <div><strong>Prepared by</strong>{{ $user->name }}</div>
        <div><strong>Noted by</strong>BAC Chairperson</div>
    </div>
</main>
</body>
</html>
