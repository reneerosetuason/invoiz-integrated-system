<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only, additive recreation of the hub-to-hub logistics schema on the
 * original database. Mirrors the combined intent of 190000 + 200000 without
 * the test-database guard, and is safe to re-run: every object is created only
 * when it is missing, and ownership is assigned only from an unassigned state.
 */
return new class extends Migration
{
    private const APPROVED_HUBS = [
        'Invoiz Manila Logistics Center',
        'Invoiz Cebu Logistics Center',
        'Invoiz Central Luzon Sorting Center',
    ];

    public function up(): void
    {
        $this->createCompaniesTable();
        $this->addCenterScopeColumns();
        $this->addUserScopeColumn();
        $this->createOwnershipHistoriesTable();
        $this->createFleetAndTransferTables();
        $this->assignApprovedHubs();
        $this->verify();
    }

    private function createCompaniesTable(): void
    {
        if (Schema::hasTable('logistics_companies')) {
            return;
        }

        Schema::create('logistics_companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('registration_number')->nullable()->unique();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function addCenterScopeColumns(): void
    {
        Schema::table('logistics_centers', function (Blueprint $table): void {
            if (! Schema::hasColumn('logistics_centers', 'logistics_company_id')) {
                $table->foreignId('logistics_company_id')->nullable()->after('id')->constrained('logistics_companies')->restrictOnDelete();
            }
            if (! Schema::hasColumn('logistics_centers', 'ownership_verified_at')) {
                $table->timestamp('ownership_verified_at')->nullable();
            }
            if (! Schema::hasColumn('logistics_centers', 'ownership_verified_by')) {
                $table->foreignId('ownership_verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        $this->ensureIndex('logistics_centers', 'logistics_centers_logistics_company_id_is_active_index', ['logistics_company_id', 'is_active']);
    }

    private function addUserScopeColumn(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'logistics_company_id')) {
                $table->foreignId('logistics_company_id')->nullable()->after('center_id')->constrained('logistics_companies')->nullOnDelete();
            }
        });

        $this->ensureIndex('users', 'users_logistics_company_id_role_status_index', ['logistics_company_id', 'role', 'status']);
    }

    private function createOwnershipHistoriesTable(): void
    {
        if (Schema::hasTable('logistics_company_ownership_histories')) {
            return;
        }

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

    private function createFleetAndTransferTables(): void
    {
        if (! Schema::hasTable('logistics_trucks')) {
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
        }

        if (! Schema::hasTable('hub_transport_drivers')) {
            Schema::create('hub_transport_drivers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
                $table->string('name');
                $table->string('email')->nullable()->unique();
                $table->string('password')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('license_number', 100)->nullable();
                $table->foreignId('logistics_truck_id')->nullable()->constrained('logistics_trucks')->nullOnDelete();
                $table->boolean('is_online')->default(false);
                $table->string('status', 20)->default('available');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['logistics_company_id', 'license_number']);
                $table->index(['logistics_company_id', 'status']);
            });
        }

        if (! Schema::hasTable('hub_transfers')) {
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
                $table->timestamp('origin_arrived_at')->nullable();
                $table->timestamp('destination_arrived_at')->nullable();
                $table->timestamps();
                $table->index(['logistics_company_id', 'origin_center_id', 'status'], 'hub_transfers_company_origin_status_idx');
                $table->index(['logistics_company_id', 'destination_center_id', 'status'], 'hub_transfers_company_destination_status_idx');
            });
        }

        if (! Schema::hasTable('hub_transfer_parcels')) {
            Schema::create('hub_transfer_parcels', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hub_transfer_id')->constrained('hub_transfers')->restrictOnDelete();
                $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
                $table->string('loading_status', 20)->default('pending');
                $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('loaded_by_hub_driver_id')->nullable()->constrained('hub_transport_drivers')->nullOnDelete();
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
        }

        if (! Schema::hasTable('hub_transfer_events')) {
            Schema::create('hub_transfer_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('hub_transfer_id')->constrained('hub_transfers')->restrictOnDelete();
                $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->restrictOnDelete();
                $table->string('event_type', 40);
                $table->string('previous_status', 24)->nullable();
                $table->string('new_status', 24)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('hub_transport_driver_id')->nullable()->constrained('hub_transport_drivers')->nullOnDelete();
                $table->string('actor_name')->nullable();
                $table->timestamps();
                $table->index(['hub_transfer_id', 'created_at']);
            });
        }
    }

    private function assignApprovedHubs(): void
    {
        $hubIds = [];
        $found = DB::table('logistics_centers')->whereIn('name', self::APPROVED_HUBS)->get(['id', 'name']);
        if ($found->count() === count(self::APPROVED_HUBS)) {
            foreach ($found as $center) {
                $hubIds[$center->name] = (int) $center->id;
            }
        } else {
            $centers = DB::table('logistics_centers')->where('is_active', 1)->get(['id', 'name']);
            foreach ($centers as $center) {
                $hubIds[$center->name] = (int) $center->id;
            }
        }

        DB::transaction(function () use ($hubIds): void {
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

            foreach ($hubIds as $name => $id) {
                $center = DB::table('logistics_centers')->where('id', $id)->lockForUpdate()->first(['logistics_company_id']);
                if (! $center) {
                    throw new RuntimeException("Approved logistics center `{$name}` disappeared during assignment.");
                }
                $current = $center->logistics_company_id;
                if ($current !== null && (int) $current !== (int) $companyId) {
                    throw new RuntimeException("Approved logistics center `{$name}` already belongs to a different logistics company; no assignment was overwritten.");
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
                        throw new RuntimeException("Ownership for approved logistics center `{$name}` changed during assignment; no assignment was overwritten.");
                    }
                }
            }

            $conflictingStaff = DB::table('users')
                ->whereIn('center_id', array_values($hubIds))
                ->whereIn('role', ['staff', 'logistics_staff'])
                ->whereNotIn('id', [18, 30])
                ->whereNotNull('logistics_company_id')
                ->where('logistics_company_id', '!=', $companyId)
                ->exists();
            if ($conflictingStaff) {
                throw new RuntimeException('A scoped logistics staff account belongs to a different company; ownership assignment was stopped.');
            }

            DB::table('users')->whereIn('center_id', array_values($hubIds))
                ->whereIn('role', ['staff', 'logistics_staff'])
                ->whereNotIn('id', [18, 30])
                ->whereNull('logistics_company_id')->update(['logistics_company_id' => $companyId]);

            foreach ($hubIds as $name => $id) {
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

    private function ensureIndex(string $table, string $name, array $columns): void
    {
        $exists = collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['name'] === $name);
        if (! $exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    private function verify(): void
    {
        $tables = [
            'logistics_companies', 'logistics_company_ownership_histories', 'logistics_trucks',
            'hub_transport_drivers', 'hub_transfers', 'hub_transfer_parcels', 'hub_transfer_events',
        ];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Hub schema verification failed: table `{$table}` is missing.");
            }
        }

        $columns = [
            'logistics_centers' => ['logistics_company_id', 'ownership_verified_at', 'ownership_verified_by'],
            'users' => ['logistics_company_id'],
            'hub_transport_drivers' => ['email', 'password', 'logistics_truck_id', 'is_online'],
            'hub_transfers' => ['origin_arrived_at', 'destination_arrived_at'],
            'hub_transfer_parcels' => ['loaded_by_hub_driver_id'],
            'hub_transfer_events' => ['hub_transport_driver_id'],
        ];
        foreach ($columns as $table => $required) {
            foreach ($required as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Hub schema verification failed: column `{$table}.{$column}` is missing.");
                }
            }
        }

        $indexes = [
            'logistics_centers' => ['logistics_centers_logistics_company_id_is_active_index'],
            'users' => ['users_logistics_company_id_role_status_index'],
            'logistics_companies' => ['logistics_companies_name_unique', 'logistics_companies_status_index'],
            'logistics_trucks' => ['logistics_trucks_logistics_company_id_plate_number_unique'],
            'hub_transport_drivers' => ['hub_transport_drivers_email_unique', 'hub_transport_drivers_logistics_company_id_license_number_unique'],
            'hub_transfers' => ['hub_transfers_reference_unique', 'hub_transfers_company_origin_status_idx', 'hub_transfers_company_destination_status_idx'],
            'hub_transfer_parcels' => ['hub_transfer_parcels_hub_transfer_id_delivery_id_unique'],
        ];
        foreach ($indexes as $table => $names) {
            $present = collect(Schema::getIndexes($table))->pluck('name');
            foreach ($names as $name) {
                if (! $present->contains($name)) {
                    throw new RuntimeException("Hub schema verification failed: index `{$name}` is missing from `{$table}`.");
                }
            }
        }

        $foreignKeys = [
            'logistics_companies' => ['logistics_companies_created_by_foreign'],
            'logistics_centers' => ['logistics_centers_logistics_company_id_foreign', 'logistics_centers_ownership_verified_by_foreign'],
            'users' => ['users_logistics_company_id_foreign'],
            'logistics_company_ownership_histories' => ['lcoh_center_fk', 'lcoh_changed_by_fk'],
            'logistics_trucks' => ['logistics_trucks_logistics_company_id_foreign'],
            'hub_transport_drivers' => ['hub_transport_drivers_logistics_company_id_foreign', 'hub_transport_drivers_logistics_truck_id_foreign'],
            'hub_transfers' => [
                'hub_transfers_logistics_company_id_foreign', 'hub_transfers_origin_center_id_foreign',
                'hub_transfers_destination_center_id_foreign', 'hub_transfers_truck_id_foreign', 'hub_transfers_driver_id_foreign',
            ],
            'hub_transfer_parcels' => ['hub_transfer_parcels_hub_transfer_id_foreign', 'hub_transfer_parcels_delivery_id_foreign', 'hub_transfer_parcels_loaded_by_hub_driver_id_foreign'],
            'hub_transfer_events' => ['hub_transfer_events_hub_transfer_id_foreign', 'hub_transfer_events_hub_transport_driver_id_foreign'],
        ];
        foreach ($foreignKeys as $table => $names) {
            $present = collect(Schema::getForeignKeys($table))->pluck('name');
            foreach ($names as $name) {
                if (! $present->contains($name)) {
                    throw new RuntimeException("Hub schema verification failed: foreign key `{$name}` is missing from `{$table}`.");
                }
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This hub logistics schema migration is forward-only. Automatic rollback is disabled to preserve ownership and transfer records.'
        );
    }
};
