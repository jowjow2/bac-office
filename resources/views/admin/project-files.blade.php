@php($projectDocuments = $project->uploadedDocuments())

<div class="project-files-modal-shell">
    <div class="project-files-modal-header">
        <div>
            <h2>Project Files</h2>
            <p>{{ $project->title }}</p>
        </div>
    </div>

    <div class="project-files-modal-body">
        <div id="projectFilesAlert" class="project-files-alert" style="display: none;"></div>

        @if($projectDocuments->isNotEmpty())
            <div class="project-files-summary">
                {{ $projectDocuments->count() }} {{ \Illuminate\Support\Str::plural('file', $projectDocuments->count()) }} uploaded
            </div>

            <div class="project-files-list">
                @foreach($projectDocuments as $documentIndex => $document)
                    <div class="project-files-item">
                        <div class="project-files-item-copy">
                            <div class="project-files-item-name">{{ $document->display_name }}</div>
                            <div class="project-files-item-meta">
                                Click preview to open this file as PDF.
                            </div>
                        </div>

                        <div class="project-files-item-actions">
                            <a href="{{ route('admin.project.document.pdf', ['project' => $project, 'document' => $documentIndex]) }}" target="_blank" rel="noopener" class="project-files-item-link">
                                Preview
                            </a>

                            <form action="{{ route('admin.project.document.destroy', ['project' => $project, 'document' => $documentIndex]) }}" method="POST" data-project-file-delete-form>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="project-files-item-delete" onclick="return confirm('Are you sure you want to delete this file? This action cannot be undone.');">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="project-files-empty">
                No files uploaded for this project yet.
            </div>
        @endif
    </div>
</div>

<style>
    .project-files-modal-shell {
        background: #fff;
        border-radius: var(--ui-radius-lg);
        overflow: hidden;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: var(--ui-font);
        box-shadow: 0 18px 42px rgba(27, 36, 32, 0.12);
    }

    .project-files-modal-header {
        padding: 18px 20px 14px;
        border-bottom: 1px solid var(--ui-line-soft);
        background: linear-gradient(180deg, #ffffff 0%, var(--ui-surface-2) 100%);
    }

    .project-files-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--ui-ink);
    }

    .project-files-modal-header p {
        margin: 6px 0 0;
        font-size: 13px;
        color: var(--ui-muted);
    }

    .project-files-modal-body {
        padding: 18px 20px 20px;
        display: grid;
        gap: 14px;
        background: #ffffff;
    }

    .project-files-summary {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 6px 11px;
        border-radius: 999px;
        background: var(--ui-primary-soft);
        color: var(--ui-primary);
        font-size: 12px;
        font-weight: 600;
    }

    .project-files-alert {
        padding: 10px 12px;
        border-radius: var(--ui-radius-lg);
        font-size: 12px;
        line-height: 1.45;
    }

    .project-files-alert.is-success {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        color: #166534;
    }

    .project-files-alert.is-error {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #b91c1c;
    }

    .project-files-list {
        display: grid;
        gap: 10px;
    }

    .project-files-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 15px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: var(--ui-surface-2);
    }

    .project-files-item-actions {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .project-files-item-copy {
        min-width: 0;
        display: grid;
        gap: 4px;
    }

    .project-files-item-name {
        color: var(--ui-ink);
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        word-break: break-word;
    }

    .project-files-item-meta {
        color: var(--ui-muted);
        font-size: 12px;
        line-height: 1.4;
    }

    .project-files-item-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 88px;
        height: 36px;
        padding: 0 14px;
        border-radius: var(--ui-radius-lg);
        background: var(--ui-primary);
        color: #ffffff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(29, 79, 64, 0.2);
    }

    .project-files-item-link:hover {
        background: var(--ui-primary-hover);
    }

    .project-files-item-delete {
        min-width: 88px;
        height: 36px;
        padding: 0 14px;
        border: 1px solid #fecaca;
        border-radius: var(--ui-radius-lg);
        background: #fff1f2;
        color: #b91c1c;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: var(--ui-font);
    }

    .project-files-item-delete:hover {
        background: #ffe4e6;
    }

    .project-files-item-delete:disabled {
        opacity: 0.65;
        cursor: wait;
    }

    .project-files-empty {
        padding: 18px;
        border: 1px dashed var(--ui-line-strong);
        border-radius: var(--ui-radius-lg);
        color: var(--ui-muted);
        font-size: 13px;
        text-align: center;
        background: var(--ui-surface-2);
    }

    @media (max-width: 700px) {
        .project-files-item {
            flex-direction: column;
            align-items: stretch;
        }

        .project-files-item-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }

        .project-files-item-link {
            width: 100%;
        }

        .project-files-item-delete {
            width: 100%;
        }
    }
</style>
