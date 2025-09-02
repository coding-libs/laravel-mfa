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
```

Configuration
- See `config/mfa.php` for email/sms/totp options.