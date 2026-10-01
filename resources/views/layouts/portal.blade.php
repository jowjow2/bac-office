{{--
    Portal shell for every role (BAC admin, Secretariat staff, end-user
    offices and bidders), built on resources/css/ui.css only.

    Sections: title, subtitle, crumbs, actions, content. Stack: scripts.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Procurement') · SJBAC</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-bac-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-bac-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/ui.css', 'resources/js/portal.js'])
    @stack('head')
</head>
<body class="portal">
<a href="#main" class="portal-skip">Skip to content</a>

@include('partials.portal.sidebar')

<div class="portal-main">
    <x-page-header
        :title="trim($__env->yieldContent('title', 'SJBAC Procurement'))"
        :subtitle="trim($__env->yieldContent('subtitle'))"
        :crumbs="new \Illuminate\Support\HtmlString($__env->yieldContent('crumbs'))"
        :actions="new \Illuminate\Support\HtmlString($__env->yieldContent('actions'))"
    />

    <main id="main" class="portal-content ui-main" tabindex="-1">
        @if(session('success'))
            <div class="ui-alert ui-alert--success ui-toast" role="status" data-ui-autohide>
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="ui-alert ui-alert--danger" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @yield('content')
    </main>
</div>

@include('partials.notification-live')
@stack('scripts')
</body>
</html>
