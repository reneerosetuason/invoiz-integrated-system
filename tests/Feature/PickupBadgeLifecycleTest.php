<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PickupBadgeLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function adminUser(): User
    {
        return $this->user('logistics_admin');
    }

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "pickup-badge-{$role}-{$this->seq}@test.local",
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
        return LogisticsCenter::create([
            'name' => $name,
            'address' => '1 Test Street',
            'city' => 'Test City',
            'is_active' => true,
        ]);
    }

    private function delivery(?LogisticsCenter $center = null, array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-20261020-8'.str_pad((string) $this->seq, 3, '0', STR_PAD_LEFT),
            'order_id' => Order::query()->value('id'),
            'sender_name' => 'Test Sender',
            'recipient_name' => 'Test Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center?->id,
            'status' => 'assigned',
            'parcel_status' => 'received',
        ], $overrides));
    }

    private function pickupRequest(Delivery $delivery, LogisticsCenter $center, string $status = 'pending'): PickupRequest
    {
        return PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => $status,
            'requested_at' => now(),
        ]);
    }

    private function provisionedUser(string $role, LogisticsCenter $center): User
    {
        $user = $this->user($role, $center->id);

        LogisticsCenterApplication::create([
            'business_name' => ucfirst($role).' Logistics '.$this->seq,
            'owner_name' => $user->name,
            'email' => $user->email,
            'phone' => '0917'.random_int(1000000, 9999999),
            'address' => '1 Test Street',
            'status' => 'approved',
            'provisioned_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_pickup_badge_counts_pending_requests_and_clears_after_review(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('Badge Center A');
        $first = $this->pickupRequest($this->delivery($center), $center);
        $second = $this->pickupRequest($this->delivery($center), $center);

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee('data-pickup-count="2"', false);

        $this->actingAs($admin)
            ->get("/logistics/pickup-requests/{$first->id}")
            ->assertOk();

        $this->assertSame('pending', $first->fresh()->status);
        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="2"', false);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$first->id}/approve")
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false);

        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$second->id}/reject", ['rejection_reason' => 'Pickup slot unavailable.'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('rejected', $second->fresh()->status);
    }

    public function test_pickup_badge_is_scoped_to_the_user_and_center(): void
    {
        $centerA = $this->center('Badge Center A');
        $centerB = $this->center('Badge Center B');

        $this->pickupRequest($this->delivery($centerA), $centerA);
        $this->pickupRequest($this->delivery($centerB), $centerB);

        $admin = $this->adminUser();
        $staffA = $this->user('logistics_staff', $centerA->id);
        $staffB = $this->user('logistics_staff', $centerB->id);
        $unassigned = $this->user('logistics_staff');

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="2"', false);

        $this->actingAs($staffA)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false)
            ->assertDontSee('Badge Center B');

        $this->actingAs($staffB)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="1"', false)
            ->assertDontSee('Badge Center A');

        $this->actingAs($unassigned)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);
    }

    public function test_pending_request_already_picked_up_is_not_counted(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('Stale Badge Center');
        $delivery = $this->delivery($center, [
            'status' => 'out_for_delivery',
            'picked_up_at' => now(),
        ]);

        $stale = $this->pickupRequest($delivery, $center, 'pending');

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertSee('data-pickup-count="0"', false);

        $this->assertSame('pending', $stale->fresh()->status);
    }

    public function test_pickup_request_completes_when_delivery_reaches_pickup(): void
    {
        $center = $this->center('Lifecycle Center');
        $delivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $pickup = $this->pickupRequest($delivery, $center, 'approved');

        $delivery->status = 'picked_up';
        $delivery->picked_up_at = now();
        $delivery->save();

        $fresh = $pickup->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_pickup_request_completes_when_saved_directly_past_pickup(): void
    {
        $center = $this->center('Direct Save Center');
        $delivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $pickup = $this->pickupRequest($delivery, $center, 'pending');

        $delivery->status = 'out_for_delivery';
        $delivery->picked_up_at = now();
        $delivery->save();

        $this->assertSame('completed', $pickup->fresh()->status);
    }

    public function test_pending_filter_and_badge_share_the_same_eligibility(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('Consistent Center');
        $actionable = $this->delivery($center, ['status' => 'assigned']);
        $stale = $this->delivery($center, [
            'status' => 'out_for_delivery',
            'picked_up_at' => now(),
        ]);

        $this->pickupRequest($actionable, $center);
        $this->pickupRequest($stale, $center);

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests?status=pending')
            ->assertOk()
            ->assertSee('data-pickup-count="1"', false)
            ->assertSee($actionable->tracking_number)
            ->assertDontSee($stale->tracking_number);
    }

    public function test_cancelling_a_delivery_marks_its_open_pickup_completed(): void
    {
        $center = $this->center('Cancellation Center');
        $delivery = $this->delivery($center, ['status' => 'assigned']);
        $pickup = $this->pickupRequest($delivery, $center);

        $delivery->status = 'cancelled';
        $delivery->cancelled_at = now();
        $delivery->save();

        $fresh = $pickup->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->reviewed_at);
    }

    public function test_cancelling_a_delivery_does_not_overwrite_reviewed_pickup(): void
    {
        $reviewer = $this->adminUser();
        $center = $this->center('Reviewed Cancellation Center');

        $approvedDelivery = $this->delivery($center, ['status' => 'assigned']);
        $approved = $this->pickupRequest($approvedDelivery, $center, 'approved');
        $approvedReviewedAt = now()->subMinutes(5)->startOfSecond();
        $approved->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $approvedReviewedAt,
        ]);

        $rejectedDelivery = $this->delivery($center, ['status' => 'assigned']);
        $rejected = $this->pickupRequest($rejectedDelivery, $center, 'rejected');
        $rejectedReviewedAt = now()->subMinutes(3)->startOfSecond();
        $rejected->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $rejectedReviewedAt,
            'rejection_reason' => 'Pickup slot unavailable.',
        ]);

        foreach ([$approvedDelivery, $rejectedDelivery] as $delivery) {
            $delivery->status = 'cancelled';
            $delivery->cancelled_at = now();
            $delivery->save();
        }

        $approved->refresh();
        $rejected->refresh();

        $this->assertSame('approved', $approved->status);
        $this->assertSame($reviewer->id, $approved->reviewed_by);
        $this->assertTrue($approved->reviewed_at->equalTo($approvedReviewedAt));

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertSame('Pickup slot unavailable.', $rejected->rejection_reason);
        $this->assertTrue($rejected->reviewed_at->equalTo($rejectedReviewedAt));
    }

    public function test_in_transit_and_completed_statuses_without_picked_up_at_are_not_pending_review(): void
    {
        $admin = $this->adminUser();
        $center = $this->center('In Transit Center');
        $deliveries = [];
        $pickups = [];

        foreach (['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered'] as $status) {
            $delivery = $this->delivery($center, [
                'status' => $status,
                'picked_up_at' => null,
            ]);
            $deliveries[] = $delivery;
            $pickups[] = $this->pickupRequest($delivery, $center, 'pending');
        }

        $this->actingAs($admin)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee('data-pickup-count="0"', false);

        $pendingIndex = $this->actingAs($admin)
            ->get('/logistics/pickup-requests?status=pending')
            ->assertOk();

        foreach ($deliveries as $delivery) {
            $pendingIndex->assertDontSee($delivery->tracking_number);
        }

        foreach ($pickups as $pickup) {
            $this->assertSame('pending', $pickup->fresh()->status);
        }
    }

    public function test_unassigned_staff_cannot_view_or_approve_pickup_request(): void
    {
        $center = $this->center('Unassigned Staff Center');
        $pickup = $this->pickupRequest($this->delivery($center), $center);
        $staff = $this->user('logistics_staff');

        $this->actingAs($staff)
            ->get("/logistics/pickup-requests/{$pickup->id}")
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertForbidden();

        $this->assertSame('pending', $pickup->fresh()->status);
    }

    public function test_global_logistics_admin_and_assigned_staff_review_only_their_scope(): void
    {
        $admin = $this->adminUser();
        $centerA = $this->center('Review Scope Center A');
        $centerB = $this->center('Review Scope Center B');
        $pickupA = $this->pickupRequest($this->delivery($centerA), $centerA);
        $pickupB = $this->pickupRequest($this->delivery($centerB), $centerB);
        $staffA = $this->user('logistics_staff', $centerA->id);

        $this->actingAs($admin)->get("/logistics/pickup-requests/{$pickupA->id}")->assertOk();
        $this->actingAs($admin)->get("/logistics/pickup-requests/{$pickupB->id}")->assertOk();
        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickupB->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $pickupB->fresh()->status);
        $this->assertSame($admin->id, $pickupB->fresh()->reviewed_by);

        $this->actingAs($staffA)->get("/logistics/pickup-requests/{$pickupA->id}")->assertOk();
        $this->actingAs($staffA)->get("/logistics/pickup-requests/{$pickupB->id}")->assertForbidden();
        $this->actingAs($staffA)
            ->post("/logistics/pickup-requests/{$pickupB->id}/approve")
            ->assertForbidden();
        $this->actingAs($staffA)
            ->post("/logistics/pickup-requests/{$pickupA->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $pickupA->fresh()->status);
        $this->assertSame($staffA->id, $pickupA->fresh()->reviewed_by);
    }

    public function test_admin_role_retains_global_pickup_access(): void
    {
        $admin = $this->user('admin');
        $centerA = $this->center('Admin Role Center A');
        $centerB = $this->center('Admin Role Center B');
        $pickupA = $this->pickupRequest($this->delivery($centerA), $centerA);
        $pickupB = $this->pickupRequest($this->delivery($centerB), $centerB);

        $this->actingAs($admin)->get("/logistics/pickup-requests/{$pickupA->id}")->assertOk();
        $this->actingAs($admin)->get("/logistics/pickup-requests/{$pickupB->id}")->assertOk();
        $this->actingAs($admin)
            ->post("/logistics/pickup-requests/{$pickupB->id}/approve")
            ->assertRedirect();

        $this->assertSame('approved', $pickupB->fresh()->status);
        $this->assertSame($admin->id, $pickupB->fresh()->reviewed_by);
    }

    public function test_legacy_is_admin_flag_does_not_grant_global_scope_to_center_staff(): void
    {
        $centerA = $this->center('Legacy Flag Center A');
        $centerB = $this->center('Legacy Flag Center B');
        $deliveryA = $this->delivery($centerA);
        $deliveryB = $this->delivery($centerB);
        $this->pickupRequest($deliveryA, $centerA);
        $pickupB = $this->pickupRequest($deliveryB, $centerB);
        $staff = $this->user('logistics_staff', $centerA->id);
        $staff->update(['is_admin' => true]);

        $this->actingAs($staff)
            ->get('/logistics/pickup-requests')
            ->assertOk()
            ->assertSee('data-pickup-count="1"', false)
            ->assertSee($deliveryA->tracking_number)
            ->assertDontSee($deliveryB->tracking_number);

        $this->actingAs($staff)
            ->get("/logistics/pickup-requests/{$pickupB->id}")
            ->assertForbidden();
    }

    public function test_center_id_without_provisioning_does_not_grant_pickup_access(): void
    {
        $center = $this->center('Incidental Center');
        $buyer = $this->user('buyer', $center->id);
        $pickup = $this->pickupRequest($this->delivery($center), $center);

        $this->actingAs($buyer)
            ->get('/logistics/pickup-requests')
            ->assertRedirect('/logistics/login');

        $this->actingAs($buyer)
            ->get("/logistics/pickup-requests/{$pickup->id}")
            ->assertRedirect('/logistics/login');

        $this->actingAs($buyer)
            ->post("/logistics/pickup-requests/{$pickup->id}/approve")
            ->assertRedirect('/logistics/login');

        $this->assertSame('pending', $pickup->fresh()->status);
    }

    public function test_provisioned_buyer_and_seller_can_review_only_their_center(): void
    {
        foreach (['buyer', 'seller'] as $role) {
            $centerA = $this->center("Provisioned {$role} Center A");
            $centerB = $this->center("Provisioned {$role} Center B");
            $user = $this->provisionedUser($role, $centerA);
            $ownDelivery = $this->delivery($centerA);
            $otherDelivery = $this->delivery($centerB);
            $own = $this->pickupRequest($ownDelivery, $centerA);
            $other = $this->pickupRequest($otherDelivery, $centerB);

            $this->actingAs($user)
                ->get('/logistics/pickup-requests')
                ->assertOk()
                ->assertSee('data-pickup-count="1"', false)
                ->assertSee($ownDelivery->tracking_number)
                ->assertDontSee($otherDelivery->tracking_number);

            $this->actingAs($user)
                ->get("/logistics/pickup-requests/{$own->id}")
                ->assertOk();
            $this->actingAs($user)
                ->get("/logistics/pickup-requests/{$other->id}")
                ->assertForbidden();
            $this->actingAs($user)
                ->post("/logistics/pickup-requests/{$own->id}/approve")
                ->assertRedirect();

            $this->assertSame('approved', $own->fresh()->status);
            $this->assertSame($user->id, $own->fresh()->reviewed_by);
        }
    }

    public function test_cancelling_after_pickup_preserves_reviewed_pickups(): void
    {
        $reviewer = $this->adminUser();
        $center = $this->center('After Pickup Cancellation Center');

        $approvedDelivery = $this->delivery($center, [
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);
        $approved = $this->pickupRequest($approvedDelivery, $center, 'approved');
        $approvedReviewedAt = now()->subMinutes(5)->startOfSecond();
        $approved->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $approvedReviewedAt,
        ]);

        $rejectedDelivery = $this->delivery($center, [
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);
        $rejected = $this->pickupRequest($rejectedDelivery, $center, 'rejected');
        $rejectedReviewedAt = now()->subMinutes(3)->startOfSecond();
        $rejected->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $rejectedReviewedAt,
            'rejection_reason' => 'Pickup slot unavailable.',
        ]);

        foreach ([$approvedDelivery, $rejectedDelivery] as $delivery) {
            $delivery->status = 'cancelled';
            $delivery->cancelled_at = now();
            $delivery->save();
        }

        $approved->refresh();
        $rejected->refresh();

        $this->assertSame('approved', $approved->status);
        $this->assertSame($reviewer->id, $approved->reviewed_by);
        $this->assertTrue($approved->reviewed_at->equalTo($approvedReviewedAt));

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertSame('Pickup slot unavailable.', $rejected->rejection_reason);
        $this->assertTrue($rejected->reviewed_at->equalTo($rejectedReviewedAt));
    }

    public function test_cancelling_with_picked_up_at_set_preserves_approved_pickup(): void
    {
        $reviewer = $this->adminUser();
        $center = $this->center('Timestamp Cancellation Center');
        $delivery = $this->delivery($center, [
            'status' => 'assigned',
            'picked_up_at' => now(),
        ]);
        $pickup = $this->pickupRequest($delivery, $center, 'approved');
        $reviewedAt = now()->subMinutes(4)->startOfSecond();
        $pickup->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $reviewedAt,
        ]);

        $delivery->status = 'cancelled';
        $delivery->cancelled_at = now();
        $delivery->save();

        $fresh = $pickup->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($reviewer->id, $fresh->reviewed_by);
        $this->assertTrue($fresh->reviewed_at->equalTo($reviewedAt));
    }

    public function test_pickup_reached_preserves_rejected_and_completed_pickups(): void
    {
        $reviewer = $this->adminUser();
        $center = $this->center('Pickup Reached Preservation Center');

        $rejectedDelivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $rejected = $this->pickupRequest($rejectedDelivery, $center, 'rejected');
        $rejectedReviewedAt = now()->subMinutes(6)->startOfSecond();
        $rejected->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $rejectedReviewedAt,
            'rejection_reason' => 'Pickup slot unavailable.',
        ]);

        $completedDelivery = $this->delivery($center, ['status' => 'going_to_pickup']);
        $completed = $this->pickupRequest($completedDelivery, $center, 'completed');
        $completedReviewedAt = now()->subMinutes(4)->startOfSecond();
        $completed->update([
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => $completedReviewedAt,
        ]);

        foreach ([$rejectedDelivery, $completedDelivery] as $delivery) {
            $delivery->status = 'picked_up';
            $delivery->picked_up_at = now();
            $delivery->save();
        }

        $rejected->refresh();
        $completed->refresh();

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame($reviewer->id, $rejected->reviewed_by);
        $this->assertSame('Pickup slot unavailable.', $rejected->rejection_reason);
        $this->assertTrue($rejected->reviewed_at->equalTo($rejectedReviewedAt));

        $this->assertSame('completed', $completed->status);
        $this->assertSame($reviewer->id, $completed->reviewed_by);
        $this->assertTrue($completed->reviewed_at->equalTo($completedReviewedAt));
    }
}
