<div class="admin-bid-edit-modal-shell">
    <div class="admin-bid-edit-modal-header">
        <div>
            <p>Edit Bid</p>
            <h2>Bid #{{ $bid->id }}</h2>
        </div>
        <span class="admin-bid-edit-project-pill">{{ $bid->project?->title ?? 'No project' }}</span>
    </div>

    <form action="{{ route('admin.bid.update', $bid) }}" method="POST" class="admin-bid-edit-modal-body admin-bid-edit-form">
        @csrf
        @method('PUT')

        <section class="admin-bid-edit-section">
            <h3>Bidder</h3>
            <div class="admin-bid-edit-grid admin-bid-edit-grid-two">
                <div class="admin-bid-edit-field">
                    <label>Bidder / Company</label>
                    <div class="admin-bid-edit-value">{{ $bid->user?->company ?: ($bid->user?->name ?? 'N/A') }}</div>
                </div>
                <div class="admin-bid-edit-field">
                    <label>Project</label>
                    <div class="admin-bid-edit-value">{{ $bid->project?->title ?? 'N/A' }}</div>
                </div>
            </div>
        </section>

        <section class="admin-bid-edit-section">
            <h3>Internal Notes</h3>
            <div class="admin-bid-edit-grid admin-bid-edit-grid-two">
                <div class="admin-bid-edit-field">
                    <label>Bid Amount (&#8369;)</label>
                    <div class="admin-bid-edit-value">{{ $bid->isFinancialSealed() ? 'Sealed (financial component)' : number_format((float) $bid->amount, 2) }}</div>
                </div>

                <div class="admin-bid-edit-field">
                    <label>Current Stage</label>
                    <div class="admin-bid-edit-value">{{ $bid->progress()->adminStatus()['label'] }}</div>
                </div>
            </div>
            <small class="admin-bid-edit-hint">The submitted amount cannot be edited. Record stage decisions from Review Bid so the bidder's track and history stay consistent.</small>

            <div class="admin-bid-edit-field">
                <label for="edit_notes_{{ $bid->id }}">Notes (not shown to the bidder)</label>
                <textarea id="edit_notes_{{ $bid->id }}" name="notes" rows="5">{{ old('notes', $bid->notes) }}</textarea>
            </div>
        </section>

        <div class="admin-bid-edit-actions">
            <button type="button" onclick="loadBidViewModal({{ $bid->id }})" class="btn-secondary">Back to Details</button>
            @if($bid->user_id)
                <a href="{{ route('admin.messages', ['tab' => 'bidders', 'user' => $bid->user_id]) }}" class="btn-secondary admin-bid-edit-message-button">
                    <i class="fas fa-message" aria-hidden="true"></i>
                    Message Bidder
                </a>
            @endif
            <button type="button" onclick="closeBidViewModal()" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>
