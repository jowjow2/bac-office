{{--
    Legal schedule periods the BAC chose not to meet (Project::scheduleWarnings),
    flashed as "schedule_warnings" after publishing or a schedule change. The
    dates were kept; the warning stays until closed and is in the audit log.
--}}
@php $scheduleWarnings = array_filter((array) session('schedule_warnings', [])); @endphp
@if($scheduleWarnings !== [])
    <div class="schedule-warning-card" role="status">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <div class="schedule-warning-card__body">
            <strong>Schedule warning</strong>
            <ul>
                @foreach($scheduleWarnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            <small>Your dates were kept. This is recorded in the audit log.</small>
        </div>
        <button type="button" onclick="this.parentElement.remove()" aria-label="Close schedule warning">&times;</button>
    </div>
    <style>
        .schedule-warning-card { position: fixed; top: 150px; right: 25px; z-index: 1000; display: flex; align-items: flex-start; gap: 12px; width: min(440px, calc(100vw - 32px)); padding: 14px 16px; border: 1px solid #f5d38a; border-radius: 10px; background: #fffbeb; color: #7a4a06; box-shadow: 0 4px 12px rgba(0, 0, 0, .12); font-size: 13.5px; line-height: 1.5; }
        .schedule-warning-card > i { margin-top: 2px; font-size: 16px; color: #b45309; }
        .schedule-warning-card__body { display: grid; gap: 4px; min-width: 0; }
        .schedule-warning-card__body strong { color: #5c3b02; font-size: 14px; }
        .schedule-warning-card__body ul { margin: 0; padding-left: 18px; }
        .schedule-warning-card__body small { color: #8a5a10; font-size: 12px; }
        .schedule-warning-card > button { margin-left: auto; padding: 0; border: 0; background: none; color: #7a4a06; font-size: 18px; line-height: 1; cursor: pointer; }
        @media (max-width: 640px) { .schedule-warning-card { top: auto; bottom: 16px; right: 16px; } }
    </style>
@endif
