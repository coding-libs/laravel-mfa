<?php

return [
    'code_length'      => 6,
    'code_ttl_seconds' => 300,

    'email' => [
        'enabled'      => true,
        'from_address' => env('MFA_EMAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name'    => env('MFA_EMAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Laravel')),
        'subject'      => env('MFA_EMAIL_SUBJECT', 'Your verification code'),
    ],

    'sms' => [
        'enabled' => true,
        'driver'  => env('MFA_SMS_DRIVER', 'log'), // log|null
        'from'    => env('MFA_SMS_FROM', ''),
    ],

    'totp' => [
        'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
        'digits' => (int)env('MFA_TOTP_DIGITS', 6),
        'period' => (int)env('MFA_TOTP_PERIOD', 30),
        'window' => (int)env('MFA_TOTP_WINDOW', 1),
    ],

    'remember' => [
        'enabled'        => env('MFA_REMEMBER_ENABLED', true),
        'cookie'         => env('MFA_REMEMBER_COOKIE', 'mfa_rd'),
        'lifetime_days'  => (int) env('MFA_REMEMBER_LIFETIME_DAYS', 30),
        'path'           => env('MFA_REMEMBER_PATH', '/'),
        'domain'         => env('MFA_REMEMBER_DOMAIN', null),
        'secure'         => env('MFA_REMEMBER_SECURE', null), // null to follow app('request')->isSecure()
        'http_only'      => env('MFA_REMEMBER_HTTP_ONLY', true),
        'same_site'      => env('MFA_REMEMBER_SAME_SITE', 'lax'), // lax|strict|none
    ],
];

