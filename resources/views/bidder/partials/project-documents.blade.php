@php
    $compact = $compact ?? false;
    $projectDocuments = $project->uploadedDocuments();
@endphp

@if($projectDocuments->isNotEmpty())
    <div class="bidder-project-files{{ $compact ? ' bidder-project-files-compact' : '' }}">
        <div class="bidder-project-files-heading">
            <span class="bidder-project-files-title">Project Files</span>
            <span class="bidder-project-files-count">{{ $projectDocuments->count() }} {{ \Illuminate\Support\Str::plural('file', $projectDocuments->count()) }}</span>
        </div>

        <div class="bidder-project-files-list">
            @foreach($projectDocuments as $documentIndex => $document)
                @php($previewUrl = filled($document->file_path) ? route('bidder.project.document.preview', ['project' => $project, 'document' => $documentIndex]) : null)

                @if($previewUrl)
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="bidder-project-file-link">
                        <i class="fas fa-file-lines" aria-hidden="true"></i>
                        <span>{{ $document->display_name }}</span>
                    </a>
                @else
                    <span class="bidder-project-file-link is-disabled">
                        <i class="fas fa-file-lines" aria-hidden="true"></i>
                        <span>{{ $document->display_name }}</span>
                    </span>
                @endif
            @endforeach
        </div>
    </div>
@endif
