<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BAC-Office')</title>
    @stack('pre_app_styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    @include('auth.login-modal')
    @include('layouts.navbar')

    @yield('content')
</body>
</html>
