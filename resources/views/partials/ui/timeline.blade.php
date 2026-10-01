{{-- Procurement stages from App\Support\ProcurementTimeline::stages(). --}}
@php
    $tz = config('bac-office.display_timezone');
    $stateLabels = ['done' => 'Completed', 'current' => 'Current stage', 'upcoming' => 'Upcoming', 'skipped' => 'Not applicable', 'stopped' => 'Stopped'];
@endphp
<ol class="ui-timeline" aria-label="Procurement stages">
    @foreach($stages as $index => $stage)
        <li class="ui-timeline__item is-{{ $stage['state'] }}" @if($stage['state'] === 'current') aria-current="step" @endif>
            <span class="ui-timeline__marker" aria-hidden="true">
                @switch($stage['state'])
                    @case('done') <i class="fas fa-check"></i> @break
                    @case('stopped') <i class="fas fa-xmark"></i> @break
                    @case('skipped') <i class="fas fa-minus"></i> @break
                    @default {{ $index + 1 }}
                @endswitch
            </span>
            <div>
                <div class="ui-timeline__head">
                    <p class="ui-timeline__title">
                        {{ $stage['label'] }}
                        <span class="sr-only">({{ $stateLabels[$stage['state']] ?? $stage['state'] }})</span>
                    </p>
                    @if($stage['date'])
                        <span class="ui-timeline__date">{{ $stage['date']->copy()->timezone($tz)->format('M d, Y') }}</span>
                    @endif
                </div>
                <p class="ui-timeline__meta"><b>{{ $stage['office'] }}</b>@if($stage['note']) &middot; {{ $stage['note'] }}@endif</p>
            </div>
        </li>
    @endforeach
</ol>
