<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'logistics_settings';

    private const USER_INDEX = 'log_settings_user_id_idx';

    private const USER_FOREIGN_KEY = 'log_settings_user_fk';

    public function up(): void
    {
        // A complete table means the repair already happened (for example the
        // original database was rebuilt from an equivalent schema). Returning
        // early keeps this migration from poisoning the queue with its
        // test-database guard.
        if ($this->alreadyRepaired()) {
            return;
        }

        $database = DB::connection()->getDatabaseName();
        if ($database !== 'invoiz_ecommerce_test') {
            throw new RuntimeException(
                "Logistics settings repair is restricted to `invoiz_ecommerce_test`; current database is `{$database}`."
            );
        }

        $this->assertUsersTable();

        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('photo_path')->nullable();
                $table->json('notifications')->nullable();
                $table->boolean('email_notifications')->default(true);
                $table->json('delivery')->nullable();
                $table->json('preferences')->nullable();
                $table->timestamps();
                $table->index('user_id', self::USER_INDEX);
                $table->foreign('user_id', self::USER_FOREIGN_KEY)
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });

            return;
        }

        $this->assertCompatibleTable();
        $this->ensureUserRelationship();
    }

    private function alreadyRepaired(): bool
    {
        if (! Schema::hasTable(self::TABLE)) {
            return false;
        }

        $expected = [
            'id', 'user_id', 'photo_path', 'notifications', 'email_notifications',
            'delivery', 'preferences', 'created_at', 'updated_at',
        ];
        sort($expected);

        $actual = collect(Schema::getColumns(self::TABLE))->pluck('name')->sort()->values()->all();

        return $actual === $expected;
    }

    private function assertUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            throw new RuntimeException('Logistics settings repair requires the users table.');
        }

        $usersEngine = DB::selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['users']
        );
        $userId = collect(Schema::getColumns('users'))->firstWhere('name', 'id');
        if (strtoupper((string) ($usersEngine->ENGINE ?? '')) !== 'INNODB'
            || ! $userId
            || strtolower($userId['type_name']) !== 'bigint'
            || strtolower($userId['type']) !== 'bigint unsigned'
            || $userId['nullable']
            || $userId['default'] !== null
            || ! $userId['auto_increment']) {
            throw new RuntimeException('Logistics settings repair requires an InnoDB users table with an unsigned auto-increment bigint id.');
        }

        $primaryIndexes = collect(Schema::getIndexes('users'))
            ->filter(fn (array $index): bool => $index['primary'] ?? false);
        if ($primaryIndexes->count() !== 1
            || $primaryIndexes->first()['columns'] !== ['id']
            || ! $primaryIndexes->first()['unique']) {
            throw new RuntimeException('Logistics settings repair requires users.id to be the primary key.');
        }
    }

    private function assertCompatibleTable(): void
    {
        $engine = DB::selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [self::TABLE]
        );
        if (strtoupper((string) ($engine->ENGINE ?? '')) !== 'INNODB') {
            throw new RuntimeException('Existing logistics_settings table must use InnoDB.');
        }

        $expectedColumns = [
            'id' => ['bigint', 'bigint unsigned', false, null, true],
            'user_id' => ['bigint', 'bigint unsigned', false, null, false],
            'photo_path' => ['varchar', 'varchar(255)', true, null, false],
            'notifications' => ['json', 'json', true, null, false],
            'email_notifications' => ['tinyint', 'tinyint(1)', false, '1', false],
            'delivery' => ['json', 'json', true, null, false],
            'preferences' => ['json', 'json', true, null, false],
            'created_at' => ['timestamp', 'timestamp', true, null, false],
            'updated_at' => ['timestamp', 'timestamp', true, null, false],
        ];
        $actualColumns = collect(Schema::getColumns(self::TABLE))->keyBy('name');
        if ($actualColumns->keys()->sort()->values()->all()
            !== collect(array_keys($expectedColumns))->sort()->values()->all()) {
            throw new RuntimeException('Existing logistics_settings table has missing or unexpected columns.');
        }

        foreach ($expectedColumns as $name => [$typeName, $type, $nullable, $default, $autoIncrement]) {
            $actual = $actualColumns->get($name);
            if (! $actual
                || strtolower($actual['type_name']) !== $typeName
                || strtolower($actual['type']) !== $type
                || $actual['nullable'] !== $nullable
                || ($actual['default'] === null ? null : (string) $actual['default']) !== $default
                || $actual['auto_increment'] !== $autoIncrement) {
                throw new RuntimeException("Existing logistics_settings.{$name} column is incompatible.");
            }
        }

        $this->assertIndexStructure();
        $this->assertForeignKeyStructure();
    }

    private function ensureUserRelationship(): void
    {
        $hasUserIndex = $this->assertIndexStructure();
        $hasUserForeignKey = $this->assertForeignKeyStructure();

        if (! $hasUserForeignKey && DB::table(self::TABLE)
            ->leftJoin('users', 'users.id', '=', self::TABLE.'.user_id')
            ->whereNull('users.id')
            ->exists()) {
            throw new RuntimeException('Orphaned logistics_settings user IDs prevent adding the required foreign key.');
        }

        if (! $hasUserIndex || ! $hasUserForeignKey) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($hasUserIndex, $hasUserForeignKey): void {
                if (! $hasUserIndex) {
                    $table->index('user_id', self::USER_INDEX);
                }
                if (! $hasUserForeignKey) {
                    $table->foreign('user_id', self::USER_FOREIGN_KEY)
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            });
        }
    }

    /**
     * Accept only the primary key and at most one non-unique, single-column
     * user_id index. A composite index beginning with user_id is not equivalent:
     * it does not represent the intended standalone lookup/supporting index.
     */
    private function assertIndexStructure(): bool
    {
        $indexes = collect(Schema::getIndexes(self::TABLE));
        $primary = $indexes->filter(fn (array $index): bool => $index['primary'] ?? false);
        if ($primary->count() !== 1
            || $primary->first()['columns'] !== ['id']
            || ! $primary->first()['unique']) {
            throw new RuntimeException('Existing logistics_settings table has an incompatible primary key.');
        }

        $secondary = $indexes->reject(fn (array $index): bool => $index['primary'] ?? false);
        if ($secondary->contains(
            fn (array $index): bool => $index['columns'] !== ['user_id'] || $index['unique']
        )) {
            throw new RuntimeException('Existing logistics_settings table has an unexpected or incompatible index.');
        }
        if ($secondary->count() > 1) {
            throw new RuntimeException('Existing logistics_settings table has duplicate user_id indexes.');
        }

        return $secondary->isNotEmpty();
    }

    private function assertForeignKeyStructure(): bool
    {
        $foreignKeys = collect(Schema::getForeignKeys(self::TABLE));
        if ($foreignKeys->count() > 1) {
            throw new RuntimeException('Existing logistics_settings table has unexpected foreign keys.');
        }
        if ($foreignKeys->isEmpty()) {
            return false;
        }

        $foreignKey = $foreignKeys->first();
        $onUpdate = strtolower((string) ($foreignKey['on_update'] ?? ''));
        if ($foreignKey['columns'] !== ['user_id']
            || $foreignKey['foreign_table'] !== 'users'
            || $foreignKey['foreign_columns'] !== ['id']
            || strtolower((string) $foreignKey['on_delete']) !== 'cascade'
            || ! in_array($onUpdate, ['no action', 'restrict'], true)) {
            throw new RuntimeException('Existing logistics_settings table has an incompatible foreign key.');
        }

        return true;
    }

    public function down(): void
    {
        throw new RuntimeException('This logistics settings repair is forward-only and cannot be rolled back.');
    }
};
