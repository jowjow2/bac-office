@extends('layouts.portal')

@section('title', 'My purchase requests')
@section('subtitle', 'The purchase requests you filed for '.auth()->user()->office.'.')

@section('actions')
    <a href="{{ route('end-user.requests.create') }}" class="ui-btn ui-btn--primary"><i class="fas fa-plus" aria-hidden="true"></i> New purchase request</a>
@endsection

@section('content')
    @include('end-user.requests._list')
@endsection
