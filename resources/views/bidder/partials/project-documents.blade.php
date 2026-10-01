@php
    $compact = $compact ?? false;
    // Only files verified as this project's own bidding documents are listed.
    $officialDocuments = $project->officialDocuments();
    $withheldCount = $project->unverifiedDocuments()->count();
    $groups = [
        'Bidding Documents' => $officialDocuments->reject(fn ($document) => $document->document_type === 'supplemental_bulletin'),
        'Supplemental Bid Bulletins' => $officialDocuments->filter(fn ($document) => $document->document_type === 'supplemental_bulletin'),
    ];
@endphp

<div class="bidder-project-files{{ $compact ? ' bidder-project-files-compact' : '' }}">
    <div class="bidder-project-files-heading">
        <span class="bidder-project-files-title">Official Bidding Documents</span>
        <span class="bidder-project-files-count">{{ $officialDocuments->count() }} {{ \Illuminate\Support\Str::plural('file', $officialDocuments->count()) }}</span>
    </div>

    @if($officialDocuments->isEmpty())
        <p class="bidder-project-files-empty">No official bidding documents are posted for this project yet. Obtain them from the BAC Secretariat.</p>
    @endif

    @foreach($groups as $groupLabel => $documents)
        @if($documents->isNotEmpty() || $groupLabel === 'Supplemental Bid Bulletins')
            <div class="bidder-project-files-group">
                <span class="bidder-project-files-subtitle">{{ $groupLabel }}</span>
                @if($documents->isEmpty())
                    <span class="bidder-project-files-none">None issued</span>
                @else
                    <div class="bidder-project-files-list">
                        @foreach($documents as $documentIndex => $document)
                            {{-- Index into officialDocuments(), the same list the preview route resolves. --}}
                            <a href="{{ route('bidder.project.document.preview', ['project' => $project, 'document' => $officialDocuments->search($document, true)]) }}" target="_blank" rel="noopener" class="bidder-project-file-link">
                                <i class="fas fa-file-lines" aria-hidden="true"></i>
                                <span>
                                    {{ $document->document_type ? $document->document_type_label . ': ' : '' }}{{ $document->display_name }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    @endforeach

    @if($withheldCount > 0)
        <p class="bidder-project-files-warning" role="note">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            {{ $withheldCount }} {{ \Illuminate\Support\Str::plural('file', $withheldCount) }} linked to this project could not be verified as an official bidding document and {{ $withheldCount === 1 ? 'is' : 'are' }} not shown.
        </p>
    @endif
</div>
