<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'seller_type')) {
                $table->string('seller_type', 100)->nullable()->after('line_of_business');
            }
            if (! Schema::hasColumn('sellers', 'tin')) {
                $table->string('tin', 50)->nullable()->after('seller_type');
            }
            if (! Schema::hasColumn('sellers', 'region')) {
                $table->string('region', 150)->nullable()->after('tin');
            }
            if (! Schema::hasColumn('sellers', 'province')) {
                $table->string('province', 150)->nullable()->after('region');
            }
            if (! Schema::hasColumn('sellers', 'city')) {
                $table->string('city', 150)->nullable()->after('province');
            }
            if (! Schema::hasColumn('sellers', 'barangay')) {
                $table->string('barangay', 150)->nullable()->after('city');
            }
            if (! Schema::hasColumn('sellers', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('barangay');
            }
            if (! Schema::hasColumn('sellers', 'address_line')) {
                $table->string('address_line', 255)->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('sellers', 'dti_certificate')) {
                $table->string('dti_certificate', 255)->nullable()->after('business_permit');
            }
            if (! Schema::hasColumn('sellers', 'bir_2303')) {
                $table->string('bir_2303', 255)->nullable()->after('dti_certificate');
            }
            if (! Schema::hasColumn('sellers', 'terms_accepted')) {
                $table->boolean('terms_accepted')->default(false)->after('approval_status');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'region')) {
                $table->string('region', 150)->nullable()->after('barangay');
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 150)->nullable()->after('region');
            }
            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 20)->nullable()->after('city');
            }
        });

        try {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` MODIFY `birthday` DATE NULL");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` MODIFY `age` INT UNSIGNED NULL");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` MODIFY `sex` ENUM('male','female','other') NULL");
        } catch (\Throwable $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $cols = ['seller_type', 'tin', 'region', 'province', 'city', 'barangay', 'postal_code', 'address_line', 'dti_certificate', 'bir_2303', 'terms_accepted'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('sellers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = ['region', 'city', 'postal_code'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
