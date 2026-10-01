{{-- Shown at the top of a Contract Implementation step modal when its last save failed. Used inside partials.contract-implementation. --}}
@if($mine && old('ci_form') === $key && $errors->any())
    <div class="ci-note is-danger" role="alert">
        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
        <div>
            <strong>This step was not saved.</strong>
            {{ $errors->count() === 1 ? 'Correct the field marked below.' : 'Correct the fields marked below.' }}
            @foreach(['status', 'implementation', 'document'] as $general)
                @if($errors->has($general)) {{ $errors->first($general) }}@endif
            @endforeach
        </div>
    </div>
@endif
