{{--
    Invitation to Bid details of RA 12009 IRR Sec. 50.2(d)-(h), as rows of an
    enclosing <dl class="ui-dl">: award criterion with its weights, the
    consulting evaluation procedure and the place of the bid opening.
--}}
@php /** @var \App\Models\Project $project */ @endphp
@if($project->mode()->isCompetitive())
    <div><dt>Award criterion</dt><dd>{{ $project->awardCriterionLabel() ?? 'As stated in the bidding documents' }}</dd></div>
    @if($project->usesWeightedCriteria() && ! empty($project->evaluation_criteria))
        <div><dt>Criteria and weights</dt><dd>
            @foreach($project->evaluation_criteria as $criterion)
                {{ $criterion['name'] }} — {{ rtrim(rtrim(number_format((float) $criterion['weight'], 2), '0'), '.') }}%@if(! $loop->last)<br>@endif
            @endforeach
            @if($project->award_criterion === 'mearb' && $project->quality_price_ratio)
                <br><span class="ui-optional">Quality-price ratio {{ $project->quality_price_ratio }}% technical / {{ 100 - $project->quality_price_ratio }}% price</span>
            @endif
        </dd></div>
    @endif
    @if($project->category === 'consultancy' && $project->evaluation_procedure)
        <div><dt>Evaluation procedure</dt><dd>{{ \App\Models\Project::EVALUATION_PROCEDURES[$project->evaluation_procedure] ?? $project->evaluation_procedure }}</dd></div>
    @endif
    <div><dt>Place of bid opening</dt><dd>{{ $project->bid_opening_venue ?: 'As stated in the Invitation to Bid' }}</dd></div>
@endif
