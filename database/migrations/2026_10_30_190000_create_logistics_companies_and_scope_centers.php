<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const APPROVED_HUBS = [
        'Invoiz Manila Logistics Center',
        'Invoiz Cebu Logistics Center',
        'Invoiz Central Luzon Sorting Center',
    ];

    public function up(): void
    {
        // The forward-only original-database migration (2026_10_31_100000)
        // may already have created the complete hub schema. Re-running the
        // test-database bootstrapper would then only trip its database-name
        // guard and poison the migration queue, so it is a safe no-op here.
        if ($this->completeSchemaAlreadyPresent()) {
            return;
        }

        $isKnownPartialState = $this->preflightSchema();
        $this->verifyGlobalLogisticsAdmin();
        $approvedHubIds = $this->resolveApprovedInvoizHubs();

        try {
            if ($isKnownPartialState) {
                $this->completePartialOwnershipHistorySchema();
            } else {
                $this->createCompanyAndScopeSchema();
            }

            $this->createFleetAndTransferTables();

            $this->assignApprovedInvoizHubs($approvedHubIds);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The logistics company migration failed after preflight and may be partially applied. Do not retry or roll back automatically; inspect the schema, migration status, and ownership records, then use the documented manual recovery procedure.',
                0,
                $exception
            );
        }
    }

    /**
     * True when every table and column this migration owns already exists.
     * Used only to make the migration a no-op on the original database.
     */
    private function completeSchemaAlreadyPresent(): bool
    {
        foreach ([
            'logistics_companies',
            'logistics_company_ownership_histories',
            'logistics_trucks',
            'hub_transport_drivers',
            'hub_transfers',
            'hub_transfer_parcels',
            'hub_transfer_events',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        foreach ([
            'users' => ['logistics_company_id'],
            'logistics_centers' => ['logistics_company_id', 'ownership_verified_at', 'ownership_verified_by'],
            'hub_transport_drivers' => ['email', 'password', 'logistics_truck_id', 'is_online'],
            'hub_transfers' => ['origin_arrived_at', 'destination_arrived_at'],
            'hub_transfer_parcels' => ['loaded_by_hub_driver_id'],
            'hub_transfer_events' => ['hub_transport_driver_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                return false;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function preflightSchema(): bool
    {
        $this->assertTargetDatabase();

        $requiredColumns = [
            'users' => ['id', 'email', 'role', 'is_admin', 'status', 'account_status', 'center_id'],
            'logistics_centers' => ['id', 'name', 'is_active'],
            'deliveries' => ['id'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Logistics company migration requires source table `{$table}`.");
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Logistics company migration requires source column `{$table}.{$column}`.");
                }
            }
        }

        $targetTables = [
            'logistics_companies',
            'logistics_company_ownership_histories',
            'logistics_trucks',
            'hub_transport_drivers',
            'hub_transfers',
            'hub_transfer_parcels',
            'hub_transfer_events',
        ];
        $targetColumns = [
            'users' => ['logistics_company_id'],
            'logistics_centers' => ['logistics_company_id', 'ownership_verified_at', 'ownership_verified_by'],
        ];
        $hasTargetObjects = false;
        foreach ($targetTables as $table) {
            $hasTargetObjects = $hasTargetObjects || Schema::hasTable($table);
        }
        foreach ($targetColumns as $table => $columns) {
            foreach ($columns as $column) {
                $hasTargetObjects = $hasTargetObjects || Schema::hasColumn($table, $column);
            }
        }
        foreach ([
            'users' => ['users_logistics_company_id_role_status_index', 'users_logistics_company_id_foreign'],
            'logistics_centers' => [
                'logistics_centers_logistics_company_id_is_active_index',
                'logistics_centers_logistics_company_id_foreign',
                'logistics_centers_ownership_verified_by_foreign',
            ],
        ] as $table => $objectNames) {
            $indexes = Schema::hasTable($table) ? collect(Schema::getIndexes($table))->pluck('name') : collect();
            $foreignKeys = Schema::hasTable($table) ? collect(Schema::getForeignKeys($table))->pluck('name') : collect();
            $hasTargetObjects = $hasTargetObjects
                || $indexes->intersect($objectNames)->isNotEmpty()
                || $foreignKeys->intersect($objectNames)->isNotEmpty();
        }

        if (! $hasTargetObjects) {
            return false;
        }

        $this->assertKnownPartialState($targetTables, $targetColumns);

        return true;
    }

    private function assertTargetDatabase(): void
    {
        $database = DB::connection()->getDatabaseName();
        if ($database !== 'invoiz_ecommerce_test') {
            throw new RuntimeException(
                "Logistics company migration is restricted to `invoiz_ecommerce_test`; current database is `{$database}`."
            );
        }
    }

    /**
     * @param  list<string>  $targetTables
     * @param  array<string, list<string>>  $targetColumns
     */
    private function assertKnownPartialState(array $targetTables, array $targetColumns): void
    {
        $expectedPresentTables = ['logistics_companies', 'logistics_company_ownership_histories'];
        $expectedMissingTables = array_diff($targetTables, $expectedPresentTables);
        foreach ($expectedPresentTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Unexpected partial migration state: expected table `{$table}` is missing.");
            }
        }
        foreach ($expectedMissingTables as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException("Unexpected partial migration state: table `{$table}` already exists.");
            }
        }
        foreach ($targetColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Unexpected partial migration state: column `{$table}.{$column}` is missing.");
                }
            }
        }

        $this->assertExactColumns('logistics_companies', [
            'id', 'name', 'registration_number', 'status', 'created_by', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $this->assertExactColumns('logistics_company_ownership_histories', [
            'id', 'logistics_center_id', 'from_company_id', 'to_company_id', 'action', 'evidence', 'reason',
            'changed_by', 'actor_name', 'created_at', 'updated_at',
        ]);
        foreach ([
            ['logistics_companies', 'id', 'bigint unsigned', false, null, true],
            ['logistics_companies', 'name', 'varchar(255)', false, null, false],
            ['logistics_companies', 'registration_number', 'varchar(255)', true, null, false],
            ['logistics_companies', 'status', 'varchar(20)', false, 'active', false],
            ['logistics_companies', 'created_by', 'bigint unsigned', true, null, false],
            ['logistics_companies', 'created_at', 'timestamp', true, null, false],
            ['logistics_companies', 'updated_at', 'timestamp', true, null, false],
            ['logistics_companies', 'deleted_at', 'timestamp', true, null, false],
            ['logistics_company_ownership_histories', 'id', 'bigint unsigned', false, null, true],
            ['logistics_company_ownership_histories', 'logistics_center_id', 'bigint unsigned', false, null, false],
            ['logistics_company_ownership_histories', 'from_company_id', 'bigint unsigned', true, null, false],
            ['logistics_company_ownership_histories', 'to_company_id', 'bigint unsigned', true, null, false],
            ['logistics_company_ownership_histories', 'action', 'varchar(30)', false, null, false],
            ['logistics_company_ownership_histories', 'evidence', 'text', true, null, false],
            ['logistics_company_ownership_histories', 'reason', 'text', true, null, false],
            ['logistics_company_ownership_histories', 'changed_by', 'bigint unsigned', true, null, false],
            ['logistics_company_ownership_histories', 'actor_name', 'varchar(255)', true, null, false],
            ['logistics_company_ownership_histories', 'created_at', 'timestamp', true, null, false],
            ['logistics_company_ownership_histories', 'updated_at', 'timestamp', true, null, false],
        ] as [$table, $column, $type, $nullable, $default, $autoIncrement]) {
            $this->assertColumnShape($table, $column, $type, $nullable, $default, $autoIncrement);
        }
        $this->assertColumnShape('users', 'logistics_company_id', 'bigint unsigned', true);
        $this->assertColumnShape('logistics_centers', 'logistics_company_id', 'bigint unsigned', true);
        $this->assertColumnShape('logistics_centers', 'ownership_verified_at', 'timestamp', true);
        $this->assertColumnShape('logistics_centers', 'ownership_verified_by', 'bigint unsigned', true);

        $this->assertKnownIndexes('logistics_companies', [
            'primary' => [['id'], true],
            'logistics_companies_name_unique' => [['name'], true],
            'logistics_companies_registration_number_unique' => [['registration_number'], true],
            'logistics_companies_status_index' => [['status'], false],
            'logistics_companies_created_by_foreign' => [['created_by'], false],
        ], true, true);
        $this->assertKnownForeignKeys('logistics_companies', [
            'logistics_companies_created_by_foreign' => ['created_by', 'users', 'id', 'set null'],
        ], true, true);

        $this->assertKnownIndexes('logistics_company_ownership_histories', [
            'primary' => [['id'], true],
            'lcoh_center_fk' => [['logistics_center_id'], false],
            'lcoh_changed_by_fk' => [['changed_by'], false],
            'lcoh_center_created_at_idx' => [['logistics_center_id', 'created_at'], false],
        ], false, true);
        $this->assertKnownForeignKeys('logistics_company_ownership_histories', [
            'lcoh_center_fk' => ['logistics_center_id', 'logistics_centers', 'id', 'restrict'],
            'lcoh_changed_by_fk' => ['changed_by', 'users', 'id', 'set null'],
        ], false, true);
        $this->assertKnownIndexes('users', [
            'primary' => [['id'], true],
            'users_center_id_foreign' => [['center_id'], false],
            'users_email_unique' => [['email'], true],
            'users_logistics_company_id_role_status_index' => [['logistics_company_id', 'role', 'status'], false],
        ], true, true);
        $this->assertKnownForeignKeys('users', [
            'users_center_id_foreign' => ['center_id', 'logistics_centers', 'id', 'set null'],
            'users_logistics_company_id_foreign' => ['logistics_company_id', 'logistics_companies', 'id', 'set null'],
        ], true, true);
        $this->assertKnownIndexes('logistics_centers', [
            'primary' => [['id'], true],
            'logistics_centers_ownership_verified_by_foreign' => [['ownership_verified_by'], false],
            'logistics_centers_logistics_company_id_is_active_index' => [['logistics_company_id', 'is_active'], false],
        ], true, true);
        $this->assertKnownForeignKeys('logistics_centers', [
            'logistics_centers_logistics_company_id_foreign' => ['logistics_company_id', 'logistics_companies', 'id', 'restrict'],
            'logistics_centers_ownership_verified_by_foreign' => ['ownership_verified_by', 'users', 'id', 'set null'],
        ], true, true);

        if (DB::table('logistics_companies')->exists()
            || DB::table('logistics_company_ownership_histories')->exists()
            || DB::table('users')->whereNotNull('logistics_company_id')->exists()
            || DB::table('logistics_centers')->where(function ($query): void {
                $query->whereNotNull('logistics_company_id')
                    ->orWhereNotNull('ownership_verified_at')
                    ->orWhereNotNull('ownership_verified_by');
            })->exists()) {
            throw new RuntimeException('Unexpected data exists in the partial logistics schema; manual reconciliation is required.');
        }
    }

    /**
     * @param  list<string>  $expected
     */
    private function assertExactColumns(string $table, array $expected): void
    {
        if (Schema::getColumnListing($table) !== $expected) {
            throw new RuntimeException("Unexpected columns or column order in partial table `{$table}`.");
        }
    }

    private function assertColumnShape(
        string $table,
        string $column,
        string $type,
        bool $nullable,
        ?string $default = null,
        bool $autoIncrement = false
    ): void {
        $definition = collect(Schema::getColumns($table))->firstWhere('name', $column);
        if (! $definition
            || strtolower($definition['type']) !== $type
            || $definition['nullable'] !== $nullable
            || $definition['default'] !== $default
            || $definition['auto_increment'] !== $autoIncrement) {
            throw new RuntimeException("Unexpected definition for partial-schema column `{$table}.{$column}`.");
        }
    }

    /**
     * @param  array<string, array{0: list<string>, 1: bool}>  $expected
     */
    private function assertKnownIndexes(string $table, array $expected, bool $requireAll, bool $rejectUnknown): void
    {
        $indexes = collect(Schema::getIndexes($table))->keyBy('name');
        if ($rejectUnknown && $indexes->keys()->diff(array_keys($expected))->isNotEmpty()) {
            throw new RuntimeException("Unexpected index exists on partial-schema table `{$table}`.");
        }

        foreach ($expected as $name => [$columns, $unique]) {
            $index = $indexes->get($name);
            if (! $index) {
                if ($requireAll) {
                    throw new RuntimeException("Required partial-schema index `{$name}` is missing from `{$table}`.");
                }

                continue;
            }
            if ($index['columns'] !== $columns || $index['unique'] !== $unique) {
                throw new RuntimeException("Unexpected definition for partial-schema index `{$name}` on `{$table}`.");
            }
        }
    }

    /**
     * @param  array<string, array{0: string, 1: string, 2: string, 3: string}>  $expected
     */
    private function assertKnownForeignKeys(string $table, array $expected, bool $requireAll, bool $rejectUnknown): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy('name');
        if ($rejectUnknown && $foreignKeys->keys()->diff(array_keys($expected))->isNotEmpty()) {
            throw new RuntimeException("Unexpected foreign key exists on partial-schema table `{$table}`.");
        }

        foreach ($expected as $name => [$column, $referencedTable, $referencedColumn, $onDelete]) {
            $foreignKey = $foreignKeys->get($name);
            if (! $foreignKey) {
                if ($requireAll) {
                    throw new RuntimeException("Required partial-schema foreign key `{$name}` is missing from `{$table}`.");
                }

                continue;
            }
            if ($foreignKey['columns'] !== [$column]
                || $foreignKey['foreign_table'] !== $referencedTable
                || $foreignKey['foreign_columns'] !== [$referencedColumn]
                || strtolower($foreignKey['on_delete']) !== $onDelete) {
                throw new RuntimeException("Unexpected definition for partial-schema foreign key `{$name}` on `{$table}`.");
            }
        }
    }

    private function createCompanyAndScopeSchema(): void
    {
        Schema::create('logistics_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('registration_number')->nullable()->unique();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('logistics_centers', function (Blueprint $table): void {
            $table->foreignId('logistics_company_id')->nullable()->after('id')->constrained('logistics_companies')->restrictOnDelete();
            $table->timestamp('ownership_verified_at')->nullable();
            $table->foreignId('ownership_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['logistics_company_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('logistics_company_id')->nullable()->after('center_id')->constrained('logistics_companies')->nullOnDelete();
            $table->index(['logistics_company_id', 'role', 'status']);
        });

        Schema::create('logistics_company_ownership_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_center_id');
            $table->foreign('logistics_center_id', 'lcoh_center_fk')
                ->references('id')
                ->on('logistics_centers')
                ->restrictOnDelete();
            $table->unsignedBigInteger('from_company_id')->nullable();
            $table->unsignedBigInteger('to_company_id')->nullable();
            $table->string('action', 30);
            $table->text('evidence')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable();
            $table->foreign('changed_by', 'lcoh_changed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->timestamps();
            $table->index(['logistics_center_id', 'created_at'], 'lcoh_center_created_at_idx');
        });
    }

    private function completePartialOwnershipHistorySchema(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('logistics_company_ownership_histories'))->keyBy('name');
        Schema::table('logistics_company_ownership_histories', function (Blueprint $table) use ($foreignKeys): void {
            if (! $foreignKeys->has('lcoh_center_fk')) {
                $table->foreign('logistics_center_id', 'lcoh_center_fk')
                    ->references('id')
                    ->on('logistics_centers')
                    ->restrictOnDelete();
            }
            if (! $foreignKeys->has('lcoh_changed_by_fk')) {
                $table->foreign('changed_by', 'lcoh_changed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });

        $indexes = collect(Schema::getIndexes('logistics_company_ownership_histories'))->keyBy('name');
        if (! $indexes->has('lcoh_center_created_at_idx')) {
            Schema::table('logistics_company_ownership_histories', function (Blueprint $table): void {
                $table->index(['logistics_center_id', 'created_at'], 'lcoh_center_created_at_idx');
            });
        }
    }

    private function createFleetAndTransferTables(): void
    {
        Schema::create('logistics_trucks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
            $table->string('plate_number', 40);
            $table->string('name')->nullable();
            $table->string('vehicle_type', 80)->default('truck');
            $table->string('model')->nullable();
            $table->unsignedInteger('parcel_capacity')->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->string('status', 20)->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['logistics_company_id', 'plate_number']);
            $table->index(['logistics_company_id', 'status']);
        });

        Schema::create('hub_transport_drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->string('status', 20)->default('available');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['logistics_company_id', 'license_number']);
            $table->index(['logistics_company_id', 'status']);
        });

        Schema::create('hub_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
            $table->foreignId('origin_center_id')->constrained('logistics_centers')->restrictOnDelete();
            $table->foreignId('destination_center_id')->constrained('logistics_centers')->restrictOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained('logistics_trucks')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('hub_transport_drivers')->restrictOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('planned_dispatch_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('arrived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['logistics_company_id', 'origin_center_id', 'status'], 'hub_transfers_company_origin_status_idx');
            $table->index(['logistics_company_id', 'destination_center_id', 'status'], 'hub_transfers_company_destination_status_idx');
        });

        Schema::create('hub_transfer_parcels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hub_transfer_id')->constrained('hub_transfers')->restrictOnDelete();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->string('loading_status', 20)->default('pending');
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->string('receiving_status', 20)->default('pending');
            $table->string('condition', 20)->nullable();
            $table->text('exception_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['hub_transfer_id', 'delivery_id']);
            $table->index(['delivery_id', 'receiving_status']);
        });

        Schema::create('hub_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hub_transfer_id')->constrained('hub_transfers')->restrictOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('previous_status', 24)->nullable();
            $table->string('new_status', 24)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->timestamps();
            $table->index(['hub_transfer_id', 'created_at']);
        });
    }

    /**
     * @return array<string, int>
     */
    private function resolveApprovedInvoizHubs(): array
    {
        $hubIds = [];
        foreach (self::APPROVED_HUBS as $name) {
            $matches = DB::table('logistics_centers')->where('name', $name)->get(['id', 'name']);
            if ($matches->count() !== 1) {
                throw new RuntimeException(
                    "Expected exactly one approved logistics center named `{$name}` before migration; found {$matches->count()}."
                );
            }
            $match = $matches->first();
            if ($match->name !== $name) {
                throw new RuntimeException("Approved logistics center name mismatch: expected exactly `{$name}`.");
            }
            $hubIds[$name] = (int) $match->id;
        }

        return $hubIds;
    }

    /**
     * @param  array<string, int>  $approvedHubIds
     */
    private function assignApprovedInvoizHubs(array $approvedHubIds): void
    {
        if ($approvedHubIds === []) {
            return;
        }

        DB::transaction(function () use ($approvedHubIds): void {
            $companies = DB::table('logistics_companies')->lockForUpdate()->get(['id', 'name']);
            if ($companies->count() > 1 || ($companies->isNotEmpty() && $companies->first()->name !== 'INVOIZ Logistics')) {
                throw new RuntimeException('Unexpected logistics company data exists; ownership assignment was stopped.');
            }

            $companyId = $companies->first()->id ?? null;
            if ($companyId === null) {
                $companyId = DB::table('logistics_companies')->insertGetId([
                    'name' => 'INVOIZ Logistics', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach ($approvedHubIds as $name => $id) {
                $center = DB::table('logistics_centers')->where('id', $id)->lockForUpdate()->first(['logistics_company_id']);
                if (! $center) {
                    throw new RuntimeException("Approved logistics center `{$name}` disappeared during migration.");
                }
                $current = $center->logistics_company_id;
                if ($current !== null && (int) $current !== (int) $companyId) {
                    throw new RuntimeException("Approved logistics center `{$name}` already belongs to a different logistics company.");
                }
                if ($current === null) {
                    $assigned = DB::table('logistics_centers')
                        ->where('id', $id)
                        ->whereNull('logistics_company_id')
                        ->update([
                            'logistics_company_id' => $companyId,
                            'ownership_verified_at' => now(),
                            'updated_at' => now(),
                        ]);
                    if ($assigned !== 1) {
                        throw new RuntimeException("Ownership for approved logistics center `{$name}` changed during migration; no assignment was overwritten.");
                    }
                }
            }

            $conflictingStaff = DB::table('users')
                ->whereIn('center_id', array_values($approvedHubIds))
                ->whereIn('role', ['staff', 'logistics_staff'])
                ->where('id', '!=', 18)
                ->whereNotNull('logistics_company_id')
                ->where('logistics_company_id', '!=', $companyId)
                ->exists();
            if ($conflictingStaff) {
                throw new RuntimeException('A scoped logistics staff account belongs to a different company; ownership assignment was stopped.');
            }

            DB::table('users')->whereIn('center_id', array_values($approvedHubIds))
                ->whereIn('role', ['staff', 'logistics_staff'])
                ->where('id', '!=', 18)
                ->whereNull('logistics_company_id')->update(['logistics_company_id' => $companyId]);

            foreach ($approvedHubIds as $name => $id) {
                $matchingHistory = DB::table('logistics_company_ownership_histories')
                    ->where('logistics_center_id', $id)
                    ->where('to_company_id', $companyId)
                    ->where('action', 'approved_assignment')
                    ->lockForUpdate()
                    ->get(['id']);
                if ($matchingHistory->count() > 1) {
                    throw new RuntimeException("Duplicate ownership history already exists for approved logistics center `{$name}`.");
                }
                if ($matchingHistory->isEmpty()) {
                    DB::table('logistics_company_ownership_histories')->insert([
                        'logistics_center_id' => $id, 'from_company_id' => null, 'to_company_id' => $companyId,
                        'action' => 'approved_assignment', 'evidence' => 'Project owner approved INVOIZ Logistics ownership mapping.',
                        'reason' => 'Explicit approved hub ownership configuration.', 'actor_name' => 'System migration',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function verifyGlobalLogisticsAdmin(): void
    {
        $designated = DB::table('users')->where('id', 18)
            ->first(['id', 'email', 'role', 'is_admin', 'status', 'account_status']);
        $roleAssignments = DB::table('users')->where('role', 'logistics_admin')->count();
        if (! $designated && $roleAssignments === 0) {
            throw new RuntimeException('The designated global Logistics Admin (user ID 18) is missing; production migrations require that account.');
        }

        if (! $designated || $roleAssignments !== 1) {
            throw new RuntimeException('The designated global Logistics Admin (user ID 18) is missing or duplicate logistics_admin roles exist. No account fields were changed.');
        }
        if ($designated->email !== 'logistics_admin@test.com' || $designated->role !== 'logistics_admin'
            || (bool) $designated->is_admin || $designated->status !== 'active'
            || $designated->account_status !== 'active') {
            throw new RuntimeException('The designated global Logistics Admin identity or active status does not match the approved account. No account fields were changed.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This logistics company migration is forward-only. Automatic rollback is disabled to preserve ownership and transfer records. Use the documented manual recovery procedure.'
        );
    }
};
