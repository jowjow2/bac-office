@extends('layouts.portal')

@section('title', 'Notifications')
@section('subtitle', 'Updates on the purchase requests of '.auth()->user()->office.'.')

@section('actions')
    @if($notifications->whereNull('read_at')->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="ui-btn ui-btn--secondary">Mark all as read</button>
        </form>
    @endif
@endsection

@section('content')
@php $tz = config('bac-office.display_timezone'); @endphp
<div class="ui-page">

    <section class="ui-card">
        @if($notifications->isEmpty())
            <div class="ui-empty">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <strong>No notifications yet</strong>
            </div>
        @else
            <ul class="ui-feed">
                @foreach($notifications as $notification)
                    <li class="ui-feed__item {{ $notification->read_at ? '' : 'ui-feed__item--info' }}">
                        <span class="ui-feed__dot" aria-hidden="true"></span>
                        <div>
                            <p class="ui-feed__title">
                                <a class="ui-link" href="{{ route('notifications.open', $notification) }}">{{ $notification->title }}</a>
                                @unless($notification->read_at)<span class="ui-badge ui-badge--info" style="margin-left:6px;">New</span>@endunless
                            </p>
                            <p class="ui-feed__detail">{{ $notification->message }}</p>
                            <p class="ui-feed__meta">{{ $notification->created_at->timezone($tz)->format('M d, Y h:i A') }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
