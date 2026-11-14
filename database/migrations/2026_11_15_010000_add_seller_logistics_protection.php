<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'packaging_cost')) {
            Schema::table('products', fn (Blueprint $t) => $t->decimal('packaging_cost', 12, 2)->nullable());
        }
        if (! Schema::hasColumn('orders', 'logistics_policy_snapshot')) {
            Schema::table('orders', fn (Blueprint $t) => $t->json('logistics_policy_snapshot')->nullable());
        }
        if (! Schema::hasColumn('payment_transactions', 'platform_logistics_subsidy')) {
            Schema::table('payment_transactions', fn (Blueprint $t) => $t->decimal('platform_logistics_subsidy', 14, 2)->default(0));
        }
        if (Schema::hasTable('return_settlements') && ! Schema::hasColumn('return_settlements', 'platform_subsidy_cents')) {
            Schema::table('return_settlements', fn (Blueprint $t) => $t->unsignedBigInteger('platform_subsidy_cents')->default(0));
        }
        if (Schema::hasTable('return_settlements') && ! Schema::hasColumn('return_settlements', 'seller_reimbursement_cents')) {
            Schema::table('return_settlements', fn (Blueprint $t) => $t->unsignedBigInteger('seller_reimbursement_cents')->default(0));
        }
        if (! Schema::hasTable('logistics_funding_accounts')) {
            Schema::create('logistics_funding_accounts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('funded_cents')->default(0);
                $t->unsignedBigInteger('reserved_cents')->default(0);
                $t->unsignedBigInteger('spent_cents')->default(0);
            });
        }
        DB::table('logistics_funding_accounts')->insertOrIgnore(['id' => 1]);
        if (! Schema::hasTable('logistics_funding_entries')) {
            Schema::create('logistics_funding_entries', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 120)->unique();
                $t->string('kind', 20);
                $t->unsignedBigInteger('amount_cents');
                $t->string('status', 20);
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->json('snapshot')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_funding_entries');
        Schema::dropIfExists('logistics_funding_accounts');
        Schema::table('return_settlements', fn (Blueprint $t) => $t->dropColumn(['platform_subsidy_cents', 'seller_reimbursement_cents']));
        Schema::table('payment_transactions', fn (Blueprint $t) => $t->dropColumn('platform_logistics_subsidy'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('logistics_policy_snapshot'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('packaging_cost'));
    }
};
