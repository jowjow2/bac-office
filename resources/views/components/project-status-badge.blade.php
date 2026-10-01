@props(['label', 'statusClass'])

<span class="projects-status-badge projects-status-badge--{{ $statusClass }}">
    <span aria-hidden="true"></span>
    {{ $label }}
</span>
