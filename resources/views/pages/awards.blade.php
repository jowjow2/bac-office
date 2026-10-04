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
        $docs = $selected['documents'] ?? [];
        $docIndex = max(0, (int) collect($docs)->search(fn ($doc) => $doc['key'] === request()->query('doc')));
        $doc = $docs[$docIndex] ?? null;
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
                    <h2 id="award-post-title" class="award-post-title" data-award-field="post_title">{{ $doc['title'] ?? $postTitle($selected) }}</h2>
                    <p class="award-post-subject" data-award-field="title">{{ $selected['title'] }}</p>
                    <p class="award-post-posted">
                        <span>Posted: <time data-award-field="date" datetime="{{ $selected['date_iso'] }}">{{ $selected['date'] }}</time></span>
                        <span>Reference No. <b class="award-post-mono" data-award-field="reference">{{ $selected['reference'] }}</b></span>
                        <span>Project <b class="award-post-mono" data-award-field="project_reference">{{ $selected['project_reference'] ?: '—' }}</b></span>
                    </p>
                </header>

                {{-- The award's documents, paged with the arrows or the tabs (public-awards.js). --}}
                <div class="award-post-viewer" data-award-viewer data-doc-index="{{ $docIndex }}">
                    <div class="award-doc-bar" data-award-doc-bar @if(count($docs) < 2) hidden @endif>
                        <div class="award-doc-tabs" role="tablist" aria-label="Award documents" data-award-doc-tabs>
                            @foreach($docs as $i => $item)
                                <button type="button" role="tab" class="award-doc-tab" data-award-doc="{{ $i }}" aria-selected="{{ $i === $docIndex ? 'true' : 'false' }}">{{ $item['label'] }}</button>
                            @endforeach
                        </div>
                        <span class="award-doc-count" data-award-doc-count>{{ $docIndex + 1 }} of {{ count($docs) }}</span>
                    </div>
                    <div class="award-doc-stage">
                        <button type="button" class="award-doc-nav is-prev" data-award-doc-prev aria-label="Previous document" @if(count($docs) < 2) hidden @endif @disabled($docIndex === 0)>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <iframe
                            class="award-post-frame"
                            title="{{ $doc['label'] ?? 'Notice of Award' }}: {{ $selected['title'] }}"
                            src="{{ $doc['url'] ?? 'about:blank' }}"
                            data-award-frame
                            @unless($doc['url'] ?? null) hidden @endunless
                        ></iframe>
                        <div class="award-post-missing" data-award-missing @if($doc['url'] ?? null) hidden @endif>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="m9.5 12.5 5 5m0-5-5 5"/></svg>
                            <strong>Document not available</strong>
                            <span data-award-missing-text>{{ $doc['missing'] ?? 'This document has not been published online.' }}</span>
                        </div>
                        <button type="button" class="award-doc-nav is-next" data-award-doc-next aria-label="Next document" @if(count($docs) < 2) hidden @endif @disabled($docIndex >= count($docs) - 1)>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="award-post-facts" aria-label="Award details">
                    <dl>
                        <div><dt>Winning bidder</dt><dd data-award-field="winner">{{ $selected['winner'] }}</dd></div>
                        <div><dt>Contract amount</dt><dd class="award-post-amount" data-award-field="amount">{{ $selected['amount'] }}</dd></div>
                        <div><dt>Status</dt><dd><span class="award-post-status" data-award-field="status">{{ $selected['status'] }}</span></dd></div>
                        <div><dt>Notice to Proceed</dt><dd data-award-ntp>@if($selected['ntp_url'] ?? null)Issued {{ $selected['ntp_issued'] }} &middot; <button type="button" class="award-doc-link" data-award-doc-goto="ntp">View</button>@else Not yet issued @endif</dd></div>
                    </dl>
                    <div class="award-post-actions">
                        <a href="{{ $selected['bidder_verify_url'] ?? '#' }}" class="award-post-qr public-qr-preview-trigger" data-public-qr-trigger="award-bidder-qr-modal" data-award-bidder data-award-bidder-link aria-label="QR code of the winning bidder's awarded bids" @unless($selected['bidder_qr_url']) hidden @endunless>
                            <img src="{{ $selected['bidder_qr_url'] ?? '' }}" alt="" data-award-bidder-qr>
                        </a>
                        <a href="{{ $selected['verify_url'] }}" class="btn-outline" data-award-verify>View award details</a>
                        <a href="{{ $doc['url'] ?? '#' }}" target="_blank" rel="noopener" class="btn" data-award-open @unless($doc['url'] ?? null) hidden @endunless>Open document</a>
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
                                    @if(count($record['documents'] ?? []) > 1)<small class="award-post-card-docs">Notice of Award &middot; Notice to Proceed</small>@endif
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

        @endif
    </main>

    <style>
        .award-doc-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; margin: 0 0 12px; }
        .award-doc-bar[hidden] { display: none; }
        .award-doc-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 999px; background: rgba(255, 255, 255, .14); }
        .award-doc-tab { min-height: 34px; padding: 0 16px; border: 0; border-radius: 999px; background: transparent; color: #fff; font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; }
        .award-doc-tab[aria-selected="true"] { background: #fff; color: var(--ui-ink, #1b2420); }
        .award-doc-tab:focus-visible, .award-doc-nav:focus-visible { outline: 3px solid #9bc9b7; outline-offset: 2px; }
        .award-doc-count { color: rgba(255, 255, 255, .85); font-size: 13px; font-variant-numeric: tabular-nums; }
        .award-doc-stage { position: relative; display: flex; width: 100%; }
        .award-doc-nav { position: absolute; top: 50%; z-index: 2; display: grid; width: 46px; height: 46px; place-items: center; padding: 0; border: 0; border-radius: 50%; background: #fff; color: var(--ui-ink, #1b2420); box-shadow: 0 6px 18px rgba(27, 36, 32, .3); cursor: pointer; transform: translateY(-50%); transition: transform .15s ease, opacity .15s ease; }
        .award-doc-nav svg { width: 22px; height: 22px; }
        .award-doc-nav.is-prev { left: 4px; }
        .award-doc-nav.is-next { right: 4px; }
        .award-doc-nav:hover:not(:disabled) { transform: translateY(-50%) scale(1.06); }
        .award-doc-nav:disabled { opacity: .35; cursor: default; box-shadow: none; }
        .award-doc-nav[hidden] { display: none; }
        .award-post-viewer { flex-direction: column; }
        .award-doc-link { padding: 0; border: 0; background: none; color: var(--ui-primary, #1d4f40); font: inherit; font-weight: 700; text-decoration: underline; cursor: pointer; }
        .award-post-card-docs { color: var(--ui-primary, #1d4f40) !important; font-weight: 600; }
        @media (max-width: 640px) {
            .award-doc-bar { flex-wrap: wrap; }
            .award-doc-tabs { width: 100%; }
            .award-doc-tab { flex: 1; padding: 0 8px; font-size: 12.5px; }
            .award-doc-nav { width: 38px; height: 38px; }
        }
    </style>

    @vite('resources/js/public-awards.js')
@endsection
