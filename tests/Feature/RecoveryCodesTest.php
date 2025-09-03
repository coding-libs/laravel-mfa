<?php

use CodingLibs\MFA\Models\MfaRecoveryCode;
use Illuminate\Contracts\Auth\Authenticatable;

uses(Tests\TestCase::class);

class FakeUser implements Authenticatable
{
	public function __construct(public int|string $id) {}
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

it('generates recovery codes and persists hashed versions', function () {
	$user = new FakeUser(1);
	$mfa = app(\CodingLibs\MFA\MFA::class);

	$codes = $mfa->generateRecoveryCodes($user, 5, 12);
	expect($codes)->toHaveCount(5);
	foreach ($codes as $code) {
		expect(strlen($code))->toBe(12);
	}

	$remaining = $mfa->getRemainingRecoveryCodesCount($user);
	expect($remaining)->toBe(5);

	$records = MfaRecoveryCode::query()
		->where('model_type', get_class($user))
		->where('model_id', $user->getAuthIdentifier())
		->get();
	expect($records)->toHaveCount(5);
	foreach ($records as $rec) {
		// Ensure hashes are stored, not plaintext
		expect($codes)->not->toContain($rec->code_hash);
		expect($rec->used_at)->toBeNull();
	}
});

it('verifies and consumes a recovery code', function () {
	$user = new FakeUser(2);
	$mfa = app(\CodingLibs\MFA\MFA::class);

	$codes = $mfa->generateRecoveryCodes($user, 3, 10);
	$ok = $mfa->verifyRecoveryCode($user, $codes[0]);
	expect($ok)->toBeTrue();

	// cannot reuse the same code
	$okAgain = $mfa->verifyRecoveryCode($user, $codes[0]);
	expect($okAgain)->toBeFalse();

	$remaining = $mfa->getRemainingRecoveryCodesCount($user);
	expect($remaining)->toBe(2);
});

it('regenerates on use when enabled', function () {
	config(['mfa.recovery.regenerate_on_use' => true]);
	$user = new FakeUser(3);
	$mfa = app(\CodingLibs\MFA\MFA::class);

	$codes = $mfa->generateRecoveryCodes($user, 4, 8);
	$ok = $mfa->verifyRecoveryCode($user, $codes[1]);
	expect($ok)->toBeTrue();

	// Pool size remains steady due to auto-regeneration
	$remaining = $mfa->getRemainingRecoveryCodesCount($user);
	expect($remaining)->toBe(4);
});

it('clearRecoveryCodes deletes all codes', function () {
	$user = new FakeUser(4);
	$mfa = app(\CodingLibs\MFA\MFA::class);

	$mfa->generateRecoveryCodes($user, 2, 10);
	$deleted = $mfa->clearRecoveryCodes($user);
	expect($deleted)->toBeGreaterThanOrEqual(2);

	$remaining = $mfa->getRemainingRecoveryCodesCount($user);
	expect($remaining)->toBe(0);
});

