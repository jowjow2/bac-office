@forelse($loginLogs as $log)
    <tr data-login-log-id="{{ $log->id }}">
        <td>{{ $log->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</td>
        <td>{{ strtoupper($log->login_method) }}</td>
        <td>{{ ucfirst($log->status) }}@if($log->failure_reason) ({{ str_replace('_', ' ', $log->failure_reason) }}) @endif</td>
        <td>{{ $log->ip_address ?? 'N/A' }}</td>
        <td>{{ \Illuminate\Support\Str::limit($log->user_agent ?? 'N/A', 60) }}</td>
    </tr>
@empty
    <tr>
        <td colspan="5" style="color:#64748b;">No login activity recorded yet for this bidder.</td>
    </tr>
@endforelse