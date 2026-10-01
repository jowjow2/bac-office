{{--
    One focused step of Contract Implementation in its own modal: what the step is, three short
    instructions, and the form (the slot). Opened by a [data-ci-step="{id}"] button; reopens
    after a failed save when $reopen is true. Styles and script live in partials.contract-implementation.
--}}
@props(['id', 'eyebrow', 'title', 'meta' => null, 'steps' => [], 'reopen' => false])
<dialog class="ci-dialog is-compact" id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if($reopen) data-ci-reopen-step @endif>
    <button type="button" class="ci-dialog-close" data-ci-close aria-label="Close {{ strtolower($eyebrow) }}"><i class="fas fa-xmark" aria-hidden="true"></i></button>
    <div class="ci-dialog-scroll">
        <div class="ci">
            <header class="ci-head">
                <div>
                    <p class="ci-eyebrow">{{ $eyebrow }}</p>
                    <h2 id="{{ $id }}-title">{{ $title }}</h2>
                    @if($meta)<p class="ci-head-meta">{{ $meta }}</p>@endif
                </div>
            </header>
            <div class="ci-body">
                @if($steps)
                    <ol class="ci-howto" aria-label="How to complete this step">
                        @foreach($steps as $index => $step)
                            <li><span>{{ $index + 1 }}</span> {{ $step }}</li>
                        @endforeach
                    </ol>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</dialog>
