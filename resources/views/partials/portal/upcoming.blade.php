{{--
    Scheduled activities in the next two weeks, from the project schedules.
    Expects $upcoming (ProcurementPipeline::upcoming()).
--}}
@use('App\Support\Format')

<section class="ui-card" aria-labelledby="upcoming-title">
    <header class="ui-card__head ui-card__head--plain">
        <h2 class="ui-card__title" id="upcoming-title">{{ $upcomingTitle ?? 'Upcoming · next 14 days' }}</h2>
    </header>
    <div class="ui-card__body">
        @if($upcoming->isEmpty())
            <p class="ui-empty-line"><i class="far fa-calendar" aria-hidden="true"></i> Nothing scheduled in the next two weeks.</p>
        @else
            <ul class="ui-upcoming">
                @foreach($upcoming as $event)
                    @php $local = Format::local($event['at']); @endphp
                    <li class="ui-upcoming__item">
                        <time class="ui-upcoming__date" datetime="{{ $event['at']->toIso8601String() }}">
                            <span class="ui-upcoming__month">{{ $local->format('M') }}</span>
                            <span class="ui-upcoming__day">{{ $local->format('j') }}</span>
                        </time>
                        <div>
                            <p class="ui-upcoming__title">{{ $event['title'] }}</p>
                            <p class="ui-upcoming__meta">
                                @if($event['url'])
                                    <a href="{{ $event['url'] }}" class="ui-mono">{{ $event['reference'] }}</a>
                                @else
                                    <span class="ui-mono">{{ $event['reference'] }}</span>
                                @endif
                                @if($event['time']) · {{ $local->format('g:i A') }}@endif
                            </p>
                            <p class="ui-upcoming__meta">{{ \Illuminate\Support\Str::limit($event['detail'], 70) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
