<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add ownership and verification columns to logistics_trucks
        if (Schema::hasTable('logistics_trucks')) {
            Schema::table('logistics_trucks', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_trucks', 'ownership_type')) {
                    $table->string('ownership_type', 30)->default('company')->after('logistics_company_id');
                }
                if (! Schema::hasColumn('logistics_trucks', 'hub_transport_driver_id')) {
                    $table->foreignId('hub_transport_driver_id')
                        ->nullable()
                        ->after('ownership_type')
                        ->constrained('hub_transport_drivers')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('logistics_trucks', 'verification_status')) {
                    $table->string('verification_status', 30)->default('approved')->after('status');
                }
                if (! Schema::hasColumn('logistics_trucks', 'verification_notes')) {
                    $table->text('verification_notes')->nullable()->after('verification_status');
                }
                if (! Schema::hasColumn('logistics_trucks', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')
                        ->nullable()
                        ->after('verification_notes')
                        ->constrained('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('logistics_trucks', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
                if (! Schema::hasColumn('logistics_trucks', 'vehicle_registration')) {
                    $table->string('vehicle_registration', 100)->nullable()->after('plate_number');
                }
                if (! Schema::hasColumn('logistics_trucks', 'document_path')) {
                    $table->string('document_path', 255)->nullable()->after('vehicle_registration');
                }
            });
        }

        // 2. Add breakdown columns to hub_transfer_earnings
        if (Schema::hasTable('hub_transfer_earnings')) {
            Schema::table('hub_transfer_earnings', function (Blueprint $table) {
                if (! Schema::hasColumn('hub_transfer_earnings', 'base_amount')) {
                    $table->decimal('base_amount', 10, 2)->default(0.00)->after('recipient_role');
                }
                if (! Schema::hasColumn('hub_transfer_earnings', 'vehicle_allowance')) {
                    $table->decimal('vehicle_allowance', 10, 2)->default(0.00)->after('base_amount');
                }
                if (! Schema::hasColumn('hub_transfer_earnings', 'vehicle_ownership')) {
                    $table->string('vehicle_ownership', 30)->default('company')->after('vehicle_allowance');
                }
            });
        }

        // 3. Create vehicle_incidents table
        if (! Schema::hasTable('vehicle_incidents')) {
            Schema::create('vehicle_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
                $table->foreignId('logistics_truck_id')->constrained('logistics_trucks')->restrictOnDelete();
                $table->foreignId('hub_transport_driver_id')->nullable()->constrained('hub_transport_drivers')->nullOnDelete();
                $table->foreignId('hub_transfer_id')->nullable()->constrained('hub_transfers')->nullOnDelete();
                $table->string('incident_type', 50)->default('accident');
                $table->string('severity', 30)->default('minor'); // minor, moderate, severe, total_loss
                $table->string('status', 30)->default('reported'); // reported, under_investigation, under_repair, resolved, closed
                $table->dateTime('incident_date');
                $table->string('location')->nullable();
                $table->text('description');
                $table->text('damage_details')->nullable();
                $table->text('investigation_notes')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->string('vehicle_action_taken', 50)->nullable(); // marked_damaged, under_repair, retired, returned_to_service
                $table->json('evidence_photos')->nullable();
                $table->foreignId('reported_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('investigated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['logistics_company_id', 'status']);
                $table->index(['logistics_truck_id', 'status']);
            });
        }

        // 4. Modify rider_applications.vehicle_ownership to accommodate company/driver_owned
        if (Schema::hasTable('rider_applications')) {
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE rider_applications MODIFY COLUMN vehicle_ownership VARCHAR(50) NULL');
            } catch (\Throwable $e) {
                // Ignore if not supported or already modified
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_incidents');

        if (Schema::hasTable('hub_transfer_earnings')) {
            Schema::table('hub_transfer_earnings', function (Blueprint $table) {
                if (Schema::hasColumn('hub_transfer_earnings', 'vehicle_ownership')) {
                    $table->dropColumn('vehicle_ownership');
                }
                if (Schema::hasColumn('hub_transfer_earnings', 'vehicle_allowance')) {
                    $table->dropColumn('vehicle_allowance');
                }
                if (Schema::hasColumn('hub_transfer_earnings', 'base_amount')) {
                    $table->dropColumn('base_amount');
                }
            });
        }

        if (Schema::hasTable('logistics_trucks')) {
            Schema::table('logistics_trucks', function (Blueprint $table) {
                if (Schema::hasColumn('logistics_trucks', 'reviewed_by')) {
                    $table->dropForeign(['reviewed_by']);
                }
                if (Schema::hasColumn('logistics_trucks', 'hub_transport_driver_id')) {
                    $table->dropForeign(['hub_transport_driver_id']);
                }
                $table->dropColumn(array_filter([
                    Schema::hasColumn('logistics_trucks', 'ownership_type') ? 'ownership_type' : null,
                    Schema::hasColumn('logistics_trucks', 'hub_transport_driver_id') ? 'hub_transport_driver_id' : null,
                    Schema::hasColumn('logistics_trucks', 'verification_status') ? 'verification_status' : null,
                    Schema::hasColumn('logistics_trucks', 'verification_notes') ? 'verification_notes' : null,
                    Schema::hasColumn('logistics_trucks', 'reviewed_by') ? 'reviewed_by' : null,
                    Schema::hasColumn('logistics_trucks', 'reviewed_at') ? 'reviewed_at' : null,
                    Schema::hasColumn('logistics_trucks', 'vehicle_registration') ? 'vehicle_registration' : null,
                    Schema::hasColumn('logistics_trucks', 'document_path') ? 'document_path' : null,
                ]));
            });
        }
    }
};
