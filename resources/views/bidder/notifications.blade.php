@extends('layouts.legacy-portal')

@section('legacy-content')
<div class="admin-dashboard dashboard-home admin-dashboard-page bidder-dashboard-page bidder-notifications-page">
    @push('legacy-head')
    @vite(['resources/css/dashboard.css'])
    @endpush
    @include('partials.bidder-sidebar')
    <div class="main-area">
        <x-page-header title="Notifications" subtitle="System alerts and updates" />
        <main class="dashboard-content dashboard-home-content">
            @include('partials.notifications.center')
        </main>
    </div>
</div>
@endsection
