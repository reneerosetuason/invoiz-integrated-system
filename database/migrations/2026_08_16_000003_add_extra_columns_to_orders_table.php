<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('sub_total', 12, 2)->default(0)->after('total');
            $table->decimal('discount_total', 12, 2)->default(0)->after('sub_total');
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('discount_total');
            $table->decimal('tax_total', 12, 2)->default(0)->after('shipping_fee');
            $table->decimal('commission_amount', 12, 2)->default(0)->after('tax_total');
            $table->enum('delivery_status', ['pending', 'assigned', 'in_transit', 'delivered'])->default('pending')->after('payment_status');
            $table->text('notes')->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sub_total', 'discount_total', 'shipping_fee', 'tax_total', 'commission_amount', 'delivery_status', 'notes']);
        });
    }
};
