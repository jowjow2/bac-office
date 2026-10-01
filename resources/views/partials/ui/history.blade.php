{{-- Decision history from App\Support\ProcurementTimeline::history(). --}}
@php $tz = config('bac-office.display_timezone'); @endphp
@if(empty($history))
    <div class="ui-empty">
        <i class="fas fa-clock-rotate-left" aria-hidden="true"></i>
        <strong>No decisions recorded yet</strong>
        <span>Decisions and proceedings appear here as they are recorded.</span>
    </div>
@else
    <ul class="ui-feed">
        @foreach($history as $event)
            <li class="ui-feed__item ui-feed__item--{{ $event['tone'] }}">
                <span class="ui-feed__dot" aria-hidden="true"></span>
                <div>
                    <p class="ui-feed__title">{{ $event['title'] }}</p>
                    @if($event['detail'])
                        <p class="ui-feed__detail">{{ $event['detail'] }}</p>
                    @endif
                    <p class="ui-feed__meta">
                        @php $local = $event['at']->copy()->timezone($tz); @endphp
                        {{-- Date-only records (e.g. the PhilGEPS posting date) have no meaningful time. --}}
                        <time datetime="{{ $event['at']->toIso8601String() }}">{{ $local->format($local->format('H:i:s') === '00:00:00' ? 'M d, Y' : 'M d, Y h:i A') }}</time>
                        @if($event['actor']) &middot; {{ $event['actor'] }} @endif
                        @if($event['attachment'])
                            &middot; <a class="ui-link" href="{{ route(...$event['attachment']['route']) }}" target="_blank" rel="noopener"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $event['attachment']['name'] }}</a>
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ul>
@endif
