{{-- Load the shared shell before the first paint, including on older pages. --}}
@once
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap">
    @vite(['resources/css/portal-shell.css', 'resources/js/portal.js'])
@endonce
