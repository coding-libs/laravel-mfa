# mfa
Multi Factor Authentication
CodingLibs Laravel MFA

 

Installation
- Install via Composer from Packagist:
```
composer require coding-libs/laravel-mfa
```
- The service provider auto-registers. Publish config and migrations:
```
php artisan vendor:publish --tag=mfa-config
php artisan vendor:publish --tag=mfa-migrations
php artisan migrate
```

 

Features
- **Email** and **SMS** one-time code challenges with pluggable channels
- **Configurable channel classes** - extend Email and SMS channels via configuration
- **Challenge generation without sending** - generate codes without automatic delivery
- Google Authenticator compatible **TOTP** (RFC 6238) setup and verification
- Built-in QR code generation to display TOTP provisioning URI (uses bacon/bacon-qr-code)
- Remember device support via secure, hashed tokens stored in `mfa_remembered_devices`
- Recovery Codes: generate, verify, and manage one-time backup codes
- Simple API via `MFA` facade/service for issuing and verifying codes
- Publishable config and migrations; encrypted storage of TOTP secret
- Extendable channel system to add providers like WhatsApp, Twilio, etc.

MFA Channels
- **Email**: delivers a one-time code via Laravel Mail
- **SMS**: delivers a one-time code via the configured SMS driver (defaults to `log`)
- **TOTP**: time-based one-time password compatible with Google Authenticator and similar apps

Compatibility
- Laravel 11 and 12
- PHP >= 8.2


Usage
```php
use CodingLibs\MFA\Facades\MFA;

// Email/SMS - Generate and send automatically
$challenge = MFA::issueChallenge(auth()->user(), 'email');
// then later
$ok = MFA::verifyChallenge(auth()->user(), 'email', '123456');

// Generate challenge without sending
$challenge = MFA::generateChallenge(auth()->user(), 'email');
// or
$challenge = MFA::issueChallenge(auth()->user(), 'email', false);
// Now handle sending manually

// TOTP
$setup = MFA::setupTotp(auth()->user());
// $setup['otpauth_url'] -> QR code; then verify
$ok = MFA::verifyTotp(auth()->user(), '123456');

// Generate QR code (base64 PNG) from existing TOTP (uses bacon/bacon-qr-code)
$base64 = MFA::generateTotpQrCodeBase64(auth()->user(), issuer: 'MyApp');
// <img src="$base64" />

// Remember device (set cookie on successful MFA)
[$token, $cookie] = [null, null];
$result = MFA::rememberDevice(auth()->user(), lifetimeDays: 30, deviceName: 'My Laptop');
$token = $result['token'];
$cookie = $result['cookie']; // Symfony Cookie instance — attach to response

// Later, skip MFA if remembered device cookie is valid
$shouldSkip = MFA::shouldSkipVerification(auth()->user(), MFA::getRememberTokenFromRequest(request()));

// Recovery Codes
// Generate a fresh set (returns plaintext codes to show once)
$codes = MFA::generateRecoveryCodes(auth()->user());
// Verify and consume a recovery code
$ok = MFA::verifyRecoveryCode(auth()->user(), $inputCode);
// Count remaining unused codes
$remaining = MFA::getRemainingRecoveryCodesCount(auth()->user());
// Clear all codes
$deleted = MFA::clearRecoveryCodes(auth()->user());
```

Remember Devices (Optional)
- Enable or configure in `config/mfa.php` under `remember` (or via env: see below)
- On successful MFA, call `MFA::rememberDevice(...)` and attach the returned cookie to the response
- On subsequent requests, use `MFA::shouldSkipVerification($user, MFA::getRememberTokenFromRequest($request))`
- To revoke a remembered device, call `MFA::forgetRememberedDevice($user, $token)`

Recovery Codes
- What they are: single‑use backup codes that let users complete MFA when they cannot access their primary factor (e.g., lost phone or no network).
- Storage and security:
  - Plaintext codes are returned only once at generation time; only their hashes are stored in `mfa_recovery_codes`.
  - Hashing algorithm is configurable via `mfa.recovery.hash_algo` (default `sha256`).
  - Codes are marked as used at first successful verification and cannot be reused.
