<?php

use CodingLibs\MFA\Channels\EmailChannel;
use CodingLibs\MFA\Channels\SmsChannel;
use CodingLibs\MFA\Facades\MFA;
use CodingLibs\MFA\Contracts\MfaChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelEnabledConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_channel_can_be_disabled_via_config()
    {
        // Test with email disabled
        config(['mfa.email.enabled' => false]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should not be able to issue email challenges when disabled
        $challenge = MFA::issueChallenge($user, 'email');
        expect($challenge)->toBeNull();
    }

    public function test_sms_channel_can_be_disabled_via_config()
    {
        // Test with SMS disabled
        config(['mfa.sms.enabled' => false]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should not be able to issue SMS challenges when disabled
        $challenge = MFA::issueChallenge($user, 'sms');
        expect($challenge)->toBeNull();
    }

    public function test_email_channel_can_be_enabled_via_config()
    {
        // Test with email enabled (default)
        config(['mfa.email.enabled' => true]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should be able to issue email challenges when enabled
        $challenge = MFA::issueChallenge($user, 'email');
        expect($challenge)->not->toBeNull();
        expect($challenge->method)->toBe('email');
    }

    public function test_sms_channel_can_be_enabled_via_config()
    {
        // Test with SMS enabled (default)
        config(['mfa.sms.enabled' => true]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should be able to issue SMS challenges when enabled
        $challenge = MFA::issueChallenge($user, 'sms');
        expect($challenge)->not->toBeNull();
        expect($challenge->method)->toBe('sms');
    }

    public function test_email_channel_respects_env_variable()
    {
        // Test with environment variable
        putenv('MFA_EMAIL_ENABLED=false');
        config(['mfa.email.enabled' => env('MFA_EMAIL_ENABLED', true)]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should not be able to issue email challenges when disabled via env
        $challenge = MFA::issueChallenge($user, 'email');
        expect($challenge)->toBeNull();
        
        // Clean up
        putenv('MFA_EMAIL_ENABLED');
    }

    public function test_sms_channel_respects_env_variable()
    {
        // Test with environment variable
        putenv('MFA_SMS_ENABLED=false');
        config(['mfa.sms.enabled' => env('MFA_SMS_ENABLED', true)]);
        
        $user = new TestUser(1, 'test@example.com');
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Should not be able to issue SMS challenges when disabled via env
        $challenge = MFA::issueChallenge($user, 'sms');
        expect($challenge)->toBeNull();
        
        // Clean up
        putenv('MFA_SMS_ENABLED');
    }

    public function test_disabled_channels_are_not_registered()
    {
        // Disable both channels
        config(['mfa.email.enabled' => false]);
        config(['mfa.sms.enabled' => false]);
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Check that channels are not available
        expect(MFA::getChannel('email'))->toBeNull();
        expect(MFA::getChannel('sms'))->toBeNull();
    }

    public function test_enabled_channels_are_registered()
    {
        // Enable both channels (default)
        config(['mfa.email.enabled' => true]);
        config(['mfa.sms.enabled' => true]);
        
        // Re-register channels to pick up new config
        MFA::reRegisterDefaultChannels();
        
        // Check that channels are available
        expect(MFA::getChannel('email'))->not->toBeNull();
        expect(MFA::getChannel('sms'))->not->toBeNull();
    }
}

class TestUser implements \Illuminate\Contracts\Auth\Authenticatable
{
    public function __construct(public int|string $id, public ?string $email = null) {}
    
    public function getAuthIdentifierName()
    {
        return 'id';
    }
    
    public function getAuthIdentifier()
    {
        return $this->id;
    }
    
    public function getAuthPassword()
    {
        return '';
    }
    
    public function getAuthPasswordName()
    {
        return 'password';
    }
    
    public function getRememberToken()
    {
        return '';
    }
    
    public function setRememberToken($value)
    {
        // Not implemented
    }
    
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}
