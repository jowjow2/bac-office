<link rel="icon" type="image/x-icon" href="{{ asset('favicon-bac-hd.ico') }}?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-bac-32.png') }}?v=2">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-bac-192.png') }}?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-bac-180.png') }}?v=2">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.page-transition')
@php
    // Older pages have no <title> of their own: name the browser tab after the page.
    $tabTitles = [
        'admin.projects' => 'Projects & biddings', 'admin.projects.create' => 'New procurement', 'admin.bids' => 'Bid management',
        'admin.bid.view' => 'Bid review', 'admin.bid.edit' => 'Edit bid', 'admin.payments' => 'Bidding fee payments',
        'admin.awards.index' => 'Awards & contracts', 'admin.award.view' => 'Award', 'admin.users' => 'Manage users',
        'admin.users.review' => 'Bidder review', 'admin.audit-logs' => 'Audit logs', 'admin.assignments' => 'Staff assignments',
        'admin.messages' => 'Messages', 'admin.notifications' => 'Notifications', 'admin.feedback' => 'Feedback',
        'admin.reports' => 'Report analytics', 'staff.assign-projects' => 'My projects', 'staff.messages' => 'Messages',
        'staff.notifications' => 'Notifications', 'staff.payments' => 'Bidding fee payments', 'staff.review-bids' => 'Review bids',
        'staff.users.review' => 'Bidder review', 'bidder.available-projects' => 'Available projects', 'bidder.messages' => 'BAC messages',
        'bidder.notifications' => 'Notifications',
    ];
    $tabRoute = request()->route()?->getName() ?? '';
    $tabTitle = $tabTitles[$tabRoute] ?? \Illuminate\Support\Str::of(\Illuminate\Support\Str::afterLast($tabRoute, '.'))->replace(['-', '_'], ' ')->title();
@endphp
<title>{{ $tabTitle ?: 'Portal' }} · SJBAC</title>
