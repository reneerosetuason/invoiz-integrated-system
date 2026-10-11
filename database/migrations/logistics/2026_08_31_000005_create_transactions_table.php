<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number');
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('logistics_center_id')->nullable()->constrained('logistics_centers')->nullOnDelete();
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('rider_fee', 12, 2)->default(15);
            $table->decimal('admin_commission', 12, 2)->default(0);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('delivery_id');
            $table->index('rider_id');
            $table->index('logistics_center_id');
            $table->index('tracking_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
