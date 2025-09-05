<?php

namespace CodingLibs\MFA;

use CodingLibs\MFA\Contracts\MfaChannel;

class ChannelRegistry
{
    /** @var array<string, MfaChannel> */
    protected array $channels = [];

    public function register(MfaChannel $channel): void
    {
        $this->channels[strtolower($channel->getName())] = $channel;
    }

    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->channels);
    }

    public function get(string $name): ?MfaChannel
    {
        return $this->channels[strtolower($name)] ?? null;
    }

    /** @return array<string, MfaChannel> */
    public function all(): array
    {
        return $this->channels;
    }

    public function clear(): void
    {
        $this->channels = [];
    }
}

