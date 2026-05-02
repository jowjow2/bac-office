@extends('layouts.public')

@section('title', 'Contact BAC Office')
@section('body_class', 'public-page contact-page')

@push('pre_app_styles')
    @vite('resources/css/contact.css')
@endpush

@section('content')
    <main class="public-shell contact-shell">
        <section class="public-page-hero contact-hero" data-contact-reveal="up">
            <div class="contact-hero-copy">
                <p class="public-page-kicker">Contact BAC Office</p>
                <h1>Contact BAC Office</h1>
                <p>
                    For procurement inquiries, bidding concerns, and document assistance, you may contact the BAC Office
                    through the details below.
                </p>
            </div>

            <div class="contact-hero-highlights">
                <div class="contact-hero-chip">
                    <span class="contact-chip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                    </span>
                    Municipal Government of San Jose
                </div>
                <div class="contact-hero-chip">
                    <span class="contact-chip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m4 8 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    Official BAC support channel
                </div>
                <div class="contact-hero-chip">
                    <span class="contact-chip-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-3-6 3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 8h6M9 12h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </span>
                    Citizen guidance for bidders
                </div>
            </div>
        </section>

        <section class="contact-grid" data-contact-reveal="up">
            <div class="contact-stack">
                <div class="contact-info-grid">
                    <article class="contact-info-card">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M12 21s7-4.35 7-11a7 7 0 1 0-14 0c0 6.65 7 11 7 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                        </div>
                        <div>
                            <h2>Office Address</h2>
                            <p>BAC Office, Municipal Government of San Jose, Occidental Mindoro</p>
                        </div>
                    </article>

                    <article class="contact-info-card">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m4 8 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <h2>Official Email</h2>
                            <p><a href="mailto:bacoffice@sanjose.gov.ph">bacoffice@sanjose.gov.ph</a></p>
                        </div>
                    </article>

                    <article class="contact-info-card">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M6.6 10.8a15.9 15.9 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.36c1 .34 2.05.52 3.15.52A1.5 1.5 0 0 1 21.5 16.9V20A1.5 1.5 0 0 1 20 21.5C10.34 21.5 2.5 13.66 2.5 4A1.5 1.5 0 0 1 4 2.5h3.1A1.5 1.5 0 0 1 8.6 4c0 1.1.18 2.15.52 3.15a1.5 1.5 0 0 1-.36 1.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <h2>Contact Number</h2>
                            <p><a href="tel:+63430000000">(043) 000-0000</a></p>
                        </div>
                    </article>

                    <article class="contact-info-card">
                        <div class="contact-info-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <h2>Office Hours</h2>
                            <p>Monday to Friday, 8:00 AM - 5:00 PM</p>
                        </div>
                    </article>
                </div>

                <article class="contact-map-card">
                    <div class="contact-card-heading">
                        <div>
                            <p class="contact-card-kicker">Location Map</p>
                            <h2>Visit the BAC Office</h2>
                        </div>
                        <p>Find the BAC Office inside the Municipal Government of San Jose for in-person procurement assistance.</p>
                    </div>

                    <div class="contact-map-frame">
                        <iframe
                            title="BAC Office location map"
                            src="https://www.google.com/maps?q=Municipal%20Government%20of%20San%20Jose%20Occidental%20Mindoro&output=embed"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                        ></iframe>
                    </div>
                </article>
            </div>

            <article class="contact-form-card">
                <div class="contact-card-heading">
                    <div>
                        <p class="contact-card-kicker">Inquiry Form</p>
                        <h2>Send a Message</h2>
                    </div>
                    <p>Complete the form below and we will prepare your inquiry in your default email app.</p>
                </div>

                <form class="contact-form" data-contact-form>
                    <label class="contact-field">
                        <span>Full Name</span>
                        <input type="text" name="full_name" placeholder="Enter your full name" required>
                    </label>

                    <label class="contact-field">
                        <span>Email Address</span>
                        <input type="email" name="email" placeholder="Enter your email address" required>
                    </label>

                    <label class="contact-field">
                        <span>Subject</span>
                        <input type="text" name="subject" placeholder="Procurement inquiry subject" required>
                    </label>

                    <label class="contact-field">
                        <span>Message</span>
                        <textarea name="message" rows="6" placeholder="Write your inquiry, bidding concern, or request for document assistance." required></textarea>
                    </label>

                    <button type="submit" class="btn contact-submit-btn">Send Message</button>

                    <p class="contact-form-note">
                        Sending this form opens your default email app addressed to <strong>bacoffice@sanjose.gov.ph</strong>.
                    </p>
                </form>
            </article>
        </section>

        <section class="charter-section" data-contact-reveal="up">
            <div class="charter-header">
                <div>
                    <p class="public-page-kicker">Citizen's Charter / Help Guide</p>
                    <h2>Citizen's Charter / Help Guide</h2>
                    <p>
                        This guide helps bidders, suppliers, and citizens understand the basic steps in joining procurement
                        activities of the BAC Office.
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
                            <p>Wait for BAC Office verification.</p>
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
            const contactForm = document.querySelector('[data-contact-form]');
            const contactRevealItems = document.querySelectorAll('[data-contact-reveal]');

            if (contactForm) {
                contactForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const formData = new FormData(contactForm);
                    const fullName = String(formData.get('full_name') || '').trim();
                    const email = String(formData.get('email') || '').trim();
                    const subject = String(formData.get('subject') || '').trim();
                    const message = String(formData.get('message') || '').trim();

                    const body = [
                        `Full Name: ${fullName}`,
                        `Email Address: ${email}`,
                        '',
                        'Message:',
                        message,
                    ].join('\n');

                    window.location.href = `mailto:bacoffice@sanjose.gov.ph?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
                });
            }

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
