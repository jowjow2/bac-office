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
            {{-- About BAC opens a menu of the About page's sections (hover, focus, or the arrow). --}}
            @php
                $aboutSections = [
                    ['how-to-bid', 'How to bid', 'Six steps from registering to the Notice to Proceed'],
                    ['what-the-bac-does', 'What the BAC does', 'Its mandate and the principles it follows'],
                    ['committee', 'The Committee', 'BAC members, Secretariat and Technical Working Group'],
                    ['laws', 'Laws & resources', 'RA 12009, its IRR and official references'],
                    ['faq', 'Frequently asked', 'PhilGEPS, fees, online bids and deadlines'],
                    ['contact-bac', 'Contact the BAC', 'Office, hours, email and phone'],
                ];
                try {
                    $aboutHasCommittee = \App\Support\BacProfile::get()['members'] !== [];
                } catch (\Throwable) {
                    $aboutHasCommittee = false;
                }
                if (! $aboutHasCommittee) {
                    $aboutSections = array_values(array_filter($aboutSections, fn ($section) => $section[0] !== 'committee'));
                }
            @endphp
            <div class="nav-dropdown" data-nav-dropdown>
                <a href="{{ url('/about') }}" class="nav-dropdown__link {{ request()->is('about') ? 'active' : '' }}">About BAC</a>
                <button type="button" class="nav-dropdown__toggle" aria-expanded="false" aria-controls="aboutMenu" aria-label="About BAC sections">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="nav-dropdown__menu" id="aboutMenu">
                    @foreach($aboutSections as [$anchor, $label, $hint])
                        <a href="{{ url('/about') }}#{{ $anchor }}" class="nav-dropdown__item"><strong>{{ $label }}</strong><small>{{ $hint }}</small></a>
                    @endforeach
                </div>
            </div>
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

@once
<script>
    // About BAC menu: hover opens it on wide screens (CSS); the arrow toggles it everywhere.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-nav-dropdown]').forEach(function (dropdown) {
            const toggle = dropdown.querySelector('.nav-dropdown__toggle');
            const setOpen = function (open) {
                dropdown.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            };
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                setOpen(!dropdown.classList.contains('is-open'));
            });
            dropdown.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { setOpen(false); toggle.focus(); }
            });
            document.addEventListener('click', function (event) {
                if (!dropdown.contains(event.target)) setOpen(false);
            });
            dropdown.addEventListener('focusout', function (event) {
                if (!dropdown.contains(event.relatedTarget)) setOpen(false);
            });
        });
    });
</script>
@endonce
