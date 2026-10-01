<nav class="navbar" id="siteNavbar">
    <div class="navbar-inner">
        <div class="nav-left">
            <a href="{{ route('home') }}" class="logo">
                <img src="{{ asset('Images/Logo.png') }}" alt="Logo" class="logo-img">
                BAC-OFFICE
            </a>

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
        </div>

        <div id="navLinks" class="nav-links">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ url('/about') }}" class="{{ request()->is('about') ? 'active' : '' }}">About BAC</a>
            <a href="{{ route('public.procurement') }}" class="{{ request()->routeIs('public.procurement') ? 'active' : '' }}">Procurement</a>
            <a href="{{ route('public.awards') }}" class="{{ request()->routeIs('public.awards') ? 'active' : '' }}">Awards & Contracts</a>
            <a href="{{ url('/contact') }}" class="{{ request()->is('contact') ? 'active' : '' }}">Contact Us</a>
        </div>

        <div id="navRight" class="nav-right">
            <form action="{{ request()->routeIs('public.awards') ? route('public.awards') : route('public.procurement') }}" method="GET" class="search-form">
                <span class="search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-4-4"></path>
                    </svg>
                </span>
                <input
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="{{ request()->routeIs('public.awards') ? 'Search awards or contractors...' : 'Search procurement...' }}"
                    class="search-input"
                    autocomplete="off"
                >
            </form>

            <button type="button" onclick="openLogin()" class="login-btn login-signup-btn" aria-label="Open login or signup" title="Login or signup">
                Login/Signup
            </button>
        </div>
    </div>
</nav>
