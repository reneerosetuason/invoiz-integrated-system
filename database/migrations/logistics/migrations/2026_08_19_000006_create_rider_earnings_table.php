<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('delivery'); // delivery | bonus | adjustment
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('earned_on');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'earned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_earnings');
    }
};