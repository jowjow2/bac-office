@extends('layouts.admin')

@section('content')

<h2>Publish Procurement</h2>

<p>List of procurements ready for publishing.</p>

 <table border="1" cellpadding="10">

<tr>
<th>Title</th>
<th>Category</th>
<th>Status</th>
<th>Posted Date</th>
</tr>

@forelse($projects as $p)

<tr>

<td>{{ $p->title }}</td>
<td>{{ $p->category }}</td>
<td>{{ $p->status }}</td>
<td>{{ $p->created_at->format('M d, Y') }}</td>

</tr>

@empty

<tr>
<td colspan="4">No published procurements</td>
</tr>

@endforelse

</table>

@endsection