<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Edit bid" subtitle="Update bid details and review status" />

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title">Edit Bid</h1>
                <p class="subtitle">Adjust bid amount, status, and reviewer notes.</p>
            </div>

            @if ($errors->any())
                <div style="margin-bottom: 18px; background: #fee2e2; color: #991b1b; padding: 14px 16px; border-radius: 12px; border: 1px solid #fecaca;">
                    <strong style="display: block; margin-bottom: 6px;">Please fix the following:</strong>
                    <ul style="margin: 0; padding-left: 18px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="table-container" style="background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb;">
                    <h3 style="margin: 0; font-size: 22px; color: #1b2420;">Bid #{{ $bid->id }}</h3>
                    <p style="margin: 6px 0 0; color: #6b736e; font-size: 14px;">{{ $bid->project->title ?? 'N/A' }} • {{ $bid->user->company ?: ($bid->user->name ?? 'N/A') }}</p>
                </div>

                <form action="{{ route('admin.bid.update', $bid) }}" method="POST" style="padding: 24px; display: grid; gap: 18px;">
                    @csrf
                    @method('PUT')

                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                        <div>
                            <span class="bid-field-label">Bid Amount (P)</span>
                            <div class="bid-field-input">{{ $bid->isFinancialSealed() ? 'Sealed (financial component)' : number_format((float) $bid->amount, 2) }}</div>
                        </div>
                        <div>
                            <span class="bid-field-label">Current Stage</span>
                            <div class="bid-field-input">{{ $bid->progress()->adminStatus()['label'] }}</div>
                        </div>
                    </div>
                    <small style="display:block; color:#6b736e; font-size:12px;">The submitted amount cannot be edited. Record stage decisions from Review Bid.</small>

                    <div>
                        <label class="bid-field-label">Notes (not shown to the bidder)</label>
                        <textarea name="notes" rows="5" class="bid-field-input" style="resize: vertical; min-height: 110px;">{{ old('notes', $bid->notes) }}</textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ route('admin.bid.view', $bid) }}" class="btn-secondary" style="text-decoration: none;">Cancel</a>
                        <button type="submit" class="btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<style>
    .bid-field-label {
        display: block;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: normal;
        text-transform: none;
        color: var(--ui-muted);
    }

    .bid-field-input {
        width: 100%;
        min-height: 36px;
        padding: 12px 14px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius);
        background: #fff;
        color: var(--ui-ink);
        font-size: 13px;
        line-height: 1.5;
        box-sizing: border-box;
        font-family: inherit;
    }

    .bid-field-input:focus {
        outline: none;
        border-color: var(--ui-primary-line);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .bid-detail-box {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: var(--ui-surface-2);
        color: var(--ui-ink);
        font-size: 14px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    @media (max-width: 900px) {
        .dashboard-content form > div:first-child {
            grid-template-columns: 1fr !important;
        }
    }
</style>
