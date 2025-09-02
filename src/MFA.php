<?php

namespace CodingLibs\MFA;

use CodingLibs\MFA\Channels\EmailChannel;
use CodingLibs\MFA\Channels\SmsChannel;
use CodingLibs\MFA\Models\MfaChallenge;
use CodingLibs\MFA\Models\MfaMethod;
use CodingLibs\MFA\Totp\GoogleTotp;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MFA
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function setupTotp(Authenticatable $user, ?string $issuer = null, ?string $label = null): array
    {
        $secret = GoogleTotp::generateSecret();
        $issuer = $issuer ?: Arr::get($this->config, 'totp.issuer', 'Laravel');
        $label = $label ?: method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? (string) $user->getAuthIdentifier());
        $otpauth = GoogleTotp::buildOtpAuthUrl($secret, $label, $issuer, Arr::get($this->config, 'totp.digits', 6));

        $this->enableMethod($user, 'totp', ['secret' => $secret]);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauth,
        ];
    }

    public function verifyTotp(Authenticatable $user, string $code): bool
    {
        $method = $this->getMethod($user, 'totp');
        if (! $method || ! $method->secret) {
            return false;
        }

        $digits = Arr::get($this->config, 'totp.digits', 6);
        $period = Arr::get($this->config, 'totp.period', 30);
        $window = Arr::get($this->config, 'totp.window', 1);

        $verified = GoogleTotp::verifyCode($method->secret, $code, $digits, $period, $window);
        if ($verified) {
            $method->last_used_at = Carbon::now();
            $method->save();
        }
        return $verified;
    }

    public function issueChallenge(Authenticatable $user, string $method): ?MfaChallenge
    {
        $method = strtolower($method);
        if (! in_array($method, ['email', 'sms'], true)) {
            return null;
        }

        $codeLength = Arr::get($this->config, 'code_length', 6);
        $ttlSeconds = Arr::get($this->config, 'code_ttl_seconds', 300);
        $code = str_pad((string) random_int(0, (10 ** $codeLength) - 1), $codeLength, '0', STR_PAD_LEFT);

        $challenge = new MfaChallenge();
        $challenge->user_type = get_class($user);
        $challenge->user_id = $user->getAuthIdentifier();
        $challenge->method = $method;
        $challenge->code = $code;
        $challenge->expires_at = Carbon::now()->addSeconds($ttlSeconds);
        $challenge->save();

        if ($method === 'email') {
            (new EmailChannel($this->config['email'] ?? []))->send($user, $code);
        } else if ($method === 'sms') {
            (new SmsChannel($this->config['sms'] ?? []))->send($user, $code);
        }

        return $challenge;
    }

    public function verifyChallenge(Authenticatable $user, string $method, string $code): bool
    {
        $now = Carbon::now();

        $challenge = MfaChallenge::query()
            ->where('user_type', get_class($user))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('method', strtolower($method))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->latest('id')
            ->first();

        if (! $challenge) {
            return false;
        }

        if (hash_equals($challenge->code, $code)) {
            $challenge->consumed_at = $now;
            $challenge->save();

            $this->enableMethod($user, $challenge->method);

            return true;
        }

        return false;
    }

    public function enableMethod(Authenticatable $user, string $method, array $attributes = []): MfaMethod
    {
        $record = $this->getMethod($user, $method);
        if (! $record) {
            $record = new MfaMethod();
            $record->user_type = get_class($user);
            $record->user_id = $user->getAuthIdentifier();
            $record->method = strtolower($method);
        }

        foreach ($attributes as $key => $value) {
            $record->setAttribute($key, $value);
        }

        $record->enabled_at = Carbon::now();
        $record->save();

        return $record;
    }

    public function disableMethod(Authenticatable $user, string $method): bool
    {
        $record = $this->getMethod($user, $method);
        if (! $record) {
            return false;
        }
        $record->enabled_at = null;
        return $record->save();
    }

    public function isEnabled(Authenticatable $user, string $method): bool
    {
        $record = $this->getMethod($user, $method);
        return (bool) ($record && $record->enabled_at);
    }

    public function getMethod(Authenticatable $user, string $method): ?MfaMethod
    {
        return MfaMethod::query()
            ->where('user_type', get_class($user))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('method', strtolower($method))
            ->first();
    }
}

