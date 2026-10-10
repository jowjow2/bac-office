{{-- Award criterion and bidding-documents reference the financial opening needs (BAC Admin). --}}
<form action="{{ route('admin.project.bid-opening-rules', ['project' => $project->id]) }}" method="POST" class="br-form br-opening-rules" aria-label="Record financial opening rules">
    @csrf
    <strong>Record opening rules</strong>
    <p class="br-hint">Use the award criterion and reference from the signed bidding documents.</p>
    <label class="br-field">Award criterion
        @if($technicalOpened && $criterion)<input type="hidden" name="award_criterion" value="{{ $criterion }}">@endif
        <select name="award_criterion" required onchange="const field = this.form.querySelector('[data-br-min-score]'); const required = this.value === 'mearb'; field.hidden = !required; field.querySelector('input').required = required;" @disabled($technicalOpened && $criterion)>
            <option value="">Select the criterion</option>
            @foreach($criterionLabels as $key => $label)
                <option value="{{ $key }}" @selected(old('award_criterion', $criterion) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="br-field">Bidding documents reference
        <input type="text" name="opening_documents_reference" value="{{ old('opening_documents_reference', $project->opening_documents_reference) }}" maxlength="500" placeholder="ITB section or bidding document reference" required>
    </label>
    <label class="br-field" data-br-min-score @if(old('award_criterion', $criterion) !== 'mearb') hidden @endif>Minimum technical score (MEARB)
        <input type="number" name="minimum_technical_score" min="0" max="999999" step="0.0001" value="{{ old('minimum_technical_score', $project->minimum_technical_score) }}" @if(old('award_criterion', $criterion) === 'mearb') required @endif>
    </label>
    @if($errors->has('opening'))<p class="br-blocked" role="alert">{{ $errors->first('opening') }}</p>@endif
    <button type="submit" class="br-btn br-btn--primary">Save opening rules</button>
</form>
