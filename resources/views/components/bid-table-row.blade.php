@props(['bid', 'ranking' => null])
@php
    $tz = config('bac-office.display_timezone');
    $name = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
    $initials = collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
    $mode = $bid->project?->mode();
    $documentStatus = $bid->submissionDocumentStatus();
    $missing = $documentStatus['missing_labels'] ?? [];
    // One short word or two in the row; the full sentence and the missing files are in the tooltip and the bid view.
    $documentShort = match ($documentStatus['key']) {
        'complete' => 'Complete',
        'received_sealed' => 'Complete · sealed',
        'incomplete' => count($missing).' missing',
        'sealed_envelope' => 'Sealed envelope',
        'draft' => 'Draft',
        default => $documentStatus['label'],
    };
    $documentTitle = $documentStatus['label'].($missing ? ': '.implode(', ', $missing) : '');
    $documentIcon = in_array($documentStatus['key'], ['complete', 'received_sealed'], true) ? 'fa-circle-check' : ($documentStatus['key'] === 'incomplete' ? 'fa-circle-exclamation' : 'fa-lock');
    $submittedAt = $bid->submitted_at ?? $bid->created_at;
    $nextAction = $bid->isSealed()
        ? 'View sealed submission'
        : ($mode?->isCompetitive() ? 'Review bid' : 'Review '.$mode?->submissionNoun());
    // The button says it in a word or two; the menu keeps the full wording.
    $buttonLabel = $bid->isSealed() ? 'View sealed' : 'Review';
    $openingAt = $bid->project?->schedule?->bid_opening_date;
@endphp
<tr>
    <td data-label="Project" class="bid-project-cell">
        <span class="bid-project-ref">{{ $bid->project?->reference_no ?: 'No reference' }}</span>
        <div class="bid-project-title" title="{{ $bid->project?->title }}">{{ $bid->project?->title ?? 'N/A' }}</div>
        <span class="bid-mode-label">{{ $mode?->label() ?? 'Mode not recorded' }}</span>
    </td>
    <td data-label="Bidder" class="bid-bidder-cell">
        <div class="bid-bidder">
            <span class="bid-avatar" aria-hidden="true">{{ $initials }}</span>
            <span class="bid-bidder-text">
                <span class="bid-name-line">
                    <span class="bid-name" title="{{ $name }}">{{ $name }}</span>
                    @if($bid->user?->philgepsCertificate?->file_url)
                        <span class="bid-verified-mark" aria-label="PhilGEPS certificate on file" title="PhilGEPS certificate on file"><i class="fas fa-certificate" aria-hidden="true"></i></span>
                    @endif
                </span>
                <span class="bid-email" title="{{ $bid->user?->email }}">{{ $bid->user?->email ?? 'N/A' }}</span>
            </span>
        </div>
    </td>
    <td data-label="Submitted" class="bid-submission-cell">
        @if($bid->isDraft())
            <strong class="bid-date">Draft only</strong>
            <span class="bid-time">Not officially submitted</span>
        @else
            <strong class="bid-date">{{ $submittedAt?->timezone($tz)->format('M d, Y') ?? 'Not recorded' }}</strong>
            <span class="bid-time">{{ $submittedAt?->timezone($tz)->format('h:i A') ?? '' }}</span>
            <span class="bid-receipt" title="Receipt number">{{ $bid->receipt_no ?: 'No receipt no.' }}</span>
        @endif
    </td>
    <td data-label="Bid amount" class="bid-amount-cell bid-numeric">
        @if($bid->isFinancialSealed())
            <span class="bid-sealed-value" title="{{ $bid->isSealed() ? 'Technical opens at scheduled opening' : 'Opens after technical approval and PIN' }}"><i class="fas fa-lock" aria-hidden="true"></i> Sealed</span>
        @else
            <strong class="bid-amount">&#8369;{{ number_format((float) $bid->amount, 2) }}</strong>
        @endif
        <x-bid-rank :ranking="$ranking" compact />
    </td>
    <td data-label="Documents" class="bid-document-cell">
        <span class="bid-document-status is-{{ $documentStatus['key'] }}" title="{{ $documentTitle }}">
            <i class="fas {{ $documentIcon }}" aria-hidden="true"></i>
            {{ $documentShort }}
        </span>
    </td>
    <td data-label="Stage" class="bid-stage-cell">
        <x-bid-status-badge :bid="$bid" />
        @if($bid->isSealed() && $openingAt)
            <span class="bid-stage-note">Opens {{ $openingAt->timezone($tz)->format('M d, h:i A') }}</span>
        @endif
    </td>
    <td data-label="Next action" class="bid-actions-cell">
        <div class="bid-actions">
            <button type="button" class="bid-control bid-review-button" data-bid-review="{{ $bid->id }}" title="{{ $nextAction }}">
                <i class="fas {{ $bid->isSealed() ? 'fa-lock' : 'fa-arrow-right' }}" aria-hidden="true"></i>
                <span>{{ $buttonLabel }}</span>
            </button>
            <button type="button" class="bid-control bid-menu-toggle" aria-label="More actions for {{ $name }}" aria-haspopup="menu" aria-expanded="false" aria-controls="bid-menu-{{ $bid->id }}">
                <i class="fas fa-ellipsis" aria-hidden="true"></i>
            </button>
        </div>
        <div id="bid-menu-{{ $bid->id }}" class="bid-actions-menu" role="menu" aria-label="More actions for {{ $name }}" hidden>
            <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}">{{ $nextAction }}</button>
            @if(!$bid->isFinancialSealed() && $bid->proposal_url)
                <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="proposal">View proposal</button>
            @endif
            <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="history">Activity history</button>
        </div>
    </td>
</tr>
