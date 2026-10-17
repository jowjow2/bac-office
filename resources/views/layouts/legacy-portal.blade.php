@if($portalFragment ?? false)
    @yield('legacy-content')
@else
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('partials.dashboard-viewport')
    @stack('legacy-head')
</head>
<body class="portal-legacy">
    @yield('legacy-content')
    <span id="portal-page-ready" hidden></span>
</body>
</html>
@endif
