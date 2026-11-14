<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentDispute;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\HubRoadDistanceService;
use App\Services\HubTransferService;
use App\Services\TripPaymentCalculator;
use App\Services\TripPaymentService;
use App\Support\EscrowEarnings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Uses the allowlisted MySQL sandbox, with rollback; never migrate:fresh. */
class DynamicTripPaymentTest extends TestCase
{
    use DatabaseTransactions;

    private function rates(): array
    {
        return ['trip_base_payment' => 100, 'trip_rate_per_km' => 5, 'trip_bonus_50' => 50, 'trip_bonus_80' => 100, 'trip_volumetric_divisor' => 5000];
    }

    private function truckData(): array
    {
        return ['capacity_kg' => 1000, 'parcel_capacity' => 100, 'cargo_length_cm' => 200, 'cargo_width_cm' => 100, 'cargo_height_cm' => 100];
    }

    private function parcelData(int $id = 1): array
    {
        return ['id' => $id, 'weight' => 2, 'length_cm' => 20, 'width_cm' => 10, 'height_cm' => 10];
    }

    public function test_volume_distance_and_multiple_parcel_calculation(): void
    {
        $result = app(TripPaymentCalculator::class)->calculate($this->truckData(), [$this->parcelData(), $this->parcelData(2)], 40, $this->rates());
        $this->assertEqualsWithDelta(0.004, $result['volume'], 0.000001);
        $this->assertEquals(4, $result['weight']);
        $this->assertEquals(0.4, $result['weightUtilization']);
        $this->assertEquals(0.2, $result['volumeUtilization']);
        $this->assertSame(30000, $result['total']);
        $this->assertSame([1 => 15000, 2 => 15000], $result['allocations']);
    }

    public static function utilizationCases(): array
    {
        return [[49.99, 0], [50, 5000], [79.99, 5000], [80, 10000], [100, 10000]];
    }

    #[DataProvider('utilizationCases')]
    public function test_bonus_boundaries_use_the_higher_utilization(float $percent, int $bonus): void
    {
        $parcel = $this->parcelData();
        $parcel['weight'] = $percent * 10;
        $r = app(TripPaymentCalculator::class)->calculate($this->truckData(), [$parcel], 1, $this->rates());
        $this->assertSame($bonus, $r['bonus']);
        // Volume can independently determine the bonus, with a very light parcel.
        $parcel = ['id' => 1, 'weight' => 1, 'length_cm' => 200 * $percent / 100, 'width_cm' => 100, 'height_cm' => 100];
        $r = app(TripPaymentCalculator::class)->calculate($this->truckData(), [$parcel], 1, $this->rates());
        $this->assertSame($bonus, $r['bonus']);
    }

