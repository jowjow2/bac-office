<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Reports</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 32px; }
        h1, h2 { margin: 0 0 10px; }
        p { margin: 0 0 16px; color: #475569; }
        .meta { margin-bottom: 24px; font-size: 14px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
        .card { border: 1px solid #dbe1ea; border-radius: 12px; padding: 16px; }
        .card strong { display: block; font-size: 24px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #dbe1ea; padding: 10px 12px; text-align: left; font-size: 14px; }
        th { background: #f8fafc; }
        @media print { body { margin: 16px; } }
    </style>
</head>
<body onload="window.print()">
    <h1>Staff Reports &amp; Analytics</h1>
    <p>Generate and export procurement reports</p>

    <div class="meta">Generated at: {{ now()->format('M d, Y h:i A') }}</div>

    <div class="grid">
        <div class="card">
            <strong>P{{ number_format($totalBudgetAllocated, 2) }}</strong>
            <div>Total Budget Allocated</div>
        </div>
        <div class="card">
            <strong>P{{ number_format($totalAwardedAmount, 2) }}</strong>
            <div>Total Awarded</div>
        </div>
        <div class="card">
            <strong>P{{ number_format($governmentSavings, 2) }}</strong>
            <div>Gov't Savings</div>
        </div>
        <div class="card">
            <strong>{{ $bidParticipation }}</strong>
            <div>Bid Participation</div>
        </div>
    </div>

    <h2>Project Summary Report</h2>
    <table>
        <thead>
            <tr>
                <th>Project</th>
                <th>Budget</th>
                <th>Bids</th>
                <th>Awarded</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assignedProjects as $project)
                <tr>
                    <td>{{ $project->title }}</td>
                    <td>P{{ number_format((float) $project->budget, 2) }}</td>
                    <td>{{ $project->bids_count }}</td>
                    <td>{{ $project->status === 'awarded' ? 'Yes' : '-' }}</td>
                    <td>{{ ucfirst($project->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Bidder Performance</h2>
    <table>
        <thead>
            <tr>
                <th>Bidder</th>
                <th>Total Bids</th>
                <th>Approved</th>
                <th>Won</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bidderPerformance as $bidder)
                <tr>
                    <td>{{ $bidder['bidder'] }}</td>
                    <td>{{ $bidder['total_bids'] }}</td>
                    <td>{{ $bidder['approved'] }}</td>
                    <td>{{ $bidder['won'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
