@extends('layouts.legacy-portal')

@section('legacy-content')
<div class="admin-dashboard staff-role-page dashboard-home admin-dashboard-page staff-dashboard staff-dashboard-page">
    @push('legacy-head')
    @vite(['resources/css/dashboard.css'])
    @endpush

    @include('partials.staff-sidebar')

    <div class="main-area">
        <x-page-header title="Notifications" subtitle="Staff activity alerts" />

        <main class="dashboard-content dashboard-home-content">
            @include('partials.notifications.center')
        </main>
    </div>
</div>
@endsection
