@extends('layouts.public')

@section('title', 'About the BAC')
@section('body_class', 'public-page')

@push('pre_app_styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@section('content')
@php
    /** Office details and BAC composition: admin → About page (App\Support\BacProfile). */
    $profile = \App\Support\BacProfile::get();
    $office = $profile['office'];
    $entity = config('bac-office.procuring_entity', 'Municipality of San Jose, Occidental Mindoro');
    $svpCeiling = \App\Support\ProcurementMode::lguSvpCeiling();
    $groups = collect(\App\Support\BacProfile::GROUPS)
        ->map(fn ($label, $key) => ['label' => $label, 'people' => \App\Support\BacProfile::group($profile, $key)])
        ->filter(fn ($group) => $group['people'] !== []);
    $registerUrl = route('login.page', ['auth_tab' => 'register']);
    $steps = [
        ['Register as a bidder', 'Create your account and upload your eligibility documents (DTI/SEC/CDA registration, business permit, PhilGEPS registration and the others listed).', $registerUrl, 'Register'],
        ['Wait for the BAC\'s approval', 'The BAC Secretariat checks your documents. You are notified when your account is approved or if a document needs correcting.', null, null],
        ['Choose a project and get the bidding documents', 'Open a project under Procurement. Download its bidding documents and pay the bidding documents fee, if any, at the venue in the notice.', route('public.procurement'), 'Browse opportunities'],
        ['Submit your bid on time', 'Submit online through the portal, or as sealed envelopes at the BAC Secretariat, as the project notice says. Late bids are not accepted.', null, null],
        ['Bid opening and evaluation', 'Bids are opened at the scheduled time; you may attend. Follow each step of the evaluation in your account.', null, null],
        ['Award, contract and Notice to Proceed', 'The winning bidder receives the Notice of Award, signs the contract and receives the Notice to Proceed. Awards are posted publicly.', route('public.awards'), 'Awards & Contracts'],
    ];
    $resources = [
        ['RA 12009', 'New Government Procurement Act and its IRR', 'Department of Budget and Management', 'https://www.dbm.gov.ph/index.php/republic-act-ra-no-12009-new-government-procurement-act-ngpa'],
        ['IRR', 'Implementing Rules and Regulations of RA 12009', 'Government Procurement Policy Board', 'https://www.gppb.gov.ph/ngpa-implementing-rules-and-regulations/'],
        ['Transition', 'Effectivity and transitory periods under RA 12009', 'Government Procurement Policy Board', 'https://www.gppb.gov.ph/important-update-effectivity-and-transistory-periods-under-ra-12009/'],
        ['RA 9184', 'Government Procurement Reform Act and its 2016 Revised IRR — for procurement still covered by the transitory provisions', 'Government Procurement Policy Board', 'https://www.gppb.gov.ph/ra-9184-and-2016-revised-irr/'],
        ['PhilGEPS', 'Philippine Government Electronic Procurement System', 'Procurement Service – PhilGEPS', 'https://www.philgeps.gov.ph/'],
        ['GPPB', 'Policies, circulars and resolutions on public procurement', 'Government Procurement Policy Board', 'https://www.gppb.gov.ph/'],
    ];
    $faqs = [
        ['Do I need to be registered in PhilGEPS?', 'Yes. Competitive bidding asks for the PhilGEPS Certificate of Registration (Platinum Membership); alternative modes such as Small Value Procurement ask for at least your PhilGEPS registration number. Each project\'s notice lists exactly what it needs.'],
        ['How much is the bidding documents fee?', 'It depends on the project\'s Approved Budget for the Contract and is shown on each project. Pay it at the venue in the notice and keep the Official Receipt. Small Value Procurement and other alternative modes are usually free.'],
        ['Can I submit my bid online?', 'Each project\'s notice says how bids are submitted: online through this portal, or as sealed envelopes at the BAC Secretariat. For manual projects, nothing is filed online.'],
        ['What happens if I miss the deadline?', 'Bids received after the submission deadline are not accepted, whether online or on paper.'],
        ['How do I follow my bid?', 'Sign in and open My bids or Track evaluation. You are notified at each step, from the bid opening to the award.'],
    ];
@endphp

<main class="public-shell ab">
    {{-- Who the BAC is --}}
    <section class="ab-hero">
        <div class="ab-hero__copy">
            <p class="ab-kicker">{{ $entity }}</p>
            <h1>Bids and Awards Committee</h1>
            <p class="ab-lead">The BAC conducts the procurement of goods, infrastructure projects and consulting services for the Municipality, from posting the opportunity to recommending the award, under Republic Act No. 12009, the New Government Procurement Act, and its Implementing Rules and Regulations.</p>
            <div class="ab-actions">
                <a href="{{ route('public.procurement') }}" class="btn">Browse opportunities</a>
                <a href="#how-to-bid" class="btn-outline">How to bid</a>
            </div>
            <dl class="ab-facts">
                @if($office['address'])<div><dt><i class="fas fa-location-dot" aria-hidden="true"></i> Office</dt><dd>{{ $office['address'] }}</dd></div>@endif
                @if($office['hours'])<div><dt><i class="fas fa-clock" aria-hidden="true"></i> Office hours</dt><dd>{{ $office['hours'] }}</dd></div>@endif
                @if($office['email'])<div><dt><i class="fas fa-envelope" aria-hidden="true"></i> Email</dt><dd><a href="mailto:{{ $office['email'] }}">{{ $office['email'] }}</a></dd></div>@endif
                @if($office['phone'])<div><dt><i class="fas fa-phone" aria-hidden="true"></i> Phone</dt><dd>{{ $office['phone'] }}</dd></div>@endif
            </dl>
        </div>
        <figure class="ab-hero__photo">
            <img src="{{ asset('Images/bac-office-front-clean.webp') }}" alt="The BAC office, Municipal Compound, San Jose" loading="lazy">
            <figcaption>BAC Office · Municipal Compound, San Jose</figcaption>
        </figure>
    </section>

    {{-- How to participate --}}
    <section class="ab-section" id="how-to-bid" aria-labelledby="ab-how-title">
        <header class="ab-head">
            <p class="ab-kicker">For bidders and suppliers</p>
            <h2 id="ab-how-title">How to take part in a bidding</h2>
        </header>
        <ol class="ab-steps">
            @foreach($steps as $i => [$title, $text, $url, $link])
                <li class="ab-step">
                    <span class="ab-step__num" aria-hidden="true">{{ $i + 1 }}</span>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                        @if($url)<a href="{{ $url }}" class="ab-link">{{ $link }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>@endif
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="ab-modes">
            <article>
                <h3><i class="fas fa-gavel" aria-hidden="true"></i> Competitive bidding</h3>
                <p>The general rule. Open to all eligible bidders: a posted Invitation to Bid, a public bid opening, evaluation and post-qualification of the lowest calculated bid.</p>
            </article>
            <article>
                <h3><i class="fas fa-file-invoice" aria-hidden="true"></i> Small Value Procurement</h3>
                <p>For an Approved Budget of up to <strong>&#8369;{{ number_format($svpCeiling) }}</strong> for this municipality. The BAC requests quotations from at least three suppliers; quotations are opened together after the deadline.</p>
            </article>
            <article>
                <h3><i class="fas fa-scale-balanced" aria-hidden="true"></i> Other alternative modes</h3>
                <p>Negotiated procurement, direct contracting and the other alternative modes are used only when their legal grounds under RA 12009 apply.</p>
            </article>
        </div>
    </section>

    {{-- What the BAC does --}}
    <section class="ab-section" aria-labelledby="ab-does-title">
        <header class="ab-head">
            <p class="ab-kicker">Mandate</p>
            <h2 id="ab-does-title">What the BAC does</h2>
        </header>
        <div class="ab-duties">
            @foreach([
                ['fa-bullhorn', 'Posts opportunities', 'Publishes Invitations to Bid and Requests for Quotation, bidding documents, schedules and bid bulletins.'],
                ['fa-envelope-open-text', 'Conducts the bidding', 'Holds pre-bid conferences, receives and opens bids, and keeps the proceedings on record.'],
                ['fa-magnifying-glass-chart', 'Evaluates and post-qualifies', 'Checks eligibility, evaluates the bids and post-qualifies the bidder with the lowest calculated bid.'],
                ['fa-award', 'Recommends the award', 'Recommends the award to the Head of the Procuring Entity and posts the result publicly.'],
            ] as [$icon, $title, $text])
                <article class="ab-duty">
                    <span aria-hidden="true"><i class="fas {{ $icon }}"></i></span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </article>
            @endforeach
        </div>
        <ul class="ab-values" aria-label="Principles">
            @foreach(['Transparency', 'Competitiveness', 'Accountability', 'Efficiency', 'Value for money', 'Public monitoring'] as $value)
                <li>{{ $value }}</li>
            @endforeach
        </ul>
    </section>

    {{-- Who sits on the BAC (only when the admin has listed them) --}}
    @if($groups->isNotEmpty())
        <section class="ab-section" aria-labelledby="ab-people-title">
            <header class="ab-head">
                <p class="ab-kicker">Composition</p>
                <h2 id="ab-people-title">The Bids and Awards Committee</h2>
            </header>
            <div class="ab-people">
                @foreach($groups as $group)
                    <article class="ab-group">
                        <h3>{{ $group['label'] }}</h3>
                        <ul>
                            @foreach($group['people'] as $person)
                                <li><span class="ab-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of($person['name'])->explode(' ')->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span><span><strong>{{ $person['name'] }}</strong>@if($person['position'])<small>{{ $person['position'] }}</small>@endif</span></li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Laws and official resources --}}
    <section class="ab-section" aria-labelledby="ab-laws-title">
        <header class="ab-head">
            <p class="ab-kicker">Legal basis</p>
            <h2 id="ab-laws-title">Laws and official resources</h2>
        </header>
        <ul class="ab-resources">
            @foreach($resources as [$tag, $title, $source, $url])
                <li>
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="ab-resource">
                        <span class="ab-resource__tag">{{ $tag }}</span>
                        <span class="ab-resource__text"><strong>{{ $title }}</strong><small>{{ $source }} · {{ parse_url($url, PHP_URL_HOST) }}</small></span>
                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- FAQ and contact --}}
    <section class="ab-section ab-split" aria-labelledby="ab-faq-title">
        <div>
            <header class="ab-head">
                <p class="ab-kicker">Questions</p>
                <h2 id="ab-faq-title">Frequently asked</h2>
            </header>
            <div class="ab-faq">
                @foreach($faqs as [$question, $answer])
                    <details>
                        <summary>{{ $question }}</summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
        <aside class="ab-contact" aria-label="Contact the BAC">
            <h3>Contact the BAC Secretariat</h3>
            <dl>
                @if($office['address'])<div><dt>Office</dt><dd>{{ $office['address'] }}</dd></div>@endif
                @if($office['hours'])<div><dt>Hours</dt><dd>{{ $office['hours'] }}</dd></div>@endif
                @if($office['email'])<div><dt>Email</dt><dd><a href="mailto:{{ $office['email'] }}">{{ $office['email'] }}</a></dd></div>@endif
                @if($office['phone'])<div><dt>Phone</dt><dd>{{ $office['phone'] }}</dd></div>@endif
                @if($office['person'])<div><dt>Contact person</dt><dd>{{ $office['person'] }}@if($office['person_position'])<small>{{ $office['person_position'] }}</small>@endif</dd></div>@endif
            </dl>
            <a href="{{ url('/contact') }}" class="btn">Send a message</a>
        </aside>
    </section>
