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
