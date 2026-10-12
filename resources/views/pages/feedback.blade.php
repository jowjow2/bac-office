@extends('layouts.public')

@section('title', 'Send feedback · SJBAC')
@section('description', 'Tell the Bids and Awards Committee what works and what does not on the SJBAC Procurement Portal.')
@section('body_class', 'public-page')

@section('content')
    @php $kinds = \App\Models\Feedback::CATEGORIES; @endphp
    <main class="public-shell feedback-page">
        <section class="public-page-hero">
            <p class="public-page-kicker">Feedback</p>
            <h1>How is the portal working for you?</h1>
            <p>Tell the BAC what to improve, what is not working, or what you liked. It takes about a minute.</p>
        </section>

        <section class="public-detail-card fb-card">
            @if(session('feedback_sent'))
                <div class="fb-thanks" role="status">
                    <svg viewBox="0 0 24 24" width="30" height="30" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1f7a4d"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div>
                        <strong>Thank you for your feedback.</strong>
                        <p>The BAC reads every message. If you left an email, we may reply to it.</p>
                        <a class="fb-link" href="{{ route('home') }}">Back to the home page</a>
                    </div>
                </div>
            @elseif(! $available)
                <p class="fb-unavailable">Feedback is not available right now. Please try again later.</p>
            @else
                <form method="POST" action="{{ route('feedback.store') }}" class="fb-form" novalidate>
                    @csrf
                    <input type="hidden" name="page_url" value="{{ old('page_url', parse_url((string) url()->previous(), PHP_URL_PATH)) }}">
                    {{-- Only bots fill this in. --}}
                    <div class="fb-trap" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <fieldset class="fb-field">
                        <legend>How would you rate the portal? <span class="fb-req" aria-hidden="true">*</span></legend>
                        <div class="fb-stars" role="radiogroup" aria-label="Rating">
                            @for($star = 5; $star >= 1; $star--)
                                <input type="radio" name="rating" id="fb-star-{{ $star }}" value="{{ $star }}" @checked((int) old('rating') === $star)>
                                <label for="fb-star-{{ $star }}" title="{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}"><svg viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path fill="currentColor" d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3 6.1 20.6l1.3-6.6L2.5 9.4l6.6-.8z"/></svg><span class="fb-sr">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</span></label>
                            @endfor
                        </div>
                        @error('rating')<span class="fb-error">{{ $message }}</span>@enderror
                    </fieldset>

                    <fieldset class="fb-field">
                        <legend>What kind of feedback is it? <span class="fb-req" aria-hidden="true">*</span></legend>
                        <div class="fb-kinds">
                            @foreach($kinds as $key => $kind)
                                <label class="fb-kind">
                                    <input type="radio" name="category" value="{{ $key }}" @checked(old('category') === $key)>
                                    <span>{{ $kind['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('category')<span class="fb-error">{{ $message }}</span>@enderror
                    </fieldset>

                    <div class="fb-field">
                        <label for="fb-message">Your message <span class="fb-req" aria-hidden="true">*</span></label>
                        <textarea id="fb-message" name="message" rows="5" maxlength="2000" placeholder="What happened, or what would you like to see?" required>{{ old('message') }}</textarea>
                        @error('message')<span class="fb-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="fb-row">
                        <div class="fb-field">
                            <label for="fb-name">Name <span class="fb-opt">(optional)</span></label>
                            <input id="fb-name" type="text" name="name" maxlength="120" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name">
                        </div>
                        <div class="fb-field">
                            <label for="fb-email">Email <span class="fb-opt">(optional, if you want a reply)</span></label>
                            <input id="fb-email" type="email" name="email" maxlength="190" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email">
                            @error('email')<span class="fb-error">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <p class="fb-note">We do not keep your IP address. Your name and email are used only to reply. See the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

                    <button type="submit" class="fb-submit">Send feedback</button>
                </form>
            @endif
        </section>
    </main>

    <style>
        .feedback-page .fb-card { max-width: 720px; }
        .fb-form { display: grid; gap: 20px; }
        .fb-field { display: grid; gap: 7px; margin: 0; padding: 0; border: 0; min-width: 0; }
        .fb-field > label, .fb-field > legend { padding: 0; color: var(--ui-ink); font-size: 14px; font-weight: 600; }
        .fb-req { color: var(--ui-danger, #b42318); }
        .fb-opt { color: var(--ui-muted); font-weight: 400; font-size: 12.5px; }
        .fb-field input[type="text"], .fb-field input[type="email"], .fb-field textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--ui-line-strong, #d2cbbb); border-radius: 10px; background: #fff; font: inherit; font-size: 14px; color: var(--ui-ink); }
        .fb-field textarea { resize: vertical; min-height: 110px; }
        .fb-field input:focus-visible, .fb-field textarea:focus-visible { outline: 2px solid rgba(29, 79, 64, .4); outline-offset: 1px; }
        .fb-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .fb-error { color: var(--ui-danger, #b42318); font-size: 12.5px; }
        .fb-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        .fb-stars svg { display: block; width: 1em; height: 1em; }
        .fb-stars { display: inline-flex; flex-direction: row-reverse; justify-content: flex-end; gap: 4px; }
        .fb-stars input { position: absolute; opacity: 0; pointer-events: none; }
        .fb-stars label { color: #cfd6d2; font-size: 30px; line-height: 1; cursor: pointer; transition: color .12s ease, transform .12s ease; }
        .fb-stars label:hover, .fb-stars label:hover ~ label, .fb-stars input:checked ~ label { color: #e0a82e; }
        .fb-stars label:hover { transform: scale(1.1); }
        .fb-stars input:focus-visible + label { outline: 2px solid rgba(29, 79, 64, .5); outline-offset: 3px; border-radius: 4px; }
        .fb-kinds { display: flex; flex-wrap: wrap; gap: 10px; }
        .fb-kind { position: relative; cursor: pointer; }
        .fb-kind input { position: absolute; opacity: 0; pointer-events: none; }
        .fb-kind span { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border: 1px solid var(--ui-line-strong, #d2cbbb); border-radius: 999px; background: #fff; color: var(--ui-ink-2, #34413b); font-size: 14px; font-weight: 600; }
        .fb-kind input:checked + span { border-color: var(--ui-primary, #1d4f40); background: var(--ui-primary-soft, #e4f0ea); color: var(--ui-primary, #1d4f40); }
        .fb-kind input:focus-visible + span { outline: 2px solid rgba(29, 79, 64, .5); outline-offset: 2px; }
        .fb-note { margin: 0; color: var(--ui-muted); font-size: 12.5px; line-height: 1.55; }
        .fb-note a { color: var(--ui-primary, #1d4f40); }
        .fb-submit { display: inline-flex; align-items: center; justify-content: center; gap: 9px; justify-self: start; min-height: 44px; padding: 0 24px; border: 0; border-radius: 10px; background: var(--ui-primary, #1d4f40); color: #fff; font: inherit; font-size: 15px; font-weight: 700; cursor: pointer; }
        .fb-submit:hover { filter: brightness(1.08); }
        .fb-trap { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .fb-thanks { display: flex; gap: 14px; align-items: flex-start; }
        .fb-thanks > svg { flex: none; }
        .fb-thanks strong { font-size: 17px; }
        .fb-thanks p { margin: 4px 0 10px; color: var(--ui-ink-2); line-height: 1.55; }
        .fb-link { color: var(--ui-primary, #1d4f40); font-weight: 600; }
        .fb-unavailable { margin: 0; color: var(--ui-muted); }
        @media (max-width: 640px) { .fb-row { grid-template-columns: 1fr; } .fb-stars label { font-size: 34px; } }
    </style>
@endsection