</main>

<style>
    .ab { display: grid; gap: 28px; }
    .ab-kicker { margin: 0; color: var(--ui-primary, #1d4f40); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .ab-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr); gap: 0; overflow: hidden; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 20px; background: #fff; }
    .ab-hero__copy { display: grid; align-content: center; gap: 14px; padding: 40px; }
    .ab-hero h1 { margin: 0; color: var(--ui-ink, #1b2420); font-size: clamp(28px, 3.2vw, 40px); font-weight: 700; line-height: 1.12; letter-spacing: -.02em; }
    .ab-lead { margin: 0; max-width: 620px; color: var(--ui-ink-2, #3c4641); font-size: 15.5px; line-height: 1.65; }
    .ab-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; }
    .ab-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 24px; margin: 10px 0 0; padding-top: 18px; border-top: 1px solid var(--ui-line-soft, #efebe2); }
    .ab-facts dt { color: var(--ui-muted, #66756e); font-size: 12px; font-weight: 600; }
    .ab-facts dt i { width: 14px; color: var(--ui-primary, #1d4f40); }
    .ab-facts dd { margin: 2px 0 0; color: var(--ui-ink, #1b2420); font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
    .ab-facts a, .ab-contact a:not(.btn) { color: var(--ui-primary, #1d4f40); }
    .ab-hero__photo { position: relative; margin: 0; min-height: 320px; }
    .ab-hero__photo img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .ab-hero__photo figcaption { position: absolute; left: 14px; bottom: 14px; padding: 6px 10px; border-radius: 8px; background: rgba(27, 36, 32, .72); color: #fff; font-size: 12px; }

    .ab-section { display: grid; gap: 18px; }
    .ab-head h2 { margin: 4px 0 0; color: var(--ui-ink, #1b2420); font-size: 24px; font-weight: 700; letter-spacing: -.01em; }
    .ab :is(.ab-step h3, .ab-modes h3, .ab-duty h3, .ab-group h3, .ab-contact h3) { font-weight: 700; }

    .ab-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 0; padding: 0; list-style: none; }
    .ab-step { display: grid; grid-template-columns: 34px minmax(0, 1fr); gap: 14px; padding: 18px; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 14px; background: #fff; }
    .ab-step__num { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 50%; background: var(--ui-primary, #1d4f40); color: #fff; font-weight: 700; }
    .ab-step h3 { margin: 4px 0 6px; color: var(--ui-ink, #1b2420); font-size: 15px; }
    .ab-step p { margin: 0; color: var(--ui-ink-2, #3c4641); font-size: 13.5px; line-height: 1.55; }
    .ab-link { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; color: var(--ui-primary, #1d4f40); font-size: 13.5px; font-weight: 700; text-decoration: none; }
    .ab-link i { font-size: 11px; }
    .ab-modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .ab-modes article { padding: 18px; border-radius: 14px; background: var(--ui-primary-soft, #e8f1ec); }
    .ab-modes h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; color: var(--ui-ink, #1b2420); font-size: 15px; }
    .ab-modes h3 i { color: var(--ui-primary, #1d4f40); }
    .ab-modes p { margin: 0; color: var(--ui-ink-2, #3c4641); font-size: 13.5px; line-height: 1.55; }

    .ab-duties { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .ab-duty { padding: 18px; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 14px; background: #fff; }
    .ab-duty > span { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 10px; background: var(--ui-primary-soft, #e8f1ec); color: var(--ui-primary, #1d4f40); }
    .ab-duty h3 { margin: 12px 0 6px; color: var(--ui-ink, #1b2420); font-size: 15px; }
    .ab-duty p { margin: 0; color: var(--ui-ink-2, #3c4641); font-size: 13.5px; line-height: 1.55; }
    .ab-values { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
    .ab-values li { padding: 6px 12px; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 999px; background: #fff; color: var(--ui-ink-2, #3c4641); font-size: 13px; font-weight: 600; }

    .ab-people { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; }
    .ab-group { padding: 18px; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 14px; background: #fff; }
    .ab-group h3 { margin: 0 0 12px; color: var(--ui-ink, #1b2420); font-size: 15px; }
    .ab-group ul { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
    .ab-group li { display: grid; grid-template-columns: 38px minmax(0, 1fr); align-items: center; gap: 12px; }
    .ab-avatar { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: var(--ui-primary-soft, #e8f1ec); color: var(--ui-primary, #1d4f40); font-size: 13px; font-weight: 700; }
    .ab-group strong { display: block; color: var(--ui-ink, #1b2420); font-size: 14px; }
    .ab-group small, .ab-contact small { display: block; color: var(--ui-muted, #66756e); font-size: 12.5px; }

    .ab-resources { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin: 0; padding: 0; list-style: none; }
    .ab-resource { display: grid; grid-template-columns: 82px minmax(0, 1fr) 14px; align-items: center; gap: 14px; height: 100%; padding: 14px 16px; border: 1px solid var(--ui-line, #e6e1d6); border-radius: 12px; background: #fff; color: inherit; text-decoration: none; transition: border-color .15s ease, box-shadow .15s ease; }
    .ab-resource:hover, .ab-resource:focus-visible { border-color: var(--ui-primary, #1d4f40); box-shadow: 0 4px 14px rgba(27, 36, 32, .06); outline: none; }
    .ab-resource__tag { justify-self: start; padding: 3px 9px; border-radius: 999px; background: var(--ui-primary-soft, #e8f1ec); color: var(--ui-primary, #1d4f40); font-size: 12px; font-weight: 700; }
    .ab-resource__text strong { display: block; color: var(--ui-ink, #1b2420); font-size: 14px; line-height: 1.4; }
    .ab-resource__text small { display: block; margin-top: 2px; color: var(--ui-muted, #66756e); font-size: 12px; }
    .ab-resource > i { color: var(--ui-subtle, #78827c); font-size: 12px; }

    .ab-split { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); align-items: start; gap: 24px; }
    .ab-faq { display: grid; gap: 8px; margin-top: 18px; }
    .ab-faq details { border: 1px solid var(--ui-line, #e6e1d6); border-radius: 12px; background: #fff; }
    .ab-faq summary { padding: 14px 16px; color: var(--ui-ink, #1b2420); font-size: 14.5px; font-weight: 600; cursor: pointer; }
    .ab-faq details[open] summary { border-bottom: 1px solid var(--ui-line-soft, #efebe2); }
    .ab-faq p { margin: 0; padding: 12px 16px 16px; color: var(--ui-ink-2, #3c4641); font-size: 13.5px; line-height: 1.6; }
    .ab-contact { display: grid; gap: 12px; padding: 22px; border-radius: 16px; background: var(--ui-ink, #1b2420); color: #fff; }
    .ab-contact h3 { margin: 0; font-size: 17px; }
    .ab-contact dl { display: grid; gap: 10px; margin: 0; }
    .ab-contact dt { color: rgba(255, 255, 255, .65); font-size: 12px; }
    .ab-contact dd { margin: 2px 0 0; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
    .ab-contact a:not(.btn) { color: #9bd4bd; }
    .ab-contact small { color: rgba(255, 255, 255, .65); }
    .ab-contact .btn { justify-self: start; }

    @media (max-width: 1024px) {
        .ab-steps, .ab-modes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ab-duties { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 860px) {
        .ab-hero, .ab-split { grid-template-columns: minmax(0, 1fr); }
        .ab-hero__photo { order: -1; min-height: 220px; }
        .ab-hero__copy { padding: 26px 20px; }
        .ab-resources { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 600px) {
        .ab-steps, .ab-modes, .ab-duties, .ab-facts { grid-template-columns: minmax(0, 1fr); }
        .ab-head h2 { font-size: 21px; }
        .ab-resource { grid-template-columns: minmax(0, 1fr) 14px; }
        .ab-resource__tag { grid-column: 1 / -1; }
    }
</style>
@endsection
