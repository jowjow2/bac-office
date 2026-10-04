@extends('layouts.portal')

@section('title', 'Notifications')
@section('subtitle', 'Updates on the purchase requests of '.auth()->user()->office.'.')


@section('content')
@include('partials.notifications.center')
@endsection
