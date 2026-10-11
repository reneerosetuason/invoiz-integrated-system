<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->decimal('capacity_kg', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->string('vehicle_verification', 20)->default('pending')->after('vehicle_capacity_kg');
            $table->text('vehicle_verification_note')->nullable()->after('vehicle_verification');
            $table->timestamp('vehicle_verified_at')->nullable()->after('vehicle_verification_note');
            $table->unsignedBigInteger('vehicle_verified_by')->nullable()->after('vehicle_verified_at');
        });

        Schema::table('rider_applications', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('license_number', 100)->nullable()->after('address');
            $table->string('vehicle_registration', 100)->nullable()->after('license_plate');
            $table->json('documents')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropColumn(['address', 'license_number', 'vehicle_registration', 'documents']);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['vehicle_verification', 'vehicle_verification_note', 'vehicle_verified_at', 'vehicle_verified_by']);
        });

        Schema::dropIfExists('vehicle_types');
    }
};
