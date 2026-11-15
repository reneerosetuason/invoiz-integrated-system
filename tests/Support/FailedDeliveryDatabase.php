<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Explicit, synthetic fixtures. Never runs application migrations. */
final class FailedDeliveryDatabase
{
    public static function create(): void
    {
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Failed-delivery fixtures require in-memory SQLite.');
        }
        $schema = Schema::connection('sqlite');
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('rider');
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('logistics_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->timestamps();
        });

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_verification')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('method')->nullable();
            $table->string('status')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->nullable();
            $table->string('priority')->nullable();
            $table->string('package_type')->nullable();
            $table->string('package_description')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('sender_address')->nullable();
            $table->decimal('sender_lat', 10, 7)->nullable();
            $table->decimal('sender_lng', 10, 7)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_address')->nullable();
            $table->decimal('recipient_lat', 10, 7)->nullable();
            $table->decimal('recipient_lng', 10, 7)->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->decimal('cod_amount_received', 10, 2)->nullable();
            $table->timestamp('cod_received_at')->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('pickup_pin')->nullable();
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('reattempt_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('sorting_center_pickup_at')->nullable();
            $table->unsignedBigInteger('sorting_center_pickup_rider_id')->nullable();
            $table->unsignedBigInteger('inter_hub_rider_id')->nullable();
            $table->timestamp('inter_hub_dispatched_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('name')->nullable();
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('status');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('type')->nullable();
            $table->string('signature_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_path')->nullable();
            $table->text('preferences')->nullable();
            $table->timestamps();
        });
        $schema->create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        $schema->create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        $schema->create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        $schema->create('courier_pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('courier')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        $schema->create('hub_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->unsignedBigInteger('arrived_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('planned_dispatch_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('origin_arrived_at')->nullable();
            $table->timestamp('destination_arrived_at')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfer_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id');
            $table->string('loading_status')->default('pending');
            $table->unsignedBigInteger('loaded_by')->nullable();
            $table->unsignedBigInteger('loaded_by_hub_driver_id')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->string('receiving_status')->default('pending');
            $table->string('condition')->nullable();
            $table->text('exception_reason')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        $schema->table('delivery_failures', function (Blueprint $table) {
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->nullable();
        });
        $schema->table('logistics_settings', function (Blueprint $table) {
            $table->json('notifications')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->json('delivery')->nullable();
        });
        $schema->table('riders', function (Blueprint $table) {
            $table->decimal('vehicle_capacity_kg', 8, 2)->nullable();
            $table->string('license_plate')->nullable();
        });
        $schema->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();
        });
        $schema->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('icon')->nullable();
            $table->string('priority')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('audience')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }
}
