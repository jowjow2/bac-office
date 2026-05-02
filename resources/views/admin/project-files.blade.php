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
                                <button type="submit" class="project-files-item-delete" onclick="return confirm('Delete this file?');">
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
        border-radius: 16px;
        overflow: hidden;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
    }

    .project-files-modal-header {
        padding: 18px 20px 14px;
        border-bottom: 1px solid #edf2f7;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .project-files-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }

    .project-files-modal-header p {
        margin: 6px 0 0;
        font-size: 13px;
        color: #64748b;
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
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
    }

    .project-files-alert {
        padding: 10px 12px;
        border-radius: 10px;
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
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
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
        color: #0f172a;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.45;
        word-break: break-word;
    }

    .project-files-item-meta {
        color: #64748b;
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
        border-radius: 10px;
        background: #1d4ed8;
        color: #ffffff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.2);
    }

    .project-files-item-link:hover {
        background: #1e40af;
    }

    .project-files-item-delete {
        min-width: 88px;
        height: 36px;
        padding: 0 14px;
        border: 1px solid #fecaca;
        border-radius: 10px;
        background: #fff1f2;
        color: #b91c1c;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
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
        border: 1px dashed #cbd5e1;
        border-radius: 14px;
        color: #64748b;
        font-size: 13px;
        text-align: center;
        background: #f8fafc;
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
