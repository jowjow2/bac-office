@extends('layouts.public')

@section('title', 'Privacy Policy · SJBAC')
@section('body_class', 'public-page')

@section('content')
    <main class="public-shell privacy-page">
        <section class="public-page-hero">
            <p class="public-page-kicker">Privacy Policy</p>
            <h1>How the SJBAC Procurement Portal handles your information</h1>
            <p>
                The Bids and Awards Committee (BAC) of the Municipality of San Jose, Occidental Mindoro runs this portal
                for public procurement. We process personal information under the Data Privacy Act of 2012 (Republic Act
                No. 10173) and only as public procurement requires. Effective October 2026.
            </p>
        </section>

        <section class="public-detail-card privacy-card">
            <h2>What we collect</h2>
            <ul>
                <li><strong>Account details:</strong> name, email address, role and office, and your password (stored only in scrambled, one-way form).</li>
                <li><strong>Bidder registration:</strong> company name, business registration number, contact person and number, business address, and the eligibility documents you upload.</li>
                <li><strong>Bids and payments:</strong> bid documents and amounts, submission times, the financial PIN (stored only in scrambled form), and official receipt details of bidding documents fees recorded by the BAC.</li>
                <li><strong>Messages and notices</strong> exchanged with the BAC in the portal.</li>
                <li><strong>Security records:</strong> sign-in time, method, IP address and browser, and a log of actions taken on procurement records (audit trail).</li>
                <li><strong>Google sign-in:</strong> if you choose "Continue with Google", we receive only your Google email address and name to match the account already registered with that email. We do not receive your Google password, contacts or files, and we never create an account from Google data.</li>
            </ul>

            <h2>Why we use it</h2>
            <ul>
                <li>To register and verify bidders, receive and evaluate bids, and award contracts under Republic Act No. 12009 (New Government Procurement Act), Republic Act No. 9184 and their implementing rules.</li>
                <li>To send you sign-in codes, schedule updates, results and other procurement notices.</li>
                <li>To keep the procurement secure and accountable: sealed bids stay closed until their opening, and actions are recorded for audit.</li>
                <li>To publish what the law requires to be public, such as notices of procurement and awards (project, winning bidder and contract amount).</li>
            </ul>

            <h2>Who sees it</h2>
            <ul>
                <li>The BAC, its Secretariat and the Head of the Procuring Entity, for the procurement you take part in.</li>
                <li>Government oversight bodies when required by law, such as the Commission on Audit and the Government Procurement Policy Board.</li>
                <li>Service providers that run the portal for us under our instructions: hosting and file storage (Vercel), the database (Neon) and email delivery (Google). They may not use your information for their own purposes.</li>
            </ul>
            <p>We do not sell your information or use it for advertising.</p>

            <h2>Cookies and your browser</h2>
            <p>
                The portal uses cookies only to keep you signed in and to protect forms against forgery. While you prepare a
                bid, the files you attach can be kept in your own browser so you do not lose them; they are cleared when the
                bid is submitted or when you sign out.
            </p>

            <h2>How long we keep it</h2>
            <p>
                Procurement records, including bids, evaluations and awards, are kept for as long as government auditing and
                records-retention rules require. Other information is kept only while your account is active or as needed to
                answer an audit or a legal claim.
            </p>

            <h2>Your rights</h2>
            <p>
                Under the Data Privacy Act you may ask to be informed, to access and correct your information, to object, and
                to have information erased or blocked where the law allows, given that procurement records must be kept. You
                may also file a complaint with the National Privacy Commission.
            </p>

            <h2>Contact</h2>
            <p>
                For privacy questions or requests, contact the BAC Secretariat through the <a href="{{ url('/contact') }}">Contact page</a>.
            </p>
        </section>
    </main>

    <style>
        .privacy-page .privacy-card { max-width: 860px; }
        .privacy-page .privacy-card h2 { margin: 26px 0 8px; font-size: 18px; }
        .privacy-page .privacy-card h2:first-child { margin-top: 0; }
        .privacy-page .privacy-card :is(p, li) { line-height: 1.65; }
        .privacy-page .privacy-card ul { margin: 0; padding-left: 20px; display: grid; gap: 6px; }
    </style>
@endsection
