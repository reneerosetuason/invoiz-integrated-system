<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsSetting;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Services\ReturnToSellerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FailedDeliveryDatabase;
use Tests\TestCase;

class FailedDeliveryRedeliveryTest extends TestCase
{
    use \Tests\Support\FailedDeliveryScenario;

    public function test_failed_parcel_waits_at_destination_hub_without_cancelling_or_refunding(): void
    {
        $this->riderPost('failed', ['reason' => 'Customer refused order'])->assertOk();
        $this->riderPost('return-to-center')->assertOk();
        $delivery = $this->delivery->fresh();
        $this->assertSame('delivery_failed', $delivery->status);
        $this->assertSame('sorted', $delivery->parcel_status);
        $this->assertSame($this->hub->id, $delivery->center_id);
        $this->assertNotNull($delivery->returned_at);
        $this->assertNull($delivery->dispatched_at);
        $this->assertSame('Customer refused order', $delivery->return_reason);
        $this->assertSame('out_for_delivery', $delivery->order->status);
        $this->assertSame('paid', $delivery->order->payment_status);
        $this->assertNull($delivery->cod_received_at);
        $state = app(ReturnToSellerService::class)->state($delivery);
        $this->assertSame('awaiting_redelivery', $state['state']);
        $this->assertFalse($state['can_dispatch']);
        $this->assertSame(0, DeliveryStatusLog::where('status', ReturnToSellerService::DISPATCHED)->count());
        $this->riderPost('return-to-center')->assertStatus(409);
    }

    public function test_staff_can_retry_twice_but_cannot_start_a_fourth_attempt(): void
    {
        $other = $this->eligibleRider($this->identity('rider', 'next.rider@example.test'));
        LogisticsSetting::create(['user_id' => $this->staff->id,
            'delivery' => ['max_attempts' => 1, 'allow_reassignment' => true]]);
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->riderPost('failed', ['reason' => 'Customer unavailable'])->assertOk();
            $this->actingAs($this->staff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
                ['rider_id' => $other->id])->assertStatus(409);
            $this->riderPost('return-to-center')->assertOk();
            $this->actingAs($this->staff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
                ['rider_id' => $other->id])->assertRedirect()->assertSessionHas('success');
            $fresh = $this->delivery->fresh();
            $this->assertSame('assigned', $fresh->status);
            $this->assertSame('awaiting_redelivery', app(ReturnToSellerService::class)->state($fresh)['state']);
            $this->assertNull($fresh->sorting_center_pickup_at);
            $this->assertNull($fresh->reattempt_requested_at);
            $this->riderUser = $other->user;
            $this->riderPost('sorting-center-pickup')->assertStatus(409);
            $this->actingAs($this->staff, 'web')->post(route('deliveries.dispatch', $fresh))
                ->assertRedirect()->assertSessionHas('success');
            $this->riderPost('sorting-center-pickup')->assertOk();
            Sanctum::actingAs($this->riderUser, ['rider']);
            $this->patchJson('/api/logistics/rider/deliveries/'.$fresh->id.'/status', ['status' => 'out_for_delivery'])
                ->assertOk();
            $this->assertNull($this->delivery->fresh()->returned_at);
            $this->assertSame($attempt, DeliveryStatusLog::where('status', 'delivery_failed')->count());
        }
        $this->riderPost('failed', ['reason' => 'Customer unavailable'])->assertOk();
        $this->riderPost('return-to-center')->assertOk();
        $this->actingAs($this->staff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
            ['rider_id' => $other->id])->assertRedirect()->assertSessionHasErrors('rider_id');
        $this->assertSame('delivery_failed', $this->delivery->fresh()->status);
        $this->assertSame(3, $this->delivery->fresh()->failedAttempts());
        $this->assertSame(1, Delivery::count());
        $this->assertSame('TRK-REDELIVERY-TEST', $this->delivery->fresh()->tracking_number);
        $this->assertSame('paid', $this->delivery->fresh()->order->payment_status);
    }

    public function test_another_rider_cannot_record_the_hub_return(): void
    {
        $this->delivery->update(['status' => 'delivery_failed']);
        $intruder = $this->identity('rider', 'intruder@example.test');
        $this->eligibleRider($intruder);
        Sanctum::actingAs($intruder, ['rider']);
        $this->postJson('/api/logistics/rider/deliveries/'.$this->delivery->id.'/return-to-center')->assertForbidden();
        $this->assertNull($this->delivery->fresh()->returned_at);
    }

    public function test_failed_parcels_cannot_be_dispatched_back_to_sellers(): void
    {
        $this->delivery->update(['status' => 'delivery_failed', 'returned_at' => now()]);
        $this->expectException(ValidationException::class);
        app(ReturnToSellerService::class)->dispatch($this->delivery, $this->staff);
    }

    public function test_damaged_parcels_keep_their_separate_seller_return_flow(): void
    {
        $this->delivery->update(['status' => 'damaged', 'returned_at' => now()]);
        $service = app(ReturnToSellerService::class);
        $this->assertTrue($service->state($this->delivery)['can_dispatch']);
        $service->dispatch($this->delivery, $this->staff);
        $this->assertSame('dispatched', $service->state($this->delivery->fresh())['state']);
    }

    public function test_historic_seller_returns_do_not_reenter_the_delivery_queue(): void
    {
        $this->delivery->update(['status' => 'delivery_failed', 'returned_at' => now()]);
        DeliveryStatusLog::create(['delivery_id' => $this->delivery->id,
            'status' => ReturnToSellerService::DISPATCHED]);
        $state = app(ReturnToSellerService::class)->state($this->delivery->fresh());
        $this->assertSame('dispatched', $state['state']);
        $this->assertTrue($state['can_confirm']);
        $this->actingAs($this->staff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
            ['rider_id' => $this->rider->id])->assertRedirect()->assertSessionHasErrors('rider_id');
        $this->assertSame('delivery_failed', $this->delivery->fresh()->status);
    }

    public function test_staff_from_another_hub_cannot_assign_a_returned_parcel(): void
    {
        $this->delivery->update(['status' => 'delivery_failed', 'returned_at' => now()]);
        $otherHub = LogisticsCenter::create(['name' => 'Other Hub', 'is_active' => true,
            'logistics_company_id' => $this->hub->logistics_company_id]);
        $otherStaff = $this->identity('staff', 'other.staff@example.test');
        $otherStaff->update(['center_id' => $otherHub->id,
            'logistics_company_id' => $this->hub->logistics_company_id]);
        $this->actingAs($otherStaff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
            ['rider_id' => $this->rider->id])->assertForbidden();
        $this->assertSame('delivery_failed', $this->delivery->fresh()->status);
    }

    public function test_retry_does_not_bypass_rider_eligibility(): void
    {
        $this->delivery->update(['status' => 'delivery_failed', 'returned_at' => now()]);
        $this->rider->update(['is_online' => false]);
        $this->actingAs($this->staff, 'web')->post(route('deliveries.assign-rider', $this->delivery),
            ['rider_id' => $this->rider->id])->assertRedirect()->assertSessionHasErrors('rider_id');
        $this->assertSame('delivery_failed', $this->delivery->fresh()->status);
    }
}
