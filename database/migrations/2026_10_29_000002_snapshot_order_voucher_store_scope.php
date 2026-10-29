<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_vouchers', 'seller_user_id')) {
            Schema::table('order_vouchers', function (Blueprint $table) {
                $table->unsignedBigInteger('seller_user_id')->nullable();
            });
        }

        // Capture the current interpretation before any ownership repairs.
        // Voucher records use sellers.id, while order groups use users.id.
        // Zero preserves a platform-wide discount on an existing order.
        $sellerUsers = DB::table('sellers')->pluck('user_id', 'id');
        $voucherScopes = DB::table('vouchers')->pluck('seller_id', 'id')->map(
            fn ($sellerId) => $sellerId ? (int) ($sellerUsers->get($sellerId) ?? $sellerId) : 0
        );
        foreach ($voucherScopes as $voucherId => $sellerUserId) {
            DB::table('order_vouchers')->where('voucher_id', $voucherId)->whereNull('seller_user_id')
                ->update(['seller_user_id' => $sellerUserId]);
        }
        DB::table('order_vouchers')->whereNull('seller_user_id')->update(['seller_user_id' => 0]);
    }

    public function down(): void
    {
        // Keep historical financial snapshots; rolling back must not discard them.
    }
};
