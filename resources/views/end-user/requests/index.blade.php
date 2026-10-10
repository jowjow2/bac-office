@extends('layouts.portal')

@section('title', 'My purchase requests')
@section('subtitle', 'Purchase requests recorded by the BAC for '.auth()->user()->office.'. Hand the BAC a signed hard copy to file a new one.')

@section('content')
    @include('end-user.requests._list')
@endsection
