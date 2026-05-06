@php
    $compact = $compact ?? false;
    $projectDocuments = $projectDocuments ?? $project->uploadedDocuments();
    $documentLimit = $documentLimit ?? ($compact ? 2 : false);
    $visibleDocuments = $documentLimit ? $projectDocuments->take($documentLimit) : $projectDocuments;
@endphp

<div class="public-bid-preview{{ $compact ? ' public-bid-preview-compact' : '' }}">
    @if($projectDocuments->isNotEmpty())
        <div class="public-bid-preview-strip">
            @foreach($visibleDocuments as $documentIndex => $document)
                @php
                    $documentName = $document->display_name ?: 'Bidding file';
                    $documentPreviewUrl = route('public.procurement.document.preview', ['project' => $project, 'document' => $documentIndex]);
                    $documentEmbedUrl = route('public.procurement.document.pdf', ['project' => $project, 'document' => $documentIndex]) . '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
                @endphp

                <a href="{{ $documentPreviewUrl }}" target="_blank" rel="noopener" class="public-bid-preview-item" aria-label="Open {{ $documentName }}">
                    <div class="public-bid-preview-frame">
                        <div class="public-bid-preview-page">
                            <iframe src="{{ $documentEmbedUrl }}" title="Preview of {{ $documentName }}" loading="lazy"></iframe>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="public-doc-empty">No bidding files have been uploaded for this project yet.</div>
    @endif
</div>
