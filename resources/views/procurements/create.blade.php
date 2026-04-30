@extends('layouts.admin')

@section('content')

<div class="form-container">

<h2>Create Procurement</h2>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<p class="form-subtitle">Fill in the procurement details below</p>

<form action="{{ route('procurements.store') }}" method="POST" enctype="multipart/form-data">

@csrf

<div class="form-group">
<label>Title</label>
<input type="text" name="title" placeholder="Enter procurement title" required>
</div>

<div class="form-group">
<label>Description</label>
<textarea name="description" placeholder="Enter description"></textarea>
</div>

<div class="form-group">
<label>Category</label>
<input type="text" name="category" placeholder="Enter category">
</div>

<div class="form-group">
<label>Publish Date</label>
<input type="date" name="publish_date">
</div>

<div class="form-group">
<label>Upload Document</label>
<input type="file" name="document">
</div>

<button class="btn-submit">Create Procurement</button>

</form>

</div>

@endsection
