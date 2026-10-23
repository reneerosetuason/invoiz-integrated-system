<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STATUSES = [
        'waiting_for_rider',
        'assigned',
        'accepted',
        'going_to_pickup',
        'arrived_at_shop',
        'picked_up',
        'out_for_delivery',
        'arrived_at_customer',
        'delivered',
        'delivery_failed',
        'lost',
        'damaged',
        'returned_to_seller',
        'cancelled',
    ];

    private const LEGACY = [
        'waiting_for_rider',
        'assigned',
        'accepted',
        'going_to_pickup',
        'arrived_at_shop',
        'picked_up',
        'out_for_delivery',
        'arrived_at_customer',
        'delivered',
        'delivery_failed',
        'cancelled',
    ];

    public function up(): void
    {
        $this->replaceStatusEnum(self::STATUSES);
    }

    public function down(): void
    {
        DB::table('deliveries')
            ->whereIn('status', ['lost', 'damaged', 'returned_to_seller'])
            ->update(['status' => 'delivery_failed']);

        $this->replaceStatusEnum(self::LEGACY);
    }

    private function replaceStatusEnum(array $statuses): void
    {
        $values = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

        DB::statement("ALTER TABLE deliveries MODIFY status ENUM({$values}) NOT NULL DEFAULT 'waiting_for_rider'");
    }
};
