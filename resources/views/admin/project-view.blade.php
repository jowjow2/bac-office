<div class="view-project-modal-shell">
    <div class="view-project-modal-header">
        <div>
            <h2>View Project</h2>
        </div>
    </div>

    <div class="view-project-modal-body">
        <div class="view-project-grid">
            <div class="view-project-field">
                <label>Project Title</label>
                <div class="view-project-value">{{ $project->title }}</div>
            </div>

            <div class="view-project-field">
                <label>Status</label>
                <div class="view-project-value">
                    <span class="view-project-status-pill {{ $project->status }}">{{ \Illuminate\Support\Str::headline($project->status) }}</span>
                </div>
            </div>
        </div>

        <div class="view-project-grid">
            <div class="view-project-field">
                <label>Budget (&#8369;)</label>
                <div class="view-project-value">P{{ number_format((float) $project->budget, 2) }}</div>
            </div>

            <div class="view-project-field">
                <label>Total Bids</label>
                <div class="view-project-value">{{ $project->bids_count }}</div>
            </div>
        </div>

        <div class="view-project-field">
            <label>Description</label>
            <div class="view-project-value view-project-textarea">{{ $project->description ?: 'N/A' }}</div>
        </div>

        <div class="view-project-grid">
            <div class="view-project-field">
                <label>Deadline</label>
                <div class="view-project-value">{{ $project->deadline ? $project->deadline->format('m/d/Y') : 'N/A' }}</div>
            </div>

            <div class="view-project-field">
                <label>Assign Staff</label>
                <div class="view-project-value">{{ $project->assignments->first()?->staff?->name ?? 'Unassigned' }}</div>
            </div>
        </div>

        <div class="view-project-actions">
            <button type="button" onclick="closeViewModal()" class="btn-secondary">Close</button>
            <button type="button" onclick="closeViewModal(); loadEditModal({{ $project->id }})" class="btn-primary">Edit Project</button>
        </div>
    </div>
</div>

<style>
    .view-project-modal-shell {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
    }

    .view-project-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-height: 64px;
        padding: 0 20px;
        border-bottom: 1px solid #edf2f7;
        background: #ffffff;
    }

    .view-project-modal-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        line-height: 1.2;
        color: #111827;
    }

    .view-project-modal-body {
        padding: 16px 16px 0;
        display: grid;
        gap: 8px;
        box-sizing: border-box;
        background: #ffffff;
    }

    .view-project-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 4px;
    }

    .view-project-field {
        margin-bottom: 6px;
    }

    .view-project-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .view-project-value {
        width: 100%;
        min-height: 40px;
        display: flex;
        align-items: center;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        background: #fff;
        color: #111827;
        font-size: 13px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    .view-project-textarea {
        min-height: 84px;
        align-items: flex-start;
        white-space: pre-wrap;
    }

    .view-project-status-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
    }

    .view-project-status-pill.open {
        background: #dcfce7;
        color: #166534;
    }

    .view-project-status-pill.approved_for_bidding {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .view-project-status-pill.awarded {
        background: #fef3c7;
        color: #b45309;
    }

    .view-project-status-pill.closed {
        background: #e5e7eb;
        color: #475569;
    }

    .view-project-actions {
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

    .view-project-actions .btn-primary,
    .view-project-actions .btn-secondary {
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

    .view-project-actions .btn-primary {
        background: #1d4ed8;
        border: 1px solid #1d4ed8;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 10px 24px rgba(29, 78, 216, 0.22);
    }

    .view-project-actions .btn-primary:hover {
        background: #1e40af;
        border-color: #1e40af;
    }

    .view-project-actions .btn-secondary {
        background: #fff;
        border: 1px solid #d1d5db;
        color: #334155;
        font-weight: 500;
    }

    .view-project-actions .btn-secondary:hover {
        background: #f8fafc;
    }

    @media (max-width: 700px) {
        .view-project-grid {
            grid-template-columns: 1fr;
        }

        .view-project-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .view-project-actions .btn-primary,
        .view-project-actions .btn-secondary {
            width: 100%;
        }
    }
</style>
