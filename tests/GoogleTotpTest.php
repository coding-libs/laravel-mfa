<?php

use CodingLibs\MFA\Totp\GoogleTotp;

it('generates a base32-like secret and builds otpauth url', function () {
    $secret = GoogleTotp::generateSecret();
    expect($secret)->toBeString()->not->toBe('')->and(strlen($secret))->toBeGreaterThan(10);

    $url = GoogleTotp::buildOtpAuthUrl($secret, 'user@example.com', 'Acme', 6, 30);
    expect($url)->toStartWith('otpauth://totp/')
        ->toContain($secret)
        ->toContain('issuer=Acme')
        ->toContain('digits=6')
        ->toContain('period=30');
});

it('verifies a known code for a fixed time slice', function () {
    // RFC 6238 test secret in base32 (for SHA1): "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ"
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    // Mock time() by calculating expected code at a specific slice using internal methods via reflection
    $ref = new ReflectionClass(GoogleTotp::class);
    $base32Decode = $ref->getMethod('base32Decode');
    $base32Decode->setAccessible(true);
    $hotp = $ref->getMethod('hotp');
    $hotp->setAccessible(true);
    $truncate = $ref->getMethod('truncateToDigits');
    $truncate->setAccessible(true);

    $period = 30;
    $digits = 6;
    $timeSlice = intdiv(59, $period); // from RFC 6238 example 59s
    $hash = $hotp->invoke(null, $base32Decode->invoke(null, $secret), $timeSlice);
    $expected = $truncate->invoke(null, $hash, $digits);

    // Now verify using public API with window 0 to ensure exact slice
    $verified = GoogleTotp::verifyCode($secret, $expected, $digits, $period, 0);
    expect($verified)->toBeTrue();
});

