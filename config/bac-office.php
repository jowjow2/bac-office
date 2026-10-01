<?php

return [
    'demo_clock' => [
        'enabled' => (bool) env('BAC_DEMO_MODE', false),
        'allow_testing' => false,
        'timezone' => 'Asia/Manila',
    ],

    'registration' => [
        'max_document_size_kb' => (int) env('BAC_BIDDER_DOCUMENT_MAX_KB', 20480),
    ],

    // Local time of the procuring entity (LGU San Jose, Occidental Mindoro).
    // Same as app.timezone, which is Philippine Standard Time.
    'display_timezone' => env('BAC_DISPLAY_TIMEZONE', env('APP_TIMEZONE', 'Asia/Manila')),

    // The procuring entity, as it appears on PhilGEPS notices.
    'procuring_entity' => env('BAC_PROCURING_ENTITY', 'Municipality of San Jose, Occidental Mindoro'),

    // Contact details every Invitation to Bid must state (RA 12009 IRR Sec.
    // 50.2(m)): address, telephone, fax, e-mail, website and the designated
    // contact person. Set the blank ones in .env; the procurement record
    // shows a reminder until they are filled.
    'contact' => [
        'office' => env('BAC_CONTACT_OFFICE', 'BAC Secretariat'),
        'address' => env('BAC_CONTACT_ADDRESS', 'Municipal Hall, San Jose, Occidental Mindoro'),
        'email' => env('BAC_CONTACT_EMAIL', 'bacoffice@sanjose.gov.ph'),
        'phone' => env('BAC_CONTACT_PHONE'),
        'fax' => env('BAC_CONTACT_FAX'),
        'website' => env('BAC_CONTACT_WEBSITE'),
        'person' => env('BAC_CONTACT_PERSON'),
        'person_position' => env('BAC_CONTACT_PERSON_POSITION', 'Head, BAC Secretariat'),
    ],

    // LGU type and income class drive the Small Value Procurement ceiling of
    // the RA 12009 IRR (GPPB Resolution No. 02-2025), Section 34.2. San Jose,
    // Occidental Mindoro is a 1st class municipality.
    'lgu' => [
        'type' => env('BAC_LGU_TYPE', 'municipality'),
        'income_class' => (int) env('BAC_LGU_INCOME_CLASS', 1),
    ],
];
