@props(['project'])
@php
    $assignedStaff = $project->assignments->first()?->staff?->name;
    $budget = (float) $project->budget;
    $isOverdue = $project->status === 'open'
        && $project->deadline
        && $project->deadline->isPast()
        && (!$project->deadline->isToday() || $project->deadline->format('H:i:s') !== '00:00:00');
    // Past the deadline the project no longer takes bids: submissions are closed until the opening is recorded.
    $displayStatusLabel = $isOverdue
        ? ($project->requiresRecordedBidOpening() ? 'Awaiting opening' : 'Submission closed')
        : \Illuminate\Support\Str::ucfirst(str_replace('_', ' ', $project->status ?: 'draft'));
    $displayStatusClass = $isOverdue ? 'closed' : str_replace('_', '-', $project->status ?: 'draft');
    $hasBidAnomaly = $budget > 0 && $project->relationLoaded('bids') && $project->bids->contains(function ($bid) use ($budget) {
        return (((float) $bid->bid_amount - $budget) / $budget) * 100 > 500;
    });
    $deadlineTone = 'none';
    $deadlineMeta = 'No date set';

    if ($project->deadline) {
        if ($isOverdue) {
            $deadlineTone = 'past';
            $deadlineMeta = 'Submission closed';
        } elseif ($project->deadline->isToday()) {
            $deadlineTone = 'today';
            $deadlineMeta = 'Due today';
        } elseif ($project->deadline->isPast()) {
            $deadlineTone = 'past';
            $deadlineMeta = 'Past due';
        } elseif ($project->deadline->diffInDays(now()) <= 7) {
            $deadlineTone = 'soon';
            $deadlineMeta = 'Due soon';
        } else {
            $deadlineTone = 'scheduled';
            $deadlineMeta = $project->deadline->diffForHumans();
        }
    }
    $needsUrgentStaffing = !$assignedStaff && in_array($deadlineTone, ['today', 'past'], true);
@endphp

