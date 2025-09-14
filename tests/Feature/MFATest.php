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

it('returns only channels enabled by both client and config', function () {
	$user = new MFATestFakeUser(2001, 'user@example.com');
	$mfa = app(MFA::class);

	// Initially, no channels should be enabled by client
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->toBeEmpty();

	// Enable email method for the user
	$mfa->enableMethod($user, 'email');
	
	// Now email should be in enabled channels (if config allows it)
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->toHaveKey('email');
	expect($enabledChannels['email'])->toBeInstanceOf(MfaChannel::class);

	// Enable SMS method for the user
	$mfa->enableMethod($user, 'sms');
	
	// Now both email and sms should be in enabled channels (if config allows them)
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->toHaveKeys(['email', 'sms']);
	expect($enabledChannels['email'])->toBeInstanceOf(MfaChannel::class);
	expect($enabledChannels['sms'])->toBeInstanceOf(MfaChannel::class);

	// Disable email method for the user
	$mfa->disableMethod($user, 'email');
	
	// Now only sms should be in enabled channels
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->not->toHaveKey('email');
	expect($enabledChannels)->toHaveKey('sms');
	expect($enabledChannels['sms'])->toBeInstanceOf(MfaChannel::class);
});

it('excludes channels disabled in config even if enabled by client', function () {
	$user = new MFATestFakeUser(2002, 'user@example.com');
	
	// Create MFA instance with custom config where email is disabled
	$config = config('mfa');
	$config['email']['enabled'] = false;
	$mfa = new MFA($config);

	// Enable email method for the user
	$mfa->enableMethod($user, 'email');
	
	// Email should not be in enabled channels because it's disabled in config
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->not->toHaveKey('email');
	
	// But SMS should still be there if enabled in config and by client
	$mfa->enableMethod($user, 'sms');
	$enabledChannels = $mfa->getEnabledChannels($user);
	expect($enabledChannels)->toHaveKey('sms');
	expect($enabledChannels)->not->toHaveKey('email');
});

it('can check if a channel is enabled in config', function () {
	$mfa = app(MFA::class);

	// Test with default config (email, sms, and recovery should be enabled)
	expect($mfa->isChannelEnabledInConfig('email'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('sms'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('recovery'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('totp'))->toBeTrue(); // totp doesn't have enabled config, defaults to true

	// Test with custom config where email is disabled
	$config = config('mfa');
	$config['email']['enabled'] = false;
	$mfa = new MFA($config);

	expect($mfa->isChannelEnabledInConfig('email'))->toBeFalse();
	expect($mfa->isChannelEnabledInConfig('sms'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('recovery'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('totp'))->toBeTrue();

	// Test with custom config where sms is disabled
	$config = config('mfa');
	$config['sms']['enabled'] = false;
	$mfa = new MFA($config);

	expect($mfa->isChannelEnabledInConfig('email'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('sms'))->toBeFalse();
	expect($mfa->isChannelEnabledInConfig('recovery'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('totp'))->toBeTrue();
});

it('can check channel config via facade', function () {
	// Test via facade (using app() to get the instance)
	$mfa = app(MFA::class);
	expect($mfa->isChannelEnabledInConfig('email'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('sms'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('recovery'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('totp'))->toBeTrue();
});

it('can check recovery codes config when disabled', function () {
	// Test with custom config where recovery is disabled
	$config = config('mfa');
	$config['recovery']['enabled'] = false;
	$mfa = new MFA($config);

	expect($mfa->isChannelEnabledInConfig('email'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('sms'))->toBeTrue();
	expect($mfa->isChannelEnabledInConfig('recovery'))->toBeFalse();
	expect($mfa->isChannelEnabledInConfig('totp'))->toBeTrue();
});


