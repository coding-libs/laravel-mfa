<?php

use CodingLibs\MFA\MFA;
use CodingLibs\MFA\Contracts\MfaChannel;
use CodingLibs\MFA\Models\MfaChallenge;
use CodingLibs\MFA\Models\MfaMethod;
use CodingLibs\MFA\Totp\GoogleTotp;
use Illuminate\Contracts\Auth\Authenticatable;

class MFATestFakeUser implements Authenticatable
{
	public function __construct(public int|string $id, public ?string $email = null) {}
	public function getAuthIdentifierName()
	{
		return 'id';
	}
	public function getAuthIdentifier()
	{
		return $this->id;
	}
	public function getAuthPassword()
	{
		return '';
	}
	public function getAuthPasswordName()
	{
		return 'password';
	}
	public function getRememberToken()
	{
		return null;
	}
	public function setRememberToken($value): void {}
	public function getRememberTokenName()
	{
		return 'remember_token';
	}
}

class MFATestDummyChannel implements MfaChannel
{
	public ?string $lastCode = null;
	public function __construct(public string $name = 'custom') {}
	public function getName(): string { return $this->name; }
	public function send(Authenticatable $user, string $code, array $options = []): void
	{
		$this->lastCode = $code;
	}
}

it('sets up TOTP and enables the method', function () {
	$user = new MFATestFakeUser(1001, 'user@example.com');
	$mfa = app(MFA::class);

	$result = $mfa->setupTotp($user, 'Acme', 'user@example.com');

	expect($result)
		->toHaveKeys(['secret', 'otpauth_url']);

	$method = $mfa->getMethod($user, 'totp');
	expect($method)->toBeInstanceOf(MfaMethod::class);
	expect($mfa->isEnabled($user, 'totp'))->toBeTrue();
	expect($method->secret)->toBeString()->not->toBe('');
});

it('verifies a valid TOTP code and updates last_used_at', function () {
	$user = new MFATestFakeUser(1002, 'user2@example.com');
	$mfa = app(MFA::class);

	$result = $mfa->setupTotp($user, 'Acme', 'user2@example.com');
	$secret = $result['secret'];

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
	$hash = $hotp->invoke(null, $base32Decode->invoke(null, $secret), $timeSlice);
	$expected = $truncate->invoke(null, $hash, $digits);

	$ok = $mfa->verifyTotp($user, $expected);
	expect($ok)->toBeTrue();

	$method = $mfa->getMethod($user, 'totp');
	expect($method->last_used_at)->not->toBeNull();
});

it('issues a challenge through a custom channel and verifies it', function () {
	$user = new MFATestFakeUser(1003);
	$mfa = app(MFA::class);

	$channel = new MFATestDummyChannel('custom');
	$mfa->registerChannel($channel);

	$challenge = $mfa->issueChallenge($user, 'custom');
	expect($challenge)->toBeInstanceOf(MfaChallenge::class);
	expect($challenge->code)->toBeString()->toHaveLength(6);
	expect($channel->lastCode)->toBe($challenge->code);

	$verified = $mfa->verifyChallenge($user, 'custom', $challenge->code);
	expect($verified)->toBeTrue();

	$challenge->refresh();
	expect($challenge->consumed_at)->not->toBeNull();
	expect($mfa->isEnabled($user, 'custom'))->toBeTrue();
});

it('fails to verify expired challenges', function () {
	$user = new MFATestFakeUser(1004);
	$mfa = app(MFA::class);

	$channel = new MFATestDummyChannel('custom2');
	$mfa->registerChannel($channel);

	$challenge = $mfa->issueChallenge($user, 'custom2');
	$challenge->expires_at = now()->subMinute();
	$challenge->save();

	$verified = $mfa->verifyChallenge($user, 'custom2', $challenge->code);
	expect($verified)->toBeFalse();
});

it('enables and disables methods', function () {
	$user = new MFATestFakeUser(1005);
	$mfa = app(MFA::class);

	$mfa->enableMethod($user, 'email');
	expect($mfa->isEnabled($user, 'email'))->toBeTrue();

	$mfa->disableMethod($user, 'email');
	expect($mfa->isEnabled($user, 'email'))->toBeFalse();
});

it('remembers device, skips verification with token, and can forget device', function () {
	config(['mfa.remember.enabled' => true, 'mfa.remember.lifetime_days' => 1]);

	$user = new MFATestFakeUser(1006);
	$mfa = app(MFA::class);

	$request = Illuminate\Http\Request::create('/', 'GET');
	app()->instance('request', $request);

	$result = $mfa->rememberDevice($user, null, 'MyDevice');
	expect($result['token'])->toBeString()->not->toBe('');
	expect($result['cookie'])->not->toBeNull();
	expect($result['cookie']->getName())->toBe($mfa->getRememberCookieName());

	$skip = $mfa->shouldSkipVerification($user, $result['token']);
	expect($skip)->toBeTrue();

	$deleted = $mfa->forgetRememberedDevice($user, $result['token']);
	expect($deleted)->toBeGreaterThanOrEqual(1);

	$skipAgain = $mfa->shouldSkipVerification($user, $result['token']);
	expect($skipAgain)->toBeFalse();
});