<tr class="projects-row">
    <td data-label="Project">
        <div class="projects-title-stack">
            <button type="button" onclick="loadViewModal({{ $project->id }})" class="projects-title-button" title="{{ $project->title }}">
                {{ $project->title }}
            </button>
            <p class="projects-title-meta" title="{{ $project->modeLabel() }} · {{ $project->mode()->legalBasisShort() }}{{ $project->reference_no ? ' · '.$project->reference_no : '' }}">
                <span>{{ $project->modeLabel() }}</span>
                <span>{{ $project->mode()->legalBasisShort() }}</span>
                @if($project->reference_no)
                    <span class="projects-ref">{{ $project->reference_no }}</span>
                @endif
            </p>
            @if($project->description)
                <p class="projects-title-desc">{{ \Illuminate\Support\Str::limit($project->description, 96) }}</p>
            @endif
        </div>
    </td>
    <td data-label="Budget">
        <span class="projects-money">&#8369;{{ number_format($budget, 2) }}</span>
    </td>
    <td data-label="Deadline">
        <span class="projects-deadline projects-deadline--{{ $deadlineTone }}">
            <strong>{{ $project->deadline ? $project->deadline->format('M d, Y') : 'Not set' }}</strong>
            <small>{{ $deadlineMeta }}</small>
        </span>
    </td>
    <td data-label="Staff">
        @if($assignedStaff || $project->archived_at)
            <span class="projects-staff-chip {{ $assignedStaff ? '' : 'is-empty' }}">
                <i class="fas fa-user-check" aria-hidden="true"></i>
                <span>{{ $assignedStaff ?: 'Unassigned' }}</span>
            </span>
        @else
            {{-- Opens the quick "Assign staff" dialog of the Projects page. --}}
            <button type="button" class="projects-staff-chip is-empty is-action {{ $needsUrgentStaffing ? 'is-urgent' : '' }}"
                data-quick-assign="{{ $project->id }}" data-project-title="{{ $project->title }}"
                title="{{ $needsUrgentStaffing ? 'Urgent: due today or past due. Assign a staff member.' : 'Assign a staff member' }}">
                <i class="fas fa-user-plus" aria-hidden="true"></i>
                <span class="sr-only">Unassigned.</span>
                <span>Assign staff</span>
                @if($needsUrgentStaffing)
                    <i class="fas fa-triangle-exclamation projects-staff-urgent-icon" aria-hidden="true"></i>
                    <span class="sr-only">Urgent staffing needed</span>
                @endif
            </button>
        @endif
    </td>
    <td data-label="Bids">
        <span class="projects-bids-cell">
            <span class="projects-bids-count {{ $project->bids_count > 0 ? 'has-bids' : '' }}">
                {{ $project->bids_count }}
            </span>
            @if($hasBidAnomaly)
                <a href="{{ route('admin.bids', ['project' => $project->id]) }}" class="projects-bid-anomaly" title="Unusual bid variance &mdash; review bids for this project" aria-label="Unusual bid variance &mdash; review bids for this project">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                </a>
            @endif
        </span>
    </td>
    <td data-label="Status">
        <x-project-status-badge :label="$displayStatusLabel" :status-class="$displayStatusClass" />
        @if($project->isScheduledForPublication())
            <span title="Hidden from the public until then (server time, Asia/Manila)" style="display:inline-flex;align-items:center;gap:5px;margin-top:5px;padding:2px 8px;border-radius:999px;background:var(--ui-warning-soft);color:var(--ui-warning);font-size:11.5px;font-weight:700;white-space:nowrap"><i class="fas fa-clock" aria-hidden="true"></i> Scheduled: public {{ $project->publicationTime()->format('M d, Y g:i A') }}</span>
        @endif
    </td>
    <td data-label="Actions">
        <div class="projects-actions">
            <button type="button" onclick="loadViewModal({{ $project->id }})" class="projects-action-button projects-action-view" title="View project details">
                <i class="fas fa-eye" aria-hidden="true"></i>
                <span>View</span>
            </button>
            <div class="projects-action-menu">
                <button type="button" class="projects-action-button projects-action-more" onclick="toggleProjectActionMenu(this)" aria-haspopup="true" aria-expanded="false" title="More actions: Edit, Archive, Delete" aria-label="More actions: Edit, Archive, Delete">
                    <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                    <span class="sr-only">More actions</span>
                </button>
                <div class="projects-action-dropdown" role="menu">
                    <a href="{{ route('admin.procurement.show', $project) }}" class="projects-action-menu-item" role="menuitem">
                        <i class="fas fa-timeline" aria-hidden="true"></i>
                        <span>Procurement Record</span>
                    </a>
                    <button type="button" onclick="loadEditModal({{ $project->id }}); closeProjectActionMenus();" class="projects-action-menu-item" role="menuitem">
                        <i class="fas fa-pen" aria-hidden="true"></i>
                        <span>Edit Project</span>
                    </button>
                    @if($project->status === 'draft')
                        <button type="button" onclick="publishDraft({{ $project->id }}, this)" class="projects-action-menu-item projects-action-publish" role="menuitem">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i>
                            <span>Publish Project</span>
                        </button>
                    @endif
                    <form action="{{ route('admin.project.archive', $project) }}" method="POST" class="projects-archive-form" onsubmit="return confirm('Are you sure you want to archive this project? It will be removed from the active project list but kept in the system.');">
                        @csrf
                        <button type="submit" class="projects-action-menu-item projects-action-archive" role="menuitem">
                            <i class="fas fa-box-archive" aria-hidden="true"></i>
                            <span>Archive Project</span>
                        </button>
                    </form>
                    <form action="{{ route('admin.project.destroy', $project) }}" method="POST" class="projects-delete-form" onsubmit="return confirm('Are you sure you want to delete this project? This will also remove its bids, awards, and staff assignments. This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="projects-action-menu-item projects-action-delete" role="menuitem">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                            <span>Delete Project</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </td>
</tr>
