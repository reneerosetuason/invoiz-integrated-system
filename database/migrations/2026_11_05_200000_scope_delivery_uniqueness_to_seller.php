<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('deliveries'));
        $old = $indexes->filter(fn ($i) => $i['unique'] && ! $i['primary'] && $i['columns'] === ['order_id']);
        if ($old->isEmpty()) { return; }
        // Install the replacement first so any order FK retains a supporting index.
        if (! $indexes->contains(fn ($i) => $i['unique'] && $i['columns'] === ['order_id', 'seller_id'])) {
            Schema::table('deliveries', fn (Blueprint $table) => $table->unique(['order_id', 'seller_id'], 'deliveries_order_seller_unique'));
        }
        foreach ($old as $index) {
            Schema::table('deliveries', fn (Blueprint $table) => $table->dropUnique($index['name']));
        }
    }
    public function down(): void { /* Shared orders may now have multiple seller parcels. */ }
};
