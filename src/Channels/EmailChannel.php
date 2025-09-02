<?php

namespace CodingLibs\MFA\Channels;

use CodingLibs\MFA\Contracts\MfaChannel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;

class EmailChannel implements MfaChannel
{
    public function __construct(protected array $config = [])
    {
    }

    public function getName(): string
    {
        return 'email';
    }

    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        if (! ($this->config['enabled'] ?? true)) {
            return;
        }

        $to = method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? null);
        if (! $to) {
            return;
        }

        $fromAddress = $this->config['from_address'] ?? null;
        $fromName = $this->config['from_name'] ?? null;
        $subject = $this->config['subject'] ?? 'Your verification code';
        if (isset($options['subject'])) {
            $subject = $options['subject'];
        }

        Mail::raw("Your verification code is: {$code}", function ($message) use ($to, $fromAddress, $fromName, $subject) {
            $message->to($to)->subject($subject);
            if ($fromAddress) {
                $message->from($fromAddress, $fromName);
            }
        });
    }
}

