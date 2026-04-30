<nav class="navbar" id="siteNavbar">
    <div class="nav-left">
        <a href="{{ route('home') }}" class="logo">BAC-OFFICE</a>

        <button
            type="button"
            id="menuToggle"
            class="menu-toggle"
            aria-controls="navLinks"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            &#9776;
        </button>

        <div id="navLinks" class="nav-links">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ url('/about') }}" class="{{ request()->is('about') ? 'active' : '' }}">About BAC</a>
            <a href="{{ route('public.procurement') }}" class="{{ request()->routeIs('public.procurement') ? 'active' : '' }}">Procurement</a>
            <a href="{{ route('public.awards') }}" class="{{ request()->routeIs('public.awards') ? 'active' : '' }}">Awards & Contracts</a>
            <a href="{{ url('/contact') }}" class="{{ request()->is('contact') ? 'active' : '' }}">Contact Us</a>
        </div>
    </div>

    <div id="navRight" class="nav-right">
        <form action="{{ route('public.procurement') }}" method="GET" class="search-form">
            <input
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search projects..."
                class="search-input"
                autocomplete="off"
            >
            <button type="submit" class="search-btn">Search</button>
        </form>

        <button type="button" onclick="openLogin()" class="login-btn">Login</button>
    </div>
</nav>