- Generating and displaying to the user:
```php
// Generate N codes (defaults come from config)
$codes = MFA::generateRecoveryCodes($user); // array of plaintext codes

// Show these codes once to the user and prompt them to store securely
// e.g., render as a list and offer a download/print option
```
- Verifying a code and optional regeneration-on-use:
```php
if (MFA::verifyRecoveryCode($user, $input)) {
    // Success: log user in and consider rotating codes if desired
}
```
- Pool size maintenance: set `mfa.recovery.regenerate_on_use = true` to automatically replace a consumed code with a new one so the remaining count stays steady.
- Managing codes:
```php
// Count remaining unused codes
$remaining = MFA::getRemainingRecoveryCodesCount($user);

// Replace all existing codes with a new set
$fresh = MFA::generateRecoveryCodes($user); // replaceExisting=true by default

// Append without deleting existing codes
$extra = MFA::generateRecoveryCodes($user, count: 2, replaceExisting: false);

// Clear all codes
$deleted = MFA::clearRecoveryCodes($user);
```
- UX recommendations:
  - Require the user to confirm they’ve saved the codes before leaving the setup screen.
  - Offer copy, download (txt), and print actions. Avoid storing plaintext on your servers.
  - Warn that each code is one-time and will be invalid after use.

Configuration
- See `config/mfa.php` for all options. Key settings:
  - **code_length**: OTP digits for email/sms (default 6)
  - **code_ttl_seconds**: Challenge expiry (default 300s)
  - **email**:
    - enabled (bool)
    - from_address, from_name, subject
    - channel: custom channel class (default: EmailChannel)
  - **sms**:
    - enabled (bool)
    - driver: `log` (default) or custom integration
    - from: optional sender id/number
    - channel: custom channel class (default: SmsChannel)
  - **totp**:
    - issuer: defaults to `config('app.name')`
    - digits: 6 by default
    - period: 30s by default
    - window: 1 slice tolerance by default
  - **remember**:
    - enabled (bool, default true)
    - cookie: cookie name (default `mfa_rd`)
    - lifetime_days: validity window (default 30)
    - path, domain, secure, http_only, same_site
  - **recovery**:
    - enabled (bool, default true)
    - codes_count: number of codes to generate (default 10)
    - code_length: length of each code (default 10)
    - regenerate_on_use: whether to auto-regenerate when consumed (default false)
    - hash_algo: hashing algorithm for stored codes (default `sha256`)

Environment variables (examples)
```
MFA_EMAIL_ENABLED=true
MFA_EMAIL_FROM_ADDRESS="no-reply@example.com"
MFA_EMAIL_FROM_NAME="Example App"
MFA_EMAIL_SUBJECT="Your verification code"
MFA_EMAIL_CHANNEL="App\Channels\CustomEmailChannel"

MFA_SMS_ENABLED=true
MFA_SMS_DRIVER=log
MFA_SMS_FROM="ExampleApp"
MFA_SMS_CHANNEL="App\Channels\CustomSmsChannel"

MFA_TOTP_ISSUER="Example App"
MFA_TOTP_DIGITS=6
MFA_TOTP_PERIOD=30
MFA_TOTP_WINDOW=1

MFA_REMEMBER_ENABLED=true
MFA_REMEMBER_COOKIE=mfa_rd
MFA_REMEMBER_LIFETIME_DAYS=30
MFA_REMEMBER_PATH=/
MFA_REMEMBER_DOMAIN=
MFA_REMEMBER_SECURE=null
MFA_REMEMBER_HTTP_ONLY=true
MFA_REMEMBER_SAME_SITE=lax

MFA_RECOVERY_ENABLED=true
MFA_RECOVERY_CODES_COUNT=10
MFA_RECOVERY_CODE_LENGTH=10
MFA_RECOVERY_REGENERATE_ON_USE=false
MFA_RECOVERY_HASH_ALGO=sha256
```

Database
- Publishing migrations creates tables:
  - `mfa_methods`: tracks enabled MFA methods per user; stores encrypted TOTP `secret`
  - `mfa_challenges`: stores pending OTP codes for email/sms with expiry and consumed_at
  - `mfa_remembered_devices`: stores hashed tokens for device recognition with IP, UA, and expiry
  - `mfa_recovery_codes`: stores hashed recovery codes and usage timestamp

