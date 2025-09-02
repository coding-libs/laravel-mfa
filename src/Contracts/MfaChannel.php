<?php

namespace CodingLibs\MFA\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MfaChannel
{
    public function getName(): string;

    public function send(Authenticatable $user, string $code, array $options = []): void;
}

