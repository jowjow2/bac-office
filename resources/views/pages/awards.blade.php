@extends('layouts.public')

@section('title', 'Awards & Contracts')
@section('body_class', 'public-page')

@section('content')
    @php
        /** $records / $postings come from PublicAwardController::publicRecord(). */
        $totalAwardedValue = $awards->sum('contract_amount');
        $cardUrl = fn (array $record) => route('public.awards', array_filter(['q' => $query, 'award' => $record['id'], 'page' => $postings->currentPage() > 1 ? $postings->currentPage() : null])).'#award-document';
        $pageUrl = fn (int $page) => route('public.awards', array_filter(['q' => $query, 'award' => $selected['id'] ?? null, 'page' => $page > 1 ? $page : null]));
        $postTitle = fn (array $record) => trim(($record['date_code'] ? $record['date_code'].' – ' : '').'Notice of Award');
    @endphp

    <main class="public-shell award-posts" data-award-docs>
        <header class="board-bar">
            <div>
                <p class="board-eyebrow">Bids and Awards Committee &middot; San Jose, Occidental Mindoro</p>
                <h1>Awards &amp; Contracts</h1>
            </div>
            <form action="{{ route('public.awards') }}" method="GET" class="board-search" role="search">
                <label for="award-docs-q" class="sr-only">Search awards</label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="award-docs-q" type="search" name="q" value="{{ $query }}" placeholder="Search project, contractor or reference no." autocomplete="off">
                <button type="submit" class="btn">Search</button>
            </form>
        </header>

        @if($query !== '')
            <p class="board-results">
                {{ $records->count() }} result{{ $records->count() === 1 ? '' : 's' }} for "<strong>{{ $query }}</strong>"
                <a href="{{ route('public.awards') }}">Clear search</a>
            </p>
        @endif

        @if($selected === null)
            <div class="public-empty-state award-posts-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                <p>
                    @if($query !== '')
                        No published award matched "<strong>{{ $query }}</strong>".
                    @else
                        No awarded contracts have been published yet.
                    @endif
                </p>
            </div>
        @else
            {{-- The selected posting: title, posting date and reference, then its document. --}}
            <article id="award-document" class="award-post" aria-labelledby="award-post-title" tabindex="-1">
                <header class="award-post-head">
                    <h2 id="award-post-title" class="award-post-title" data-award-field="post_title">{{ $postTitle($selected) }}</h2>
                    <p class="award-post-subject" data-award-field="title">{{ $selected['title'] }}</p>
                    <p class="award-post-posted">
                        <span>Posted: <time data-award-field="date" datetime="{{ $selected['date_iso'] }}">{{ $selected['date'] }}</time></span>
                        <span>Reference No. <b class="award-post-mono" data-award-field="reference">{{ $selected['reference'] }}</b></span>
                        <span>Project <b class="award-post-mono" data-award-field="project_reference">{{ $selected['project_reference'] ?: '—' }}</b></span>
                    </p>
                </header>

                <div class="award-post-viewer">
                    <iframe
                        class="award-post-frame"
                        title="Notice of Award: {{ $selected['title'] }}"
                        src="{{ $selected['document_url'] ?? 'about:blank' }}"
                        data-award-frame
                        @unless($selected['document_url']) hidden @endunless
                    ></iframe>
                    <div class="award-post-missing" data-award-missing @if($selected['document_url']) hidden @endif>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="m9.5 12.5 5 5m0-5-5 5"/></svg>
                        <strong>Document not available</strong>
                        <span>The signed Notice of Award for this record has not been published online.</span>
                    </div>
                </div>

                <div class="award-post-facts" aria-label="Award details">
                    <dl>
                        <div><dt>Winning bidder</dt><dd data-award-field="winner">{{ $selected['winner'] }}</dd></div>
                        <div><dt>Contract amount</dt><dd class="award-post-amount" data-award-field="amount">{{ $selected['amount'] }}</dd></div>
                        <div><dt>Status</dt><dd><span class="award-post-status" data-award-field="status">{{ $selected['status'] }}</span></dd></div>
                        <div><dt>Notice to Proceed</dt><dd data-award-ntp>@if($selected['ntp_url'] ?? null)Issued {{ $selected['ntp_issued'] }} &middot; <a href="{{ $selected['ntp_url'] }}" target="_blank" rel="noopener">View PDF</a>@else Not yet issued @endif</dd></div>
                    </dl>
                    <div class="award-post-actions">
                        <a href="{{ $selected['bidder_verify_url'] ?? '#' }}" class="award-post-qr public-qr-preview-trigger" data-public-qr-trigger="award-bidder-qr-modal" data-award-bidder data-award-bidder-link aria-label="QR code of the winning bidder's awarded bids" @unless($selected['bidder_qr_url']) hidden @endunless>
                            <img src="{{ $selected['bidder_qr_url'] ?? '' }}" alt="" data-award-bidder-qr>
                        </a>
                        <a href="{{ $selected['verify_url'] }}" class="btn-outline" data-award-verify>View award details</a>
                        <a href="{{ $selected['document_url'] ?? '#' }}" target="_blank" rel="noopener" class="btn" data-award-open @unless($selected['document_url']) hidden @endunless>Open document</a>
                    </div>
                </div>
            </article>

            @if($records->contains(fn ($record) => $record['bidder_qr_url']))
                <div id="award-bidder-qr-modal" class="public-qr-modal" hidden aria-hidden="true">
                    <div class="public-qr-backdrop" data-public-qr-close></div>
                    <section class="public-qr-dialog" role="dialog" aria-modal="true" aria-label="QR code for the winning bidder's bidding record">
                        <button type="button" class="public-qr-close" data-public-qr-close aria-label="Close QR preview">&times;</button>
                        <div class="public-qr-image-frame">
                            <img src="{{ $selected['bidder_qr_url'] ?? '' }}" alt="QR code for the winning bidder's bidding record" data-award-bidder-qr>
                        </div>
                        <p class="public-qr-caption">Scan to see this bidder's awarded bids</p>
                    </section>
                </div>
            @endif

            <section class="award-post-list" aria-labelledby="award-post-list-title">
                <header class="award-post-list-head">
                    <h2 id="award-post-list-title">Award postings</h2>
                    <p>{{ $records->count() }} award{{ $records->count() === 1 ? '' : 's' }} &middot; &#8369;{{ number_format((float) $totalAwardedValue, 2) }} total contract value</p>
                </header>

                <ul class="award-post-cards">
                    @foreach($postings as $record)
                        @php $isSelected = $record['id'] === $selected['id']; @endphp
                        <li>
                            <a href="{{ $cardUrl($record) }}"
                               class="award-post-card {{ $isSelected ? 'is-selected' : '' }}"
                               data-award-card
                               data-award='@json($record + ['post_title' => $postTitle($record)])'
                               @if($isSelected) aria-current="true" @endif>
                                <span class="award-post-thumb" aria-hidden="true">
                                    <img src="{{ asset('Images/Logo2.png') }}" alt="" loading="lazy">
                                    <span>Bids and Awards Committee<br>San Jose, Occidental Mindoro</span>
                                    @unless($record['document_url'])<em>No document</em>@endunless
                                </span>
                                <span class="award-post-card-body">
                                    <strong>{{ $postTitle($record) }} – {{ $record['title'] }}</strong>
                                    <small>{{ $record['date'] }}</small>
                                    <small class="award-post-mono">{{ $record['reference'] }}</small>
                                </span>
                                <span class="award-post-card-flag">Now viewing</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if($postings->hasPages())
                    <nav class="award-post-pages" aria-label="Award postings pages">
                        @if($postings->onFirstPage())
                            <span class="is-disabled" aria-hidden="true">&larr;</span>
                        @else
                            <a href="{{ $pageUrl($postings->currentPage() - 1) }}" aria-label="Previous page">&larr;</a>
                        @endif
                        @foreach(range(1, $postings->lastPage()) as $page)
                            @if($page === $postings->currentPage())
                                <span class="is-current" aria-current="page">{{ $page }}</span>
                            @elseif($page === 1 || $page === $postings->lastPage() || abs($page - $postings->currentPage()) <= 1)
                                <a href="{{ $pageUrl($page) }}">{{ $page }}</a>
                            @elseif(abs($page - $postings->currentPage()) === 2)
                                <span class="is-gap" aria-hidden="true">&hellip;</span>
                            @endif
                        @endforeach
                        @if($postings->hasMorePages())
                            <a href="{{ $pageUrl($postings->currentPage() + 1) }}" aria-label="Next page">&rarr;</a>
                        @else
                            <span class="is-disabled" aria-hidden="true">&rarr;</span>
                        @endif
                    </nav>
                @endif
            </section>

            {{-- Awards & Contracts → Notice to Proceed: the signed NTP of each posted award (PublicAwardController::index). --}}
            <section class="award-post-list award-ntp-list" id="notice-to-proceed" aria-labelledby="award-ntp-title">
                <header class="award-post-list-head">
                    <h2 id="award-ntp-title">Notice to Proceed</h2>
                    <p>{{ $noticesToProceed->count() }} issued</p>
                </header>
                @if($noticesToProceed->isEmpty())
                    <p class="award-ntp-empty">No Notice to Proceed has been published yet.</p>
                @else
                    <ul class="award-ntp-rows">
                        @foreach($noticesToProceed as $ntp)
                            <li class="award-ntp-row">
                                <div>
                                    <strong>{{ $ntp['title'] }}</strong>
                                    <small>
                                        @if($ntp['project_reference'])<span class="award-post-mono">{{ $ntp['project_reference'] }}</span> &middot; @endif
                                        {{ $ntp['winner'] }} &middot; Issued {{ $ntp['issued_on'] }}
                                    </small>
                                </div>
                                <span class="award-ntp-actions">
                                    <a href="{{ $ntp['view_url'] }}" target="_blank" rel="noopener" class="btn-outline">View PDF</a>
                                    <a href="{{ $ntp['download_url'] }}" class="btn">Download</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </main>

    <style>
        .award-ntp-list { margin-top: 28px; }
        .award-ntp-rows { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
        .award-ntp-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 16px; border: 1px solid #e5dfd2; border-radius: 12px; background: #fff; }
        .award-ntp-row strong { display: block; font-size: 15px; }
        .award-ntp-row small { display: block; margin-top: 3px; color: #6b736e; font-size: 13px; }
        .award-ntp-actions { display: flex; flex: 0 0 auto; gap: 8px; }
        .award-ntp-empty { padding: 14px 16px; border: 1px dashed #d9d2c3; border-radius: 12px; color: #6b736e; }
        @media (max-width: 640px) { .award-ntp-row { flex-direction: column; align-items: flex-start; } }
    </style>

    @vite('resources/js/public-awards.js')
@endsection
