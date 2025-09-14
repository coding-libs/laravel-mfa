<?php

namespace CodingLibs\MFA;

use CodingLibs\MFA\Channels\EmailChannel;
use CodingLibs\MFA\Channels\SmsChannel;
use CodingLibs\MFA\ChannelRegistry;
use CodingLibs\MFA\Contracts\MfaChannel;
use CodingLibs\MFA\Support\QrCodeGenerator;
use CodingLibs\MFA\Models\MfaChallenge;
use CodingLibs\MFA\Models\MfaMethod;
use CodingLibs\MFA\Models\MfaRememberedDevice;
use CodingLibs\MFA\Models\MfaRecoveryCode;
use CodingLibs\MFA\Totp\GoogleTotp;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Cookie;

class MFA
{
    protected array $config;
    protected ChannelRegistry $registry;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->registry = new ChannelRegistry();
        $this->registerDefaultChannels();
    }

    protected function registerDefaultChannels(): void
    {
        // Register email channel if enabled
        if (Arr::get($this->config, 'email.enabled', true)) {
            $this->registry->register($this->createChannel('email', $this->config['email'] ?? []));
        }
        
        // Register SMS channel if enabled
        if (Arr::get($this->config, 'sms.enabled', true)) {
            $this->registry->register($this->createChannel('sms', $this->config['sms'] ?? []));
        }
    }

    protected function createChannel(string $type, array $config): MfaChannel
    {
        $channelClass = $config['channel'] ?? $this->getDefaultChannelClass($type);
        
        if (!class_exists($channelClass)) {
            throw new \InvalidArgumentException("Channel class '{$channelClass}' does not exist for type '{$type}'");
        }

        if (!is_subclass_of($channelClass, MfaChannel::class)) {
            throw new \InvalidArgumentException("Channel class '{$channelClass}' must implement " . MfaChannel::class);
        }

        return new $channelClass($config);
    }

    protected function getDefaultChannelClass(string $type): string
    {
        return match ($type) {
            'email' => EmailChannel::class,
            'sms' => SmsChannel::class,
            default => throw new \InvalidArgumentException("Unknown channel type: {$type}")
        };
    }

    public function setupTotp(Authenticatable $user, ?string $issuer = null, ?string $label = null): array
    {
        $secret = GoogleTotp::generateSecret();
        $issuer = $issuer ?: Arr::get($this->config, 'totp.issuer', 'Laravel');
        $label = $label ?: (method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? (string) $user->getAuthIdentifier()));
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

    public function issueChallenge(Authenticatable $user, string $method, bool $send = true): ?MfaChallenge
    {
        $method = strtolower($method);
        $channel = $this->registry->get($method);
        if (! $channel) {
            return null;
        }

        $challenge = $this->generateChallenge($user, $method);
        if (! $challenge) {
            return null;
        }

        if ($send) {
            $channel->send($user, $challenge->code);
        }

        return $challenge;
    }

    public function generateChallenge(Authenticatable $user, string $method): ?MfaChallenge
    {
        $method = strtolower($method);
        $channel = $this->registry->get($method);
        if (! $channel) {
            return null;
        }

        $codeLength = Arr::get($this->config, 'code_length', 6);
        $ttlSeconds = Arr::get($this->config, 'code_ttl_seconds', 300);
        $code = str_pad((string) random_int(0, (10 ** $codeLength) - 1), $codeLength, '0', STR_PAD_LEFT);

        $challenge = new MfaChallenge();
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        $challenge->setAttribute($typeColumn, get_class($user));
        $challenge->setAttribute($idColumn, $user->getAuthIdentifier());
        $challenge->method = $method;
        $challenge->code = $code;
        $challenge->expires_at = Carbon::now()->addSeconds($ttlSeconds);
        $challenge->save();

        return $challenge;
    }

    public function registerChannel(MfaChannel $channel): void
    {
        $this->registry->register($channel);
    }

    public function registerChannelFromConfig(string $type, array $config): void
    {
        $channel = $this->createChannelFromConfig($config);
        $this->registry->register($channel);
    }

    public function reRegisterDefaultChannels(): void
    {
        $this->registry->clear();
        $this->registerDefaultChannels();
    }

    protected function createChannelFromConfig(array $config): MfaChannel
    {
        if (empty($config)) {
            throw new \InvalidArgumentException('Channel config cannot be empty');
        }

        $channelClass = $config['channel'] ?? throw new \InvalidArgumentException('channel must be specified in config');
        
        if (!is_string($channelClass)) {
            throw new \InvalidArgumentException('channel must be a string class name');
        }

        if (!class_exists($channelClass)) {
            throw new \InvalidArgumentException("Channel class '{$channelClass}' does not exist");
        }

        if (!is_subclass_of($channelClass, MfaChannel::class)) {
            throw new \InvalidArgumentException("Channel class '{$channelClass}' must implement " . MfaChannel::class);
        }

        return new $channelClass($config);
    }

    public function getChannel(string $name): ?MfaChannel
    {
        return $this->registry->get($name);
    }

    public function generateTotpQrCodeBase64(Authenticatable $user, ?string $issuer = null, ?string $label = null, int $size = 200): ?string
    {
        $method = $this->getMethod($user, 'totp');
        if (! $method || ! $method->secret) {
            return null;
        }

        $issuer = $issuer ?: Arr::get($this->config, 'totp.issuer', 'Laravel');
        $label = $label ?: (method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? (string) $user->getAuthIdentifier()));
        $digits = Arr::get($this->config, 'totp.digits', 6);
        $period = Arr::get($this->config, 'totp.period', 30);

        $otpauth = GoogleTotp::buildOtpAuthUrl($method->secret, $label, $issuer, $digits, $period);
        return QrCodeGenerator::generateBase64Png($otpauth, $size);
    }

    public function generateTotpQrCodeSvg(Authenticatable $user, ?string $issuer = null, ?string $label = null, int $size = 200): ?string
    {
        $method = $this->getMethod($user, 'totp');
        if (! $method || ! $method->secret) {
            return null;
        }

        $issuer = $issuer ?: Arr::get($this->config, 'totp.issuer', 'Laravel');
        $label = $label ?: (method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? (string) $user->getAuthIdentifier()));
        $digits = Arr::get($this->config, 'totp.digits', 6);
        $period = Arr::get($this->config, 'totp.period', 30);

        $otpauth = GoogleTotp::buildOtpAuthUrl($method->secret, $label, $issuer, $digits, $period);
        return QrCodeGenerator::generateSvg($otpauth, $size);
    }

    public function generateTotpQrCodeBase64Svg(Authenticatable $user, ?string $issuer = null, ?string $label = null, int $size = 200): ?string
    {
        $method = $this->getMethod($user, 'totp');
        if (! $method || ! $method->secret) {
            return null;
        }

        $issuer = $issuer ?: Arr::get($this->config, 'totp.issuer', 'Laravel');
        $label = $label ?: (method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? (string) $user->getAuthIdentifier()));
        $digits = Arr::get($this->config, 'totp.digits', 6);
        $period = Arr::get($this->config, 'totp.period', 30);

        $otpauth = GoogleTotp::buildOtpAuthUrl($method->secret, $label, $issuer, $digits, $period);
        return QrCodeGenerator::generateBase64Svg($otpauth, $size);
    }

    public function verifyChallenge(Authenticatable $user, string $method, string $code): bool
    {
        $now = Carbon::now();

        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        $challenge = MfaChallenge::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
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

    public function isRememberEnabled(): bool
    {
        return (bool) Arr::get($this->config, 'remember.enabled', false);
    }

    public function getRememberCookieName(): string
    {
        return Arr::get($this->config, 'remember.cookie', 'mfa_rd');
    }

    public function getRememberTokenFromRequest(Request $request): ?string
    {
        $name = $this->getRememberCookieName();
        $value = $request->cookies->get($name);
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function shouldSkipVerification(Authenticatable $user, ?string $token): bool
    {
        if (! $this->isRememberEnabled() || ! $token) {
            return false;
        }

        $hash = hash('sha256', $token);
        $now = Carbon::now();
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        $record = MfaRememberedDevice::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->where('token_hash', $hash)
            ->where('expires_at', '>', $now)
            ->first();

        if (! $record) {
            return false;
        }

        $record->last_used_at = $now;
        // Optional: update the stored user agent and IP when we see the device again
        $request = app('request');
        if ($request instanceof \Illuminate\Http\Request) {
            $record->user_agent = (string) $request->userAgent();
            $record->ip_address = (string) $request->ip();
        }
        $record->save();

        return true;
    }

    public function rememberDevice(Authenticatable $user, ?int $lifetimeDays = null, ?string $deviceName = null): array
    {
        if (! $this->isRememberEnabled()) {
            return ['token' => null, 'cookie' => null];
        }

        $days = $lifetimeDays ?? (int) Arr::get($this->config, 'remember.lifetime_days', 30);
        $expiresAt = Carbon::now()->addDays($days);

        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);

        $record = new MfaRememberedDevice();
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        $record->setAttribute($typeColumn, get_class($user));
        $record->setAttribute($idColumn, $user->getAuthIdentifier());
        $record->token_hash = $hash;
        $record->device_name = $deviceName;
        $request = app('request');
        if ($request instanceof \Illuminate\Http\Request) {
            $record->user_agent = (string) $request->userAgent();
            $record->ip_address = (string) $request->ip();
        }
        $record->expires_at = $expiresAt;
        $record->last_used_at = Carbon::now();
        $record->save();

        $cookie = $this->makeRememberCookie($plainToken, $days);

        return ['token' => $plainToken, 'cookie' => $cookie];
    }

    public function makeRememberCookie(string $token, ?int $lifetimeDays = null): Cookie
    {
        $days = $lifetimeDays ?? (int) Arr::get($this->config, 'remember.lifetime_days', 30);
        $name = $this->getRememberCookieName();
        $path = Arr::get($this->config, 'remember.path', '/');
        $domain = Arr::get($this->config, 'remember.domain');
        $secureConfig = Arr::get($this->config, 'remember.secure');
        $secure = $secureConfig === null ? app('request')->isSecure() : (bool) $secureConfig;
        $httpOnly = (bool) Arr::get($this->config, 'remember.http_only', true);
        $sameSite = Arr::get($this->config, 'remember.same_site', 'lax');

        $expires = Carbon::now()->addDays($days);

        return new Cookie($name, $token, $expires, $path, $domain, $secure, $httpOnly, false, $sameSite);
    }

    public function forgetRememberedDevice(Authenticatable $user, string $token): int
    {
        $hash = hash('sha256', $token);
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        return MfaRememberedDevice::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->where('token_hash', $hash)
            ->delete();
    }

    public function enableMethod(Authenticatable $user, string $method, array $attributes = []): MfaMethod
    {
        $record = $this->getMethod($user, $method);
        if (! $record) {
            $record = new MfaMethod();
            $morph = $this->config['morph'] ?? [];
            $morphName = $morph['name'] ?? 'model';
            $typeColumn = $morphName . '_type';
            $idColumn = $morphName . '_id';
            $record->setAttribute($typeColumn, get_class($user));
            $record->setAttribute($idColumn, $user->getAuthIdentifier());
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
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        return MfaMethod::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->where('method', strtolower($method))
            ->first();
    }

    /**
     * Generate and persist recovery codes for the given user.
     * Returns the plaintext codes; hashes are stored in DB.
     */
    public function generateRecoveryCodes(Authenticatable $user, ?int $count = null, ?int $length = null, bool $replaceExisting = true): array
    {
        $count = $count ?? (int) Arr::get($this->config, 'recovery.codes_count', 10);
        $length = $length ?? (int) Arr::get($this->config, 'recovery.code_length', 10);

        if ($replaceExisting) {
            $morph = $this->config['morph'] ?? [];
            $morphName = $morph['name'] ?? 'model';
            $typeColumn = $morphName . '_type';
            $idColumn = $morphName . '_id';
            MfaRecoveryCode::query()
                ->where($typeColumn, get_class($user))
                ->where($idColumn, $user->getAuthIdentifier())
                ->delete();
        }

        $plaintextCodes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = $this->generateReadableCode($length);
            $hash = $this->hashRecoveryCode($code);

            $record = new MfaRecoveryCode();
            $morph = $this->config['morph'] ?? [];
            $morphName = $morph['name'] ?? 'model';
            $typeColumn = $morphName . '_type';
            $idColumn = $morphName . '_id';
            $record->setAttribute($typeColumn, get_class($user));
            $record->setAttribute($idColumn, $user->getAuthIdentifier());
            $record->code_hash = $hash;
            $record->used_at = null;
            $record->save();

            $plaintextCodes[] = $code;
        }

        return $plaintextCodes;
    }

    /** Verify and consume a recovery code for the user. */
    public function verifyRecoveryCode(Authenticatable $user, string $code): bool
    {
        $hash = $this->hashRecoveryCode($code);
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        $record = MfaRecoveryCode::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->whereNull('used_at')
            ->where('code_hash', $hash)
            ->first();

        if (! $record) {
            return false;
        }

        $record->used_at = Carbon::now();
        $record->save();

        if ((bool) Arr::get($this->config, 'recovery.regenerate_on_use', false)) {
            $length = (int) Arr::get($this->config, 'recovery.code_length', 10);
            // Generate a single replacement code to keep the pool size steady
            $this->generateRecoveryCodes($user, 1, $length, false);
        }

        return true;
    }

    /** Get remaining (unused) recovery codes count for the user. */
    public function getRemainingRecoveryCodesCount(Authenticatable $user): int
    {
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        return MfaRecoveryCode::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->whereNull('used_at')
            ->count();
    }

    /** Delete all recovery codes for the user. Returns number deleted. */
    public function clearRecoveryCodes(Authenticatable $user): int
    {
        $morph = $this->config['morph'] ?? [];
        $morphName = $morph['name'] ?? 'model';
        $typeColumn = $morphName . '_type';
        $idColumn = $morphName . '_id';
        return MfaRecoveryCode::query()
            ->where($typeColumn, get_class($user))
            ->where($idColumn, $user->getAuthIdentifier())
            ->delete();
    }

    protected function generateReadableCode(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // exclude ambiguous chars
        $maxIndex = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $idx = random_int(0, $maxIndex);
            $code .= $alphabet[$idx];
        }
        return $code;
    }

    protected function hashRecoveryCode(string $code): string
    {
        $algo = (string) Arr::get($this->config, 'recovery.hash_algo', 'sha256');
        return hash($algo, $code);
    }

    /**
     * Get all channels that are enabled both by the client and by configuration.
     * 
     * @param Authenticatable $user The user to check enabled methods for
     * @return array<string, MfaChannel> Array of channel name => channel instance
     */
    public function getEnabledChannels(Authenticatable $user): array
    {
        $enabledChannels = [];
        
        foreach ($this->registry->all() as $name => $channel) {
            // Check if channel is enabled in config
            $configEnabled = $this->isChannelEnabledInConfig($name);
            
            // Check if method is enabled by client
            $clientEnabled = $this->isEnabled($user, $name);
            
            if ($configEnabled && $clientEnabled) {
                $enabledChannels[$name] = $channel;
            }
        }
        
        return $enabledChannels;
    }

    /**
     * Check if a channel is enabled in the configuration.
     * 
     * @param string $channelName The channel name to check
     * @return bool True if the channel is enabled in config
     */
    public function isChannelEnabledInConfig(string $channelName): bool
    {
        $channelName = strtolower($channelName);
        
        // Check if the channel has an 'enabled' property in config
        $enabled = Arr::get($this->config, "{$channelName}.enabled", true);
        
        return (bool) $enabled;
    }
}

