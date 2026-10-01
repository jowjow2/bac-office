<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BAC Report Analytics</title>
    <style>
        @page { margin: 24px; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        h2 { margin: 20px 0 8px; font-size: 13px; color: #1b2420; }
        p { margin: 0 0 4px; color: #6b736e; }
        .meta { margin: 0 0 14px; color: #6b736e; }
        .filters { margin-bottom: 14px; padding: 8px 10px; border: 1px solid #dbe3ed; background: #faf8f3; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px; }
        .card { width: 16.66%; padding: 10px; border: 1px solid #dbe3ed; background: #fff; }
        .card strong { display: block; margin-top: 5px; font-size: 17px; color: #1b2420; }
        .card span { color: #6b736e; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 7px 8px; border: 1px solid #dbe3ed; text-align: left; }
        th { background: #172033; color: #fff; font-size: 9px; text-transform: uppercase; }
        td { color: #33403a; }
        .section-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 0 -8px; }
        .section-grid td { width: 50%; vertical-align: top; border: 0; padding: 0 8px; }
        .empty { color: #99a19c; }
    </style>
</head>
<body>
    <h1>Report Analytics</h1>
    <p>BAC procurement performance and monitoring dashboard</p>
    <div class="meta">Generated {{ now()->format('M d, Y h:i A') }}</div>
    <div class="filters">
        Filters: {{ $filters['date_from'] ?: 'All dates' }} to {{ $filters['date_to'] ?: 'All dates' }}
        &nbsp; | &nbsp; {{ $filters['status_label'] }}
        &nbsp; | &nbsp; {{ $filters['procurement_type_label'] }}
    </div>

    <table class="grid">
        <tr>
            @foreach($summaryCards as $card)
                <td class="card"><span>{{ $card['label'] }}</span><strong>{{ number_format((int) $card['value']) }}</strong></td>
            @endforeach
        </tr>
    </table>

                <h2>Procurement Status Distribution</h2>
    <table>
        <thead><tr><th>Status</th><th>Projects</th></tr></thead>
        <tbody>
            @foreach($procurementStatusDistribution as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

                <h2>Monthly Procurement Activity</h2>
    <table>
        <thead><tr><th>Month</th><th>Projects</th><th>Bids</th><th>Awards</th></tr></thead>
        <tbody>
            @foreach($monthlyActivity as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ $row['projects'] }}</td><td>{{ $row['bids'] }}</td><td>{{ $row['awards'] }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <table class="section-grid">
        <tr>
            <td>
                <h2>Bids per Project</h2>
                <table><thead><tr><th>Project</th><th>Bids</th></tr></thead><tbody>
                    @forelse($bidsPerProject as $row)<tr><td>{{ $row['full_label'] }}</td><td>{{ $row['value'] }}</td></tr>@empty<tr><td colspan="2" class="empty">No data</td></tr>@endforelse
                </tbody></table>
            </td>
            <td>
                <h2>Bid Result Distribution</h2>
                <table><thead><tr><th>Result</th><th>Bids</th></tr></thead><tbody>
                    @foreach($bidResultDistribution as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>@endforeach
                </tbody></table>
            </td>
        </tr>
        <tr>
            <td>
                <h2>ABC vs Winning Bid Amount</h2>
                <table><thead><tr><th>Project</th><th>ABC</th><th>Winning bid</th></tr></thead><tbody>
                    @forelse($abcVsWinning as $row)<tr><td>{{ $row['full_label'] }}</td><td>₱{{ number_format($row['abc'], 2) }}</td><td>₱{{ number_format($row['winning'], 2) }}</td></tr>@empty<tr><td colspan="3" class="empty">No awarded bid amounts</td></tr>@endforelse
                </tbody></table>
            </td>
            <td>
                <h2>Bidder Participation</h2>
                <table><thead><tr><th>Bidder</th><th>Bids</th></tr></thead><tbody>
                    @forelse($bidderParticipation as $row)<tr><td>{{ $row['full_label'] }}</td><td>{{ $row['value'] }}</td></tr>@empty<tr><td colspan="2" class="empty">No data</td></tr>@endforelse
                </tbody></table>
            </td>
        </tr>
    </table>

    <h2>Monitoring</h2>
    <table>
        <thead><tr><th>Queue</th><th>Count</th></tr></thead>
        <tbody>
            <tr><td>Upcoming Deadlines</td><td>{{ $monitoring['upcoming_deadlines']['count'] }}</td></tr>
            <tr><td>Overdue Projects</td><td>{{ $monitoring['overdue_projects']['count'] }}</td></tr>
            <tr><td>Pending Bidder Validations</td><td>{{ $monitoring['pending_bidder_validations']['count'] }}</td></tr>
            <tr><td>Projects Awaiting BAC Evaluation</td><td>{{ $monitoring['awaiting_bac_evaluation']['count'] }}</td></tr>
        </tbody>
    </table>
</body>
</html>
