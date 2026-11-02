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
        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 255);
                $table->string('region_name', 100)->nullable();
                $table->string('island_group_code', 50)->nullable();
                $table->timestamps();
            });
        }

        Schema::table('provinces', function (Blueprint $table) {
            $table->string('code', 20)->change();
            $table->string('region_code', 20)->change();
            $table->index('region_code');
        });

        // Drop the foreign key constraint on municipalities.province_id so it can be nullable
        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->change();
            $table->string('code', 20)->change();
            $table->string('region_code', 20)->change();
            $table->string('province_code', 20)->nullable()->after('province_id');
            $table->boolean('is_city')->default(false)->after('name');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
            $table->index(['region_code', 'province_code']);
        });

        Schema::table('barangays', function (Blueprint $table) {
            $table->string('code', 20)->change();
            $table->string('municipality_code', 20)->nullable()->after('municipality_id');
            $table->index('municipality_code');
        });

        Schema::table('rider_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_applications', 'region')) {
                $table->string('region', 255)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('rider_applications', 'region_code')) {
                $table->string('region_code', 20)->nullable()->after('region');
            }
            if (! Schema::hasColumn('rider_applications', 'province_code')) {
                $table->string('province_code', 20)->nullable()->after('province');
            }
            if (! Schema::hasColumn('rider_applications', 'municipality_code')) {
                $table->string('municipality_code', 20)->nullable()->after('municipality');
            }
            if (! Schema::hasColumn('rider_applications', 'barangay_code')) {
                $table->string('barangay_code', 20)->nullable()->after('barangay');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->dropColumn(['region', 'region_code', 'province_code', 'municipality_code', 'barangay_code']);
        });

        Schema::table('barangays', function (Blueprint $table) {
            $table->dropColumn('municipality_code');
        });

        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'is_city']);
        });

        Schema::dropIfExists('regions');
    }
};
