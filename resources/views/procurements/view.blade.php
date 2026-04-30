@include('admin.dashboard')

@section('content')

<h1>Procurement Details</h1>

<div class="procurement-details">

<p><strong>Project Title:</strong> {{ $procurement['title'] }}</p>

<p><strong>ABC Budget:</strong> {{ $procurement['budget'] }}</p>

<p><strong>Description:</strong> {{ $procurement['description'] }}</p>

<p><strong>Status:</strong> {{ $procurement['status'] }}</p>

<p><strong>Bid Opening Date:</strong> {{ $procurement['opening_date'] }}</p>

</div>

<a href="{{ url()->previous() }}" class="back-btn">Back</a>

@endsection