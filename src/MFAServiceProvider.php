<?php

namespace CodingLibs\MFA;

use CodingLibs\MFA\Models\MfaChallenge;
use CodingLibs\MFA\Models\MfaMethod;
use Illuminate\Support\ServiceProvider;

class MFAServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mfa.php', 'mfa');

        $this->app->singleton(MFA::class, function ($app) {
            return new MFA($app['config']->get('mfa', []));
        });

        $this->app->alias(MFA::class, 'mfa');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mfa.php' => config_path('mfa.php'),
        ], 'mfa-config');

        if (! class_exists('CreateMfaTables')) {
            $timestamp = date('Y_m_d_His');
            $timestamp2 = date('Y_m_d_His', time() + 1);
            $this->publishes([
                __DIR__ . '/../database/migrations/create_mfa_tables.php' => database_path("migrations/{$timestamp}_create_mfa_tables.php"),
                __DIR__ . '/../database/migrations/create_mfa_remembered_devices_table.php' => database_path("migrations/{$timestamp2}_create_mfa_remembered_devices_table.php"),
            ], 'mfa-migrations');
        }
    }
}

