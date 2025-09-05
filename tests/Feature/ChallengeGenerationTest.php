<?php

use CodingLibs\MFA\MFA;
use CodingLibs\MFA\Contracts\MfaChannel;
use CodingLibs\MFA\Models\MfaChallenge;
use Illuminate\Contracts\Auth\Authenticatable;

class ChallengeGenerationTestFakeUser implements Authenticatable
{
    public function __construct(public int|string $id, public ?string $email = null) {}
    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword() { return ''; }
    public function getAuthPasswordName() { return 'password'; }
    public function getRememberToken() { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName() { return 'remember_token'; }
    public function getEmailForVerification() { return $this->email; }
}

class ChallengeGenerationTestChannel implements MfaChannel
{
    public ?string $lastCode = null;
    public int $sendCallCount = 0;
    
    public function __construct(protected array $config = []) {}
    
    public function getName(): string { return 'test_channel'; }
    
    public function send(Authenticatable $user, string $code, array $options = []): void
    {
        $this->lastCode = $code;
        $this->sendCallCount++;
    }
}

it('generates challenge without sending when send=false', function () {
    $user = new ChallengeGenerationTestFakeUser(3001, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    $challenge = $mfa->issueChallenge($user, 'test_channel', false);
    
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);
    expect($challenge->code)->toBeString()->toHaveLength(6);
    expect($challenge->method)->toBe('test_channel');
    expect($challenge->expires_at)->not->toBeNull();
    
    // Channel should not have been called
    expect($channel->sendCallCount)->toBe(0);
    expect($channel->lastCode)->toBeNull();
});

it('generates challenge and sends when send=true (default behavior)', function () {
    $user = new ChallengeGenerationTestFakeUser(3002, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    $challenge = $mfa->issueChallenge($user, 'test_channel', true);
    
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);
    expect($challenge->code)->toBeString()->toHaveLength(6);
    
    // Channel should have been called
    expect($channel->sendCallCount)->toBe(1);
    expect($channel->lastCode)->toBe($challenge->code);
});

it('generates challenge and sends by default (backward compatibility)', function () {
    $user = new ChallengeGenerationTestFakeUser(3003, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    $challenge = $mfa->issueChallenge($user, 'test_channel');
    
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);
    expect($challenge->code)->toBeString()->toHaveLength(6);
    
    // Channel should have been called (default behavior)
    expect($channel->sendCallCount)->toBe(1);
    expect($channel->lastCode)->toBe($challenge->code);
});

it('generateChallenge creates challenge without sending', function () {
    $user = new ChallengeGenerationTestFakeUser(3004, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    $challenge = $mfa->generateChallenge($user, 'test_channel');
    
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);
    expect($challenge->code)->toBeString()->toHaveLength(6);
    expect($challenge->method)->toBe('test_channel');
    expect($challenge->expires_at)->not->toBeNull();
    
    // Channel should never be called with generateChallenge
    expect($channel->sendCallCount)->toBe(0);
    expect($channel->lastCode)->toBeNull();
});

it('generateChallenge returns null for unknown channel', function () {
    $user = new ChallengeGenerationTestFakeUser(3005, 'user@example.com');
    $mfa = app(MFA::class);

    $challenge = $mfa->generateChallenge($user, 'unknown_channel');
    
    expect($challenge)->toBeNull();
});

it('issueChallenge with send=false returns null for unknown channel', function () {
    $user = new ChallengeGenerationTestFakeUser(3006, 'user@example.com');
    $mfa = app(MFA::class);

    $challenge = $mfa->issueChallenge($user, 'unknown_channel', false);
    
    expect($challenge)->toBeNull();
});

it('can manually send code after generating challenge', function () {
    $user = new ChallengeGenerationTestFakeUser(3007, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    // Generate challenge without sending
    $challenge = $mfa->generateChallenge($user, 'test_channel');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);
    expect($channel->sendCallCount)->toBe(0);

    // Manually send the code
    $channel->send($user, $challenge->code);
    expect($channel->sendCallCount)->toBe(1);
    expect($channel->lastCode)->toBe($challenge->code);
});

it('generated challenge can be verified normally', function () {
    $user = new ChallengeGenerationTestFakeUser(3008, 'user@example.com');
    $mfa = app(MFA::class);

    $channel = new ChallengeGenerationTestChannel();
    $mfa->registerChannel($channel);

    // Generate challenge without sending
    $challenge = $mfa->generateChallenge($user, 'test_channel');
    expect($challenge)->toBeInstanceOf(MfaChallenge::class);

    // Verify the challenge works normally
    $verified = $mfa->verifyChallenge($user, 'test_channel', $challenge->code);
    expect($verified)->toBeTrue();

    // Check that the challenge was consumed
    $challenge->refresh();
    expect($challenge->consumed_at)->not->toBeNull();
    expect($mfa->isEnabled($user, 'test_channel'))->toBeTrue();
});
