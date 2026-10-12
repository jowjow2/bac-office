<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SJBAC')</title>
    @php
        $seoDescription = trim($__env->yieldContent('description')) ?: 'SJBAC Procurement: public bidding opportunities, awards and contracts of the Bids and Awards Committee (BAC) of San Jose, Occidental Mindoro. A capstone project, not the official website of the Municipality.';
        $seoTitle = trim($__env->yieldContent('title')) ?: 'SJBAC';
    @endphp
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ trim($__env->yieldContent('robots')) ?: 'index,follow' }}">
    <link rel="canonical" href="{{ url(request()->path() === '/' ? '/' : request()->path()) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SJBAC Procurement">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ url(request()->path() === '/' ? '/' : request()->path()) }}">
    <meta property="og:image" content="{{ asset('favicon-bac-512.png') }}">
    <meta name="twitter:card" content="summary">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon-bac-hd.ico') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-bac-32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-bac-192.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-bac-180.png') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @stack('pre_app_styles')
    {{-- Visit counts for the public pages (Vercel Web Analytics): no cookies, no names. Only on the live site. --}}
    @production
        <script>window.va = window.va || function () { (window.vaq = window.vaq || []).push(arguments); };</script>
        <script defer src="/_vercel/insights/script.js"></script>
    @endproduction
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    @include('auth.login-modal')
    @include('layouts.navbar')

    @yield('content')
</body>
</html>
