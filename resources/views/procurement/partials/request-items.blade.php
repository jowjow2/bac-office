{{-- Item breakdown of a purchase request (ProcurementRequest::itemRows). --}}
@php
    $rows = $procurementRequest->itemRows();
    $quantity = fn (float $value) => number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
@endphp
@if($rows !== [])
    <div class="ui-table-wrap">
        <table class="ui-table">
            <caption class="sr-only">Items requested</caption>
            <thead>
                <tr>
                    <th scope="col">Item</th>
                    <th scope="col" class="is-num">Quantity</th>
                    <th scope="col">Unit</th>
                    <th scope="col" class="is-num">Est. unit cost</th>
                    <th scope="col" class="is-num">Item total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['description'] }}</td>
                        <td class="is-num">{{ $quantity($row['quantity']) }}</td>
                        <td>{{ $row['unit'] }}</td>
                        <td class="is-num">₱{{ number_format($row['unit_cost'], 2) }}</td>
                        <td class="is-num">₱{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" colspan="4">Estimated total cost</th>
                    <td class="is-num"><strong>₱{{ number_format((float) $procurementRequest->estimated_cost, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
