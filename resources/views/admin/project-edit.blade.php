@php($projectDocuments = $project->uploadedDocuments())

<div class="edit-project-modal-shell">
    <div class="edit-project-modal-header">
        <div>
            <h2>Edit Project</h2>
        </div>
    </div>

    <form action="{{ route('admin.project.update', $project->id) }}" method="POST" enctype="multipart/form-data" class="edit-project-form">
        @csrf
        @method('PUT')

        <div id="editFormAlert" class="edit-project-alert" style="display: none;"></div>

        <div class="edit-project-grid">
            <div class="edit-project-field">
                <label>Project Title</label>
                <input type="text" name="title" value="{{ old('title', $project->title) }}" required class="edit-project-input">
                <p class="edit-project-error" data-error-for="title"></p>
            </div>

            <div class="edit-project-field">
                <label>Budget (&#8369;)</label>
                <input type="number" name="budget" value="{{ old('budget', $project->budget) }}" required min="0" max="9999999999999.99" step="0.01" class="edit-project-input">
                <p class="edit-project-error" data-error-for="budget"></p>
            </div>
        </div>

        <div class="edit-project-field">
            <label>Description</label>
            <textarea name="description" required class="edit-project-textarea">{{ old('description', $project->description) }}</textarea>
            <p class="edit-project-error" data-error-for="description"></p>
        </div>

        <div class="edit-project-field">
            <label>Current Files</label>
            <div class="edit-project-file-box">
                @if($projectDocuments->isNotEmpty())
                    <div class="edit-project-file-list">
                        @foreach($projectDocuments as $documentIndex => $document)
                            <a href="{{ route('admin.project.document.pdf', ['project' => $project, 'document' => $documentIndex]) }}" target="_blank" rel="noopener" class="edit-project-file-link">{{ $document->display_name }}</a>
                        @endforeach
                    </div>
                @else
                    No files uploaded
                @endif
            </div>
        </div>

        <div class="edit-project-field">
            <label>Upload New Files</label>
            <input type="file" name="document_files[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="edit-project-input" style="height: auto; padding-top: 9px; padding-bottom: 9px;">
            <p class="edit-project-error" data-error-for="document_files"></p>
        </div>

        <div class="edit-project-grid">
            <div class="edit-project-field">
                <label>Deadline</label>
                <input type="date" name="deadline" value="{{ old('deadline', $project->deadline ? \Carbon\Carbon::parse($project->deadline)->format('Y-m-d') : '') }}" required class="edit-project-input">
                <p class="edit-project-error" data-error-for="deadline"></p>
            </div>

            <div class="edit-project-field">
                <label>Assign Staff</label>
                <select name="staff_id" class="edit-project-input edit-project-select">
                    <option value="">Select staff</option>
                    @foreach($staffMembers as $staff)
                        <option value="{{ $staff->id }}" {{ (string) old('staff_id', $currentAssignment?->staff_id) === (string) $staff->id ? 'selected' : '' }}>
                            {{ $staff->name }}
                        </option>
                    @endforeach
                </select>
                <p class="edit-project-error" data-error-for="staff_id"></p>
            </div>
        </div>

        <div class="edit-project-field">
            <label>Status</label>
            <select name="status" required class="edit-project-input edit-project-select">
                <option value="approved_for_bidding" {{ old('status', $project->status) == 'approved_for_bidding' ? 'selected' : '' }}>Approved for Bidding</option>
                <option value="open" {{ old('status', $project->status) == 'open' ? 'selected' : '' }}>Open</option>
                <option value="closed" {{ old('status', $project->status) == 'closed' ? 'selected' : '' }}>Closed</option>
                <option value="awarded" {{ old('status', $project->status) == 'awarded' ? 'selected' : '' }}>Awarded</option>
            </select>
            <p class="edit-project-error" data-error-for="status"></p>
        </div>

        <div class="edit-project-actions">
            <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
            <button type="submit" id="editSubmitBtn" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<style>
    .edit-project-modal-shell {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
    }

    .edit-project-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 64px;
        padding: 0 20px;
        border-bottom: 1px solid #edf2f7;
        background: #ffffff;
    }

    .edit-project-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.2;
        color: #111827;
    }

    .edit-project-form {
        padding: 16px 16px 0;
        box-sizing: border-box;
        background: #ffffff;
    }

    .edit-project-alert {
        margin-bottom: 14px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #b91c1c;
        font-size: 12px;
    }

    .edit-project-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 4px;
    }

    .edit-project-field {
        margin-bottom: 6px;
    }

    .edit-project-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .edit-project-input,
    .edit-project-textarea {
        width: 100%;
        max-width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        color: #111827;
        font-size: 13px;
        font-weight: 400;
        line-height: 1.5;
        padding: 10px 12px;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
    }

    .edit-project-input {
        height: 40px;
    }

    .edit-project-input:focus,
    .edit-project-textarea:focus {
        outline: none;
        border-color: #93c5fd;
        box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
        background: #ffffff;
    }

    .edit-project-textarea {
        min-height: 50px;
        max-height: 82px;
        padding-top: 10px;
        resize: vertical;
    }

    .edit-project-file-box {
        width: 100%;
        min-height: 40px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        color: #111827;
        font-size: 13px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    .edit-project-file-list {
        display: grid;
        gap: 8px;
        width: 100%;
    }

    .edit-project-file-link {
        color: #1d4ed8;
        text-decoration: none;
        word-break: break-all;
    }

    .edit-project-input.input-error,
    .edit-project-textarea.input-error {
        border-color: #f87171;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.12);
    }

    .edit-project-error {
        margin: 4px 0 0;
        color: #dc2626;
        font-size: 11px;
        line-height: 1.4;
    }

    .edit-project-error:empty {
        display: none;
    }

    .edit-project-select {
        appearance: auto;
    }

    .edit-project-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        align-items: center;
        margin: 2px -16px 0;
        padding: 12px 16px 14px;
        border-top: 1px solid #edf2f7;
        background: #fff;
        box-sizing: border-box;
    }

    .edit-project-actions .btn-primary,
    .edit-project-actions .btn-secondary {
        min-width: 132px;
        height: 38px;
        padding: 0 16px;
        border-radius: 10px;
        font-size: 12px;
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .edit-project-actions .btn-primary {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.22);
    }

    .edit-project-actions .btn-primary:hover {
        background: #1e40af;
        border-color: #1e40af;
    }

    .edit-project-actions .btn-secondary {
        background: #fff;
        border: 1px solid #d1d5db;
        color: #334155;
        font-weight: 500;
    }

    .edit-project-actions .btn-secondary:hover {
        background: #f8fafc;
    }

    @media (max-width: 700px) {
        .edit-project-grid {
            grid-template-columns: 1fr;
        }

        .edit-project-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .edit-project-actions .btn-primary,
        .edit-project-actions .btn-secondary {
            width: 100%;
        }
    }
</style>
