<?php

use CodingLibs\MFA\MFA;
use CodingLibs\MFA\Contracts\MfaChannel;
use CodingLibs\MFA\Models\MfaChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomChannelTestFakeUser implements Authenticatable
{
    public function __construct(public int|string $id, public ?string $email = null, public ?string $phone = null) {}
    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword() { return ''; }
    public function getAuthPasswordName() { return 'password'; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName() { return 'remember_token'; }
    public function getEmailForVerification() { return $this->email; }
    public function getPhoneNumberForVerification() { return $this->phone; }
}

class CustomEmailChannel implements MfaChannel
{
    public ?string $lastCode = null;
    public ?string $lastTo = null;
    public ?string $lastSubject = null;
    public ?string $lastFromAddress = null;
    public ?string $lastFromName = null;
    public string $channelName;
    
    public function __construct(protected array $config = []) 
    {
        $this->channelName = $config['channel_name'] ?? 'email';
    }
    
    public function getName(): string { return $this->channelName; }
    
    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        $this->lastCode = $code;
        $this->lastTo = method_exists($user, 'getEmailForVerification') ? $user->getEmailForVerification() : ($user->email ?? null);
        $this->lastSubject = $options['subject'] ?? $this->config['subject'] ?? 'Your verification code';
        $this->lastFromAddress = $this->config['from_address'] ?? null;
        $this->lastFromName = $this->config['from_name'] ?? null;
    }
}

class CustomSmsChannel implements MfaChannel
{
    public ?string $lastCode = null;
    public ?string $lastTo = null;
    public ?string $lastMessage = null;
    public ?string $lastDriver = null;
    
    public function __construct(protected array $config = []) {}
    
    public function getName(): string { return 'sms'; }
    
    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        $this->lastCode = $code;
        $this->lastTo = method_exists($user, 'getPhoneNumberForVerification') ? $user->getPhoneNumberForVerification() : ($user->phone ?? null);
        $this->lastMessage = $options['message'] ?? "Your verification code is: {$code}";
        $this->lastDriver = $this->config['driver'] ?? 'log';
    }
}

it('uses custom email channel class from config', function () {
    config([
        'mfa.email.channel' => CustomEmailChannel::class,
        'mfa.email.from_address' => 'custom@example.com',
        'mfa.email.from_name' => 'Custom App',
        'mfa.email.subject' => 'Custom Verification Code'
    ]);

    $user = new CustomChannelTestFakeUser(2001, 'user@example.com');
    $mfa = app(MFA::class);

    $challenge = $mfa->issueChallenge($user, 'email');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);

    // Get the email channel from the registry to check its state
    $emailChannel = $mfa->getChannel('email');
    expect($emailChannel)->toBeInstanceOf(CustomEmailChannel::class);
    expect($emailChannel->lastCode)->toBe($challenge->code);
    expect($emailChannel->lastTo)->toBe('user@example.com');
    expect($emailChannel->lastSubject)->toBe('Custom Verification Code');
    expect($emailChannel->lastFromAddress)->toBe('custom@example.com');
    expect($emailChannel->lastFromName)->toBe('Custom App');
});

it('uses custom sms channel class from config', function () {
    config([
        'mfa.sms.channel' => CustomSmsChannel::class,
        'mfa.sms.driver' => 'custom',
        'mfa.sms.from' => '+1234567890'
    ]);

    $user = new CustomChannelTestFakeUser(2002, null, '+1234567890');
    $mfa = app(MFA::class);

    $challenge = $mfa->issueChallenge($user, 'sms');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);

    // Get the sms channel from the registry to check its state
    $smsChannel = $mfa->getChannel('sms');
    expect($smsChannel)->toBeInstanceOf(CustomSmsChannel::class);
    expect($smsChannel->lastCode)->toBe($challenge->code);
    expect($smsChannel->lastTo)->toBe('+1234567890');
    expect($smsChannel->lastDriver)->toBe('custom');
});

it('registers custom channel from config using registerChannelFromConfig', function () {
    $user = new CustomChannelTestFakeUser(2003, 'user@example.com');
    $mfa = app(MFA::class);

    $customConfig = [
        'channel' => CustomEmailChannel::class,
        'channel_name' => 'custom_email',
        'from_address' => 'test@example.com',
        'from_name' => 'Test App',
        'subject' => 'Test Verification'
    ];

    $mfa->registerChannelFromConfig('custom_email', $customConfig);

    $challenge = $mfa->issueChallenge($user, 'custom_email');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);

    $customChannel = $mfa->getChannel('custom_email');
    expect($customChannel)->toBeInstanceOf(CustomEmailChannel::class);
    expect($customChannel->lastCode)->toBe($challenge->code);
    expect($customChannel->lastTo)->toBe('user@example.com');
    expect($customChannel->lastSubject)->toBe('Test Verification');
});

it('throws exception for non-existent channel class', function () {
    config([
        'mfa.email.channel' => 'NonExistentChannelClass'
    ]);

    expect(fn() => app(MFA::class))->toThrow(InvalidArgumentException::class, "Channel class 'NonExistentChannelClass' does not exist for type 'email'");
});

it('throws exception for channel class that does not implement MfaChannel', function () {
    config([
        'mfa.email.channel' => stdClass::class
    ]);

    expect(fn() => app(MFA::class))->toThrow(InvalidArgumentException::class, "Channel class 'stdClass' must implement " . MfaChannel::class);
});

it('falls back to default channel class when channel is not specified', function () {
    config([
        'mfa.email' => [
            'enabled' => true,
            'from_address' => 'default@example.com',
            'from_name' => 'Default App',
            'subject' => 'Default Verification Code'
            // No channel specified
        ]
    ]);

    $user = new CustomChannelTestFakeUser(2004, 'user@example.com');
    $mfa = app(MFA::class);

    $challenge = $mfa->issueChallenge($user, 'email');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);

    // Should use the default EmailChannel class
    $emailChannel = $mfa->getChannel('email');
    expect($emailChannel)->toBeInstanceOf(\CodingLibs\MFA\Channels\EmailChannel::class);
});
