<?php

use CodingLibs\MFA\MFA;
use Illuminate\Contracts\Auth\Authenticatable;

class MFANegFakeUser implements Authenticatable
{
	public function __construct(public int|string $id) {}
	public function getAuthIdentifierName() { return 'id'; }
	public function getAuthIdentifier() { return $this->id; }
	public function getAuthPassword() { return ''; }
	public function getAuthPasswordName() { return 'password'; }
	public function getRememberToken() { return null; }
	public function setRememberToken($value): void {}
	public function getRememberTokenName() { return 'remember_token'; }
}

it('verifyTotp returns false when method not set', function () {
	$user = new MFANegFakeUser(9001);
	$mfa = app(MFA::class);
	expect($mfa->verifyTotp($user, '000000'))->toBeFalse();
});

it('issueChallenge returns null for unknown channel', function () {
	$user = new MFANegFakeUser(9002);
	$mfa = app(MFA::class);
	expect($mfa->issueChallenge($user, 'unknown'))->toBeNull();
});

it('verifyChallenge returns false when no active challenge', function () {
	$user = new MFANegFakeUser(9003);
	$mfa = app(MFA::class);
	expect($mfa->verifyChallenge($user, 'email', '123456'))->toBeFalse();
});


