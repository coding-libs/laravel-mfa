<?php

use CodingLibs\MFA\ChannelRegistry;
use CodingLibs\MFA\Contracts\MfaChannel;
use Illuminate\Contracts\Auth\Authenticatable;

class RegistryDummy implements MfaChannel
{
	public function __construct(public string $name) {}
	public function getName(): string { return $this->name; }
	public function send(Authenticatable $user, string $code, array $options = []): void {}
}

it('returns null for missing channels', function () {
	$registry = new ChannelRegistry();
	expect($registry->get('unknown'))->toBeNull();
});

it('all returns lowercase keys and latest wins on duplicates', function () {
	$registry = new ChannelRegistry();
	$first = new RegistryDummy('Email');
	$second = new RegistryDummy('EMAIL');

	$registry->register($first);
	$registry->register($second);

	$all = $registry->all();
	expect(array_keys($all))->toBe(['email']);
	expect($registry->get('email'))->toBe($second);
});


