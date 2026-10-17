<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer conversations table for fresh ecommerce DBs.
 * No migration in the project creates `conversations`, but
 * App\Models\Conversation + ChatBridge + shop chat routes need it.
 * Create only if missing; add seller_id if an older copy lacks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('buyer_id');
                $table->unsignedBigInteger('seller_id')->nullable();
                $table->string('subject', 150)->nullable();
                $table->timestamps();
                $table->index('buyer_id');
                $table->index('seller_id');
            });
        } else {
            Schema::table('conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('conversations', 'seller_id')) {
                    $table->unsignedBigInteger('seller_id')->nullable()->after('buyer_id');
                }
                if (! Schema::hasColumn('conversations', 'subject')) {
                    $table->string('subject', 150)->nullable()->after('seller_id');
                }
            });
        }
    }

    public function down(): void
    {
        // non-destructive: leave table as-is
    }
};
