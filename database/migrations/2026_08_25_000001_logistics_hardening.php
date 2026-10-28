<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->timestamp('estimated_delivery_at')->nullable()->after('pickup_pin');
            $table->string('cancellation_reason')->nullable()->after('failure_reason');
            $table->unsignedBigInteger('created_by')->nullable()->after('cancellation_reason');
            $table->timestamp('archived_at')->nullable()->after('created_by');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
            $table->string('archive_note')->nullable()->after('archived_by');

            $table->index('archived_at');
        });

        Schema::table('delivery_status_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('changed_by')->nullable()->after('notes');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->decimal('vehicle_capacity_kg', 8, 2)->nullable()->after('license_plate');
            $table->boolean('is_verified')->default(false)->after('status');
            $table->timestamp('approved_at')->nullable()->after('is_verified');
        });

        Schema::create('rider_application_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_application_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['rider_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_application_logs');

        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['vehicle_capacity_kg', 'is_verified', 'approved_at']);
        });

        Schema::table('delivery_status_logs', function (Blueprint $table) {
            $table->dropColumn('changed_by');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_delivery_at',
                'cancellation_reason',
                'created_by',
                'archived_at',
                'archived_by',
                'archive_note',
            ]);
        });
    }
};
