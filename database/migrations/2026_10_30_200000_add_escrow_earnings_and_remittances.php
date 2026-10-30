<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deliveries')) {
            Schema::table('deliveries', function (Blueprint $table) {
                if (! Schema::hasColumn('deliveries', 'origin_center_id')) {
                    $table->unsignedBigInteger('origin_center_id')->nullable()->after('center_id')->index();
                }
                if (! Schema::hasColumn('deliveries', 'inter_hub_rider_id')) {
                    $table->unsignedBigInteger('inter_hub_rider_id')->nullable()->after('sorting_center_pickup_rider_id')->index();
                }
                if (! Schema::hasColumn('deliveries', 'inter_hub_dispatched_at')) {
                    $table->timestamp('inter_hub_dispatched_at')->nullable()->after('inter_hub_rider_id');
                }
            });

            DB::table('deliveries')
                ->whereNull('origin_center_id')
                ->whereNotNull('center_id')
                ->update(['origin_center_id' => DB::raw('center_id')]);
        }

        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_transactions', 'origin_hub_fee')) {
                    $table->decimal('origin_hub_fee', 12, 2)->default(0)->after('logistics_fee');
                }
                if (! Schema::hasColumn('payment_transactions', 'pickup_rider_fee')) {
                    $table->decimal('pickup_rider_fee', 12, 2)->default(0)->after('origin_hub_fee');
                }
                if (! Schema::hasColumn('payment_transactions', 'inter_hub_driver_fee')) {
                    $table->decimal('inter_hub_driver_fee', 12, 2)->default(0)->after('pickup_rider_fee');
                }
                if (! Schema::hasColumn('payment_transactions', 'destination_hub_fee')) {
                    $table->decimal('destination_hub_fee', 12, 2)->default(0)->after('inter_hub_driver_fee');
                }
                if (! Schema::hasColumn('payment_transactions', 'last_mile_rider_fee')) {
                    $table->decimal('last_mile_rider_fee', 12, 2)->default(0)->after('destination_hub_fee');
                }
                if (! Schema::hasColumn('payment_transactions', 'seller_deficit')) {
                    $table->decimal('seller_deficit', 12, 2)->default(0)->after('last_mile_rider_fee');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'origin_center_id')) {
                    $table->unsignedBigInteger('origin_center_id')->nullable()->after('logistics_center_id');
                }
                if (! Schema::hasColumn('transactions', 'destination_center_id')) {
                    $table->unsignedBigInteger('destination_center_id')->nullable()->after('origin_center_id');
                }
                if (! Schema::hasColumn('transactions', 'pickup_rider_id')) {
                    $table->unsignedBigInteger('pickup_rider_id')->nullable()->after('rider_id');
                }
                if (! Schema::hasColumn('transactions', 'inter_hub_rider_id')) {
                    $table->unsignedBigInteger('inter_hub_rider_id')->nullable()->after('pickup_rider_id');
                }
                if (! Schema::hasColumn('transactions', 'origin_hub_fee')) {
                    $table->decimal('origin_hub_fee', 12, 2)->default(0)->after('logistics_fee');
                }
                if (! Schema::hasColumn('transactions', 'pickup_rider_fee')) {
                    $table->decimal('pickup_rider_fee', 12, 2)->default(0)->after('origin_hub_fee');
                }
                if (! Schema::hasColumn('transactions', 'inter_hub_driver_fee')) {
                    $table->decimal('inter_hub_driver_fee', 12, 2)->default(0)->after('pickup_rider_fee');
                }
                if (! Schema::hasColumn('transactions', 'destination_hub_fee')) {
                    $table->decimal('destination_hub_fee', 12, 2)->default(0)->after('inter_hub_driver_fee');
                }
                if (! Schema::hasColumn('transactions', 'last_mile_rider_fee')) {
                    $table->decimal('last_mile_rider_fee', 12, 2)->default(0)->after('destination_hub_fee');
                }
            });
        }

        if (! Schema::hasTable('escrow_remittances')) {
            Schema::create('escrow_remittances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_id')->nullable()->index();
                $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
                $table->string('recipient_role', 40)->index();
                $table->unsignedBigInteger('recipient_user_id')->nullable()->index();
                $table->unsignedBigInteger('rider_id')->nullable()->index();
                $table->unsignedBigInteger('logistics_center_id')->nullable()->index();
                $table->unsignedBigInteger('seller_id')->nullable()->index();
                $table->string('recipient_name', 191)->nullable();
                $table->string('rule_applied', 191)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('settlement_mode', 40)->default('escrow_bank_remittance');
                $table->string('status', 40)->default('held_in_escrow')->index();
                $table->string('bank_reference', 150)->nullable();
                $table->timestamp('remitted_at')->nullable();
                $table->unsignedBigInteger('remitted_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'seller_id', 'recipient_role'], 'escrow_remit_order_seller_role_idx');
                $table->index(['delivery_id', 'recipient_role'], 'escrow_remit_delivery_role_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_remittances');
    }
};

