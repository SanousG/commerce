<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}");

        if (
            ! $app->environment('testing')
            || $connection !== 'pgsql'
            || ($database['driver'] ?? null) !== 'pgsql'
            || ($database['host'] ?? null) !== 'pgsql.testing'
            || ($database['database'] ?? null) !== 'commerce_testing'
            || ($database['username'] ?? null) !== 'commerce_testing'
            || filled($database['url'] ?? null)
            || str_contains(strtolower((string) ($database['host'] ?? '')), 'supabase.com')
        ) {
            throw new RuntimeException(
                'Database tests require the isolated pgsql.testing/commerce_testing connection.'
            );
        }

        return $app;
    }
}