API Overview (Facade `MFA`)
- **issueChallenge(Authenticatable $user, string $method, bool $send = true): ?MfaChallenge**
- **generateChallenge(Authenticatable $user, string $method): ?MfaChallenge** - Generate without sending
- **verifyChallenge(Authenticatable $user, string $method, string $code): bool**
- **setupTotp(Authenticatable $user, ?string $issuer = null, ?string $label = null): array** returns `['secret','otpauth_url']`
- **verifyTotp(Authenticatable $user, string $code): bool**
- **generateTotpQrCodeBase64(Authenticatable $user, ?string $issuer = null, ?string $label = null, int $size = 200): ?string**
- **isEnabled(Authenticatable $user, string $method): bool**
- **enableMethod(Authenticatable $user, string $method, array $attributes = []): MfaMethod**
- **disableMethod(Authenticatable $user, string $method): bool**
- Remember device helpers:
  - **isRememberEnabled(): bool**
  - **rememberDevice(Authenticatable $user, ?int $lifetimeDays = null, ?string $deviceName = null): array** returns `['token','cookie']`
  - **getRememberCookieName(): string**
  - **getRememberTokenFromRequest(Request $request): ?string**
  - **shouldSkipVerification(Authenticatable $user, ?string $token): bool**
  - **makeRememberCookie(string $token, ?int $lifetimeDays = null): Cookie**
  - **forgetRememberedDevice(Authenticatable $user, string $token): int**
  - Recovery codes:
    - **generateRecoveryCodes(Authenticatable $user, ?int $count = null, ?int $length = null, bool $replaceExisting = true): array** returns plaintext codes
    - **verifyRecoveryCode(Authenticatable $user, string $code): bool**
    - **getRemainingRecoveryCodesCount(Authenticatable $user): int**
    - **clearRecoveryCodes(Authenticatable $user): int**

## Custom Channel Classes

### Configuration-Based Custom Channels

You can extend the built-in Email and SMS channels by configuring custom channel classes:

```php
// config/mfa.php
'email' => [
    'enabled' => true,
    'channel' => \App\Channels\CustomEmailChannel::class,
    'from_address' => 'noreply@example.com',
    // ... other config
],

'sms' => [
    'enabled' => true,
    'channel' => \App\Channels\CustomSmsChannel::class,
    'driver' => 'custom',
    // ... other config
],
```

```php
// app/Channels/CustomEmailChannel.php
use CodingLibs\MFA\Channels\EmailChannel;

class CustomEmailChannel extends EmailChannel
{
    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        // Custom sending logic
        Mail::to($user->email)->send(new CustomMfaMail($code, $this->config));
    }
}
```

### Programmatic Channel Registration

```php
// In a service provider
MFA::registerChannelFromConfig('custom_channel', [
    'channel' => CustomChannel::class,
    'channel_name' => 'custom_channel',
    'custom_setting' => 'value'
]);
```

## Challenge Generation Without Sending

Generate challenge codes without automatic delivery:

```php
// Generate challenge without sending
$challenge = MFA::generateChallenge(auth()->user(), 'email');
echo $challenge->code; // Use the code as needed

// Or use issueChallenge with send=false
$challenge = MFA::issueChallenge(auth()->user(), 'email', false);

// Manual sending
$channel = MFA::getChannel('email');
$channel->send(auth()->user(), $challenge->code, ['subject' => 'Custom Subject']);
```

## Creating a Custom MFA Channel

Steps
1. Implement `CodingLibs\MFA\Contracts\MfaChannel` with a unique `getName()` and a `send(...)` method
2. Register your channel during app boot (e.g., in a service provider) via `MFA::registerChannel(...)`
3. Issue a challenge using the new channel name: `MFA::issueChallenge($user, 'your-channel')`
```php
use CodingLibs\MFA\Contracts\MfaChannel;
use CodingLibs\MFA\Facades\MFA;
use Illuminate\Contracts\Auth\Authenticatable;

class WhatsAppChannel implements MfaChannel {
    public function __construct(private array $config = []) {}
    public function getName(): string { return 'whatsapp'; }
    public function send(Authenticatable $user, string $code, array $options = []): void {
        // send via provider...
    }
}

// register at boot
MFA::registerChannel(new WhatsAppChannel(config('mfa.whatsapp', [])));

// then issue
MFA::issueChallenge(auth()->user(), 'whatsapp');
```

Notes
- SMS driver defaults to `log`. Integrate your provider by implementing a custom channel
  or enhancing `SmsChannel` in your app via service container bindings.
- TOTP `secret` is stored encrypted by default via Eloquent cast.
- QR code generation requires either Imagick or GD PHP extensions. If neither is available,
  generation will throw a runtime exception.