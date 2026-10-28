<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add rider personal-information fields (middle initial, sex, birthday,
     * age) and a structured address to rider_applications. The age is derived
     * on the server from the birth date at application time. Nullable so
     * existing application rows remain valid.
     */
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->string('middle_initial', 5)->nullable()->after('name');
            $table->enum('sex', ['male', 'female', 'other'])->nullable()->after('middle_initial');
            $table->date('birthday')->nullable()->after('sex');
            $table->unsignedTinyInteger('age')->nullable()->after('birthday');
            $table->string('house_number', 50)->nullable()->after('address');
            $table->string('street', 255)->nullable()->after('house_number');
            $table->string('barangay', 255)->nullable()->after('street');
            $table->string('municipality', 255)->nullable()->after('barangay');
            $table->string('province', 255)->nullable()->after('municipality');
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropColumn([
                'middle_initial',
                'sex',
                'birthday',
                'age',
                'house_number',
                'street',
                'barangay',
                'municipality',
                'province',
            ]);
        });
    }
};