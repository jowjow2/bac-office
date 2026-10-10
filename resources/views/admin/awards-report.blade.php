<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awards and contracts report</title>
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
        .sheet { max-width: 1040px; margin: 24px auto; padding: 36px 40px; border: 1px solid var(--line); border-radius: 14px; background: #fff; }
        body.is-embed { background: #fff; }
        body.is-embed .sheet { max-width: none; margin: 0; border: 0; border-radius: 0; }
        .head { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 16px; border-bottom: 2px solid var(--green); }
        .head h1 { margin: 0; font-size: 21px; letter-spacing: -.01em; }
        .head p { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        .head .right { text-align: right; color: var(--muted); font-size: 12.5px; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0; }
        .kpi { padding: 14px 16px; border: 1px solid var(--line); border-radius: 12px; background: var(--soft); }
        .kpi small { display: block; color: var(--muted); font-size: 12px; font-weight: 600; }
        .kpi strong { display: block; margin-top: 4px; font-size: 20px; font-variant-numeric: tabular-nums; }
        .kpi.is-total { border-color: #bcd9cc; background: var(--green-soft); }
        h2 { margin: 26px 0 10px; font-size: 14px; letter-spacing: .02em; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { padding: 9px 10px; border-bottom: 1px solid var(--line); background: var(--soft); color: var(--muted); font-size: 11.5px; font-weight: 700; text-align: left; }
        td { padding: 9px 10px; border-bottom: 1px solid #eef2ef; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tfoot td { border-top: 2px solid var(--ink); border-bottom: 0; font-weight: 700; }
        .sub { display: block; color: var(--muted); font-size: 12px; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 999px; background: var(--green-soft); color: var(--green); font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .pill.is-off { background: #fdf0ee; color: #b42318; }
        .empty { padding: 30px; border: 1px dashed #cfd8d3; border-radius: 12px; color: var(--muted); text-align: center; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; margin-top: 56px; }
        .sign div { padding-top: 6px; border-top: 1px solid var(--ink); font-size: 12.5px; color: var(--muted); }
        .sign strong { display: block; color: var(--ink); }
        @media (max-width: 760px)  .bar { padding: 10px 14px; } .bar form { width: 100%; } .bar .spacer { display: none; } .bar .primary { width: 100%; justify-content: center; } .sheet { margin: 12px; padding: 20px 16px; } .kpis { grid-template-columns: 1fr 1fr; } .head { flex-direction: column; } .head .right { text-align: left; } .sign { gap: 24px; } table { display: block; overflow-x: auto; } }
        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .sheet { max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body class="{{ $embed ? 'is-embed' : '' }}">
@php
    $peso = fn ($value) => '₱'.number_format((float) $value, 2);
    $keep = $embed ? ['embed' => 1] : [];
    $label = $all ? 'All dates' : ($from->isSameDay($to) ? $from->format('F j, Y') : $from->format('M j, Y').' – '.$to->format('M j, Y'));
    $today = now($zone)->startOfDay();
    $presets = [
        'This year' => ['from' => $today->copy()->startOfYear()->toDateString(), 'to' => $today->toDateString()],
        'This month' => ['from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
    ];
@endphp

<div class="bar">
    @unless($embed)<a href="{{ route('admin.awards.index') }}">&larr; Back to awards</a>@endunless
    <span class="chips" style="display:inline-flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('admin.awards.report', ['all' => 1] + $keep) }}" class="{{ $all ? 'is-on' : '' }}">All time</a>
        @foreach($presets as $name => $range)
            <a href="{{ route('admin.awards.report', $range + $keep) }}" class="{{ ! $all && $from->toDateString() === $range['from'] && $to->toDateString() === $range['to'] ? 'is-on' : '' }}">{{ $name }}</a>
        @endforeach
    </span>
    <form method="GET" action="{{ route('admin.awards.report') }}">
        @if($embed)<input type="hidden" name="embed" value="1">@endif
        <label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}">
        <label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}">
        <button type="submit">Apply</button>
    </form>
    <span class="spacer"></span>
    <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<main class="sheet">
    <header class="head">
        <div>
            <h1>Awards and contracts report</h1>
            <p>Bids and Awards Committee &middot; San Jose, Occidental Mindoro</p>
            <p><strong>Period (award date):</strong> {{ $label }}</p>
        </div>
        <div class="right">Generated {{ now($zone)->format('M j, Y g:i A') }}<br>by {{ $user->name }}</div>
    </header>

    <section class="kpis" aria-label="Summary">
        <div class="kpi is-total"><small>Total contract value</small><strong>{{ $peso($contractValue) }}</strong></div>
        <div class="kpi"><small>Awards in force</small><strong>{{ number_format($inForce->count()) }}</strong></div>
        <div class="kpi"><small>Contracts signed</small><strong>{{ number_format($signedCount) }}</strong></div>
        <div class="kpi"><small>Notices to Proceed issued</small><strong>{{ number_format($ntpCount) }}</strong></div>
    </section>

    @if($rows->isEmpty())
        <div class="empty">No awards were recorded in this period.</div>
    @else
        @if($byMode->count() > 0)
            <h2>By procurement mode</h2>
            <table>
                <thead><tr><th>Mode</th><th class="num">Awards</th><th class="num">Contract value</th></tr></thead>
                <tbody>
                    @foreach($byMode as $row)
                        <tr><td>{{ $row['mode'] }}</td><td class="num">{{ $row['count'] }}</td><td class="num">{{ $peso($row['amount']) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><td>Total</td><td class="num">{{ $inForce->count() }}</td><td class="num">{{ $peso($contractValue) }}</td></tr></tfoot>
            </table>
        @endif

        <h2>Awards register</h2>
        <table>
            <thead><tr><th>Project</th><th>Winning bidder</th><th>Award date</th><th>Contract stage</th><th class="num">Contract amount</th></tr></thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ \Illuminate\Support\Str::limit($row['title'], 60) }}<span class="sub">{{ $row['reference'] }}</span></td>
                        <td>{{ $row['bidder'] }}</td>
                        <td>{{ $row['date']?->format('M j, Y') ?? '—' }}</td>
                        <td><span class="pill {{ $row['cancelled'] ? 'is-off' : '' }}">{{ $row['stage'] }}</span></td>
                        <td class="num">{{ $peso($row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="4">Total in force</td><td class="num">{{ $peso($contractValue) }}</td></tr></tfoot>
        </table>
    @endif

    <div class="sign">
        <div><strong>Prepared by</strong>{{ $user->name }}</div>
        <div><strong>Noted by</strong>BAC Chairperson</div>
    </div>
</main>
</body>
</html>
