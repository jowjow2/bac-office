@extends('layouts.admin')

@section('content')

<h2>Publish Procurement</h2>

<p>List of procurements ready for publishing.</p>

<table border="1" cellpadding="10">

<tr>
<th>Title</th>
<th>Category</th>
<th>Status</th>
<th>Publish Date</th>
</tr>

@forelse($procurements as $p)

<tr>

<td>{{ $p->title }}</td>
<td>{{ $p->category }}</td>
<td>{{ $p->status }}</td>
<td>{{ $p->publish_date }}</td>

</tr>

@empty

<tr>
<td colspan="4">No published procurements</td>
</tr>

@endforelse

</table>

@endsection