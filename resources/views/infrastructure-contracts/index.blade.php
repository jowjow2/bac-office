@extends('layouts.portal')
@section('title', 'Infrastructure contract tracking')
@section('subtitle', 'Technical progress for Infrastructure contracts assigned to your end-user office.')
@section('content')
<div class="ui-stack">
    @forelse($awards as $award)
        @php($record = app(\App\Support\InfrastructureImplementationWorkflow::class)->ensure($award))
        @include('infrastructure-contracts.card', ['award' => $award, 'record' => $record, 'mode' => 'end_user'])
    @empty
        <section class="ui-card"><p>No Infrastructure contract with an issued NTP is assigned to your office yet.</p></section>
    @endforelse
</div>
@endsection
