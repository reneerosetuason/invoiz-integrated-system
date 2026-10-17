<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Databases that must never be touched by a test run.
     *
     * Tests using RefreshDatabase run `migrate:fresh`, which DROPs every
     * table in the configured connection. Without this guard a stray
     * phpunit config (or a local .env override) pointing at the live
     * e-commerce database would silently destroy production data.
     */
    private const PROTECTED_DATABASES = [
        'invoiz_ecommerce_db',
    ];

    /**
     * Runs BEFORE parent::setUp() on purpose.
     *
     * RefreshDatabase performs migrate:fresh inside parent::setUp(), so a
     * check that relied on a booted application would run after the damage.
     * PHPUnit exports the <php><env> values before the app boots, so
     * getenv() is the correct pre-boot source of truth.
     */
    protected function setUp(): void
    {
        $this->guardAgainstDestructiveDatabase();

        parent::setUp();
    }

    private function guardAgainstDestructiveDatabase(): void
    {
        $driver = (string) (getenv('DB_CONNECTION') ?: 'mysql');
        $name = (string) (getenv('DB_DATABASE') ?: '');
        $host = (string) (getenv('DB_HOST') ?: '127.0.0.1');

        // Throwaway drivers need no guard.
        if ($driver === 'sqlite' || $name === '' || str_starts_with($name, ':memory:')) {
            return;
        }

        if (in_array($name, self::PROTECTED_DATABASES, true)) {
            $this->fail(sprintf(
                'REFUSING TO RUN: connection "%s" (host %s) points at protected database "%s". '
                .'Tests using RefreshDatabase run migrate:fresh and would destroy it. '
                .'Point DB_DATABASE at a dedicated *_test schema instead.',
                $driver,
                $host,
                $name
            ));
        }

        if ($host !== '127.0.0.1' && $host !== 'localhost' && $host !== '::1') {
            $this->fail(sprintf(
                'REFUSING TO RUN: non-local database host "%s" with non-test database "%s".',
                $host,
                $name
            ));
        }
    }
}
