<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Safe to re-run: the original database already received these
        // columns from the forward-only hub schema migration.
        if (! Schema::hasTable('hub_transport_drivers')) {
            return;
        }

        Schema::table('hub_transport_drivers', function (Blueprint $table): void {
            if (! Schema::hasColumn('hub_transport_drivers', 'email')) {
                $table->string('email')->nullable()->unique();
            }
            if (! Schema::hasColumn('hub_transport_drivers', 'password')) {
                $table->string('password')->nullable();
            }
            if (! Schema::hasColumn('hub_transport_drivers', 'logistics_truck_id')) {
                $table->foreignId('logistics_truck_id')->nullable()->constrained('logistics_trucks')->nullOnDelete();
            }
            if (! Schema::hasColumn('hub_transport_drivers', 'is_online')) {
                $table->boolean('is_online')->default(false);
            }
        });

        if (Schema::hasTable('hub_transfers')) {
            Schema::table('hub_transfers', function (Blueprint $table): void {
                if (! Schema::hasColumn('hub_transfers', 'origin_arrived_at')) {
                    $table->timestamp('origin_arrived_at')->nullable();
                }
                if (! Schema::hasColumn('hub_transfers', 'destination_arrived_at')) {
                    $table->timestamp('destination_arrived_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('hub_transfer_parcels')
            && ! Schema::hasColumn('hub_transfer_parcels', 'loaded_by_hub_driver_id')) {
            Schema::table('hub_transfer_parcels', function (Blueprint $table): void {
                $table->foreignId('loaded_by_hub_driver_id')->nullable()->constrained('hub_transport_drivers')->nullOnDelete();
            });
        }

        if (Schema::hasTable('hub_transfer_events')
            && ! Schema::hasColumn('hub_transfer_events', 'hub_transport_driver_id')) {
            Schema::table('hub_transfer_events', function (Blueprint $table): void {
                $table->foreignId('hub_transport_driver_id')->nullable()->constrained('hub_transport_drivers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Hub courier access columns are forward-only; dropping them would destroy driver credentials and transfer history.'
        );
    }
};
