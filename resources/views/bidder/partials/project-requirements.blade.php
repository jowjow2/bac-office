@php
    $requirement = $project->requirement;
    $requiredDocuments = collect($requirement?->required_documents ?? [])->filter()->values();
    $requirementSections = collect([
        'Eligibility Requirements' => $requirement?->eligibility_requirements,
        'Technical Requirements' => $requirement?->technical_requirements,
        'Financial Requirements' => $requirement?->financial_requirements,
        'Qualification Notes' => $requirement?->qualification_notes,
        'Special Instructions' => $requirement?->special_instructions,
    ])->filter(fn ($value) => filled($value));
@endphp

@if($requiredDocuments->isNotEmpty() || $requirementSections->isNotEmpty())
    <div class="bidder-requirements-card">
        <div class="bidder-requirements-heading">
            <div>
                <h4>Requirements to Include</h4>
                <p>Prepare these items in your bid proposal before submitting.</p>
            </div>
            <span class="bidder-requirements-icon" aria-hidden="true">
                <i class="fas fa-clipboard-check"></i>
            </span>
        </div>

        @if($requiredDocuments->isNotEmpty())
            <div class="bidder-required-docs">
                @foreach($requiredDocuments as $document)
                    <span class="bidder-required-doc">
                        <i class="fas fa-check"></i>
                        {{ $document }}
                    </span>
                @endforeach
            </div>
        @endif

        @if($requirementSections->isNotEmpty())
            <div class="bidder-requirements-grid">
                @foreach($requirementSections as $label => $content)
                    <div class="bidder-requirement-section">
                        <strong>{{ $label }}</strong>
                        <p>{{ $content }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
