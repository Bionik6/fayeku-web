<?php

return [
    'otp_expiry_minutes' => (int) env('OTP_EXPIRY_MINUTES', 10),
    'otp_max_attempts'   => (int) env('OTP_MAX_ATTEMPTS', 3),
    'fne_api_url'        => env('FNE_API_URL', ''),
    'fne_test_url'       => env('FNE_TEST_URL', 'http://54.247.95.108/ws'),
    'countries' => [
        'SN' => ['name' => 'Sénégal',       'prefix' => '+221'],
        'CI' => ['name' => "Côte d'Ivoire", 'prefix' => '+225'],
    ],
];
