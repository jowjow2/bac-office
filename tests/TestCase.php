<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against a real database. When the configuration is
     * cached (php artisan config:cache), the phpunit.xml environment is
     * ignored and RefreshDatabase would wipe the development database.
     * Run the suite with `composer test`, which clears the cache first.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Tests must run on the in-memory SQLite database, not [{$connection}: {$database}]. Run `php artisan config:clear` (or `composer test`) and try again.");
        }

        return $app;
    }
}
