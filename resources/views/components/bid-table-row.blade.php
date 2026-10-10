@props(['bid', 'ranking' => null])
@php
    $name = $bid->user?->company ?: ($bid->user?->name ?? 'N/A');
    $mode = $bid->project?->mode();
    $status = $bid->progress()->adminStatus();
    $documentStatus = $bid->submissionDocumentStatus();
    $submittedAt = $bid->submitted_at ?? $bid->created_at;
    $nextAction = $bid->isSealed()
        ? 'View sealed submission'
        : ($mode?->isCompetitive() ? 'Review bid' : 'Review '.$mode?->submissionNoun());
    $openingAt = $bid->project?->schedule?->bid_opening_date;
@endphp
<tr>
    <td data-label="Project" class="bid-project-cell">
        <div class="bid-project-ref">{{ $bid->project?->reference_no ?: 'No reference' }}</div>
        <div class="bid-project-title" title="{{ $bid->project?->title }}">{{ $bid->project?->title ?? 'N/A' }}</div>
        <span class="bid-mode-label">{{ $mode?->label() ?? 'Mode not recorded' }}</span>
    </td>
    <td data-label="Bidder" class="bid-bidder-cell">
        <div class="bid-name-line">
            <span class="bid-name" title="{{ $name }}">{{ $name }}</span>
            @if($bid->user?->philgepsCertificate?->file_url)
                <span class="bid-verified-mark" aria-label="PhilGEPS certificate on file"><i class="fas fa-certificate" aria-hidden="true"></i></span>
            @endif
        </div>
        <div class="bid-email" title="{{ $bid->user?->email }}">{{ $bid->user?->email ?? 'N/A' }}</div>
    </td>
    <td data-label="Submitted" class="bid-submission-cell">
        @if($bid->isDraft())
            <strong>Draft only</strong>
            <span>Not officially submitted</span>
        @else
            <strong>{{ $submittedAt?->timezone(config('bac-office.display_timezone'))->format('M d, Y') ?? 'Not recorded' }}</strong>
            <span>{{ $submittedAt?->timezone(config('bac-office.display_timezone'))->format('h:i:s A') ?? '' }} - {{ $bid->receipt_no ?: 'No receipt no.' }}</span>
        @endif
    </td>
    <td data-label="Bid amount" class="bid-amount-cell bid-numeric">
        @if($bid->isFinancialSealed())
            <span class="bid-sealed-value"><i class="fas fa-lock" aria-hidden="true"></i> Sealed</span>
            <small>{{ $bid->isSealed() ? 'Technical opens at scheduled opening' : 'Opens after technical approval and PIN' }}</small>
        @else
            <strong>&#8369;{{ number_format((float) $bid->amount, 2) }}</strong>
        @endif
        <x-bid-rank :ranking="$ranking" compact />
    </td>
    <td data-label="Documents" class="bid-document-cell">
        <span class="bid-document-status is-{{ $documentStatus['key'] }}">
            <i class="fas {{ in_array($documentStatus['key'], ['complete', 'received_sealed'], true) ? 'fa-file-circle-check' : ($documentStatus['key'] === 'incomplete' ? 'fa-file-circle-exclamation' : 'fa-lock') }}" aria-hidden="true"></i>
            {{ $documentStatus['label'] }}
        </span>
        @if($documentStatus['missing_labels'])
            <small>{{ implode(', ', array_slice($documentStatus['missing_labels'], 0, 2)) }}{{ count($documentStatus['missing_labels']) > 2 ? ' +' . (count($documentStatus['missing_labels']) - 2) : '' }}</small>
        @endif
    </td>
    <td data-label="Stage" class="bid-stage-cell">
        <x-bid-status-badge :bid="$bid" />
        @if($bid->isSealed() && $openingAt)
            <small>Opening {{ $openingAt->timezone(config('bac-office.display_timezone'))->format('M d, Y h:i A') }}</small>
        @endif
    </td>
    <td data-label="Next action" class="bid-actions-cell">
        <button type="button" class="bid-control bid-review-button" data-bid-review="{{ $bid->id }}">
            <i class="fas {{ $bid->isSealed() ? 'fa-lock' : 'fa-arrow-right' }}" aria-hidden="true"></i>
            <span>{{ $nextAction }}</span>
        </button>
        <button type="button" class="bid-control bid-menu-toggle" aria-label="More actions for {{ $name }}" aria-haspopup="menu" aria-expanded="false" aria-controls="bid-menu-{{ $bid->id }}">
            <i class="fas fa-ellipsis" aria-hidden="true"></i>
        </button>
        <div id="bid-menu-{{ $bid->id }}" class="bid-actions-menu" role="menu" aria-label="More actions for {{ $name }}" hidden>
            <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}">{{ $nextAction }}</button>
            @if(!$bid->isFinancialSealed() && $bid->proposal_url)
                <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="proposal">View proposal</button>
            @endif
            <button type="button" role="menuitem" data-bid-review="{{ $bid->id }}" data-review-section="history">Activity history</button>
        </div>
    </td>
</tr>
