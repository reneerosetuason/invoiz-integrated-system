<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('return_requests', 'evidence_path')) {
                $table->string('evidence_path')->nullable()->after('reason_details');
            }
            if (!Schema::hasColumn('return_requests', 'evidence_type')) {
                $table->string('evidence_type', 32)->nullable()->after('evidence_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (Schema::hasColumn('return_requests', 'evidence_type')) {
                $table->dropColumn('evidence_type');
            }
            if (Schema::hasColumn('return_requests', 'evidence_path')) {
                $table->dropColumn('evidence_path');
            }
        });
    }
};

