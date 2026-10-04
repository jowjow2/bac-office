{{--
    Pipeline by stage: seven buckets with live counts; each one filters the
    register below. Expects $buckets (key => count), $filters, $routeName.
--}}
@use('App\Support\ProcurementPipeline')

<section class="ui-card" aria-labelledby="pipeline-title">
    <header class="ui-card__head ui-card__head--plain">
        <h2 class="ui-card__title" id="pipeline-title">Pipeline by stage</h2>
        <span class="ui-card__aside">Select a stage to filter the register</span>
    </header>
    <div class="ui-card__body">
        @php $pipelineTotal = max(1, array_sum(array_map('intval', $buckets))); @endphp
        <ol class="ui-pipeline">
            @foreach(ProcurementPipeline::BUCKETS as $key => $bucket)
                @php
                    $count = (int) ($buckets[$key] ?? 0);
                    $isCurrent = $filters['stage'] === $key;
                    $query = array_filter([
                        'stage' => $isCurrent ? null : $key,
                        'mode' => $filters['mode'],
                        'q' => $filters['q'] ?: null,
                    ]);
                @endphp
                <li>
                    <a href="{{ route($routeName, $query) }}" class="ui-pipeline__stage {{ $count === 0 ? 'is-empty' : '' }}" @if($isCurrent) aria-current="true" @endif title="{{ $bucket['hint'] }}">
                        <span class="ui-pipeline__label">{{ $loop->iteration }} · {{ $bucket['label'] }}</span>
                        <span class="ui-pipeline__count">{{ number_format($count) }}</span>
                        {{-- Share of all records at this stage. --}}
                        <span class="ui-pipeline__bar" aria-hidden="true"><span style="width: {{ round($count / $pipelineTotal * 100) }}%"></span></span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
</section>
