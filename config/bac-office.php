<?php

return [
    'registration' => [
        'max_document_size_kb' => (int) env('BAC_BIDDER_DOCUMENT_MAX_KB', 20480),
    ],

    'qr_login' => [
        'token_ttl_hours' => (int) env('BAC_QR_LOGIN_TOKEN_TTL_HOURS', 720),
        'rate_limit_per_minute' => (int) env('BAC_QR_LOGIN_RATE_LIMIT_PER_MINUTE', 10),
        'require_password_on_first_use' => (bool) env('BAC_QR_LOGIN_REQUIRE_PASSWORD_ON_FIRST_USE', false),
    ],
];
