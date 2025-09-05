<?php

use CodingLibs\MFA\Facades\MFA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_channel_from_config_throws_exception_for_empty_config()
    {
        expect(fn() => MFA::registerChannelFromConfig('test', []))
            ->toThrow(InvalidArgumentException::class, 'Channel config cannot be empty');
    }

    public function test_register_channel_from_config_throws_exception_for_missing_channel_key()
    {
        expect(fn() => MFA::registerChannelFromConfig('test', ['some' => 'config']))
            ->toThrow(InvalidArgumentException::class, 'channel must be specified in config');
    }

    public function test_register_channel_from_config_throws_exception_for_non_string_channel()
    {
        expect(fn() => MFA::registerChannelFromConfig('test', ['channel' => 123]))
            ->toThrow(InvalidArgumentException::class, 'channel must be a string class name');
    }

    public function test_register_channel_from_config_throws_exception_for_non_existent_class()
    {
        expect(fn() => MFA::registerChannelFromConfig('test', ['channel' => 'NonExistentClass']))
            ->toThrow(InvalidArgumentException::class, "Channel class 'NonExistentClass' does not exist");
    }

    public function test_register_channel_from_config_throws_exception_for_invalid_interface()
    {
        expect(fn() => MFA::registerChannelFromConfig('test', ['channel' => \stdClass::class]))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    }

    public function test_register_channel_from_config_works_with_valid_config()
    {
        $config = [
            'channel' => \CodingLibs\MFA\Channels\EmailChannel::class,
            'from_address' => 'test@example.com',
        ];

        // Should not throw exception
        MFA::registerChannelFromConfig('test_email', $config);
        
        // Should be able to retrieve the channel (case insensitive)
        // The channel is registered with the name returned by getName(), not the key we pass
        $channel = MFA::getChannel('email');
        expect($channel)->not->toBeNull();
        expect($channel->getName())->toBe('email');
    }
}
