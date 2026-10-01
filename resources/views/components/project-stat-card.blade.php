@props(['statusKey', 'totalKey', 'metric', 'icon', 'count', 'active' => false])

<a
    href="{{ $statusKey === '' ? route('admin.projects') : route('admin.projects', ['status' => $statusKey]) }}"
    class="projects-summary-card projects-summary-card--{{ $totalKey }}{{ $active ? ' is-active' : '' }}{{ (int) $count === 0 ? ' is-empty' : '' }}"
    aria-label="Filter projects by {{ $metric }}"
    title="Filter projects by {{ $metric }}"
    @if($active) aria-current="page" @endif
>
    <span class="projects-summary-icon projects-summary-icon--{{ $totalKey }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
    <span>
        <small>{{ $metric }}</small>
        <strong>{{ number_format($count) }}</strong>
    </span>
</a>
