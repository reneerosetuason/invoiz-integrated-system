<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Test-schema-only, forward-only repair for two tables whose original
 * migrations are recorded as run while the physical tables are absent.
 * Keep this file outside database/migrations and invoke it only by its
 * explicit database/test-migrations path.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment() !== 'testing') {
            throw new RuntimeException('This repair may run only with APP_ENV=testing.');
        }

        $connectionName = (string) config('database.default');
        if ($connectionName === '') {
            throw new RuntimeException('No active Laravel database connection is selected.');
        }

        $connection = DB::connection($connectionName);
        if ($connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'invoiz_ecommerce_test') {
            throw new RuntimeException(
                'This repair requires the MySQL connection configured for invoiz_ecommerce_test.'
            );
        }

        // Require an interactive confirmation. A CLI TTY prompt cannot be
        // satisfied by dotenv-loaded or inherited environment configuration.
        $host = trim((string) $connection->getConfig('host'));
        $port = trim((string) ($connection->getConfig('port') ?: '3306'));
        if ($host === '' || $port === '') {
            throw new RuntimeException(
                'Repair aborted: the configured MySQL host and port must both be explicit.'
            );
        }

        if (! defined('STDIN') || ! defined('STDERR')
            || ! function_exists('stream_isatty') || ! stream_isatty(STDIN)) {
            throw new RuntimeException(
                'Repair aborted: interactive terminal confirmation is required for the test target.'
            );
        }

        $targetHost = trim($host, '[]');
        $targetHost = str_contains($targetHost, ':') ? '['.$targetHost.']' : $targetHost;
        $requiredConfirmation = $targetHost.':'.$port.'/invoiz_ecommerce_test';
        fwrite(
            STDERR,
            "Type the exact approved test target as host:port/database to continue: "
        );
        $operatorConfirmation = fgets(STDIN);
        if (! is_string($operatorConfirmation)
            || ! hash_equals($requiredConfirmation, trim($operatorConfirmation))) {
            throw new RuntimeException('Repair aborted: test-target confirmation did not match.');
        }

        // Verify the schema selected by the live write connection, not only
        // the connection's configured database name.
        try {
            $activeDatabaseRows = $connection->selectFromWriteConnection(
                'SELECT DATABASE() AS active_database'
            );
        } catch (Throwable) {
            throw new RuntimeException('Repair aborted: unable to verify the active MySQL database.');
        }

        $activeDatabase = $activeDatabaseRows[0]->active_database ?? null;
        if ($activeDatabase !== 'invoiz_ecommerce_test') {
            throw new RuntimeException(
                'Repair aborted: the active MySQL database is not invoiz_ecommerce_test.'
            );
        }

        $schema = $connection->getSchemaBuilder();
        $tableMetadata = collect($schema->getTables())->keyBy('name');

        // Validate both referenced tables and their keys/engines before any
        // target table is created.
        foreach (['deliveries', 'users'] as $parentTable) {
            if (! $schema->hasTable($parentTable)) {
                throw new RuntimeException("Required parent table [{$parentTable}] is missing.");
            }

            $parentInfo = $tableMetadata->get($parentTable);
            if (strtoupper((string) ($parentInfo['engine'] ?? '')) !== 'INNODB') {
                throw new RuntimeException(
                    "Parent table [{$parentTable}] does not use the required InnoDB engine."
                );
            }

            $parentId = collect($schema->getColumns($parentTable))->firstWhere('name', 'id');
            $parentIdHasKey = collect($schema->getIndexes($parentTable))->contains(
                fn (array $index): bool => (($index['primary'] ?? false)
                        || ($index['unique'] ?? false))
                    && ($index['columns'] ?? []) === ['id']
            );

            if (! $parentId
                || ($parentId['type_name'] ?? null) !== 'bigint'
                || ! str_contains(strtolower((string) ($parentId['type'] ?? '')), 'unsigned')
                || (bool) ($parentId['nullable'] ?? true)
                || ! ($parentId['auto_increment'] ?? false)
                || ! $parentIdHasKey) {
                throw new RuntimeException(
                    "Parent table [{$parentTable}] does not have a compatible unsigned BIGINT id key."
                );
            }
        }

        // Validate every extant target before creating either table. InnoDB
        // commits CREATE TABLE independently, so a later retry validates an
        // already-created table and creates only the still-absent table.
        $this->assertCompatibleOrMissing($schema, $tableMetadata, 'delivery_status_logs', [
            'id' => ['type' => 'bigint', 'nullable' => false, 'unsigned' => true, 'auto_increment' => true, 'default' => null],
            'delivery_id' => ['type' => 'bigint', 'nullable' => false, 'unsigned' => true, 'default' => null],
            'status' => ['type' => 'varchar', 'length' => 255, 'nullable' => false, 'default' => null],
            'location' => ['type' => 'varchar', 'length' => 255, 'nullable' => true, 'default' => null],
            'notes' => ['type' => 'text', 'nullable' => true, 'default' => null],
            'changed_by' => ['type' => 'bigint', 'nullable' => true, 'unsigned' => true, 'default' => null],
            'created_at' => ['type' => 'timestamp', 'nullable' => true, 'default' => null],
            'updated_at' => ['type' => 'timestamp', 'nullable' => true, 'default' => null],
        ], 'delivery_id', 'deliveries');

        $this->assertCompatibleOrMissing($schema, $tableMetadata, 'logistics_settings', [
            'id' => ['type' => 'bigint', 'nullable' => false, 'unsigned' => true, 'auto_increment' => true, 'default' => null],
            'user_id' => ['type' => 'bigint', 'nullable' => false, 'unsigned' => true, 'default' => null],
            'photo_path' => ['type' => 'varchar', 'length' => 255, 'nullable' => true, 'default' => null],
            'notifications' => ['type' => 'json', 'nullable' => true, 'default' => null],
            'email_notifications' => ['type' => 'tinyint', 'length' => 1, 'nullable' => false, 'unsigned' => false, 'default' => '1'],
            'delivery' => ['type' => 'json', 'nullable' => true, 'default' => null],
            'preferences' => ['type' => 'json', 'nullable' => true, 'default' => null],
            'created_at' => ['type' => 'timestamp', 'nullable' => true, 'default' => null],
            'updated_at' => ['type' => 'timestamp', 'nullable' => true, 'default' => null],
        ], 'user_id', 'users');

        if (! $schema->hasTable('delivery_status_logs')) {
            $schema->create('delivery_status_logs', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
                $table->string('status');
                $table->string('location')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('logistics_settings')) {
            $schema->create('logistics_settings', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('photo_path')->nullable();
                $table->json('notifications')->nullable();
                $table->boolean('email_notifications')->default(true);
                $table->json('delivery')->nullable();
                $table->json('preferences')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Existing target tables must match the full intended structure. This
     * method never alters an extant table; partial/incompatible schemas stop.
     *
     * @param array<string, array<string, mixed>> $expectedColumns
     */
    private function assertCompatibleOrMissing(
        $schema,
        $tableMetadata,
        string $table,
        array $expectedColumns,
        string $foreignColumn,
        string $parentTable,
    ): void {
        if (! $schema->hasTable($table)) {
            return;
        }

        $tableInfo = $tableMetadata->get($table);
        if (strtoupper((string) ($tableInfo['engine'] ?? '')) !== 'INNODB') {
            throw new RuntimeException("Existing table [{$table}] does not use InnoDB.");
        }

        $actualColumns = collect($schema->getColumns($table))->keyBy('name');
        if ($actualColumns->keys()->sort()->values()->all()
            !== collect(array_keys($expectedColumns))->sort()->values()->all()) {
            throw new RuntimeException(
                "Existing table [{$table}] has missing or unexpected columns; manual review is required."
            );
        }

        foreach ($expectedColumns as $name => $expected) {
            $actual = $actualColumns->get($name);
            if (! $actual
                || ($actual['type_name'] ?? null) !== $expected['type']
                || (bool) ($actual['nullable'] ?? false) !== $expected['nullable']) {
                throw new RuntimeException(
                    "Existing table [{$table}] has an incompatible column [{$name}]."
                );
            }

            $actualUnsigned = str_contains(
                strtolower((string) ($actual['type'] ?? '')),
                'unsigned'
            );
            if (isset($expected['unsigned']) && $actualUnsigned !== $expected['unsigned']) {
                throw new RuntimeException(
                    "Existing column [{$table}.{$name}] has an incompatible signedness."
                );
            }

            if (isset($expected['length'])) {
                preg_match('/\((\d+)\)/', (string) ($actual['type'] ?? ''), $matches);
                if ((int) ($matches[1] ?? 0) !== $expected['length']) {
                    throw new RuntimeException(
                        "Existing column [{$table}.{$name}] has an incompatible length."
                    );
                }
            }

            if (($actual['auto_increment'] ?? false) !== ($expected['auto_increment'] ?? false)) {
                throw new RuntimeException(
                    "Existing column [{$table}.{$name}] has incompatible auto-increment behavior."
                );
            }

            $actualDefault = $actual['default'] ?? null;
            $expectedDefault = $expected['default'];
            if (($actualDefault === null) !== ($expectedDefault === null)
                || ($actualDefault !== null && (string) $actualDefault !== (string) $expectedDefault)) {
                throw new RuntimeException(
                    "Existing column [{$table}.{$name}] has an incompatible default."
                );
            }
        }

        $indexes = collect($schema->getIndexes($table));
        $primaryIndex = $indexes->contains(
            fn (array $index): bool => ($index['primary'] ?? false)
                && ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['id']
        );
        $supportingIndex = $indexes->contains(
            fn (array $index): bool => ! ($index['primary'] ?? false)
                && ! ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === [$foreignColumn]
        );
        $onlyExpectedIndexes = $indexes->count() === 2;

        $foreignKeys = collect($schema->getForeignKeys($table));
        $expectedForeignKey = $foreignKeys->count() === 1 && $foreignKeys->contains(
            fn (array $key): bool => ($key['columns'] ?? []) === [$foreignColumn]
                && ($key['foreign_table'] ?? null) === $parentTable
                && ($key['foreign_columns'] ?? []) === ['id']
                && strtolower((string) ($key['on_delete'] ?? '')) === 'cascade'
                && in_array(strtolower((string) ($key['on_update'] ?? '')), ['restrict', 'no action'], true)
        );

        if (! $primaryIndex || ! $supportingIndex || ! $onlyExpectedIndexes || ! $expectedForeignKey) {
            throw new RuntimeException(
                "Existing table [{$table}] has incompatible indexes or foreign keys; manual review is required."
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This test-schema repair is forward-only and cannot be rolled back.');
    }
};
