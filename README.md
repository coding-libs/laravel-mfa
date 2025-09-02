# mfa
Multi Factor Authentication
CodingLibs Laravel MFA

Installation
- Require in your Laravel 12 app composer.json or via path repository.
- The service provider auto-registers. Publish config and migrations:
```
php artisan vendor:publish --tag=mfa-config
php artisan vendor:publish --tag=mfa-migrations
php artisan migrate
```

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
```

Configuration
- See `config/mfa.php` for email/sms/totp options.

Extending: Custom Channels
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