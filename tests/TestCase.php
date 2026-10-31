<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Positive allowlist for MySQL-backed tests.
     *
     * RefreshDatabase runs `migrate:fresh`, which DROPs every table in the
     * configured connection. PHPUnit exports <php><env> values before the
     * app boots, so getenv() is the correct pre-boot source of truth.
     *
     * Only the disposable sandbox instance is permitted. Everything else,
     * including localhost:3306 and the scratch 3307 instance, is rejected.
     */
    private const SANDBOX_HOST = '127.0.0.1';

    private const SANDBOX_PORT = 3308;

    private const SANDBOX_DATABASE = 'invoiz_ecommerce_sandbox';

    private const SANDBOX_USERNAME = 'invoiz_test';

    private const BLOCKED_PORTS = [3306, 3307];

    private const BLOCKED_DATABASES = [
        'invoiz_ecommerce_db',
        'invoiz_ecommerce_test',
        'invoizdb',
    ];

    /**
     * Runs BEFORE parent::setUp() on purpose.
     *
     * RefreshDatabase performs migrate:fresh inside parent::setUp(), so a
     * check that relied on a booted application would run after the damage.
     */
    protected function setUp(): void
    {
        $this->guardAgainstDestructiveDatabase();

        parent::setUp();
    }

    private function guardAgainstDestructiveDatabase(): void
    {
        $environment = (string) (getenv('APP_ENV') ?: '');
        $driver = (string) (getenv('DB_CONNECTION') ?: '');
        $host = (string) (getenv('DB_HOST') ?: '');
        $port = (string) (getenv('DB_PORT') ?: '');
        $name = (string) (getenv('DB_DATABASE') ?: '');
        $username = (string) (getenv('DB_USERNAME') ?: '');

        // In-memory SQLite cannot touch a server database.
        if ($driver === 'sqlite' && str_starts_with($name, ':memory:')) {
            return;
        }

        $portNumber = ctype_digit($port) ? (int) $port : 0;

        if (in_array($portNumber, self::BLOCKED_PORTS, true) || in_array($name, self::BLOCKED_DATABASES, true)) {
            $this->fail(sprintf(
                'REFUSING TO RUN: connection "%s" targets blocked port %s and/or protected database "%s". '
                .'Tests run migrate:fresh; use the isolated sandbox on 127.0.0.1:3308/invoiz_ecommerce_sandbox.',
                $driver,
                $port === '' ? '(unset)' : $port,
                $name === '' ? '(unset)' : $name
            ));
        }

        $problems = [];
        if ($environment !== 'testing') {
            $problems[] = 'APP_ENV must be "testing"';
        }
        if ($driver !== 'mysql') {
            $problems[] = 'DB_CONNECTION must be "mysql"';
        }
        if ($host !== self::SANDBOX_HOST) {
            $problems[] = 'DB_HOST must be "' . self::SANDBOX_HOST . '"';
        }
        if ($portNumber !== self::SANDBOX_PORT) {
            $problems[] = 'DB_PORT must be "' . self::SANDBOX_PORT . '"';
        }
        if ($name !== self::SANDBOX_DATABASE) {
            $problems[] = 'DB_DATABASE must be "' . self::SANDBOX_DATABASE . '"';
        }
        if ($username !== self::SANDBOX_USERNAME) {
            $problems[] = 'DB_USERNAME must be "' . self::SANDBOX_USERNAME . '"';
        }

        if ($problems !== []) {
            $this->fail(
                'REFUSING TO RUN: positive test allowlist not satisfied ('
                .implode('; ', $problems)
                .'). Use `php artisan test -c phpunit.mysql.xml`.'
            );
        }
    }
}
