@extends('layouts.public')

@section('title', 'Contact SJBAC')
@section('body_class', 'public-page contact-page')

@push('pre_app_styles')
    @vite('resources/css/contact.css')
@endpush

@section('content')
    <main class="public-shell contact-shell">
        <header class="ct-head" data-contact-reveal="up">
            <p class="public-page-kicker">Contact</p>
            <h1>Contact the BAC Secretariat</h1>
            <p>Questions about a bidding, your registration or your documents? Send a message, or visit the office during office hours.</p>
            <div class="ct-quick">
                <a href="{{ route('public.procurement') }}">Browse procurement</a>
                <a href="#charter">Help guide for bidders</a>
                <a href="{{ route('feedback') }}">Send feedback about the portal</a>
            </div>
        </header>

        <section class="ct-layout" data-contact-reveal="up">
            <article class="ct-card ct-form-card" id="message">
                @if(session('contact_sent'))
                    <div class="ct-thanks" role="status">
                        <svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#1f7a4d"/><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <div>
                            <h2>Message sent</h2>
                            <p>The BAC has received your inquiry and will reply to the email you gave. Replies usually come within the next working days.</p>
                            <a class="ct-link" href="{{ route('contact') }}">Send another message</a>
                        </div>
                    </div>
                @else
                    <h2>Send a message</h2>
                    <p class="ct-lead">It goes straight to the BAC Admin. We reply to the email you give here.</p>

                    <form method="POST" action="{{ route('contact.send') }}" class="ct-form" novalidate>
                        @csrf
                        {{-- Only bots fill this in. --}}
                        <div class="ct-trap" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                        <div class="ct-row">
                            <label class="ct-field">
                                <span>Your name</span>
                                <input type="text" name="full_name" value="{{ old('full_name', auth()->user()?->name) }}" maxlength="120" autocomplete="name" required>
                                @error('full_name')<em class="ct-error">{{ $message }}</em>@enderror
                            </label>
                            <label class="ct-field">
                                <span>Email</span>
                                <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="190" autocomplete="email" required>
                                @error('email')<em class="ct-error">{{ $message }}</em>@enderror
                            </label>
                        </div>

                        <label class="ct-field">
                            <span>What is it about?</span>
                            <select name="topic" required>
                                <option value="">Choose one</option>
                                @foreach($topics as $key => $label)
                                    <option value="{{ $key }}" @selected(old('topic') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('topic')<em class="ct-error">{{ $message }}</em>@enderror
                        </label>

                        <label class="ct-field">
                            <span>Subject</span>
                            <input type="text" name="subject" value="{{ old('subject') }}" maxlength="150" placeholder="e.g. Requirements for the road concreting project" required>
                            @error('subject')<em class="ct-error">{{ $message }}</em>@enderror
                        </label>

                        <label class="ct-field">
                            <span>Message</span>
                            <textarea name="message" rows="6" maxlength="3000" placeholder="Tell us what you need. Please do not include passwords or your financial PIN." required>{{ old('message') }}</textarea>
                            @error('message')<em class="ct-error">{{ $message }}</em>@enderror
                        </label>

                        <button type="submit" class="ct-submit">Send message</button>
                        <p class="ct-note">We use your name and email only to reply. See the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
                    </form>
                @endif
            </article>

            <aside class="ct-side">
                <article class="ct-card ct-info">
                    <h2>{{ $office['person'] ? 'Reach us' : 'BAC Secretariat' }}</h2>
                    <dl>
                        <div>
                            <dt><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>Address</dt>
                            <dd>{{ $office['address'] ?: 'Municipal Hall, San Jose, Occidental Mindoro' }}</dd>
                        </div>
                        <div>
                            <dt><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m4 8 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>Email</dt>
                            <dd>@if($office['email'])<a href="mailto:{{ $office['email'] }}">{{ $office['email'] }}</a>@else<span class="ct-muted">Use the form</span>@endif</dd>
                        </div>
                        <div>
                            <dt><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.9 15.9 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.36c1 .34 2.05.52 3.15.52A1.5 1.5 0 0 1 21.5 16.9V20A1.5 1.5 0 0 1 20 21.5C10.34 21.5 2.5 13.66 2.5 4A1.5 1.5 0 0 1 4 2.5h3.1a1.5 1.5 0 0 1 1.5 1.5c0 1.1.18 2.15.52 3.15a1.5 1.5 0 0 1-.36 1.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>Phone</dt>
                            <dd>@if($office['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $office['phone']) }}">{{ $office['phone'] }}</a>@else<span class="ct-muted">Not listed yet. Please use the form.</span>@endif</dd>
                        </div>
                        <div>
                            <dt><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>Office hours</dt>
                            <dd>{{ $office['hours'] ?: 'Monday to Friday, 8:00 AM to 5:00 PM' }}</dd>
                        </div>
                        @if($office['person'])
                            <div>
                                <dt><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>Contact person</dt>
                                <dd>{{ $office['person'] }}@if($office['person_position']) <span class="ct-muted">· {{ $office['person_position'] }}</span>@endif</dd>
                            </div>
                        @endif
                    </dl>
                </article>

                <article class="ct-card ct-map">
                    <div class="contact-map-frame">
                        <iframe
                            title="SJBAC location map"
                            src="https://www.google.com/maps?q=Municipal%20Government%20of%20San%20Jose%20Occidental%20Mindoro&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        ></iframe>
                    </div>
                </article>
            </aside>
        </section>
        <section class="charter-section" id="charter" data-contact-reveal="up">
            <div class="charter-header">
                <div>
                    <p class="public-page-kicker">Citizen's Charter / Help Guide</p>
                    <h2>Citizen's Charter / Help Guide</h2>
                    <p>
                        This guide helps bidders, suppliers, and citizens understand the basic steps in joining procurement
                        activities of the SJBAC.
                    </p>
                </div>

                <div class="charter-actions">
                    <a href="{{ route('public.procurement') }}" class="btn">View Procurement Opportunities</a>
                    <button type="button" class="btn btn-outline" onclick="openLogin(); switchTab('register');">Register as Bidder</button>
                </div>
            </div>

            <div class="charter-layout">
                <div class="charter-accordion">
                    <details class="charter-item" open>
                        <summary>
                            <span class="charter-step">01</span>
                            <span>How to Join Bidding</span>
                        </summary>
                        <div class="charter-item-body">
                            <p>Browse available procurement opportunities.</p>
                            <p>Check the project details, budget, deadline, and requirements.</p>
                            <p>Prepare the necessary documents before submission.</p>
                        </div>
                    </details>

                    <details class="charter-item">
                        <summary>
                            <span class="charter-step">02</span>
                            <span>How to Register as Bidder</span>
                        </summary>
                        <div class="charter-item-body">
                            <p>Click the Register button.</p>
                            <p>Fill out the bidder registration form.</p>
                            <p>Upload or submit required business documents.</p>
                            <p>Wait for SJBAC verification.</p>
                        </div>
                    </details>

                    <details class="charter-item">
                        <summary>
                            <span class="charter-step">03</span>
                            <span>Required Documents</span>
                        </summary>
                        <div class="charter-item-body">
                            <ul class="charter-checklist">
                                <li>Business Permit</li>
                                <li>PhilGEPS Registration</li>
                                <li>DTI / SEC / CDA Registration</li>
                                <li>Tax Clearance</li>
                                <li>Mayor's Permit</li>
                                <li>Other documents required in the bid notice</li>
                            </ul>
                        </div>
                    </details>

                    <details class="charter-item">
                        <summary>
                            <span class="charter-step">04</span>
                            <span>How to Download Bid Documents</span>
                        </summary>
                        <div class="charter-item-body">
                            <p>Go to the Procurement section.</p>
                            <p>Select the project you want to join.</p>
                            <p>Click Download Bid Documents.</p>
                            <p>Follow payment or verification instructions if required.</p>
                        </div>
                    </details>

                    <details class="charter-item">
                        <summary>
                            <span class="charter-step">05</span>
                            <span>How to Check Award Results</span>
                        </summary>
                        <div class="charter-item-body">
                            <p>Go to the Awards and Contracts section.</p>
                            <p>Search for the project title.</p>
                            <p>View the Notice of Award, Notice to Proceed, Abstract of Bids, and contract details.</p>
                        </div>
                    </details>
                </div>

                <aside class="charter-side-card">
                    <p class="contact-card-kicker">Quick Help</p>
                    <h3>Need a simple starting point?</h3>
                    <p>
                        Start by checking open procurement opportunities, then prepare your bidder documents before
                        registration or bid submission.
                    </p>

                    <div class="charter-side-points">
                        <div>
                            <strong>Step 1</strong>
                            <span>Review project requirements and timelines.</span>
                        </div>
                        <div>
                            <strong>Step 2</strong>
                            <span>Complete bidder registration and verification.</span>
                        </div>
                        <div>
                            <strong>Step 3</strong>
                            <span>Track awards and contract notices online.</span>
                        </div>
                    </div>

                    <a href="{{ route('public.awards') }}" class="btn btn-outline charter-side-btn">Check Award Results</a>
                </aside>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const contactRevealItems = document.querySelectorAll('[data-contact-reveal]');

            if (contactRevealItems.length > 0) {
                if ('IntersectionObserver' in window) {
                    const revealObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;

                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        });
                    }, {
                        threshold: 0.14,
                        rootMargin: '0px 0px -36px 0px',
                    });

                    contactRevealItems.forEach((item) => revealObserver.observe(item));
                } else {
                    contactRevealItems.forEach((item) => item.classList.add('is-visible'));
                }
            }
        });
    </script>
@endsection
