@extends('layouts.admin')

@section('content')

<h2>Procurement List</h2>

<a href="{{ route('procurements.create') }}">Create Procurement</a>

<br><br>

<table border="1" cellpadding="10">

<tr>
<th>Title</th>
<th>Category</th>
<th>Status</th>
<th>Action</th>
</tr>

@foreach($procurements as $p)

<tr>

<td>{{ $p->title }}</td>

<td>{{ $p->category }}</td>

<td>{{ $p->status }}</td>

<td>

<a href="{{ route('procurements.publish',$p->id) }}">
Publish
</a>

|

<a href="{{ route('procurements.delete',$p->id) }}">
Delete
</a>

</td>

</tr>

@endforeach

</table>

@endsection