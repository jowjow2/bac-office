{{-- One quantity per contract item, next to the contract quantity. Used inside partials.contract-implementation. --}}
<div class="ci-field">
    <span class="ci-label" id="{{ $uid }}-{{ $field }}-label">{{ $heading }} per item <span class="ci-req" aria-hidden="true">*</span></span>
    <div class="ci-table-wrap">
        <table class="ci-table" aria-labelledby="{{ $uid }}-{{ $field }}-label">
            <thead><tr><th scope="col">Item</th><th scope="col" class="is-num">Contract qty</th><th scope="col" class="is-input">{{ $heading }}</th></tr></thead>
            <tbody>
                @foreach($items as $i => $item)
                    <tr>
                        <td>{{ $item['description'] }} <span class="ci-hint">({{ $item['unit'] }})</span></td>
                        <td class="is-num">{{ $qty($item['quantity']) }}</td>
                        <td class="is-input"><input class="ci-input" type="number" name="{{ $field }}[{{ $i }}]" value="{{ $old($field.'.'.$i) }}" min="0" step="0.001" required placeholder="0" aria-label="{{ $heading }}: {{ $item['description'] }}" @if($err($field.'.'.$i)) aria-invalid="true" @endif></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($err($field))<span class="ci-error">{{ $err($field) }}</span>@endif
    @foreach($items as $i => $item)
        @if($err($field.'.'.$i))<span class="ci-error">{{ $item['description'] }}: {{ $err($field.'.'.$i) }}</span>@endif
    @endforeach
</div>
