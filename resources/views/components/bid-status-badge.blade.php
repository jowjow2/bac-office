@props(['bid'])
@php
    // Same classification as the bidder track, filters and exports.
    $status = $bid->progress()->adminStatus();
    $icon = match ($status['key']) {
        'disqualified' => 'circle-xmark',
        'not_awarded' => 'circle-minus',
        'failed_bidding' => 'rotate',
        'award_approval', 'notice_of_award', 'contract_signed', 'notice_to_proceed' => 'trophy',
        'bac_recommendation' => 'gavel',
        'post_qualification' => 'user-check',
        'bid_evaluation' => 'scale-balanced',
        'preliminary_examination' => 'folder-open',
        default => 'envelope',
    };
@endphp
<span class="admin-bids-status-pill is-{{ $status['class'] }} bid-status-badge" data-stage="{{ $status['key'] }}">
    <i class="fas fa-{{ $icon }}" aria-hidden="true"></i> {{ $status['label'] }}
</span>
