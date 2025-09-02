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
- Google Authenticator compatible **TOTP** (RFC 6238) setup and verification
- Built-in QR code generation to display TOTP provisioning URI (uses bacon/bacon-qr-code)
- Remember device support via secure, hashed tokens stored in `mfa_remembered_devices`
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

// Email/SMS
$challenge = MFA::issueChallenge(auth()->user(), 'email');
// then later
$ok = MFA::verifyChallenge(auth()->user(), 'email', '123456');

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
```

Remember Devices (Optional)
- Enable or configure in `config/mfa.php` under `remember` (or via env: see below)
- On successful MFA, call `MFA::rememberDevice(...)` and attach the returned cookie to the response
- On subsequent requests, use `MFA::shouldSkipVerification($user, MFA::getRememberTokenFromRequest($request))`
- To revoke a remembered device, call `MFA::forgetRememberedDevice($user, $token)`

Configuration
- See `config/mfa.php` for all options. Key settings:
  - **code_length**: OTP digits for email/sms (default 6)
  - **code_ttl_seconds**: Challenge expiry (default 300s)
  - **email**:
    - enabled (bool)
    - from_address, from_name, subject
  - **sms**:
    - enabled (bool)
    - driver: `log` (default) or custom integration
    - from: optional sender id/number
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

Environment variables (examples)
```
MFA_EMAIL_FROM_ADDRESS="no-reply@example.com"
MFA_EMAIL_FROM_NAME="Example App"
MFA_EMAIL_SUBJECT="Your verification code"

MFA_SMS_DRIVER=log
MFA_SMS_FROM="ExampleApp"

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
```

Database
- Publishing migrations creates tables:
  - `mfa_methods`: tracks enabled MFA methods per user; stores encrypted TOTP `secret`
  - `mfa_challenges`: stores pending OTP codes for email/sms with expiry and consumed_at
  - `mfa_remembered_devices`: stores hashed tokens for device recognition with IP, UA, and expiry

API Overview (Facade `MFA`)
- **issueChallenge(Authenticatable $user, string $method): ?MfaChallenge**
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

Creating a Custom MFA Channel
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