<?php

use CodingLibs\MFA\Totp\GoogleTotp;

it('rejects invalid code formats', function () {
	$secret = GoogleTotp::generateSecret();
	// Non-numeric and wrong length
	expect(GoogleTotp::verifyCode($secret, 'abcdef', 6, 30, 1))->toBeFalse();
	expect(GoogleTotp::verifyCode($secret, '123', 6, 30, 1))->toBeFalse();
	// Very long string
	expect(GoogleTotp::verifyCode($secret, str_repeat('9', 20), 6, 30, 1))->toBeFalse();
});

it('supports 4 and 8 digit OTPs using exact slice', function () {
	$secret = GoogleTotp::generateSecret();
	$ref = new ReflectionClass(GoogleTotp::class);
	$base32Decode = $ref->getMethod('base32Decode');
	$base32Decode->setAccessible(true);
	$hotp = $ref->getMethod('hotp');
	$hotp->setAccessible(true);
	$truncate = $ref->getMethod('truncateToDigits');
	$truncate->setAccessible(true);

	$period = 30;
	$timeSlice = (int) floor(time() / $period);
	$key = $base32Decode->invoke(null, $secret);

	$hash = $hotp->invoke(null, $key, $timeSlice);
	$code4 = $truncate->invoke(null, $hash, 4);
	$code8 = $truncate->invoke(null, $hash, 8);

	expect(GoogleTotp::verifyCode($secret, $code4, 4, $period, 0))->toBeTrue();
	expect(GoogleTotp::verifyCode($secret, $code8, 8, $period, 0))->toBeTrue();
});

it('respects zero window (adjacent slice fails)', function () {
	$secret = GoogleTotp::generateSecret();
	$ref = new ReflectionClass(GoogleTotp::class);
	$base32Decode = $ref->getMethod('base32Decode');
	$base32Decode->setAccessible(true);
	$hotp = $ref->getMethod('hotp');
	$hotp->setAccessible(true);
	$truncate = $ref->getMethod('truncateToDigits');
	$truncate->setAccessible(true);

	$period = 30;
	$digits = 6;
	$timeSlice = (int) floor(time() / $period);
	$key = $base32Decode->invoke(null, $secret);

	$prev = $truncate->invoke(null, $hotp->invoke(null, $key, $timeSlice - 1), $digits);
	$curr = $truncate->invoke(null, $hotp->invoke(null, $key, $timeSlice), $digits);

	expect(GoogleTotp::verifyCode($secret, $prev, $digits, $period, 0))->toBeFalse();
	expect(GoogleTotp::verifyCode($secret, $curr, $digits, $period, 0))->toBeTrue();
});


