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
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\HubTransferService;
use App\Services\TripPaymentCalculator;
use App\Services\TripPaymentService;
use App\Support\EscrowEarnings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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
            $parcel = Delivery::create(array_diff_key($this->parcelData(), ['id' => true]) + ['tracking_number' => 'TP-'.uniqid(), 'order_id' => $order->id, 'center_id' => $origin->id, 'destination_center_id' => $destination->id, 'recipient_name' => 'Test recipient', 'recipient_address' => 'Test road', 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted']);
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
        \App\Models\PaymentDispute::create(['order_id' => $trip->parcels()->first()->delivery->order_id, 'buyer_id' => $actor->id, 'reason' => 'Parcel dispute', 'amount' => 100, 'status' => 'open']);
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
        $this->assertEquals(['reserved' => $hasLegacyLeg ? 0 : 20, 'released' => 0], TripPaymentService::orderBudget($parcel->order));
        $trip->update(['status' => 'received']);
        $calc = EscrowEarnings::calculateForSeller($parcel->order, $seller->user_id, $parcel->fresh());
        $this->assertEquals($hasLegacyLeg ? 420 : 400, $calc['inter_hub_driver_fee']);
        $this->assertEquals($hasLegacyLeg ? 400 : 380, $calc['platform_trip_adjustment']);
        $this->assertEquals(110, $calc['merch_logistics_deducted']); // 30 + 30 + 20 + 30, unchanged seller deduction.
        $this->assertTrue($calc['conserved']);
        EscrowEarnings::syncRemittancesForOrder($parcel->order);
        $oldPayout = EscrowRemittance::where('order_id', $parcel->order_id)->where('recipient_role', 'inter_hub_driver')->sole();
        $this->assertEquals($hasLegacyLeg ? 20 : 0, $oldPayout->amount);
        if ($legacy) {
            $this->assertEquals($legacy->id, $oldPayout->hub_transfer_id);
        }
    }
}
