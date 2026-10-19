<?php

namespace XLaravel\Followable\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use XLaravel\Followable\FollowableServiceProvider;
use XLaravel\Followable\Tests\Fixtures\User;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [FollowableServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $connection = getenv('DB_CONNECTION');

        $app['config']->set('database.default', is_string($connection) && $connection !== '' ? $connection : 'testing');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
