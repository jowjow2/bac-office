<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@php
    $tz = config('bac-office.display_timezone', 'Asia/Manila');
    $hasFilters = collect($filters)->filter(fn ($value) => $value !== '')->isNotEmpty();
@endphp
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Audit logs" subtitle="Who did what, to which record, and when (Philippine time)" />

        <main class="dashboard-content">
<style>
    /* #auditLogs outranks the dashboard's !important input, button and heading rules. */
    #auditLogs { display: grid; gap: 16px; font-family: var(--ui-font); color: var(--ui-ink); font-size: 13.5px; }
    #auditLogs *, #auditLogs *::before, #auditLogs *::after { box-sizing: border-box; }
    #auditLogs .fas { font-family: "Font Awesome 6 Free" !important; font-weight: 900 !important; }
    #auditLogs .alog-card { min-width: 0; background: #fff; border: 1px solid var(--ui-line); border-radius: var(--ui-radius-lg); box-shadow: var(--ui-shadow); }

    #auditLogs .alog-filters { display: grid; grid-template-columns: minmax(200px, 2fr) repeat(2, minmax(150px, 1fr)) repeat(2, minmax(130px, 150px)); gap: 10px; align-items: end; padding: 16px 18px; }
    #auditLogs .alog-field { display: grid; gap: 5px; min-width: 0; }
    #auditLogs .alog-field span { color: var(--ui-muted); font-size: 12px; font-weight: 600; }
    #auditLogs .alog-input { width: 100% !important; height: 38px !important; min-height: 0 !important; margin: 0 !important; padding: 0 10px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink) !important; font: inherit !important; font-size: 13px !important; font-weight: 400 !important; box-shadow: none !important; }
    #auditLogs .alog-input:focus { outline: none !important; border-color: var(--ui-primary) !important; box-shadow: var(--ui-focus) !important; }
    #auditLogs .alog-search { position: relative; }
    #auditLogs .alog-search i { position: absolute; left: 11px; bottom: 13px; color: var(--ui-subtle); font-size: 12px; pointer-events: none; }
    #auditLogs .alog-search .alog-input { padding-left: 32px !important; }
    #auditLogs .alog-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
    #auditLogs .alog-actions-left { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    #auditLogs .alog-count { color: var(--ui-muted); font-size: 12.5px; }

    #auditLogs .alog-btn { display: inline-flex !important; align-items: center; justify-content: center; gap: 7px; height: 36px !important; padding: 0 14px !important; border: 1px solid var(--ui-line-strong) !important; border-radius: var(--ui-radius) !important; background: #fff !important; color: var(--ui-ink-2) !important; -webkit-text-fill-color: var(--ui-ink-2) !important; font: inherit !important; font-size: 12.5px !important; font-weight: 600 !important; text-decoration: none !important; box-shadow: none !important; cursor: pointer; }
    #auditLogs .alog-btn:hover { background: var(--ui-surface-2) !important; }
    #auditLogs .alog-btn.is-primary { border-color: var(--ui-primary) !important; background: var(--ui-primary) !important; color: #fff !important; -webkit-text-fill-color: #fff !important; }
    #auditLogs .alog-btn.is-primary:hover { background: var(--ui-primary-hover) !important; }
    #auditLogs .alog-btn[aria-disabled="true"] { pointer-events: none; opacity: .45; }

    #auditLogs .alog-table-wrap { overflow-x: auto; }
    #auditLogs .alog-table { width: 100%; border-collapse: collapse; }
    #auditLogs .alog-table th { padding: 10px 14px; background: var(--ui-surface-2); border-bottom: 1px solid var(--ui-line); color: var(--ui-muted); font-size: 11.5px; font-weight: 600; text-align: left; text-transform: none; letter-spacing: normal; white-space: nowrap; }
    #auditLogs .alog-table td { padding: 12px 14px !important; border-bottom: 1px solid var(--ui-line-soft); vertical-align: top !important; }
    #auditLogs .alog-table tbody tr:hover { background: var(--ui-surface-2); }
    #auditLogs .alog-when { white-space: nowrap; font-variant-numeric: tabular-nums; }
    #auditLogs .alog-sub { display: block; margin-top: 2px; color: var(--ui-muted); font-size: 12px; }
    #auditLogs .alog-action { font-weight: 600; }
    #auditLogs .alog-key { display: block; margin-top: 2px; color: var(--ui-subtle); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; }
    #auditLogs .alog-record a { color: var(--ui-primary) !important; -webkit-text-fill-color: var(--ui-primary) !important; font-weight: 600; text-decoration: none; overflow-wrap: anywhere; }
    #auditLogs .alog-record a:hover { text-decoration: underline; }
    #auditLogs .alog-actor { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
    #auditLogs .alog-actor.is-system { color: var(--ui-warning); font-weight: 600; }

    #auditLogs details.alog-changes summary { color: var(--ui-primary); font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    #auditLogs .alog-change-list { display: grid; gap: 6px; margin: 8px 0 0; padding: 0; list-style: none; min-width: 260px; }
    #auditLogs .alog-change-list li { display: grid; gap: 2px; padding: 7px 9px; border-radius: 8px; background: var(--ui-surface-2); font-size: 12px; }
    #auditLogs .alog-change-list strong { color: var(--ui-ink-2); font-size: 11.5px; }
    #auditLogs .alog-old { color: var(--ui-danger); text-decoration: line-through; overflow-wrap: anywhere; }
    #auditLogs .alog-new { color: #047857; overflow-wrap: anywhere; }
    #auditLogs .alog-meta { color: var(--ui-subtle); font-size: 11.5px; overflow-wrap: anywhere; }
    #auditLogs .alog-none { color: var(--ui-subtle); font-size: 12.5px; }

    #auditLogs .alog-empty { display: grid; justify-items: center; gap: 6px; padding: 40px 16px; color: var(--ui-muted); text-align: center; }
    #auditLogs .alog-empty i { color: var(--ui-subtle); font-size: 22px; }
    #auditLogs .alog-empty strong { color: var(--ui-ink); font-size: 14px; }
    #auditLogs .alog-pager { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; padding: 12px 18px; color: var(--ui-muted); font-size: 12.5px; }
    #auditLogs .alog-pager-links { display: flex; gap: 8px; }

    @media (max-width: 900px) {
        #auditLogs .alog-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        #auditLogs .alog-search { grid-column: 1 / -1; }
    }
    @media (max-width: 640px) {
        #auditLogs .alog-filters { grid-template-columns: minmax(0, 1fr); padding: 14px; }
        /* Rows become cards on phones. */
        #auditLogs .alog-table thead { display: none; }
        #auditLogs .alog-table, #auditLogs .alog-table tbody, #auditLogs .alog-table tr, #auditLogs .alog-table td { display: block; width: 100%; }
        #auditLogs .alog-table tr { padding: 10px 14px; border-bottom: 1px solid var(--ui-line); }
        #auditLogs .alog-table td { padding: 3px 0 !important; border: 0 !important; }
        #auditLogs .alog-change-list { min-width: 0; }
    }
