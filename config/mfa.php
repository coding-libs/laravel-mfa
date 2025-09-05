<?php

return [
    'code_length'      => 6,
    'code_ttl_seconds' => 300,

    'email' => [
        'enabled'      => env('MFA_EMAIL_ENABLED', true),
        'from_address' => env('MFA_EMAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
        'from_name'    => env('MFA_EMAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Laravel')),
        'subject'      => env('MFA_EMAIL_SUBJECT', 'Your verification code'),
        'channel'      => env('MFA_EMAIL_CHANNEL', \CodingLibs\MFA\Channels\EmailChannel::class),
    ],

    'sms' => [
        'enabled' => env('MFA_SMS_ENABLED', true),
        'driver'  => env('MFA_SMS_DRIVER', 'log'), // log|null
        'from'    => env('MFA_SMS_FROM', ''),
        'channel' => env('MFA_SMS_CHANNEL', \CodingLibs\MFA\Channels\SmsChannel::class),
    ],

    'totp' => [
        'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
        'digits' => (int)env('MFA_TOTP_DIGITS', 6),
        'period' => (int)env('MFA_TOTP_PERIOD', 30),
        'window' => (int)env('MFA_TOTP_WINDOW', 1),
    ],

    'remember' => [
        'enabled'       => env('MFA_REMEMBER_ENABLED', true),
        'cookie'        => env('MFA_REMEMBER_COOKIE', 'mfa_rd'),
        'lifetime_days' => (int)env('MFA_REMEMBER_LIFETIME_DAYS', 30),
        'path'          => env('MFA_REMEMBER_PATH', '/'),
        'domain'        => env('MFA_REMEMBER_DOMAIN', null),
        'secure'        => env('MFA_REMEMBER_SECURE', null), // null to follow app('request')->isSecure()
        'http_only'     => env('MFA_REMEMBER_HTTP_ONLY', true),
        'same_site'     => env('MFA_REMEMBER_SAME_SITE', 'lax'), // lax|strict|none
    ],

    'recovery' => [
        'enabled'           => env('MFA_RECOVERY_ENABLED', true),
        'codes_count'       => (int)env('MFA_RECOVERY_CODES_COUNT', 10),
        'code_length'       => (int)env('MFA_RECOVERY_CODE_LENGTH', 10),
        'regenerate_on_use' => env('MFA_RECOVERY_REGENERATE_ON_USE', false),
        'hash_algo'         => env('MFA_RECOVERY_HASH_ALGO', 'sha256'),
    ],

    // Polymorphic owner of MFA records: columns will be model_type/model_id
    'morph'    => [
        // Column name prefix; results in `${name}_type` and `${name}_id`
        'name'          => env('MFA_MORPH_NAME', 'model'),

        // ID column type for `${name}_id`.
        // Supported: unsignedBigInteger (default) | unsignedInteger | bigInteger | integer | string | uuid | ulid
        'type'          => env('MFA_MORPH_TYPE', 'unsignedBigInteger'),

        // Length for `${name}_id` when type is "string"
        'string_length' => (int)env('MFA_MORPH_STRING_LENGTH', 40),

        // Length for `${name}_type` column
        'type_length'   => (int)env('MFA_MORPH_TYPE_LENGTH', 255),
    ],
];

