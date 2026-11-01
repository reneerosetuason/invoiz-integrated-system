<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_maintenance_records')) {
            Schema::create('vehicle_maintenance_records', function (Blueprint $table): void {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('logistics_company_id')->constrained('logistics_companies')->restrictOnDelete();
                $table->foreignId('logistics_truck_id')->constrained('logistics_trucks')->cascadeOnDelete();
                $table->foreignId('vehicle_incident_id')->nullable()->constrained('vehicle_incidents')->nullOnDelete();
                $table->string('maintenance_type', 50)->default('routine_maintenance');
                $table->text('description');
                $table->string('service_provider', 255)->nullable();
                $table->decimal('estimated_cost', 12, 2)->nullable();
                $table->decimal('actual_cost', 12, 2)->nullable();
                $table->string('payment_responsibility', 30)->default('company'); // company, driver_owner, insurance, third_party, shared
                $table->text('responsibility_notes')->nullable();
                $table->string('invoice_attachment_path', 255)->nullable();
                $table->string('status', 30)->default('pending_assessment'); // pending_assessment, approved, in_repair, completed, cancelled
                $table->foreignId('authorized_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('returned_to_service_at')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['logistics_company_id', 'status']);
                $table->index(['logistics_truck_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenance_records');
    }
};
