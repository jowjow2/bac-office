@extends('layouts.admin')

@section('content')

<h2>Procurement List</h2>

 <a href="{{ route('admin.projects.create') }}">Create Procurement</a>

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

<a href="{{ route('admin.project.publish', $p->id) }}">
Publish
</a>

|

<form action="{{ route('admin.project.destroy', $p) }}" method="POST" style="display: inline;" onsubmit="return confirm('Delete this project? This will also remove its bids, awards, and staff assignments.');">
@csrf
@method('DELETE')
<button type="submit" style="background: none; border: none; color: #dc2626; padding: 0; font-size: 14px; cursor: pointer;">Delete</button>
</form>

</td>

</tr>

@endforeach

</table>

@endsection