    public static function invalidCases(): array
    {
        return [
            'overweight' => [['weight' => 1001], [], 1],
            'excess volume' => [['length_cm' => 200, 'width_cm' => 100, 'height_cm' => 100], [], 1, true],
            'individual fit' => [['length_cm' => 201], [], 1],
            'zero actual weight' => [['weight' => 0], [], 1],
            'missing length' => [['length_cm' => null], [], 1],
            'negative width' => [['width_cm' => -1], [], 1],
            'missing truck measurements' => [[], ['cargo_height_cm' => null], 1],
            'missing route' => [[], [], 0],
        ];
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_loads_cannot_be_finalized(array $parcel, array $truck, float $distance, bool $duplicateLoad = false): void
    {
        $parcels = [array_replace($this->parcelData(), $parcel)];
        if ($duplicateLoad) {
            $parcels[] = $this->parcelData(2);
        }
        $this->expectException(ValidationException::class);
        app(TripPaymentCalculator::class)->calculate(array_replace($this->truckData(), $truck), $parcels, $distance, $this->rates());
    }

    public function test_centavo_allocation_uses_chargeable_weight_and_stable_remainders(): void
    {
        $rates = $this->rates();
        $rates['trip_base_payment'] = 0.01;
        $rates['trip_rate_per_km'] = 0;
        $r = app(TripPaymentCalculator::class)->calculate($this->truckData(), [$this->parcelData(3), $this->parcelData(2), $this->parcelData(1)], 1, $rates);
        $this->assertSame([1 => 1, 2 => 0, 3 => 0], $r['allocations']);
        $rates['trip_base_payment'] = 9;
        $heavyVolume = array_replace($this->parcelData(2), ['weight' => 1, 'length_cm' => 100, 'width_cm' => 50, 'height_cm' => 40]); // 40kg chargeable
        $r = app(TripPaymentCalculator::class)->calculate($this->truckData(), [$this->parcelData(), $heavyVolume], 1, $rates);
        $this->assertSame(900, array_sum($r['allocations']));
        $this->assertGreaterThan($r['allocations'][1], $r['allocations'][2]);
    }

    private function fixture(int $count = 3): array
    {
        EscrowEarnings::updateRates($this->rates());
        $actor = User::factory()->create(['role' => 'logistics_staff', 'status' => 'active', 'account_status' => 'active']);
        $company = LogisticsCompany::create(['name' => 'Trip test '.uniqid(), 'status' => 'active']);
        $origin = LogisticsCenter::create(['name' => 'Trip origin', 'address' => 'Test road', 'city' => 'Test city', 'is_active' => true, 'logistics_company_id' => $company->id]);
        $destination = LogisticsCenter::create(['name' => 'Trip destination', 'address' => 'Other road', 'city' => 'Test city', 'is_active' => true, 'logistics_company_id' => $company->id]);
        $actor->forceFill(['center_id' => $origin->id, 'logistics_company_id' => $company->id])->save();
        $truck = LogisticsTruck::create($this->truckData() + ['logistics_company_id' => $company->id, 'plate_number' => 'TP-'.uniqid(), 'vehicle_type' => 'truck', 'status' => 'assigned', 'verification_status' => 'approved', 'ownership_type' => 'company']);
        $driver = HubTransportDriver::create(['name' => 'Trip driver', 'email' => uniqid().'@trip.test', 'password' => 'password123', 'logistics_company_id' => $company->id, 'logistics_truck_id' => $truck->id, 'status' => 'assigned', 'is_online' => true]);
        $trip = HubTransfer::create(['payment_version' => 1, 'reference' => 'TP-'.uniqid(), 'logistics_company_id' => $company->id, 'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id, 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'status' => 'loading', 'route_distance_km' => 60, 'route_distance_source' => 'Approved road route 1', 'route_recorded_by' => $actor->id]);
        for ($i = 0; $i < $count; $i++) {
            $address = Address::create(['buyer_id' => $actor->id, 'recipient_name' => 'Trip buyer', 'phone' => '09171234567', 'address_line' => '2 Test St', 'barangay' => 'Test', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
            $order = Order::create(['address_id' => $address->id, 'buyer_id' => $actor->id, 'status' => 'processing', 'payment_status' => 'paid', 'total' => 1000, 'total_amount' => 1000]);
            PaymentTransaction::create(['order_id' => $order->id, 'transaction_ref' => 'TP-'.uniqid(), 'gateway' => 'cash_on_delivery', 'amount' => 1000, 'status' => 'successful']);
            $product = Product::create(['category_id' => Category::factory()->create()->id, 'seller_id' => $actor->id, 'name' => 'Trip cargo', 'slug' => 'cargo-'.uniqid(), 'status' => 'active', 'price' => 10000, 'stock' => 5]);
            OrderItem::create(['order_id' => $order->id, 'seller_id' => $actor->id, 'product_id' => $product->id, 'product_name' => 'Trip cargo', 'quantity' => 1, 'unit_price' => 10000]);
            $parcel = Delivery::create(array_diff_key($this->parcelData(), ['id' => true]) + ['seller_id' => $actor->id, 'tracking_number' => 'TP-'.uniqid(), 'order_id' => $order->id, 'center_id' => $origin->id, 'destination_center_id' => $destination->id, 'recipient_name' => 'Test recipient', 'recipient_address' => 'Test road', 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted']);
            HubTransferParcel::create(['hub_transfer_id' => $trip->id, 'delivery_id' => $parcel->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);
        }

        return [$trip, $actor, $truck, $driver];
    }

    private function confirm(HubTransfer $trip, User $actor): void
    {
        TripPaymentService::confirm($trip, $actor, hash('sha256', json_encode(TripPaymentService::preview($trip->fresh()))));
    }

    public function test_reservation_completion_and_retries_create_one_exact_trip_payment(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        $this->confirm($trip, $actor);
        $trip->refresh();
        $this->assertSame(40000, $trip->payment_snapshot['total']);
        $this->assertEquals(40000, $trip->parcels()->sum('driver_allocation_cents'));
        $this->assertSame('held_in_escrow', EscrowRemittance::where('hub_transfer_id', $trip->id)->sole()->status);
        $trip->update(['status' => 'arrived']);
        $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'good']);
        HubTransferService::complete($trip, $actor);
        HubTransferService::complete($trip, $actor);
        TripPaymentService::complete($trip->fresh());
        $this->assertEquals(400, HubTransferEarning::where('hub_transfer_id', $trip->id)->sole()->amount);
        $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole();
        $this->assertEquals(400, $payout->amount);
        $this->assertSame('pending_remittance', $payout->status);
        $this->assertNull($payout->order_id);
        $this->assertEquals(1, HubTransferEarning::where('hub_transfer_id', $trip->id)->count());
    }

    public function test_historical_rates_and_completed_earnings_are_preserved(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        EscrowEarnings::updateRates(['trip_base_payment' => 900, 'trip_rate_per_km' => 90]);
        TripPaymentService::assertUnchanged($trip->fresh());
        $trip->update(['status' => 'received']);
        $earning = TripPaymentService::complete($trip->fresh());
        $this->assertEquals(400, $earning->amount);
        $this->assertEquals(100, $trip->fresh()->payment_snapshot['rates']['trip_base_payment']);
        $trip->update(['payment_version' => null]);
        $legacy = EscrowEarnings::creditInterHubDriverForCompletedTrip($trip->fresh());
        $this->assertEquals(400, $legacy->amount);
    }

    public function test_cancellation_reverses_reservation_and_keeps_snapshot_event(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        HubTransferService::cancel($trip, $actor, 'Truck breakdown');
        $this->assertNull($trip->fresh()->payment_snapshot);
        $this->assertSame('voided', EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->status);
        $this->assertSame(1, $trip->events()->where('event_type', 'payment_reversed')->count());
        $this->assertNull($trip->parcels()->first()->driver_allocation_cents);
    }

    public function test_changed_manifest_invalidates_confirmation_and_preview(): void
    {
        [$trip, $actor] = $this->fixture();
        $hash = hash('sha256', json_encode(TripPaymentService::preview($trip)));
        $trip->parcels()->first()->delivery->update(['weight' => 3]);
        try {
            TripPaymentService::confirm($trip, $actor, $hash);
            $this->fail('Stale preview accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->confirm($trip, $actor);
        $trip->parcels()->first()->delivery->update(['length_cm' => 30]);
        $this->expectException(HttpException::class);
        TripPaymentService::assertUnchanged($trip->fresh());
    }

    public function test_reverse_and_reconfirm_reuses_unique_settlement_without_duplicates(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        DB::transaction(fn () => TripPaymentService::reverse(HubTransferService::lockTransfer($trip->id), $actor, 'Driver assignment changed'));
        $newDriver = $trip->driver->replicate();
        $newDriver->email = uniqid().'@trip.test';
        $newDriver->save();
        $trip->update(['driver_id' => $newDriver->id]);
        $this->confirm($trip, $actor);
        $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole();
        $this->assertSame($newDriver->id, $payout->hub_transport_driver_id);
        $this->assertSame('held_in_escrow', $payout->status);
    }

    public function test_admin_settings_are_authorized_and_validate_dynamic_rates(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'is_admin' => false, 'status' => 'active', 'account_status' => 'active']);
        $data = $this->rates() + ['origin_hub_fee' => 30, 'pickup_rider_per_item' => 30, 'destination_hub_fee' => 30, 'last_mile_rider_fee' => 50];
        $this->actingAs($buyer)->post('/admin/commission/earnings', $data)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active']);
        $this->actingAs($admin)->post('/admin/commission/earnings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(100.0, EscrowEarnings::rates()['trip_base_payment']);
        $this->actingAs($admin)->post('/admin/commission/earnings', array_replace($data, ['trip_volumetric_divisor' => 0]))->assertSessionHasErrors('trip_volumetric_divisor');
    }

    public function test_staff_can_review_trip_payment_and_other_hub_staff_cannot_edit_it(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->actingAs($actor)->get(route('hub-transfers.show', $trip))->assertOk()->assertSee('Driver trip payment')->assertSee('400.00');
        $this->actingAs($actor)->post(route('hub-transfers.payment.confirm', $trip), ['preview_hash' => hash('sha256', json_encode(TripPaymentService::preview($trip)))])->assertSessionHasNoErrors()->assertRedirect();
        $actor->forceFill(['center_id' => $trip->destination_center_id])->save();
        $this->actingAs($actor)->post(route('hub-transfers.payment.reverse', $trip), ['reason' => 'Wrong hub'])->assertForbidden();
    }

    public function test_reassignment_reverses_the_payment_before_reconfirmation(): void
    {
        [$trip, $actor, $truck, $driver] = $this->fixture();
        $this->confirm($trip, $actor);
        $newTruck = $truck->replicate();
        $newTruck->plate_number = 'TP-'.uniqid();
        $newTruck->status = 'available';
        $newTruck->save();
        $newDriver = $driver->replicate();
        $newDriver->email = uniqid().'@trip.test';
        $newDriver->status = 'available';
        $newDriver->logistics_truck_id = $newTruck->id;
        $newDriver->save();
        HubTransferService::reassignFleet($trip, $newTruck, $newDriver, $actor);
        $this->assertNull($trip->fresh()->payment_confirmed_at);
        $this->assertSame('voided', EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->status);
        $this->confirm($trip, $actor);
        $this->assertSame($newDriver->id, EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->hub_transport_driver_id);
    }

    public function test_dispatch_requires_payment_confirmation(): void
    {
        [$trip, $actor] = $this->fixture();
        $trip->update(['status' => 'ready_for_dispatch']);
        $this->expectException(HttpException::class);
        HubTransferService::dispatch($trip, $actor);
    }

    public function test_real_dispatch_receive_and_admin_release_are_idempotent(): void
    {
        [$trip, $actor, $truck, $driver] = $this->fixture();
        \App\Models\PayoutAccount::create(['owner_type' => 'driver', 'owner_id' => $driver->id, 'details' => ['provider' => 'Test bank', 'holder' => $driver->name, 'account' => '000123456789']]);
        $this->confirm($trip, $actor);
        $trip->update(['status' => 'ready_for_dispatch']);
        HubTransferService::dispatch($trip, $actor);
        HubTransferService::dispatch($trip, $actor);
        HubTransferService::arrive($trip, $actor);
        foreach ($trip->parcels as $entry) {
            HubTransferService::receiveParcel($trip, $entry->delivery_id, 'good', null, $actor);
        }
        HubTransferService::complete($trip, $actor);
        $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole();
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active']);
        $this->actingAs($admin)->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'BANK-TRIP-TEST'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($admin)->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'BANK-SECOND'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('BANK-TRIP-TEST', $payout->fresh()->bank_reference);
        $this->assertSame('remitted', $payout->fresh()->status);
        $this->assertTrue($payout->fresh()->accept($driver));
        $this->assertFalse($payout->fresh()->accept($driver));
        $this->assertEquals(400, $driver->earnings()->sum('amount'));
    }

    public function test_missing_and_damaged_cargo_hold_the_entire_trip_payout(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        $trip->update(['status' => 'received']);
        $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'damaged']);
        TripPaymentService::refreshEligibility($trip);
        $this->assertSame('held_in_escrow', EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->status);
        $this->expectException(HttpException::class);
        TripPaymentService::assertReleasable($trip->fresh());
    }

    public function test_a_dispute_on_any_order_blocks_release_even_after_it_was_marked_ready(): void
    {
        [$trip, $actor] = $this->fixture();
        $this->confirm($trip, $actor);
        $trip->update(['status' => 'received']);
        $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'good']);
        TripPaymentService::refreshEligibility($trip);
        $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole();
        $this->assertSame('pending_remittance', $payout->status);
        PaymentDispute::create(['order_id' => $trip->parcels()->first()->delivery->order_id, 'buyer_id' => $actor->id, 'reason' => 'Parcel dispute', 'amount' => 100, 'status' => 'open']);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active']);
        $this->actingAs($admin)->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'BANK-BLOCKED'])->assertUnprocessable();
        $this->assertNull($payout->fresh()->bank_reference);
        TripPaymentService::refreshEligibility($trip);
        $this->assertSame('held_in_escrow', $payout->fresh()->status);
    }

    public static function priorLegCases(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('priorLegCases')]
    public function test_seller_budgets_are_reserved_and_reconciled_without_extra_checkout_charges(bool $hasLegacyLeg): void
    {
        [$trip, $actor] = $this->fixture(1);
        $parcel = $trip->parcels()->first()->delivery;
        $parcel->order->items()->delete();
        $seller = Seller::factory()->create(['status' => 'active', 'line_of_business' => 'Retail', 'seller_type' => 'individual', 'tin' => 'TEST', 'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Test', 'postal_code' => '1000', 'address_line' => 'Test road']);
        $product = Product::create(['category_id' => Category::factory()->create()->id, 'seller_id' => $seller->user_id, 'name' => 'Trip parcel product', 'slug' => 'trip-'.uniqid(), 'status' => 'active', 'price' => 1000, 'stock' => 5]);
        OrderItem::create(['order_id' => $parcel->order_id, 'product_id' => $product->id, 'seller_id' => $seller->user_id, 'quantity' => 1, 'price' => 1000, 'unit_price' => 1000, 'product_name' => 'Trip parcel product']);
        $parcel->update(['seller_id' => $seller->user_id]);
        $legacy = null;
        if ($hasLegacyLeg) {
            $legacy = $trip->replicate();
            $legacy->reference = 'LEGACY-'.uniqid();
            $legacy->payment_version = null;
            $legacy->status = 'received';
            $legacy->created_at = now()->subDay();
            $legacy->save();
            HubTransferParcel::create(['hub_transfer_id' => $legacy->id, 'delivery_id' => $parcel->id, 'loading_status' => 'loaded', 'receiving_status' => 'received', 'condition' => 'good']);
            EscrowEarnings::syncRemittancesForOrder($parcel->order);
        }
        $this->confirm($trip, $actor);
        $this->assertEquals(['reserved' => $hasLegacyLeg ? 30 : 50, 'released' => 0], TripPaymentService::orderBudget($parcel->order));
        $trip->update(['status' => 'received']);
        $calc = EscrowEarnings::calculateForSeller($parcel->order, $seller->user_id, $parcel->fresh());
        $this->assertEquals(50, $calc['inter_hub_driver_fee']);
        $this->assertEquals(0, $calc['platform_trip_adjustment']);
        $this->assertEquals(140, $calc['merch_logistics_deducted']);
        $this->assertTrue($calc['conserved']);
        EscrowEarnings::syncRemittancesForOrder($parcel->order);
        $oldPayout = EscrowRemittance::where('order_id', $parcel->order_id)->where('recipient_role', 'inter_hub_driver')->sole();
        $this->assertEquals($hasLegacyLeg ? 20 : 0, $oldPayout->amount);
        if ($legacy) {
            $this->assertEquals($legacy->id, $oldPayout->hub_transfer_id);
        }
    }

    private function automaticInputs(?string $scenario = null): array
    {
        [$trip, $actor] = $this->fixture(1);
        $trip->update(['route_distance_km' => null, 'route_distance_source' => null, 'route_recorded_by' => null]);
        $delivery = $trip->parcels()->first()->delivery;
        $delivery->order->items()->delete();
        $delivery->update(['seller_id' => $actor->id, 'weight' => null, 'length_cm' => null, 'width_cm' => null, 'height_cm' => null]);
        $product = Product::create(['category_id' => Category::factory()->create()->id, 'seller_id' => $actor->id,
            'name' => 'Measured item', 'slug' => 'auto-trip-'.uniqid(), 'status' => 'active', 'price' => 100, 'stock' => 10,
            'weight' => '300 g', 'dimensions' => '6 * 6 * 16cm']);
        OrderItem::create(['order_id' => $delivery->order_id, 'product_id' => $product->id, 'seller_id' => $actor->id,
            'quantity' => 2, 'price' => 100, 'unit_price' => 100, 'product_name' => $product->name]);
        Http::preventStrayRequests();
        Http::fake([
            '*photon*' => fn ($request) => Http::response(['features' => [
                ['properties' => ['city' => 'Test city', 'countrycode' => 'PH', 'street' => str_contains($request['q'], 'Other road') ? 'Other road' : 'Test road', 'type' => $scenario === 'city' ? 'city' : 'street'],
                    'geometry' => ['coordinates' => str_contains($request['q'], 'Other road') ? [121.1, 14.8] : [121.01, 14.6]]],
            ]]),
            '*route/v1/*' => Http::response(['code' => $scenario === 'no_route' ? 'NoRoute' : 'Ok', 'routes' => [['distance' => 75432]]], $scenario === 'outage' ? 503 : 200),
        ]);

        return [$trip, $actor, $delivery, $product];
    }

    public function test_automatic_route_and_seller_measurements_are_previewed_then_frozen_on_confirmation(): void
    {
        [$trip, $actor, $delivery, $product] = $this->automaticInputs();
        $foreignSeller = User::factory()->create();
        OrderItem::create(['order_id' => $delivery->order_id, 'product_id' => $product->id, 'seller_id' => $foreignSeller->id,
            'quantity' => 50, 'price' => 100, 'unit_price' => 100, 'product_name' => 'Other seller item']);
        $s = TripPaymentService::preview($trip);
        $this->assertSame(75.432, $s['distance']);
        $this->assertEquals(0.6, $s['weight']);
        $this->assertEqualsWithDelta(0.001152, $s['volume'], 0.0000001);
        $this->assertEquals(32, $s['parcels'][0]['height_cm']);
        $this->assertSame(47716, $s['uncapped_total']);
        $this->assertSame(1000, $s['total']);
        $this->assertNull($delivery->fresh()->length_cm);
        $this->assertNull($trip->fresh()->route_distance_km);
        TripPaymentService::confirm($trip, $actor, hash('sha256', json_encode($s)));
        $this->assertEquals(32, $delivery->fresh()->height_cm);
        $this->assertEquals(75.432, $trip->fresh()->route_distance_km);
        $this->assertSame($actor->id, (int) $trip->fresh()->route_recorded_by);
        $product->update(['dimensions' => '100 x 100 x 100 cm', 'weight' => '200 kg']);
        TripPaymentService::assertUnchanged($trip->fresh());
        $this->assertEquals(0.6, $trip->fresh()->payment_snapshot['weight']);
        $this->assertSame(1, EscrowRemittance::where('hub_transfer_id', $trip->id)->count());
        Http::assertSentCount(3); // Two geocodes, one cached road route.
    }

    public function test_seller_changes_require_reviewing_a_new_automatic_preview(): void
    {
        [$trip, $actor, $delivery, $product] = $this->automaticInputs();
        $s = TripPaymentService::preview($trip);
        $product->update(['dimensions' => '10 x 10 x 20 cm']);
        try {
            TripPaymentService::confirm($trip, $actor, hash('sha256', json_encode($s)));
            $this->fail('Stale product measurements were accepted.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertNull($delivery->fresh()->length_cm);
        $this->assertFalse(EscrowRemittance::where('hub_transfer_id', $trip->id)->exists());
    }

    public function test_recalculation_ignores_submitted_distance_and_measurement_overrides(): void
    {
        [$trip, $actor, $delivery] = $this->automaticInputs();
        $this->actingAs($actor)->post(route('hub-transfers.payment.prepare', $trip), [
            'route_distance_km' => 1, 'route_distance_source' => 'Forged',
            'parcels' => [$delivery->id => ['weight' => 999, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $s = TripPaymentService::preview($trip->fresh());
        $this->assertSame(75.432, $s['distance']);
        $this->assertEquals(0.6, $s['weight']);
        $this->assertEquals(32, $s['parcels'][0]['height_cm']);
    }

    public function test_unavailable_road_route_is_not_replaced_with_straight_line_distance(): void
    {
        [$trip] = $this->automaticInputs('no_route');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No valid road route');
        TripPaymentService::preview($trip);
    }

    public function test_geocoding_a_city_centroid_cannot_authorize_a_road_distance(): void
    {
        [$trip] = $this->automaticInputs('city');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('could not be located unambiguously');
        TripPaymentService::preview($trip);
    }

    public function test_routing_outage_gives_a_retryable_validation_error(): void
    {
        [$trip] = $this->automaticInputs('outage');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('temporarily unavailable');
        TripPaymentService::preview($trip);
    }

    public function test_missing_product_dimensions_block_payment_without_inventing_measurements(): void
    {
        [$trip, $actor, $delivery, $product] = $this->automaticInputs();
        $product->update(['dimensions' => null]);
        try {
            TripPaymentService::preview($trip);
            $this->fail('Missing dimensions accepted.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('length', $e->getMessage());
        }
        $this->assertNull($delivery->fresh()->length_cm);
        $this->assertFalse(EscrowRemittance::where('hub_transfer_id', $trip->id)->exists());
    }

    public function test_automatic_inputs_remain_visible_when_truck_cargo_dimensions_are_missing(): void
    {
        [$trip, $actor] = $this->automaticInputs();
        $trip->truck->update(['cargo_length_cm' => null]);
        $this->actingAs($actor)->get(route('hub-transfers.show', $trip))
            ->assertOk()->assertSee('Automatic road distance: 75.432 km')
            ->assertSee('Truck cargo length must be recorded')
            ->assertSee('value="32"', false)
            ->assertDontSee('name="route_distance_km"', false)
            ->assertDontSee('Confirm &amp; reserve', false);
    }

    public function test_road_routing_works_for_any_hub_pair_and_caches_each_direction_separately(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*photon*' => function ($request) {
                $city = str_contains($request['q'], 'Alpha') ? 'Alpha' : (str_contains($request['q'], 'Beta') ? 'Beta' : 'Gamma');
                $coordinates = ['Alpha' => [121, 14], 'Beta' => [122, 15], 'Gamma' => [123, 16]][$city];

                return Http::response(['features' => [[
                    'properties' => ['city' => $city, 'countrycode' => 'PH', 'name' => 'First Road', 'type' => 'street'],
                    'geometry' => ['coordinates' => $coordinates],
                ]]]);
            },
            '*route/v1/*' => fn ($request) => Http::response(['code' => 'Ok', 'routes' => [[
                'distance' => str_contains($request->url(), '121,14;122,15') ? 10000 : (str_contains($request->url(), '122,15;121,14') ? 12000 : 30000),
            ]]]),
        ]);
        $hub = fn ($city) => new LogisticsCenter(['name' => $city.' Hub', 'address' => 'First Road', 'city' => $city, 'province' => 'Laguna']);
        $router = app(HubRoadDistanceService::class);
        $this->assertEquals(10, $router->between($hub('Alpha'), $hub('Beta'))['distance']);
        $this->assertEquals(12, $router->between($hub('Beta'), $hub('Alpha'))['distance']);
        $this->assertEquals(30, $router->between($hub('Beta'), $hub('Gamma'))['distance']);
        $this->assertEquals(10, $router->between($hub('Alpha'), $hub('Beta'))['distance']);
        Http::assertSentCount(6);
    }
}
