<?php

namespace Tests;

use CodingLibs\MFA\MFAServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [MFAServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Ensure app key exists for encrypter-dependent features
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Run package migrations
        $migration = require __DIR__ . '/../database/migrations/create_mfa_tables.php';
        $migration->up();
    }
}
