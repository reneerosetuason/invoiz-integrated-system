<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only, additive allocation schema.
 *
 * - hub_transfer_earnings: one authoritative linehaul credit per seller
 *   shipment for the whole A -> B -> C route (unique delivery + role).
 * - escrow_remittances: the seven stakeholder allocations per seller group.
 * - Fee detail columns on payment_transactions / transactions.
 *
 * Every object is created only when missing, so it is safe on a database that
 * already received part of the schema. No existing rows are rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createHubTransferEarnings();
        $this->createEscrowRemittances();
        $this->addPaymentTransactionFees();
        $this->addTransactionDetails();
    }

    private function createHubTransferEarnings(): void
    {
        if (Schema::hasTable('hub_transfer_earnings')) {
            return;
        }

        Schema::create('hub_transfer_earnings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable()->index();
            $table->unsignedBigInteger('hub_transfer_id')->nullable()->index();
            $table->unsignedBigInteger('delivery_id')->index();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable()->index();
            $table->string('recipient_role', 40)->default('inter_hub_driver')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('earned_on')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'recipient_role'], 'hub_transfer_earnings_delivery_role_unique');
        });
    }

    private function createEscrowRemittances(): void
    {
        if (! Schema::hasTable('escrow_remittances')) {
            Schema::create('escrow_remittances', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('delivery_id')->nullable()->index();
                $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
                $table->unsignedBigInteger('hub_transfer_id')->nullable()->index();
                $table->unsignedBigInteger('hub_transport_driver_id')->nullable()->index();
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

                $table->unique(
                    ['order_id', 'seller_id', 'recipient_role'],
                    'escrow_remittances_order_seller_role_unique'
                );
            });

            return;
        }

        // Tolerate a table created by an earlier variant of the escrow work.
        foreach ([
            'hub_transfer_id' => fn (Blueprint $table) => $table->unsignedBigInteger('hub_transfer_id')->nullable()->index(),
            'hub_transport_driver_id' => fn (Blueprint $table) => $table->unsignedBigInteger('hub_transport_driver_id')->nullable()->index(),
            'payment_transaction_id' => fn (Blueprint $table) => $table->unsignedBigInteger('payment_transaction_id')->nullable()->index(),
        ] as $column => $definition) {
            if (! Schema::hasColumn('escrow_remittances', $column)) {
                Schema::table('escrow_remittances', $definition);
            }
        }

        if (! $this->hasIndex('escrow_remittances', 'escrow_remittances_order_seller_role_unique')) {
            Schema::table('escrow_remittances', function (Blueprint $table): void {
                $table->unique(
                    ['order_id', 'seller_id', 'recipient_role'],
                    'escrow_remittances_order_seller_role_unique'
                );
            });
        }
    }

    private function addPaymentTransactionFees(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table): void {
            foreach ([
                'origin_hub_fee',
                'pickup_rider_fee',
                'inter_hub_driver_fee',
                'destination_hub_fee',
                'last_mile_rider_fee',
                'seller_deficit',
            ] as $column) {
                if (! Schema::hasColumn('payment_transactions', $column)) {
                    $table->decimal($column, 12, 2)->default(0);
                }
            }
        });
    }

    private function addTransactionDetails(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            foreach ([
                'origin_center_id',
                'destination_center_id',
                'pickup_rider_id',
            ] as $column) {
                if (! Schema::hasColumn('transactions', $column)) {
                    $table->unsignedBigInteger($column)->nullable()->index();
                }
            }

            foreach ([
                'origin_hub_fee',
                'pickup_rider_fee',
                'inter_hub_driver_fee',
                'destination_hub_fee',
                'last_mile_rider_fee',
            ] as $column) {
                if (! Schema::hasColumn('transactions', $column)) {
                    $table->decimal($column, 12, 2)->default(0);
                }
            }
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Earnings and escrow allocation schema is forward-only; settled history must never be dropped automatically.'
        );
    }
};
