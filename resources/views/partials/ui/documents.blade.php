{{-- Documents from App\Support\ProcurementTimeline::documents(). --}}
@if(empty($documents))
    <div class="ui-empty" style="padding: 20px 0;">
        <i class="fas fa-folder-open" aria-hidden="true"></i>
        <span>No documents on file yet.</span>
    </div>
@else
    <ul class="ui-files">
        @foreach(collect($documents)->groupBy('group') as $group => $items)
            <li class="ui-files__group" aria-hidden="true">{{ $group }}</li>
            @foreach($items as $document)
                @php $href = $document['url'] ?? ($document['route'] ? route(...$document['route']) : null); @endphp
                <li>
                    <i class="fas fa-file-lines" aria-hidden="true"></i>
                    @if($href)
                        <a class="ui-link" href="{{ $href }}" target="_blank" rel="noopener">{{ $document['name'] }}<span class="sr-only"> ({{ $group }}, opens in a new tab)</span></a>
                    @else
                        <span>{{ $document['name'] }}</span>
                    @endif
                </li>
            @endforeach
        @endforeach
    </ul>
@endif
