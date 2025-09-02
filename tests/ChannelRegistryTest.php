<?php

use CodingLibs\MFA\ChannelRegistry;
use CodingLibs\MFA\Contracts\MfaChannel;
use Illuminate\Contracts\Auth\Authenticatable;

class DummyChannel implements MfaChannel
{
    public function __construct(public string $name = 'custom') {}
    public function getName(): string { return $this->name; }
    public function send(Authenticatable $user, string $code, array $options = []): void {}
}

it('registers and retrieves channels case-insensitively', function () {
    $registry = new ChannelRegistry();
    $registry->register(new DummyChannel('Email'));
    $registry->register(new DummyChannel('SMS'));

    expect($registry->has('email'))->toBeTrue();
    expect($registry->has('EMAIL'))->toBeTrue();
    expect($registry->has('sms'))->toBeTrue();
    expect($registry->get('EMAIL'))->toBeInstanceOf(MfaChannel::class);
});

