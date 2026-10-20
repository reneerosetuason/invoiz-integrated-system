<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sellers may no longer hide/remove buyer reviews — instead they reply to
 * them publicly. This stores the seller's single public reply per review.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'seller_reply')) {
                $table->text('seller_reply')->nullable()->after('comment');
            }
            if (! Schema::hasColumn('reviews', 'seller_replied_at')) {
                $table->timestamp('seller_replied_at')->nullable()->after('seller_reply');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasColumn('reviews', 'seller_reply')) {
                $table->dropColumn('seller_reply');
            }
            if (Schema::hasColumn('reviews', 'seller_replied_at')) {
                $table->dropColumn('seller_replied_at');
            }
        });
    }
};
