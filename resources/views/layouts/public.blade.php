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

    @if(\App\Models\Feedback::available() && ! request()->routeIs('feedback', 'certificate.*', 'public.certificate.*', 'public.awards.document'))
        <a href="{{ route('feedback') }}" class="feedback-tab" aria-label="Send feedback"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M4 3h16a2 2 0 012 2v11a2 2 0 01-2 2H9l-4.6 3.7A.8.8 0 013 20.1V5a2 2 0 011-1.4A2 2 0 014 3z"/></svg><span>Feedback</span></a>
        <style>
            .feedback-tab { position: fixed; right: 16px; bottom: 16px; z-index: 90; display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 999px; background: #1b2420; color: #fff; font-size: 13px; font-weight: 600; text-decoration: none; box-shadow: 0 8px 22px rgba(27, 36, 32, .28); }
            .feedback-tab:hover { background: #1d4f40; }
            @media (max-width: 640px) { .feedback-tab span { display: none; } .feedback-tab { right: 12px; bottom: 12px; width: 36px; min-height: 36px; padding: 0; justify-content: center; opacity: .88; } .feedback-tab svg { width: 16px; height: 16px; } }
            @media print { .feedback-tab { display: none; } }
        </style>
    @endif
</body>
</html>
