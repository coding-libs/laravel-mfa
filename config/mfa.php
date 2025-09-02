<?php

return [
    'code_length' => 6,
    'code_ttl_seconds' => 300,

    'email' => [
        'enabled' => true,
        'from_address' => env('MFA_EMAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('MFA_EMAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Laravel')),
        'subject' => env('MFA_EMAIL_SUBJECT', 'Your verification code'),
    ],

    'sms' => [
        'enabled' => true,
        'driver' => env('MFA_SMS_DRIVER', 'log'), // log|null
        'from' => env('MFA_SMS_FROM', ''),
    ],

    'totp' => [
        'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
        'digits' => (int) env('MFA_TOTP_DIGITS', 6),
        'period' => (int) env('MFA_TOTP_PERIOD', 30),
        'window' => (int) env('MFA_TOTP_WINDOW', 1),
    ],
];

