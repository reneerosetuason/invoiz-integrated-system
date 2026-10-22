<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Center-aware logistics notifications.
 *
 * audience = global  -> legacy/all-staff notification (default keeps old rows visible)
 * audience = center  -> only that center's staff and global admins
 * audience = admin   -> global admins only (for example an unrouted pickup)
 *
 * Existing rows intentionally remain "global" so this migration does not hide
 * historical notifications. New operational notifications set an audience and,
 * where applicable, the handling center.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'center_id')) {
                $table->foreignId('center_id')
                    ->nullable()
                    ->constrained('logistics_centers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('notifications', 'audience')) {
                $table->string('audience', 20)->default('global');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'center_id')) {
                $table->dropConstrainedForeignId('center_id');
            }

            if (Schema::hasColumn('notifications', 'audience')) {
                $table->dropColumn('audience');
            }
        });
    }
};
