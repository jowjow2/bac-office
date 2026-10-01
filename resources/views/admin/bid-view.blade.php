<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
<div class="admin-dashboard admin-role-page">
    @vite(['resources/css/dashboard.css'])

    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Bid details" subtitle="Review submitted bidder information" />

        <main class="dashboard-content">
            <div class="welcome-text">
                <h1 class="title">View Bid</h1>
                <p class="subtitle">Inspect the submitted bid proposal and bidder details.</p>
            </div>

            <div class="table-container" style="background: white; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div>
                        <h3 style="margin: 0; font-size: 22px; color: #1b2420;">Bid #{{ $bid->id }}</h3>
                        <p style="margin: 6px 0 0; color: #6b736e; font-size: 14px;">Submitted {{ $bid->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="{{ route('admin.bid.edit', $bid) }}" class="btn-primary" style="text-decoration: none;">Edit Bid</a>
                        <a href="{{ route('admin.bids') }}" class="btn-secondary" style="text-decoration: none;">Back to All Bids</a>
                    </div>
                </div>

                <div style="padding: 24px; display: grid; gap: 18px;">
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px;">
                         <div>
                             <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Project</label>
                             <div class="bid-detail-box">{{ $bid->project?->title ?? 'N/A' }}</div>
                         </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Bidder</label>
                            <div class="bid-detail-box">{{ $bid->user?->company ?: ($bid->user?->name ?? 'N/A') }}</div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Bid Amount</label>
                            <div class="bid-detail-box"><x-bid-amount :bid="$bid" prefix="P" /></div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Status</label>
                            <div class="bid-detail-box">{{ $bid->progress()->adminStatus()["label"] }}</div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Proposal File</label>
                            <div class="bid-detail-box">
                                @if($bid->proposal_url && ! $bid->isSealed())
                                    <a
                                        href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']) }}"
                                        target="_blank"
                                        rel="noopener"
                                        style="color: #1d4f40; text-decoration: none;"
                                    >{{ $bid->proposal_filename }}</a>
                                @else
                                    No file uploaded
                                @endif
                            </div>
                        </div>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Notes</label>
                        <div class="bid-detail-box" style="min-height: 100px; align-items: flex-start; white-space: pre-wrap;">{{ $bid->notes ?: 'No notes provided.' }}</div>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6b736e;">Certificate Proof</label>
                        <div class="bid-detail-box">
                        @if($bid->user?->philgepsCertificate?->file_url)
                                <a
                                    href="{{ route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'certificate']) }}"
                                    target="_blank"
                                    rel="noopener"
                                    style="color: #1d4f40; text-decoration: none;"
                                >
                                    {{ $bid->user->philgepsCertificate->display_name }}
                                </a>
                            @else
                                No PhilGEPS certificate uploaded
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .bid-detail-box {
        width: 100%;
        min-height: 44px;
        display: flex;
        align-items: center;
        padding: 12px 14px;
        border: 1px solid var(--ui-line);
        border-radius: var(--ui-radius-lg);
        background: #fff;
        color: var(--ui-ink);
        font-size: 14px;
        line-height: 1.5;
        box-sizing: border-box;
    }

    @media (max-width: 900px) {
        .dashboard-content > .table-container > div:nth-child(2) > div {
            grid-template-columns: 1fr !important;
        }
    }
</style>
