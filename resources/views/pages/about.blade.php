@extends('layouts.public')

@section('title', 'About BAC-Office')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell">
        <section class="public-page-hero">
            <p class="public-page-kicker">About BAC-Office</p>
            <h1>Procurement transparency built for clarity and public trust.</h1>
            <p>
                BAC-Office helps present project opportunities, bidding activity, and awards information in a cleaner,
                easier-to-understand public experience.
            </p>
        </section>

        <section class="public-detail-card about-legal-card">
            <div class="about-legal-copy">
                <p class="public-page-kicker">Legal Basis</p>
                <h2>Government procurement laws and official references</h2>
                <p>
                    BAC procurement activities are guided by the New Government Procurement Act, the Government
                    Procurement Reform Act, and applicable implementing rules and transition guidance issued through
                    official procurement policy resources.
                </p>
            </div>

            <div class="about-legal-links">
                <a href="https://www.dbm.gov.ph/index.php/republic-act-ra-no-12009-new-government-procurement-act-ngpa" target="_blank" rel="noopener" class="about-legal-link">
                    <span>RA 12009</span>
                    <strong>New Government Procurement Act and IRR downloads</strong>
                </a>

                <a href="https://www.gppb.gov.ph/important-update-effectivity-and-transistory-periods-under-ra-12009/" target="_blank" rel="noopener" class="about-legal-link">
                    <span>Transition</span>
                    <strong>Effectivity and transitory periods under RA 12009</strong>
                </a>

                <a href="https://www.gppb.gov.ph/wp-content/uploads/2023/06/Republic-Act-No.-9184.pdf" target="_blank" rel="noopener" class="about-legal-link">
                    <span>RA 9184</span>
                    <strong>Government Procurement Reform Act</strong>
                </a>

                <a href="https://www.gppb.gov.ph/ra-9184-and-2016-revised-irr/" target="_blank" rel="noopener" class="about-legal-link">
                    <span>IRR</span>
                    <strong>Updated 2016 Revised IRR of RA 9184</strong>
                </a>
            </div>
        </section>

        <section class="about-section">
            <div class="about-section-heading">
                <p class="public-page-kicker">Mandate</p>
                <h2>Bids and Awards Committee</h2>
                <p>
                    The BAC supports transparent, competitive, and accountable procurement by managing the public
                    bidding process and recommending awards in accordance with applicable procurement rules.
                </p>
            </div>

            <div class="about-card-grid about-card-grid-three">
                <article class="about-info-card">
                    <span>01</span>
                    <h3>Procurement Oversight</h3>
                    <p>Facilitate procurement activities from project posting through bid evaluation and recommendation.</p>
                </article>

                <article class="about-info-card">
                    <span>02</span>
                    <h3>Public Transparency</h3>
                    <p>Keep procurement opportunities, bidding notices, and awards easier for bidders and citizens to review.</p>
                </article>

                <article class="about-info-card">
                    <span>03</span>
                    <h3>Compliance Support</h3>
                    <p>Help ensure procurement steps align with governing laws, IRR provisions, and official policy guidance.</p>
                </article>
            </div>
        </section>

        <section class="about-section">
            <div class="about-section-heading">
                <p class="public-page-kicker">BAC Functions</p>
                <h2>Core procurement responsibilities</h2>
            </div>

            <div class="about-function-list">
                <div class="about-function-item">
                    <strong>Post procurement opportunities</strong>
                    <p>Publish and organize notices, bidding information, deadlines, and project details for public access.</p>
                </div>

                <div class="about-function-item">
                    <strong>Conduct bidding activities</strong>
                    <p>Coordinate pre-bid conferences, bid submission, opening of bids, and related procurement proceedings.</p>
                </div>

                <div class="about-function-item">
                    <strong>Evaluate bids</strong>
                    <p>Support eligibility checks, bid evaluation, post-qualification, and documentation review.</p>
                </div>

                <div class="about-function-item">
                    <strong>Recommend awards</strong>
                    <p>Prepare recommendations for award based on the result of evaluation and applicable procurement rules.</p>
                </div>

                <div class="about-function-item">
                    <strong>Maintain procurement records</strong>
                    <p>Keep project files, bidding documents, notices, reports, and award records organized for monitoring.</p>
                </div>

                <div class="about-function-item">
                    <strong>Support public monitoring</strong>
                    <p>Provide a clearer public-facing view of procurement status, opportunities, and award information.</p>
                </div>
            </div>
        </section>

        <section class="about-section">
            <div class="about-section-heading">
                <p class="public-page-kicker">Procurement Principles</p>
                <h2>Values that guide public procurement</h2>
            </div>

            <div class="about-principles">
                <span>Transparency</span>
                <span>Competitiveness</span>
                <span>Efficiency</span>
                <span>Accountability</span>
                <span>Public Monitoring</span>
                <span>Value for Money</span>
                <span>Sustainability</span>
                <span>Professionalism</span>
            </div>
        </section>

        <section class="about-section about-links-section">
            <div class="about-section-heading">
                <p class="public-page-kicker">Helpful Links</p>
                <h2>Official procurement resources</h2>
            </div>

            <div class="about-resource-links">
                <a href="https://www.gppb.gov.ph/" target="_blank" rel="noopener">Government Procurement Policy Board</a>
                <a href="https://www.philgeps.gov.ph/" target="_blank" rel="noopener">PhilGEPS</a>
                <a href="https://ps-philgeps.gov.ph/" target="_blank" rel="noopener">Procurement Service - PhilGEPS</a>
                <a href="https://www.gppb.gov.ph/ngpa-implementing-rules-and-regulations/" target="_blank" rel="noopener">NGPA Implementing Rules and Regulations</a>
                <a href="https://www.gppb.gov.ph/ra-9184-and-2016-revised-irr/" target="_blank" rel="noopener">RA 9184 and Updated 2016 Revised IRR</a>
            </div>
        </section>
    </main>
@endsection
