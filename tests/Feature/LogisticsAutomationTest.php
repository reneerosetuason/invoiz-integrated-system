<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\LogisticsAlerts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsAutomationTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "automation-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Automation Street',
            'city' => 'Automation City',
            'is_active' => true,
        ]);
    }

    private function delivery(?LogisticsCenter $center = null, array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-20261020-7'.str_pad((string) $this->seq, 3, '0', STR_PAD_LEFT),
            'sender_name' => 'Automation Sender',
            'recipient_name' => 'Automation Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center?->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
        ], $overrides));
    }

    private function age(string $table, int $id, int $minutes = 180): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($minutes),
        ]);
    }

    private function dated(Delivery $delivery, string $dateTime): Delivery
    {
        DB::table('deliveries')->where('id', $delivery->id)->update([
            'created_at' => $dateTime,
            'updated_at' => $dateTime,
        ]);

        return $delivery->fresh();
    }

    public function test_pickup_request_helper_is_idempotent_and_queues_staff_notification(): void
    {
        $center = $this->center('Helper Center');
        $delivery = $this->delivery($center);

        $first = PickupRequest::openForDelivery($delivery);
        $second = PickupRequest::openForDelivery($delivery);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PickupRequest::where('delivery_id', $delivery->id)->count());
        $this->assertSame($center->id, $first->center_id);
        $this->assertDatabaseHas('notifications', [
            'type' => 'new_delivery_request',
            'title' => 'Pickup Request Needs Review',
            'center_id' => $center->id,
            'audience' => 'center',
        ]);
    }

    public function test_pending_filter_and_badge_share_the_same_eligibility(): void
    {
        $center = $this->center('Consistent Center');
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();

        $actionable = $this->delivery($center, ['status' => 'assigned', 'parcel_status' => 'received']);
        PickupRequest::create([
            'delivery_id' => $actionable->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $stale = $this->delivery($center, [
            'status' => 'out_for_delivery',
            'parcel_status' => 'dispatched',
            'picked_up_at' => now(),
        ]);
        PickupRequest::create([
            'delivery_id' => $stale->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests?status=pending')
            ->assertOk()
            ->assertSee('data-pickup-count="1"', false)
            ->assertSee($actionable->tracking_number)
            ->assertDontSee($stale->tracking_number);
    }

    public function test_cancelling_a_delivery_closes_its_open_pickup_request(): void
    {
        $center = $this->center('Cancellation Center');
        $delivery = $this->delivery($center, ['status' => 'assigned', 'parcel_status' => 'received']);
        $pickup = PickupRequest::openForDelivery($delivery, notify: false);

        $delivery->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->assertSame('completed', $pickup->fresh()->status);
        $this->assertNotNull($pickup->fresh()->reviewed_at);
    }

    public function test_dashboard_uses_parcel_stage_for_processing_and_separates_today(): void
    {
        $center = $this->center('Dashboard Center');
        $staff = $this->user('logistics_staff', $center->id);

        $processing = $this->delivery($center, [
            'status' => 'assigned',
            'parcel_status' => 'received',
        ]);

        $pickup = $this->delivery($center, [
            'status' => 'assigned',
            'parcel_status' => 'pending_arrival',
        ]);
        PickupRequest::create([
            'delivery_id' => $pickup->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $delivered = $this->delivery($center, [
            'status' => 'delivered',
            'parcel_status' => 'dispatched',
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($staff)->get('/logistics/dashboard')->assertOk();

        $this->assertSame(1, $response->viewData('needsAttention')['processing']);
        $this->assertSame(1, $response->viewData('needsAttention')['pickups']);
        $this->assertSame(1, $response->viewData('todayCounts')['delivered']);

        // The processing card must point at the parcel workflow, not the
        // unassigned-rider queue.
        $response->assertSee('Awaiting sorting or dispatch')->assertSee('Pickup requests to review');
    }

    // ------------------------------------------------ LOG-DASH-003 date filter

    public function test_dashboard_date_range_counts_shipments_on_both_boundary_dates(): void
    {
        $center = $this->center('Date Range Center');
        $staff = $this->user('logistics_staff', $center->id);

        $this->dated($this->delivery($center), '2026-03-01 09:00:00');
        $this->dated($this->delivery($center), '2026-03-10 09:00:00');
        $this->dated($this->delivery($center), '2026-03-15 09:00:00');
        $this->dated($this->delivery($center), '2026-03-20 09:00:00');
        $this->dated($this->delivery($center), '2026-03-25 09:00:00');

        $response = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=2026-03-10&date_to=2026-03-20')
            ->assertOk();

        $this->assertTrue($response->viewData('dateFilterActive'));
        $this->assertSame('2026-03-10', $response->viewData('dateFrom'));
        $this->assertSame('2026-03-20', $response->viewData('dateTo'));
        $this->assertSame(3, $response->viewData('deliveries')['total'], 'both boundary dates must be counted');
        $this->assertCount(3, $response->viewData('detailedRecords'));

        // Selected dates must remain visible in the filter after submission.
        $response->assertSee('value="2026-03-10"', false);
        $response->assertSee('value="2026-03-20"', false);
    }

    public function test_dashboard_start_date_filter_excludes_earlier_shipments(): void
    {
        $center = $this->center('Start Date Center');
        $staff = $this->user('logistics_staff', $center->id);

        $this->dated($this->delivery($center), '2026-04-01 09:00:00');
        $this->dated($this->delivery($center), '2026-04-10 09:00:00');
        $this->dated($this->delivery($center), '2026-04-20 09:00:00');

        $response = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=2026-04-10')
            ->assertOk();

        $this->assertNull($response->viewData('dateTo'));
        $this->assertSame(2, $response->viewData('deliveries')['total']);
    }

    public function test_dashboard_end_date_filter_excludes_later_shipments(): void
    {
        $center = $this->center('End Date Center');
        $staff = $this->user('logistics_staff', $center->id);

        $this->dated($this->delivery($center), '2026-06-01 09:00:00');
        $this->dated($this->delivery($center), '2026-06-10 09:00:00');
        $this->dated($this->delivery($center), '2026-06-20 09:00:00');

        $response = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_to=2026-06-10')
            ->assertOk();

        $this->assertNull($response->viewData('dateFrom'));
        $this->assertSame(2, $response->viewData('deliveries')['total']);
    }

    public function test_dashboard_date_filter_handles_an_empty_range_without_errors(): void
    {
        $center = $this->center('Empty Range Center');
        $staff = $this->user('logistics_staff', $center->id);
        $this->dated($this->delivery($center), '2026-03-15 09:00:00');

        $response = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=2026-12-01&date_to=2026-12-31')
            ->assertOk();

        $this->assertSame(0, $response->viewData('deliveries')['total']);
        $this->assertCount(0, $response->viewData('detailedRecords'));
    }

    public function test_dashboard_ignores_invalid_dates_and_safely_handles_a_reversed_range(): void
    {
        $center = $this->center('Invalid Date Center');
        $staff = $this->user('logistics_staff', $center->id);
        $this->dated($this->delivery($center), '2026-05-15 09:00:00');

        // Malformed and impossible calendar dates are treated as "not set".
        $invalid = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=not-a-date&date_to=2026-13-45')
            ->assertOk();
        $this->assertFalse($invalid->viewData('dateFilterActive'));
        $this->assertSame(1, $invalid->viewData('deliveries')['total']);

        // A reversed range is not an error: both bounds apply and match nothing.
        $reversed = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=2026-05-20&date_to=2026-05-10')
            ->assertOk();
        $this->assertTrue($reversed->viewData('dateFilterActive'));
        $this->assertSame(0, $reversed->viewData('deliveries')['total']);
    }

    public function test_dashboard_date_filter_never_leaks_another_companys_shipments(): void
    {
        $centerA = $this->center('Company A Center');
        $centerB = $this->center('Company B Center');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $mine = $this->dated($this->delivery($centerA), '2026-07-15 09:00:00');
        $theirs = $this->dated($this->delivery($centerB), '2026-07-16 09:00:00');

        $response = $this->actingAs($staffA)
            ->get('/logistics/dashboard?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk();

        $this->assertSame(1, $response->viewData('deliveries')['total']);
        $response->assertSee($mine->tracking_number)->assertDontSee($theirs->tracking_number);
    }

    public function test_dashboard_date_filter_does_not_bypass_role_restrictions(): void
    {
        $center = $this->center('Restricted Center');
        $buyer = $this->user('buyer', $center->id);

        $this->actingAs($buyer)
            ->get('/logistics/dashboard?date_from=2026-07-01&date_to=2026-07-31')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_dashboard_preserves_trends_range_alongside_the_date_filter(): void
    {
        $center = $this->center('Trends Range Center');
        $staff = $this->user('logistics_staff', $center->id);
        $this->dated($this->delivery($center), '2026-08-10 09:00:00');

        $default = $this->actingAs($staff)->get('/logistics/dashboard')->assertOk();
        $this->assertSame(7, $default->viewData('trendsRange'));
        $this->assertCount(7, $default->viewData('trendsChart')['dates']);

        $thirty = $this->actingAs($staff)
            ->get('/logistics/dashboard?trends_range=30')
            ->assertOk();
        $this->assertSame(30, $thirty->viewData('trendsRange'));
        $this->assertCount(30, $thirty->viewData('trendsChart')['dates']);

        // An unknown range falls back to 7 and coexists with the date filter.
        $combined = $this->actingAs($staff)
            ->get('/logistics/dashboard?date_from=2026-08-01&date_to=2026-08-31&trends_range=999')
            ->assertOk();
        $this->assertSame(7, $combined->viewData('trendsRange'));
        $this->assertSame(1, $combined->viewData('deliveries')['total']);
        $combined->assertSee('value="2026-08-01"', false);
    }

    public function test_operational_alerts_appear_and_clear_when_conditions_are_fixed(): void
    {
        $center = $this->center('Alerts Center');
        $staff = $this->user('logistics_staff', $center->id);

        $unassigned = $this->delivery($center, ['status' => 'waiting_for_rider', 'parcel_status' => 'pending_arrival']);
        $this->age('deliveries', $unassigned->id);

        $incoming = $this->delivery($center, ['status' => 'assigned', 'parcel_status' => 'pending_arrival']);
        $this->age('deliveries', $incoming->id);

        $pickupDelivery = $this->delivery($center, ['status' => 'assigned', 'parcel_status' => 'received']);
        $pickup = PickupRequest::create([
            'delivery_id' => $pickupDelivery->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now()->subHours(3),
        ]);

        $sorting = $this->delivery($center, [
            'status' => 'assigned',
            'parcel_status' => 'received',
            'received_at' => now()->subHours(5),
        ]);
        $this->age('deliveries', $sorting->id, 300);

        $failed = $this->delivery($center, [
            'status' => 'delivery_failed',
            'parcel_status' => 'dispatched',
            'failed_at' => now()->subHours(3),
        ]);
        $this->age('deliveries', $failed->id);

        $alerts = collect(LogisticsAlerts::forUser($staff))->keyBy('key');

        $this->assertTrue($alerts->has('unassigned'));
        $this->assertTrue($alerts->has('incoming'));
        $this->assertTrue($alerts->has('pickups'));
        $this->assertTrue($alerts->has('sorting'));
        $this->assertTrue($alerts->has('failed'));

        // Correcting the underlying conditions clears the live alerts.
        $unassigned->update(['status' => 'assigned', 'parcel_status' => 'received', 'received_at' => now()]);
        $incoming->update(['parcel_status' => 'received', 'received_at' => now()]);
        $pickup->update(['status' => 'approved', 'reviewed_at' => now()]);
        $sorting->update(['parcel_status' => 'dispatched', 'dispatched_at' => now()]);
        $failed->update(['returned_at' => now()]);

        $alerts = collect(LogisticsAlerts::forUser($staff))->keyBy('key');

        $this->assertFalse($alerts->has('unassigned'));
        $this->assertFalse($alerts->has('incoming'));
        $this->assertFalse($alerts->has('pickups'));
        $this->assertFalse($alerts->has('sorting'));
        $this->assertFalse($alerts->has('failed'));
    }

    public function test_rider_recommendation_prefers_the_least_loaded_eligible_rider(): void
    {
        $center = $this->center('Ranking Center');
        $area = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Ranking Area',
            'is_active' => true,
        ]);
        $staff = $this->user('logistics_staff', $center->id);

        $delivery = $this->delivery($center, [
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'destination_center_id' => $center->id,
            'service_area_id' => $area->id,
            'weight' => 1,
        ]);

        $busy = $this->rider($center, $area, 'Busy Rider');
        $free = $this->rider($center, $area, 'Free Rider');

        $this->delivery($center, [
            'status' => 'assigned',
            'parcel_status' => 'dispatched',
            'rider_id' => $busy->id,
            'destination_center_id' => $center->id,
            'service_area_id' => $area->id,
        ]);

        $body = $this->actingAs($staff)
            ->get("/logistics/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Recommended')
            ->getContent();

        $this->assertLessThan(
            strpos($body, 'Busy Rider'),
            strpos($body, 'Free Rider'),
            'The least-loaded eligible rider should be listed first.'
        );

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/auto-assign")
            ->assertRedirect();

        $delivery->refresh();
        $this->assertSame('assigned', $delivery->status);
        $this->assertSame($free->id, $delivery->rider_id);
    }

    public function test_sorting_center_flags_parcels_missing_a_destination(): void
    {
        $center = $this->center('Routing Center');
        $staff = $this->user('logistics_staff', $center->id);

        $this->delivery($center, [
            'status' => 'assigned',
            'parcel_status' => 'scanned',
            'destination_center_id' => null,
            'service_area_id' => null,
        ]);

        $this->actingAs($staff)
            ->get('/logistics/sorting-center?tab=scanned')
            ->assertOk()
            ->assertSee('Needs destination');
    }

    public function test_admin_can_route_an_unrouted_pickup_before_review(): void
    {
        $center = $this->center('Routing Destination');
        $admin = User::where('email', 'logistics_admin@test.com')->firstOrFail();
        $delivery = $this->delivery(null);
        $pickup = PickupRequest::openForDelivery($delivery, notify: false);

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests?status=routing')
            ->assertOk()
            ->assertSee('Pickup Requests Needing Routing')
            ->assertSee($delivery->tracking_number);

        // Reviewing before routing would strand the request outside the
        // routing queue, so it must be refused.
        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertRedirect();

        $this->assertSame('pending', $pickup->fresh()->status);
        $this->assertNull($pickup->fresh()->center_id);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/route", ['center_id' => $center->id])
            ->assertRedirect();

        $this->assertSame($center->id, $pickup->fresh()->center_id);
        $this->assertSame($center->id, $delivery->fresh()->center_id);
        $this->assertDatabaseHas('notifications', [
            'type' => 'new_delivery_request',
            'title' => 'Pickup Request Routed',
            'center_id' => $center->id,
            'audience' => 'center',
        ]);

        $staff = $this->user('logistics_staff', $center->id);

        $this->actingAs($staff)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee($delivery->tracking_number);
    }

    public function test_only_admins_can_open_or_use_the_routing_queue(): void
    {
        $center = $this->center('Routing Guard');
        $staff = $this->user('logistics_staff', $center->id);
        $delivery = $this->delivery(null);
        $pickup = PickupRequest::openForDelivery($delivery, notify: false);

        $this->actingAs($staff)
            ->get('/logistics/pickup-requests?status=routing')
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/pickup-requests/{$pickup->id}/route", ['center_id' => $center->id])
            ->assertForbidden();

        $this->assertNull($pickup->fresh()->center_id);
        $this->assertNull($delivery->fresh()->center_id);
    }

    private function rider(LogisticsCenter $center, ServiceArea $area, string $name): Rider
    {
        $this->seq++;

        return Rider::create([
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'name' => $name,
            'email' => "rider-{$this->seq}@test.local",
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 50,
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'is_online' => true,
        ]);
    }
}
