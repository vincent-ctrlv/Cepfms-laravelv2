<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but a test database. If the config cache
     * is left in place (php artisan optimize), phpunit.xml's DB settings are
     * ignored and tests would otherwise wipe the development or live data.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $config = $app['config'];
        $database = (string) $config->get('database.connections.' . $config->get('database.default') . '.database');

        if (!str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Tests must use a database whose name ends in \"_test\", but got \"{$database}\". "
                . 'Run "php artisan config:clear" and check DB_DATABASE in phpunit.xml.'
            );
        }

        return $app;
    }
}