</style>

<div id="auditLogs">
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="alog-card alog-filters" role="search">
        <label class="alog-field alog-search">
            <span>Search</span>
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="{{ $filters['q'] }}" class="alog-input" placeholder="Action, user or record ID">
        </label>
        <label class="alog-field">
            <span>Record type</span>
            <select name="type" class="alog-input">
                <option value="">All records</option>
                @foreach($recordTypes as $value => $label)
                    <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="alog-field">
            <span>Done by</span>
            <select name="actor" class="alog-input">
                <option value="">Everyone</option>
                <option value="system" @selected($filters['actor'] === 'system')>System (automatic)</option>
                @foreach($actors as $actor)
                    <option value="{{ $actor->id }}" @selected($filters['actor'] === (string) $actor->id)>{{ $actor->name }} · {{ ucfirst(str_replace('_', ' ', $actor->role)) }}</option>
                @endforeach
            </select>
        </label>
        <label class="alog-field">
            <span>From</span>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="alog-input">
        </label>
        <label class="alog-field">
            <span>To</span>
            <input type="date" name="to" value="{{ $filters['to'] }}" class="alog-input">
        </label>
        <div class="alog-actions">
            <div class="alog-actions-left">
                <button type="submit" class="alog-btn is-primary"><i class="fas fa-filter" aria-hidden="true"></i> Filter</button>
                @if($hasFilters)
                    <a href="{{ route('admin.audit-logs') }}" class="alog-btn">Clear</a>
                @endif
                <span class="alog-count">
                    {{ number_format($logs->total()) }} {{ $hasFilters ? 'matching' : '' }} {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}{{ $hasFilters ? ' of '.number_format($total) : '' }}
                </span>
            </div>
            <a href="{{ route('admin.audit-logs.export', request()->only(['q', 'type', 'actor', 'from', 'to'])) }}" class="alog-btn" @if($logs->total() === 0) aria-disabled="true" @endif>
                <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
            </a>
        </div>
    </form>

    <section class="alog-card" aria-label="Audit log entries">
        @if($logs->isEmpty())
            <div class="alog-empty">
                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                <strong>{{ $hasFilters ? 'No entries match these filters' : 'No audit entries yet' }}</strong>
                <span>{{ $hasFilters ? 'Change or clear the filters.' : 'Actions on projects, bids, payments and accounts appear here.' }}</span>
            </div>
        @else
            <div class="alog-table-wrap">
                <table class="alog-table">
                    <thead>
                        <tr>
                            <th scope="col">When</th>
                            <th scope="col">Action</th>
                            <th scope="col">Record</th>
                            <th scope="col">By</th>
                            <th scope="col">Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $changes = $log->changes();
                                $at = $log->created_at?->timezone($tz);
                                $url = $log->recordUrl();
                            @endphp
                            <tr>
                                <td class="alog-when">
                                    {{ $at?->format('M d, Y') }}
                                    <span class="alog-sub">{{ $at?->format('h:i:s A') }}</span>
                                </td>
                                <td>
                                    <span class="alog-action">{{ $log->actionLabel() }}</span>
                                    <span class="alog-key">{{ $log->action }}</span>
                                </td>
                                <td class="alog-record">
                                    @if($url)
                                        <a href="{{ $url }}">{{ $log->recordLabel() }}</a>
                                    @else
                                        {{ $log->recordLabel() }}
                                    @endif
                                </td>
                                <td>
                                    @if($log->user_id === null)
                                        <span class="alog-actor is-system"><i class="fas fa-gears" aria-hidden="true"></i> System</span>
                                        <span class="alog-sub">Automatic, by schedule</span>
                                    @else
                                        <span class="alog-actor">{{ $log->actorLabel() }}</span>
                                        @if($log->user?->role)<span class="alog-sub">{{ ucfirst(str_replace('_', ' ', $log->user->role)) }}</span>@endif
                                    @endif
                                </td>
                                <td>
                                    @if($changes === [] && blank($log->ip_address))
                                        <span class="alog-none">—</span>
                                    @else
                                        <details class="alog-changes">
                                            <summary>{{ $changes === [] ? 'Details' : count($changes).' '.\Illuminate\Support\Str::plural('detail', count($changes)) }}</summary>
                                            <ul class="alog-change-list">
                                                @foreach($changes as $change)
                                                    <li>
                                                        <strong>{{ $change['field'] }}</strong>
                                                        @if($change['old'] !== null)<span class="alog-old">{{ $change['old'] }}</span>@endif
                                                        <span class="alog-new">{{ $change['new'] ?? '(removed)' }}</span>
                                                    </li>
                                                @endforeach
                                                @if(filled($log->ip_address))
                                                    <li class="alog-meta">IP {{ $log->ip_address }}@if($log->user_agent) · {{ \Illuminate\Support\Str::limit($log->user_agent, 90) }}@endif</li>
                                                @endif
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <nav class="alog-pager" aria-label="Pages">
                <span>Showing {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }} of {{ number_format($logs->total()) }}</span>
                <span class="alog-pager-links">
                    <a href="{{ $logs->previousPageUrl() ?? '#' }}" class="alog-btn" @if($logs->onFirstPage()) aria-disabled="true" @endif><i class="fas fa-chevron-left" aria-hidden="true"></i> Newer</a>
                    <a href="{{ $logs->nextPageUrl() ?? '#' }}" class="alog-btn" @if(! $logs->hasMorePages()) aria-disabled="true" @endif>Older <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                </span>
            </nav>
        @endif
    </section>
</div>
        </main>
    </div>
</div>
