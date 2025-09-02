<?php

namespace CodingLibs\MFA\Channels;

use CodingLibs\MFA\Contracts\MfaChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

class SmsChannel implements MfaChannel
{
    public function __construct(protected array $config = [])
    {
    }

    public function getName(): string
    {
        return 'sms';
    }

    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        if (! ($this->config['enabled'] ?? true)) {
            return;
        }

        $driver = $this->config['driver'] ?? 'log';
        $to = method_exists($user, 'getPhoneNumberForVerification') ? $user->getPhoneNumberForVerification() : ($user->phone ?? null);
        if (! $to) {
            return;
        }

        $message = $options['message'] ?? "Your verification code is: {$code}";

        if ($driver === 'log') {
            Log::info('MFA SMS', ['to' => $to, 'message' => $message]);
            return;
        }

        // Placeholder: extendable for twilio/aws pinoint/etc via events or bindings.
        Log::warning('MFA SMS driver not implemented', ['driver' => $driver, 'to' => $to]);
    }
}

