<aside class="sidebar">
    <a href="{{ route('bidder.dashboard') }}" class="sidebar-logo-link">
        <h2 class="sidebar-logo">
            <img src="{{ asset('Images/Logo2.png') }}" alt="BAC Office logo" class="sidebar-logo-image">
            <span>BAC-Office</span>
        </h2>
    </a>
    @include('partials.sidebar-profile')
    <ul class="sidebar-menu">
        <p class="menu-title">MAIN</p>
        <li><a href="{{ route('bidder.dashboard') }}" class="{{ request()->routeIs('bidder.dashboard') ? 'active' : '' }}"><span class="menu-icon-dashboard" aria-hidden="true"></span> Dashboard</a></li>
        <li><a href="{{ route('bidder.available-projects') }}" class="{{ request()->routeIs('bidder.available-projects', 'bidder.project.document.*', 'bidder.bids.store') ? 'active' : '' }}"><i class="fas fa-folder-open"></i> Available Projects</a></li>
         <li><a href="{{ route('bidder.my-bids') }}" class="{{ request()->routeIs('bidder.my-bids') ? 'active' : '' }}"><i class="fas fa-file-signature"></i> My Bids</a></li>
          <li>
              <a href="{{ route('bidder.bidding-track') }}" class="{{ request()->routeIs('bidder.bidding-track*') ? 'active' : '' }}">
                   <i class="fas fa-chart-line"></i> Track Bid
                  <span class="notification-badge bidder-sidebar-badge" data-bidding-track-badge hidden style="display:none"></span>
              </a>
          </li>
         <li><a href="{{ route('bidder.awarded-contracts') }}" class="{{ request()->routeIs('bidder.awarded-contracts') ? 'active' : '' }}"><i class="fas fa-award"></i> Awarded Contracts</a></li>

        <p class="menu-title">ACCOUNT</p>
        <li><a href="{{ route('bidder.company-profile') }}" class="{{ request()->routeIs('bidder.company-profile', 'bidder.profile.update', 'bidder.documents.store') ? 'active' : '' }}"><i class="fas fa-building"></i> Company Profile</a></li>
        <li>
            <a href="{{ route('bidder.messages') }}" class="{{ request()->routeIs('bidder.messages*') ? 'active' : '' }}">
                <i class="fas fa-comments"></i> Messages
                @if(($bidderUnreadMessagesCount ?? 0) > 0)
                    <span class="notification-badge bidder-sidebar-badge">{{ $bidderUnreadMessagesCount }}</span>
                @endif
            </a>
        </li>
        <li>
            <a href="{{ route('bidder.notifications') }}" class="{{ request()->routeIs('bidder.notifications*') ? 'active' : '' }}">
                <i class="fas fa-bell"></i> Notifications
                <span class="notification-badge bidder-sidebar-badge" data-notification-badge @if(($bidderNotificationCount ?? 0) <= 0) hidden style="display:none" @endif>{{ ($bidderNotificationCount ?? 0) > 0 ? $bidderNotificationCount : '' }}</span>
            </a>
        </li>

        <li>
            <form action="{{ route('logout') }}" method="POST" class="sidebar-form">
                @csrf
                <button type="submit" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </li>
    </ul>
</aside>

@include('partials.notification-live')
