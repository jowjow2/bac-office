@props(['notification'])

@php
    $isRead = $notification['is_read'] ?? false;
    $title = $notification['title'] ?? '';
    $message = $notification['message'] ?? '';
    $time = $notification['time'] ?? '';
    $notificationId = $notification['id'] ?? null;
    $url = $notificationId
        ? route('notifications.open', ['notification' => $notificationId])
        : ($notification['url'] ?? '#');

    // Determine icon and color based on notification content
    $iconClass = 'fa-bell';
    $iconColor = 'notification-icon-default';

    $titleLower = strtolower($title);
    $messageLower = strtolower($message);

    if (strpos($titleLower, 'bid') !== false || strpos($messageLower, 'bid') !== false) {
        if (strpos($messageLower, 'rejected') !== false) {
            $iconClass = 'fa-circle-xmark';
            $iconColor = 'notification-icon-danger';
        } elseif (strpos($messageLower, 'validated') !== false) {
            $iconClass = 'fa-check-circle';
            $iconColor = 'notification-icon-success';
        } else {
            $iconClass = 'fa-file-contract';
            $iconColor = 'notification-icon-info';
        }
    } elseif (strpos($titleLower, 'project') !== false || strpos($messageLower, 'project') !== false) {
        $iconClass = 'fa-briefcase';
        $iconColor = 'notification-icon-warning';
    } elseif (strpos($titleLower, 'message') !== false || strpos($messageLower, 'message') !== false) {
        $iconClass = 'fa-envelope';
        $iconColor = 'notification-icon-info';
    } elseif (strpos($titleLower, 'award') !== false || strpos($messageLower, 'award') !== false) {
        $iconClass = 'fa-trophy';
        $iconColor = 'notification-icon-warning';
    } elseif (strpos($titleLower, 'assign') !== false || strpos($messageLower, 'assign') !== false) {
        $iconClass = 'fa-tasks';
        $iconColor = 'notification-icon-primary';
    }
@endphp

<a href="{{ $url }}"
   class="notification-item {{ $isRead ? 'notification-read' : 'notification-unread' }}"
   data-notification-row
   @if($notificationId) data-notification-open data-notification-id="{{ $notificationId }}" @endif>
    <div class="notification-icon {{ $iconColor }}">
        <i class="fas {{ $iconClass }}"></i>
        @if (!$isRead)
            <span class="notification-unread-indicator"></span>
        @endif
    </div>

    <div class="notification-content">
        <h3 class="notification-title">{{ $title }}</h3>
        <p class="notification-message">{{ $message }}</p>
    </div>

    <div class="notification-time">
        <span>{{ $time }}</span>
        <div class="notification-action">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>
</a>
