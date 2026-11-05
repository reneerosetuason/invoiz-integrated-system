<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderShipmentService;
use App\Services\ShipmentWeightService;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class ShipmentWeightIntegrationTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
    }

    private function order(array $weights = ['180 g' => 1]): Order
    {
        $buyer = User::create(['name' => 'Buyer', 'email' => uniqid().'@example.test', 'password' => 'Password123!', 'role' => 'buyer']);
        $order = Order::create(['buyer_id' => $buyer->id, 'status' => 'pending', 'total' => 100, 'shipping_address' => ['recipient_name' => 'Buyer', 'address_line' => 'Test Street']]);
        foreach ($weights as $weight => $quantity) {
            $product = Product::create(['seller_id' => 88, 'name' => 'Weighted item', 'weight' => $weight, 'price' => 100, 'stock' => 10]);
            $order->items()->create(['seller_id' => 88, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => $quantity, 'price' => 100]);
        }

        return $order;
    }

    public function test_grams_are_converted_into_shipment_kilograms_at_creation(): void
    {
        $delivery = app(OrderShipmentService::class)->ensure($this->order());
        $this->assertSame(0.18, (float) $delivery->fresh()->weight);
        $this->assertDatabaseCount('deliveries', 1);
    }

    public function test_weight_sums_every_product_and_quantity_before_rounding(): void
    {
        $delivery = app(OrderShipmentService::class)->ensure($this->order(['180 g' => 3, '1.5 kg' => 2]));
        $this->assertSame(3.54, (float) $delivery->weight);
        $small = app(OrderShipmentService::class)->ensure($this->order(['1 g' => 15]));
        $this->assertSame(0.02, (float) $small->weight);
    }

    public function test_unknown_product_weight_keeps_parcel_unweighed_instead_of_using_a_partial_total(): void
    {
        $delivery = app(OrderShipmentService::class)->ensure($this->order(['180 g' => 2, 'unknown' => 1]));
        $this->assertNull($delivery->weight);
        $this->assertNull(app(ShipmentWeightService::class)->fillMissing($delivery));
    }

    public function test_existing_logistics_measurement_is_never_replaced_by_inventory_changes(): void
    {
        $order = $this->order();
        $delivery = app(OrderShipmentService::class)->ensure($order);
        $delivery->update(['weight' => 0.4]);
        Product::firstOrFail()->update(['weight' => '5 kg']);
        $this->assertSame(0.4, (float) app(OrderShipmentService::class)->ensure($order)->weight);
        $this->assertSame(0.4, app(ShipmentWeightService::class)->fillMissing($delivery));
    }

    public function test_variant_weight_overrides_base_weight_and_unrelated_variants_do_not(): void
    {
        $order = $this->order(['180 g' => 2]);
        $item = $order->items()->firstOrFail();
        $variant = ProductVariant::create(['product_id' => $item->product_id, 'weight' => 0.5, 'stock' => 1]);
        $item->update(['product_variant_id' => $variant->id]);
        $this->assertSame(1.0, app(ShipmentWeightService::class)->fromOrder($order));
        $variant->update(['product_id' => 999]);
        $this->assertSame(0.36, app(ShipmentWeightService::class)->fromOrder($order));
    }

    public function test_preview_resolves_legacy_weights_without_writing_and_assignment_repair_persists(): void
    {
        $order = $this->order();
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => 88, 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted']);
        $weights = app(ShipmentWeightService::class);
        $this->assertSame(0.18, $weights->fillMissing($delivery, false));
        $this->assertNull($delivery->fresh()->weight);
        $this->assertSame(0.18, $weights->fillMissing($delivery->fresh()));
        $this->assertSame(0.18, (float) $delivery->fresh()->weight);
        $this->assertSame('sorted', $delivery->fresh()->parcel_status);
        $this->assertDatabaseCount('delivery_status_logs', 0);
    }

    public function test_soft_deleted_products_still_supply_the_weight_for_existing_orders(): void
    {
        $order = $this->order();
        Product::firstOrFail()->delete();
        $this->assertSame(0.18, app(ShipmentWeightService::class)->fromOrder($order));
    }

    public function test_repair_command_previews_then_updates_only_missing_active_shipments(): void
    {
        $order = $this->order();
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => 88, 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted']);
        $existing = Delivery::create(['order_id' => $order->id, 'seller_id' => 88, 'status' => 'waiting_for_rider', 'weight' => 2]);
        $historical = Delivery::create(['order_id' => $order->id, 'seller_id' => 88, 'status' => 'delivered']);
        $this->artisan('shipments:repair-weights')->assertSuccessful();
        $this->assertNull($delivery->fresh()->weight);
        $this->artisan('shipments:repair-weights --apply')->assertSuccessful();
        $this->assertSame(0.18, (float) $delivery->fresh()->weight);
        $this->assertSame(2.0, (float) $existing->fresh()->weight);
        $this->assertNull($historical->fresh()->weight);
        $this->artisan('shipments:repair-weights --apply')->expectsOutputToContain('Saved 0 missing weights')->assertSuccessful();
    }

    public function test_supported_units_and_invalid_values_are_handled_without_guessing(): void
    {
        foreach (['180 g' => 0.18, '1.5 KG' => 1.5, '500 mg' => 0.0005, '2' => 2.0, '1 lb' => 0.45359237, '1 oz' => 0.028349523125] as $input => $expected) {
            $this->assertEqualsWithDelta($expected, ShipmentWeightService::kilograms($input), 0.000000001);
        }
        foreach ([null, '', '0 kg', '-1 kg', '1-2 kg', '1 kg per box of 6', '1,500 g', 'unknown', '10 ml'] as $input) {
            $this->assertNull(ShipmentWeightService::kilograms($input));
        }
    }
}
