@php
    $sidebarUser = auth()->user();
    $sidebarName = $sidebarUser?->company ?: ($sidebarUser?->name ?? 'User');
    $sidebarRole = ucfirst($sidebarUser?->role ?? 'member');
    $sidebarInitials = collect(preg_split('/\s+/', trim($sidebarName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="sidebar-profile">
    <div class="sidebar-profile-avatar">{{ $sidebarInitials }}</div>
    <div class="sidebar-profile-copy">
        <div class="sidebar-profile-name">{{ $sidebarName }}</div>
        <div class="sidebar-profile-row">
            <span class="sidebar-profile-subrole">{{ $sidebarRole }}</span>
            <span class="sidebar-profile-role">{{ $sidebarRole }}</span>
        </div>
    </div>
</div>

@vite('resources/js/dashboard.js')

