<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Philippine Standard Geographic Code (PSGC) reference tables seeded from
     * the official PSA data. Provides the Province → City/Municipality →
     * Barangay hierarchy used by the structured address pickers in the rider
     * and logistics-center application flows.
     */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 9)->unique();
            $table->string('name', 255);
            $table->string('region_code', 9);
            $table->timestamps();
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 9)->unique();
            $table->string('name', 255);
            $table->foreignId('province_id')->constrained()->onDelete('cascade');
            $table->string('region_code', 9);
            $table->timestamps();
        });

        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('code', 9)->unique();
            $table->string('name', 255);
            $table->foreignId('municipality_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
    }
};