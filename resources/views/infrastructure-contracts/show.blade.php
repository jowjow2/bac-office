@extends('layouts.portal')
@section('title', 'Infrastructure contract tracking')
@section('subtitle', $award->project->title.' · '.$award->project->reference_no)
@section('content')
@include('infrastructure-contracts.card', ['award' => $award, 'record' => $record, 'mode' => $mode])
@endsection
