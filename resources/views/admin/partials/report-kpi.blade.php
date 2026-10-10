{{--
    One headline figure on Report analytics.
    $card  label, display, value, format, note, icon, tone
    $delta optional change against the previous range: text, tone, title
    A figure with nothing to measure yet ("—") shrinks to a quiet one-line card.
--}}
@php $empty = ($card['value'] ?? null) === null; @endphp
<article class="ra-card ra-kpi tone-{{ $card['tone'] }} {{ $empty ? 'is-empty' : 'ra-rise' }}" @unless($empty) style="--i: {{ $index ?? 0 }}" @endunless>
    <div class="ra-kpi-top">
        <span class="ra-kpi-icon"><i class="fas {{ $card['icon'] }}" aria-hidden="true"></i></span>
        <span class="ra-kpi-label">{{ $card['label'] }}</span>
        @if($empty)<span class="ra-kpi-dash">—</span>@endif
    </div>
    @unless($empty)
        <strong class="ra-kpi-value" @if($card['value'] !== null) data-count="{{ $card['value'] }}" data-format="{{ $card['format'] }}" @endif>{{ $card['display'] }}</strong>
    @endunless
    <span class="ra-kpi-note">{{ $card['note'] }}</span>
    @if(! empty($delta))<span class="ra-delta is-{{ $delta['tone'] }}" title="{{ $delta['title'] }}">{{ $delta['text'] }}</span>@endif
</article>
