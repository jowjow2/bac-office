@extends('layouts.public')

@section('content')

<div style="padding:40px">

<h1>{{ $procurement->title }}</h1>

<p>{{ $procurement->category }}</p>
<p>{{ $procurement->description }}</p>

<br>

@if($procurement->document)
<a href="{{ asset('storage/'.$procurement->document) }}" target="_blank">
Download File
</a>
@endif

</div>

@endsection