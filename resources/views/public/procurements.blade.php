@extends('layouts.public')

@section('content')
<h1>Available Procurements</h1>
<div class="container">

<div class="card-grid">

@forelse($procurements as $p)

<!-- CARD -->
<div class="doc-card">

    <h3>{{ $p->title }}</h3>

    <p class="category">{{ $p->category }}</p>

    <p class="desc">
        {{ \Illuminate\Support\Str::limit($p->description,100) }}
    </p>

    <button onclick="openModal({{ $p->id }})" class="btn">
        View Details
    </button>

</div>

<!-- ✅ MODAL (DITO LANG!) -->
<div class="modal" id="modal-{{ $p->id }}">

    <div class="modal-content">

        <span class="close" onclick="closeModal({{ $p->id }})">&times;</span>

        <h2>{{ $p->title }}</h2>

        <p>{{ $p->description }}</p>

        <br>

        <!-- DOWNLOAD -->
        @if($p->document)
        <a href="{{ asset('storage/'.$p->document) }}" target="_blank" class="btn-download">
            Download File
        </a>
        @endif

        <br><br>

        <!-- BID FORM -->
        <form action="{{ route('bids.store') }}" method="POST">
            @csrf

            <input type="hidden" name="procurement_id" value="{{ $p->id }}">

            <input 
    type="password" 
    name="amount" 
    class="bid-input"
    placeholder="Enter bid amount (secured)" 
    required
>

            <button type="submit" class="btn-submit">
                Submit Bid
            </button>

        </form>

    </div>

</div>

@empty
<p>No procurements available</p>
@endforelse

</div>

</div>
<script>

function openModal(id){
    document.getElementById('modal-'+id).style.display = 'flex';
}

function closeModal(id){
    document.getElementById('modal-'+id).style.display = 'none';
}
</script>

@endsection