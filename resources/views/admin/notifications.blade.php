@extends('layouts.legacy-portal')

@section('legacy-content')
<div class="admin-dashboard admin-role-page">
    @push('legacy-head')
    @vite(['resources/css/dashboard.css'])
    @endpush

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Notifications" subtitle="System alerts and updates" />

        <main class="dashboard-content">


            @include('partials.notifications.center')
        </main>
    </div>
</div>
@endsection
