<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Logistics\Conversation;
use App\Models\Logistics\Message;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCompany;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\ServiceArea;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regression coverage for the Logistics audit fixes (BUG-01..04, SETUP-01) and
 * the parcel lifecycle slice that BUG-02 depends on. All data is created inside
 * the test transaction; no real/seeded rows are modified.
 */
class LogisticsAuditFixesTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "audit-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function center(string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name,
            'address' => '1 Audit Street',
            'city' => 'Audit City',
            'is_active' => true,
        ]);
    }

    private function rider(User $user, array $overrides = []): Rider
    {
        return Rider::create(array_merge([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0917'.str_pad((string) $this->seq, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ], $overrides));
    }

    private function transactionFor(Delivery $delivery, int $centerId): Transaction
    {
        return Transaction::create([
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'logistics_center_id' => $centerId,
            'amount' => 50,
            'rider_fee' => 50,
            'logistics_fee' => 15,
            'admin_commission' => 0,
            'status' => 'completed',
        ]);
    }

    private function makeDelivery(array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-20261020-'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'order_id' => Order::query()->value('id'),
            'sender_name' => 'Audit Sender',
            'recipient_name' => 'Audit Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
        ], $overrides));
    }

    private function conversation(): Conversation
    {
        return Conversation::create([
            'participant_type' => 'rider',
            'participant_id' => 999999,
            'participant_name' => 'Audit Rider',
            'subject' => 'Audit thread',
            'last_message_at' => now(),
        ]);
    }

    // ---------------------------------------------------------------- BUG-03

    public function test_global_logistics_admin_can_view_and_approve_any_center_pickup_request(): void
    {
        $center = $this->center('Audit Center Alpha');
        $delivery = $this->makeDelivery(['center_id' => $center->id, 'parcel_status' => 'received']);
        $pickup = PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $admin = $this->user('logistics_admin');

        $this->actingAs($admin)
            ->get("/logistics/pickup-requests/{$pickup->id}")
            ->assertOk();

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $pickup->fresh()->status);
        $this->assertSame($admin->id, $pickup->fresh()->reviewed_by);
    }

    public function test_center_staff_can_view_own_center_pickup_request_and_not_other_center(): void
    {
        $centerA = $this->center('Audit Center Alpha');
        $centerB = $this->center('Audit Center Beta');

        $own = PickupRequest::create([
            'delivery_id' => $this->makeDelivery(['center_id' => $centerA->id, 'parcel_status' => 'received'])->id,
            'center_id' => $centerA->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        $other = PickupRequest::create([
            'delivery_id' => $this->makeDelivery(['center_id' => $centerB->id, 'parcel_status' => 'received'])->id,
            'center_id' => $centerB->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $staffA = $this->user('logistics_staff', $centerA->id);

        $this->actingAs($staffA)->get("/logistics/pickup-requests/{$own->id}")->assertOk();
        $this->actingAs($staffA)->get("/logistics/pickup-requests/{$other->id}")->assertForbidden();
    }

    public function test_pickup_request_index_is_center_scoped_for_center_staff(): void
    {
        $centerA = $this->center('Audit Center Alpha');
        $centerB = $this->center('Audit Center Beta');

        $mine = $this->makeDelivery(['center_id' => $centerA->id, 'tracking_number' => 'TRK-20261020-7701']);
        $theirs = $this->makeDelivery(['center_id' => $centerB->id, 'tracking_number' => 'TRK-20261020-7702']);

        PickupRequest::create(['delivery_id' => $mine->id, 'center_id' => $centerA->id, 'status' => 'pending', 'requested_at' => now()]);
        PickupRequest::create(['delivery_id' => $theirs->id, 'center_id' => $centerB->id, 'status' => 'pending', 'requested_at' => now()]);

        $staffA = $this->user('logistics_staff', $centerA->id);

        $this->actingAs($staffA)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee('TRK-20261020-7701')
            ->assertDontSee('TRK-20261020-7702');
    }

    public function test_non_logistics_user_is_redirected_from_pickup_requests(): void
    {
        $buyer = $this->user('buyer');

        $this->actingAs($buyer)
            ->get('/logistics/pickup-requests')
            ->assertRedirect('/logistics/login');
    }

    public function test_pickup_review_ignores_stale_already_picked_up_parcel(): void
    {
        $center = $this->center('Stale Review Center');
        $admin = $this->user('logistics_admin');
        $delivery = $this->makeDelivery([
            'center_id' => $center->id,
            'status' => 'out_for_delivery',
            'parcel_status' => 'dispatched',
            'picked_up_at' => now(),
        ]);
        $pickup = PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertRedirect();

        $this->assertSame('pending', $pickup->fresh()->status);
        $this->assertNull($pickup->fresh()->reviewed_by);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/reject", [
                'rejection_reason' => 'Stale request.',
            ])
            ->assertRedirect();

        $this->assertSame('pending', $pickup->fresh()->status);
        $this->assertNull($pickup->fresh()->reviewed_by);
        $this->assertSame(0, Notification::query()
            ->where('type', 'pickup_failed')
            ->where('center_id', $center->id)
            ->count());
    }

    public function test_pickup_review_is_single_use(): void
    {
        $center = $this->center('Single Use Review Center');
        $admin = $this->user('logistics_admin');
        $delivery = $this->makeDelivery([
            'center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
        ]);
        $pickup = PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $pickup->fresh()->status);
        $this->assertSame($admin->id, $pickup->fresh()->reviewed_by);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickup->id}/reject", [
                'rejection_reason' => 'Too late.',
            ])
            ->assertRedirect();

        $fresh = $pickup->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
        $this->assertNull($fresh->rejection_reason);
        $this->assertSame(0, Notification::query()
            ->where('type', 'pickup_failed')
            ->where('center_id', $center->id)
            ->count());
    }

    // -------------------------------------------------------- center scoping

    public function test_centerless_staff_see_no_riders_and_cannot_manage_them(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $staff = $this->user('logistics_staff');

        $riderA = $this->rider($this->user('rider'), [
            'center_id' => $centerA->id,
            'name' => 'Scope Rider Alpha',
            'email' => 'scope-rider-alpha@test.local',
        ]);
        $riderB = $this->rider($this->user('rider'), [
            'center_id' => $centerB->id,
            'name' => 'Scope Rider Beta',
            'email' => 'scope-rider-beta@test.local',
        ]);

        $response = $this->actingAs($staff)
            ->get('/logistics/riders?center_id='.$centerA->id)
            ->assertOk()
            ->assertDontSee($riderA->email)
            ->assertDontSee($riderB->email);

        $this->assertSame(0, $response->viewData('riders')->total());
        $this->assertCount(0, $response->viewData('filterCenters'));
        $this->assertCount(0, $response->viewData('filterServiceAreas'));

        $this->actingAs($staff)
            ->get("/logistics/riders/{$riderA->id}")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/riders/{$riderA->id}/deactivate", ['reason' => 'Unauthorized scope check'])
            ->assertForbidden();
    }

    public function test_center_staff_only_see_their_own_center_riders(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $riderA = $this->rider($this->user('rider'), [
            'center_id' => $centerA->id,
            'name' => 'Scope Rider Alpha',
            'email' => 'scope-rider-alpha@test.local',
        ]);
        $riderB = $this->rider($this->user('rider'), [
            'center_id' => $centerB->id,
            'name' => 'Scope Rider Beta',
            'email' => 'scope-rider-beta@test.local',
        ]);

        $response = $this->actingAs($staffA)
            ->get('/logistics/riders')
            ->assertOk()
            ->assertSee($riderA->email)
            ->assertDontSee($riderB->email);

        $this->assertCount(1, $response->viewData('filterCenters'));
        $this->assertSame($centerA->id, $response->viewData('filterCenters')->first()->id);

        $this->actingAs($staffA)
            ->get("/logistics/riders/{$riderB->id}")
            ->assertForbidden();
    }

    public function test_logistics_admin_with_center_assignment_still_sees_all_riders(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $admin = $this->user('logistics_admin', $centerA->id);

        $riderA = $this->rider($this->user('rider'), [
            'center_id' => $centerA->id,
            'name' => 'Scope Rider Alpha',
            'email' => 'scope-rider-alpha@test.local',
        ]);
        $riderB = $this->rider($this->user('rider'), [
            'center_id' => $centerB->id,
            'name' => 'Scope Rider Beta',
            'email' => 'scope-rider-beta@test.local',
        ]);

        $response = $this->actingAs($admin)
            ->get('/logistics/riders')
            ->assertOk()
            ->assertSee($riderA->email)
            ->assertSee($riderB->email)
            ->assertSee("riders/{$riderB->id}/deactivate", false);

        $this->assertTrue(
            $response->viewData('riders')->getCollection()->contains('id', $riderB->id)
        );

        $this->actingAs($admin)
            ->get("/logistics/riders/{$riderB->id}")
            ->assertOk()
            ->assertSee($riderB->email);
    }

    public function test_centerless_staff_see_no_transactions(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $staff = $this->user('logistics_staff');

        $txA = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerA->id, 'tracking_number' => 'TRK-20261020-8801']),
            $centerA->id
        );
        $txB = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerB->id, 'tracking_number' => 'TRK-20261020-8802']),
            $centerB->id
        );

        $response = $this->actingAs($staff)
            ->get('/logistics/transactions?center_id='.$centerA->id)
            ->assertOk()
            ->assertDontSee($txA->tracking_number)
            ->assertDontSee($txB->tracking_number);

        $this->assertSame(0, $response->viewData('transactions')->total());
        $this->assertSame(0, (int) $response->viewData('totals')->total_count);
        $this->assertCount(0, $response->viewData('centers'));
    }

    public function test_center_staff_only_see_their_own_center_transactions(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $txA = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerA->id, 'tracking_number' => 'TRK-20261020-8803']),
            $centerA->id
        );
        $txB = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerB->id, 'tracking_number' => 'TRK-20261020-8804']),
            $centerB->id
        );

        $response = $this->actingAs($staffA)
            ->get('/logistics/transactions')
            ->assertOk()
            ->assertSee($txA->tracking_number)
            ->assertDontSee($txB->tracking_number);

        $this->assertCount(1, $response->viewData('centers'));
        $this->assertSame($centerA->id, $response->viewData('centers')->first()->id);
    }

    public function test_logistics_admin_with_center_assignment_still_sees_all_transactions(): void
    {
        $centerA = $this->center('Scope Center Alpha');
        $centerB = $this->center('Scope Center Beta');
        $admin = $this->user('logistics_admin', $centerA->id);

        $txA = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerA->id, 'tracking_number' => 'TRK-20261020-8805']),
            $centerA->id
        );
        $txB = $this->transactionFor(
            $this->makeDelivery(['center_id' => $centerB->id, 'tracking_number' => 'TRK-20261020-8806']),
            $centerB->id
        );

        $this->actingAs($admin)
            ->get('/logistics/transactions')
            ->assertOk()
            ->assertSee($txA->tracking_number)
            ->assertSee($txB->tracking_number);

        $this->actingAs($admin)
            ->get('/logistics/transactions?center_id='.$centerB->id)
            ->assertOk()
            ->assertSee($txB->tracking_number)
            ->assertDontSee($txA->tracking_number);
    }

    public function test_report_filter_options_are_center_scoped(): void
    {
        $centerA = $this->center('Scope Report Center Alpha');
        $centerB = $this->center('Scope Report Center Beta');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $riderA = $this->rider($this->user('rider'), [
            'center_id' => $centerA->id,
            'name' => 'Scope Report Rider Alpha',
            'email' => 'scope-report-rider-alpha@test.local',
        ]);
        $riderB = $this->rider($this->user('rider'), [
            'center_id' => $centerB->id,
            'name' => 'Scope Report Rider Beta',
            'email' => 'scope-report-rider-beta@test.local',
        ]);

        $areaA = ServiceArea::create([
            'logistics_center_id' => $centerA->id,
            'name' => 'Scope Report Area Alpha',
            'is_active' => true,
        ]);
        $areaB = ServiceArea::create([
            'logistics_center_id' => $centerB->id,
            'name' => 'Scope Report Area Beta',
            'is_active' => true,
        ]);

        $staffResponse = $this->actingAs($staffA)
            ->get('/logistics/reports')
            ->assertOk()
            ->assertDontSee($centerB->name)
            ->assertDontSee($riderB->name)
            ->assertDontSee($areaB->name);

        $this->assertSame([$centerA->id], $staffResponse->viewData('centers')->pluck('id')->all());
        $this->assertSame([$riderA->id], $staffResponse->viewData('riders')->pluck('id')->all());
        $this->assertSame([$areaA->id], $staffResponse->viewData('serviceAreas')->pluck('id')->all());

        $centerless = $this->user('logistics_staff');
        $centerlessResponse = $this->actingAs($centerless)
            ->get('/logistics/reports')
            ->assertOk();

        $this->assertCount(0, $centerlessResponse->viewData('centers'));
        $this->assertCount(0, $centerlessResponse->viewData('riders'));
        $this->assertCount(0, $centerlessResponse->viewData('serviceAreas'));

        $admin = $this->user('logistics_admin', $centerA->id);
        $adminResponse = $this->actingAs($admin)
            ->get('/logistics/reports')
            ->assertOk()
            ->assertSee($centerB->name);

        $this->assertTrue($adminResponse->viewData('centers')->pluck('id')->contains($centerB->id));
        $this->assertTrue($adminResponse->viewData('riders')->pluck('id')->contains($riderB->id));
        $this->assertTrue($adminResponse->viewData('serviceAreas')->pluck('id')->contains($areaB->id));
    }

    public function test_delivery_report_failed_count_aggregates_failure_states(): void
    {
        $center = $this->center('Failed Report Center');
        $admin = $this->user('logistics_admin');

        foreach (['delivery_failed', 'lost', 'damaged'] as $status) {
            $this->makeDelivery(['center_id' => $center->id, 'status' => $status]);
        }
        $this->makeDelivery(['center_id' => $center->id, 'status' => 'delivered']);
        $this->makeDelivery(['center_id' => $center->id, 'status' => 'cancelled']);
        $this->makeDelivery(['center_id' => $center->id, 'status' => 'waiting_for_rider']);

        $response = $this->actingAs($admin)
            ->get("/logistics/reports?tab=delivery&center_id={$center->id}")
            ->assertOk();

        $stats = $response->viewData('deliveryStats');

        $this->assertSame(6, $stats['total']);
        $this->assertSame(3, $stats['failed']);
        $this->assertSame(1, $stats['delivered']);
        $this->assertSame(1, $stats['cancelled']);
    }

    public function test_area_and_rider_reports_apply_date_filters(): void
    {
        $center = $this->center('Dated Report Center');
        $admin = $this->user('logistics_admin');
        $area = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Dated Report Area',
            'is_active' => true,
        ]);
        $rider = $this->rider($this->user('rider'), [
            'center_id' => $center->id,
            'name' => 'Dated Report Rider',
            'email' => 'dated-report-rider@test.local',
        ]);

        $attributes = [
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'rider_id' => $rider->id,
            'status' => 'delivered',
        ];
        $recent = $this->makeDelivery($attributes);
        $old = $this->makeDelivery($attributes);

        $recent->forceFill(['created_at' => now()->subDay()])->save();
        $old->forceFill(['created_at' => now()->subMonths(3)])->save();

        $dateFrom = now()->subWeek()->toDateString();
        $dateTo = now()->toDateString();

        $response = $this->actingAs($admin)
            ->get("/logistics/reports?tab=all&center_id={$center->id}&date_from={$dateFrom}&date_to={$dateTo}")
            ->assertOk();

        $areaRow = collect($response->viewData('areaStats'))->firstWhere('id', $area->id);
        $this->assertNotNull($areaRow);
        $this->assertSame(1, (int) $areaRow['deliveries_count']);

        $riderRow = collect($response->viewData('riderStats'))->firstWhere('id', $rider->id);
        $this->assertNotNull($riderRow);
        $this->assertSame(1, (int) $riderRow['total_deliveries']);
        $this->assertSame(1, (int) $riderRow['completed_deliveries']);
        $this->assertSame(0, (int) $riderRow['active_deliveries']);
    }

    public function test_report_date_filters_are_inclusive_and_can_return_empty(): void
    {
        $center = $this->center('Boundary Report Center');
        $admin = $this->user('logistics_admin');
        $area = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Boundary Report Area',
            'is_active' => true,
        ]);
        $rider = $this->rider($this->user('rider'), [
            'center_id' => $center->id,
            'name' => 'Boundary Report Rider',
            'email' => 'boundary-report-rider@test.local',
        ]);

        $from = now()->subDays(3)->toDateString();
        $to = now()->subDays(2)->toDateString();

        $attributes = [
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'rider_id' => $rider->id,
            'status' => 'delivered',
        ];

        $insideStart = $this->makeDelivery($attributes);
        $insideStart->forceFill(['created_at' => $from.' 00:00:00'])->save();

        $insideEnd = $this->makeDelivery($attributes);
        $insideEnd->forceFill(['created_at' => $to.' 23:59:59'])->save();

        $outsideBefore = $this->makeDelivery($attributes);
        $outsideBefore->forceFill(['created_at' => now()->subDays(5)])->save();

        $outsideAfter = $this->makeDelivery($attributes);
        $outsideAfter->forceFill(['created_at' => now()->addDays(3)])->save();

        $response = $this->actingAs($admin)
            ->get("/logistics/reports?tab=all&center_id={$center->id}&date_from={$from}&date_to={$to}")
            ->assertOk();

        $stats = $response->viewData('deliveryStats');
        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['delivered']);

        $areaRow = collect($response->viewData('areaStats'))->firstWhere('id', $area->id);
        $this->assertNotNull($areaRow);
        $this->assertSame(2, (int) $areaRow['deliveries_count']);

        $riderRow = collect($response->viewData('riderStats'))->firstWhere('id', $rider->id);
        $this->assertNotNull($riderRow);
        $this->assertSame(2, (int) $riderRow['total_deliveries']);

        // A range with no deliveries must report zero, not leak other rows.
        $future = now()->addYear()->toDateString();
        $empty = $this->actingAs($admin)
            ->get("/logistics/reports?tab=all&center_id={$center->id}&date_from={$future}&date_to={$future}")
            ->assertOk();

        $this->assertSame(0, $empty->viewData('deliveryStats')['total']);
        $emptyArea = collect($empty->viewData('areaStats'))->firstWhere('id', $area->id);
        $this->assertNotNull($emptyArea);
        $this->assertSame(0, (int) $emptyArea['deliveries_count']);
    }

    public function test_center_approval_is_atomic_and_single_use(): void
    {
        Mail::fake();

        $admin = $this->user('logistics_admin');
        $application = LogisticsCenterApplication::create([
            'business_name' => 'Atomic Center',
            'owner_name' => 'Atomic Owner',
            'email' => 'atomic-center-owner@test.local',
            'phone' => '09171234567',
            'address' => '1 Atomic Street',
            'status' => 'pending',
        ]);
        $company = LogisticsCompany::create([
            'name' => 'Atomic Verified Logistics', 'registration_number' => 'ATOMIC-REG-001', 'status' => 'active',
        ]);

        $centerCount = LogisticsCenter::count();

        $this->actingAs($admin)
            ->post("/logistics/center-applications/{$application->id}/approve", [
                'logistics_company_id' => $company->id,
                'ownership_evidence' => 'Verified registration certificate for ATOMIC-REG-001',
            ])
            ->assertRedirect();

        $application->refresh();
        $center = LogisticsCenter::where('name', 'Atomic Center')->first();

        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->provisioned_at);
        $this->assertNotNull($center);
        $this->assertSame($centerCount + 1, LogisticsCenter::count());
        $this->assertDatabaseHas('users', [
            'email' => $application->email,
            'role' => 'logistics_staff',
            'center_id' => $center->id,
        ]);
        $this->assertSame(1, Notification::query()
            ->where('type', 'center_approved')
            ->where('message', 'like', '%Atomic Center%')
            ->count());

        $this->actingAs($admin)
            ->post("/logistics/center-applications/{$application->id}/approve", [
                'logistics_company_id' => $company->id,
                'ownership_evidence' => 'Verified registration certificate for ATOMIC-REG-001',
            ])
            ->assertStatus(422);

        $this->assertSame($centerCount + 1, LogisticsCenter::count());
        $this->assertSame(1, Notification::query()
            ->where('type', 'center_approved')
            ->where('message', 'like', '%Atomic Center%')
            ->count());
    }

    // -------------------------------------------------------------- SETUP-01

    public function test_center_staff_cannot_reach_admin_staff_management(): void
    {
        $center = $this->center('Audit Center Alpha');
        $staff = $this->user('logistics_staff', $center->id);

        $this->actingAs($staff)->get('/logistics/staff')->assertForbidden();
    }

    public function test_staff_index_lists_logistics_staff_and_filters_by_center(): void
    {
        $centerA = $this->center('Audit Center Alpha');
        $centerB = $this->center('Audit Center Beta');

        $staffA = $this->user('logistics_staff', $centerA->id, ['first_name' => 'Alpha', 'last_name' => 'Staff']);
        $staffB = $this->user('staff', $centerB->id, ['first_name' => 'Beta', 'last_name' => 'Staff']);
        $admin = $this->user('logistics_admin');

        $this->actingAs($admin)
            ->get('/logistics/staff')
            ->assertOk()
            ->assertSee($staffA->email)
            ->assertSee($staffB->email);

        $this->actingAs($admin)
            ->get('/logistics/staff?center_id='.$centerA->id)
            ->assertOk()
            ->assertSee($staffA->email)
            ->assertDontSee($staffB->email);
    }

    public function test_admin_can_create_staff_assigned_to_center(): void
    {
        $center = $this->center('Audit Center Alpha');
        $admin = $this->user('logistics_admin');

        $this->actingAs($admin)->post('/logistics/staff', [
            'first_name' => 'Newly',
            'last_name' => 'Created',
            'sex' => 'other',
            'email' => 'newly-created-staff@test.local',
            'password' => 'Str0ng#Pass1',
            'password_confirmation' => 'Str0ng#Pass1',
            'terms' => '1',
            'phone' => '09171234567',
            'birthday' => '1995-05-05',
            'center_id' => $center->id,
        ])->assertRedirect(route('staff.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newly-created-staff@test.local',
            'role' => 'staff',
            'center_id' => $center->id,
        ]);
    }

    public function test_staff_show_and_edit_reject_non_staff_user(): void
    {
        $center = $this->center('Audit Center Alpha');
        $admin = $this->user('logistics_admin');
        $buyer = $this->user('buyer');

        $this->actingAs($admin)->get("/logistics/staff/{$buyer->id}")->assertNotFound();
        $this->actingAs($admin)->get("/logistics/staff/{$buyer->id}/edit")->assertNotFound();
    }

    // ---------------------------------------------------------------- BUG-04

    public function test_message_page_uses_absolute_logistics_base_url(): void
    {
        $admin = $this->user('logistics_admin');
        $conversation = $this->conversation();
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'logistics',
            'sender_id' => $admin->id,
            'body' => 'Base URL check',
        ]);

        $response = $this->actingAs($admin)
            ->get('/logistics/messages?conversation='.$conversation->id)
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('base:', $html);
        $this->assertStringNotContainsString("fetch('/messages", $html);
        $this->assertStringNotContainsString('fetch(`/messages', $html);
    }

    public function test_message_page_supports_switching_threads_without_reloading_mobile_inbox(): void
    {
        $admin = $this->user('logistics_admin');
        $first = $this->conversation();
        $second = $this->conversation();
        $second->update(['last_message_at' => now()->addMinute()]);

        $inbox = $this->actingAs($admin)
            ->get('/logistics/messages')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mobileList: true', $inbox);
        $this->assertStringContainsString('mobileList = true', $inbox);
        $this->assertStringContainsString('selectConversation(conv.id)', $inbox);

        $thread = $this->actingAs($admin)
            ->get('/logistics/messages?conversation='.$second->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('mobileList: false', $thread);
    }

    public function test_messages_open_send_update_delete_and_poll(): void
    {
        $admin = $this->user('logistics_admin');
        $conversation = $this->conversation();

        $this->actingAs($admin)
            ->getJson("/logistics/messages/{$conversation->id}")
            ->assertOk()
            ->assertJsonStructure(['active', 'messages', 'totalUnread']);

        $this->actingAs($admin)
            ->getJson('/logistics/messages/poll?conversation='.$conversation->id)
            ->assertOk()
            ->assertJsonStructure(['conversations', 'active', 'messages', 'totalUnread']);

        $this->actingAs($admin)
            ->postJson('/logistics/messages', ['conversation_id' => $conversation->id, 'body' => 'Audit hello'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $message = Message::where('conversation_id', $conversation->id)->where('body', 'Audit hello')->firstOrFail();
        $this->assertSame('logistics', $message->sender_type);

        $this->actingAs($admin)
            ->patchJson("/logistics/messages/{$message->id}", ['body' => 'Audit hello edited'])
            ->assertOk();

        $this->assertSame('Audit hello edited', $message->fresh()->body);

        $this->actingAs($admin)
            ->deleteJson("/logistics/messages/{$message->id}")
            ->assertOk();

        $this->assertTrue($message->fresh()->isDeleted());
    }

    public function test_messages_denied_for_non_logistics_user(): void
    {
        $buyer = $this->user('buyer');
        $conversation = $this->conversation();

        $this->actingAs($buyer)
            ->get('/logistics/messages')
            ->assertRedirect('/logistics/login');

        $this->actingAs($buyer)
            ->getJson("/logistics/messages/{$conversation->id}")
            ->assertRedirect('/logistics/login');
    }

    // ---------------------------------------------------------------- BUG-02

    public function test_scan_accepts_valid_tracking_and_rejects_demo_format(): void
    {
        $center = $this->center('Audit Center Alpha');
        $staff = $this->user('logistics_staff', $center->id);

        $delivery = $this->makeDelivery([
            'tracking_number' => 'TRK-20261020-9101',
            'center_id' => $center->id,
            'parcel_status' => 'received',
        ]);

        $this->actingAs($staff)
            ->get('/logistics/deliveries/scan')
            ->assertOk()
            ->assertSee('TRK-20261020-9101', false);

        $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', ['tracking_number' => 'TRK-20261020-9101'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);

        // The legacy demo identifier must never be accepted by validation.
        $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', ['tracking_number' => 'TRK-DEMO-015'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tracking_number');
    }

    public function test_scan_empty_state_when_nothing_awaits_scan(): void
    {
        $center = $this->center('Audit Center Empty');
        $staff = $this->user('logistics_staff', $center->id);

        $this->actingAs($staff)
            ->get('/logistics/deliveries/scan')
            ->assertOk()
            ->assertSee('No parcels are awaiting scan', false);
    }

    public function test_other_center_parcel_is_rejected_for_center_staff(): void
    {
        $centerA = $this->center('Audit Center Alpha');
        $centerB = $this->center('Audit Center Beta');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $this->makeDelivery([
            'tracking_number' => 'TRK-20261020-9202',
            'center_id' => $centerB->id,
            'parcel_status' => 'received',
        ]);

        $this->actingAs($staffA)
            ->postJson('/logistics/deliveries/scan-verify', ['tracking_number' => 'TRK-20261020-9202'])
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------- BUG-01

    public function test_waiting_for_rider_actions_are_aligned(): void
    {
        $center = $this->center('Audit Center Alpha');
        $admin = $this->user('logistics_admin');
        $delivery = $this->makeDelivery([
            'center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
        ]);

        $response = $this->actingAs($admin)
            ->get("/logistics/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Assign Rider')
            ->assertSee('Cancel Delivery');

        $html = $response->getContent();

        // The old markup pushed the Cancel button onto its own line via
        // self-start and used a shorter height (py-2) than the Assign button.
        // Both controls now share the same row and height (py-2.5).
        $this->assertStringContainsString('sm:items-end', $html);
        $this->assertStringContainsString(
            'text-gray-600 font-semibold px-4 py-2.5 rounded-xl transition text-sm whitespace-nowrap',
            $html
        );
        $this->assertStringContainsString(
            'text-white font-semibold py-2.5 px-5 rounded-xl transition shadow-sm text-sm whitespace-nowrap',
            $html
        );
    }

    // ------------------------------------------------------------- lifecycle

    public function test_delivery_can_be_cancelled_with_reason(): void
    {
        $center = $this->center('Audit Center Alpha');
        $staff = $this->user('logistics_staff', $center->id);
        $delivery = $this->makeDelivery([
            'center_id' => $center->id,
            'status' => 'assigned',
            'parcel_status' => 'received',
        ]);

        $this->actingAs($staff)
            ->patch("/logistics/deliveries/{$delivery->id}/update-status", [
                'status' => 'cancelled',
                'reason' => 'Customer cancelled',
            ])
            ->assertRedirect();

        $fresh = $delivery->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('Customer cancelled', $fresh->cancellation_reason);
        $this->assertNotNull($fresh->cancelled_at);
    }

    public function test_parcel_lifecycle_receive_scan_sort_dispatch(): void
    {
        $center = $this->center('Audit Center Alpha');
        $staff = $this->user('logistics_staff', $center->id);
        $area = ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Audit Area',
            'is_active' => true,
        ]);

        $delivery = $this->makeDelivery([
            'tracking_number' => 'TRK-20261020-9303',
            'status' => 'assigned',
            'parcel_status' => 'pending_arrival',
        ]);

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/receive", ['center_id' => $center->id])
            ->assertRedirect();
        $this->assertSame('received', $delivery->fresh()->parcel_status);
        $this->assertSame($center->id, $delivery->fresh()->center_id);

        $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', ['tracking_number' => 'TRK-20261020-9303'])
            ->assertOk();
        $this->assertSame('scanned', $delivery->fresh()->parcel_status);

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/sort", [
                'destination_center_id' => $center->id,
                'service_area_id' => $area->id,
            ])
            ->assertRedirect();
        $this->assertSame('sorted', $delivery->fresh()->parcel_status);

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/dispatch")
            ->assertRedirect();
        $this->assertSame('dispatched', $delivery->fresh()->parcel_status);
    }

    public function test_status_override_requires_a_meaningful_reason(): void
    {
        $center = $this->center('Audit Override Center');
        $staff = $this->user('logistics_staff', $center->id);
        $delivery = $this->makeDelivery([
            'center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
        ]);

        $this->actingAs($staff)
            ->patch("/logistics/deliveries/{$delivery->id}/update-status", [
                'status' => 'out_for_delivery',
                'override' => 1,
                'override_reason' => 'too short',
            ])
            ->assertSessionHasErrors('override_reason');

        $this->assertSame('waiting_for_rider', $delivery->fresh()->status);

        $this->actingAs($staff)
            ->patch("/logistics/deliveries/{$delivery->id}/update-status", [
                'status' => 'out_for_delivery',
                'override' => 1,
                'override_reason' => 'Customer requested expedited handling.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
    }

    public function test_rejecting_a_rider_application_requires_a_meaningful_reason(): void
    {
        $admin = $this->user('logistics_admin');
        $application = RiderApplication::create([
            'name' => 'Applicant Audit',
            'email' => 'applicant-'.bin2hex(random_bytes(3)).'@test.local',
            'phone' => '0917'.random_int(1000000, 9999999),
            'vehicle_type' => 'motorcycle',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/logistics/rider-applications/{$application->id}/reject", ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending', $application->fresh()->status);

        $this->actingAs($admin)
            ->post("/logistics/rider-applications/{$application->id}/reject", [
                'reason' => 'Documents are incomplete and illegible.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $application->fresh()->status);
    }

    // ------------------------------------------------ LOG-RIDER-006 workload

    public function test_rider_list_shows_active_jobs_and_completed_today(): void
    {
        $center = $this->center('Workload Center');
        $staff = $this->user('logistics_staff', $center->id);
        $rider = $this->rider($this->user('rider'), [
            'center_id' => $center->id,
            'name' => 'Workload Rider',
            'email' => 'workload-rider@test.local',
        ]);

        // Two active jobs (states count toward the 0..3 capacity).
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'assigned']);
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'out_for_delivery']);

        // Two delivered today, one delivered yesterday (must not count today).
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'delivered', 'delivered_at' => now()]);
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'delivered', 'delivered_at' => now()->subHours(2)]);
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'delivered', 'delivered_at' => now()->subDay()]);

        // A non-active, non-delivered job must not inflate either counter.
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $rider->id, 'status' => 'waiting_for_rider']);

        $response = $this->actingAs($staff)->get('/logistics/riders')->assertOk();

        $row = $response->viewData('riders')->getCollection()->firstWhere('id', $rider->id);
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row->active_jobs);
        $this->assertSame(2, (int) $row->completed_today);

        // The workload matches the rider app's capacity definition exactly.
        $this->assertSame($rider->fresh()->activeDeliveryCount(), (int) $row->active_jobs);

        $response->assertSee('Active Jobs')->assertSee('Today Completed');
    }

    public function test_rider_list_shows_zero_workload_without_errors(): void
    {
        $center = $this->center('Idle Center');
        $staff = $this->user('logistics_staff', $center->id);
        $rider = $this->rider($this->user('rider'), [
            'center_id' => $center->id,
            'name' => 'Idle Rider',
            'email' => 'idle-rider@test.local',
        ]);

        $response = $this->actingAs($staff)->get('/logistics/riders')->assertOk();

        $row = $response->viewData('riders')->getCollection()->firstWhere('id', $rider->id);
        $this->assertSame(0, (int) $row->active_jobs);
        $this->assertSame(0, (int) $row->completed_today);
    }

    public function test_rider_list_attributes_workload_per_rider_without_double_counting(): void
    {
        $center = $this->center('Multi Rider Center');
        $staff = $this->user('logistics_staff', $center->id);
        $busy = $this->rider($this->user('rider'), ['center_id' => $center->id, 'email' => 'busy-riders-a@test.local']);
        $light = $this->rider($this->user('rider'), ['center_id' => $center->id, 'email' => 'light-riders-b@test.local']);

        foreach (['assigned', 'accepted', 'picked_up'] as $status) {
            $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $busy->id, 'status' => $status]);
        }
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $busy->id, 'status' => 'delivered', 'delivered_at' => now()]);
        $this->makeDelivery(['center_id' => $center->id, 'rider_id' => $light->id, 'status' => 'assigned']);

        $collection = $this->actingAs($staff)->get('/logistics/riders')->assertOk()
            ->viewData('riders')->getCollection();

        $this->assertSame(3, (int) $collection->firstWhere('id', $busy->id)->active_jobs);
        $this->assertSame(1, (int) $collection->firstWhere('id', $busy->id)->completed_today);
        $this->assertSame(1, (int) $collection->firstWhere('id', $light->id)->active_jobs);
        $this->assertSame(0, (int) $collection->firstWhere('id', $light->id)->completed_today);
    }

    public function test_rider_list_workload_is_isolated_per_company(): void
    {
        $centerA = $this->center('Workload Company A');
        $centerB = $this->center('Workload Company B');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $riderA = $this->rider($this->user('rider'), ['center_id' => $centerA->id, 'email' => 'workload-scope-a@test.local']);
        $riderB = $this->rider($this->user('rider'), ['center_id' => $centerB->id, 'email' => 'workload-scope-b@test.local']);

        $this->makeDelivery(['center_id' => $centerA->id, 'rider_id' => $riderA->id, 'status' => 'assigned']);
        foreach (['assigned', 'accepted', 'picked_up'] as $status) {
            $this->makeDelivery(['center_id' => $centerB->id, 'rider_id' => $riderB->id, 'status' => $status]);
        }

        $collection = $this->actingAs($staffA)->get('/logistics/riders')->assertOk()
            ->viewData('riders')->getCollection();

        $this->assertSame([$riderA->id], $collection->pluck('id')->all());
        $this->assertSame(1, (int) $collection->firstWhere('id', $riderA->id)->active_jobs);
    }

    public function test_rider_workload_list_is_denied_to_non_logistics_users(): void
    {
        $center = $this->center('Workload Restricted Center');
        $buyer = $this->user('buyer', $center->id);

        $this->actingAs($buyer)
            ->get('/logistics/riders')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }
}
