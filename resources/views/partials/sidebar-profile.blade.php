@php
    $sidebarUser = auth()->user();
    $sidebarName = $sidebarUser?->company ?: ($sidebarUser?->name ?? 'User');
    $sidebarRole = ucfirst($sidebarUser?->role ?? 'member');
    $sidebarInitials = collect(preg_split('/\s+/', trim($sidebarName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('');
    $sidebarMessageRoute = null;
    $sidebarMessageLabel = null;
    $sidebarMessageCount = 0;

    if ($sidebarUser?->role === 'admin') {
        $sidebarMessageRoute = route('admin.messages');
        $sidebarMessageLabel = 'Messages';
        $sidebarMessageCount = (int) ($adminUnreadMessagesCount ?? 0);
    } elseif ($sidebarUser?->role === 'bidder') {
        $sidebarMessageRoute = route('bidder.messages');
        $sidebarMessageLabel = 'BAC Messages';
        $sidebarMessageCount = (int) ($bidderUnreadMessagesCount ?? 0);
    }
@endphp

<div class="sidebar-profile" aria-label="Current user">
    <span class="sidebar-profile-avatar">{{ $sidebarInitials ?: 'U' }}</span>
    <span class="sidebar-profile-copy">
        <span class="sidebar-profile-name">{{ $sidebarName }}</span>
        <span class="sidebar-profile-row">
            <span class="sidebar-profile-subrole">{{ $sidebarRole }}</span>
            <span class="sidebar-profile-role">{{ $sidebarRole }}</span>
        </span>
    </span>
</div>

@vite('resources/js/dashboard.js')
