<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SJBAC')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon-bac-hd.ico') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-bac-32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-bac-192.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-bac-180.png') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack('pre_app_styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    @include('auth.login-modal')
    @include('layouts.navbar')

    @yield('content')
</body>
</html>
