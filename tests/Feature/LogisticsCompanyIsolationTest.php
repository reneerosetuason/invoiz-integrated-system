<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\PickupRequest;
use App\Models\Remittance;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\Transaction;
use App\Models\User;
use App\Support\LogisticsAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1 authorization guarantees: company isolation for logistics staff and
 * managers, the separate Platform Administrator identity, the designated
 * global Logistics Admin, and the guards that keep hub ownership stable.
 */
class LogisticsCompanyIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(string $name): LogisticsCompany
    {
        $this->seq++;

        return LogisticsCompany::create([
            'name' => $name.' '.$this->seq.' '.uniqid(),
            'registration_number' => 'ISOL-'.$this->seq.'-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function center(string $name, ?int $companyId): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Isolation Street',
            'city' => 'Isolation City',
            'is_active' => true,
            'logistics_company_id' => $companyId,
        ]);
    }

    private function user(string $role, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst(str_replace('_', ' ', $role)).' '.$this->seq,
            'email' => "isolation-{$role}-{$this->seq}-".uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function platformAdministrator(): User
    {
        return $this->user('admin', ['is_admin' => true]);
    }

    private function designatedGlobalAdmin(): User
    {
        $admin = new User([
            'name' => 'INVOIZ Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;

        return $admin;
    }

    private function companyManager(LogisticsCompany $company): User
    {
        return $this->user('admin', [
            'is_admin' => false,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staff(LogisticsCenter $center, ?int $companyId): User
    {
        return $this->user('logistics_staff', [
            'center_id' => $center->id,
            'logistics_company_id' => $companyId,
        ]);
    }

    private function rider(LogisticsCenter $center): Rider
    {
        $this->seq++;

        return Rider::create([
            'center_id' => $center->id,
            'name' => 'Isolation Rider '.$this->seq,
            'email' => 'isolation-rider-'.$this->seq.'-'.uniqid().'@test.local',
            'phone' => '0917'.random_int(1000000, 9999999),
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 50,
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);
    }

    private function area(LogisticsCenter $center): ServiceArea
    {
        $this->seq++;

        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Isolation Area '.$this->seq,
            'is_active' => true,
        ]);
    }

    private function delivery(LogisticsCenter $center, array $overrides = []): Delivery
    {
        return Delivery::create(array_merge([
            'center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'sender_name' => 'Isolation Sender',
            'recipient_name' => 'Isolation Recipient',
            'recipient_address' => '2 Isolation Street',
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 0,
            'delivery_fee' => 50,
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

    private function transactionFor(Delivery $delivery, LogisticsCenter $center, float $amount = 50): Transaction
    {
        return Transaction::create([
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'logistics_center_id' => $center->id,
            'amount' => $amount,
            'rider_fee' => 50,
            'logistics_fee' => 15,
            'admin_commission' => 0,
            'status' => 'completed',
        ]);
    }

    private function remittanceFor(Delivery $delivery, Rider $rider, float $amount = 100): Remittance
    {
        return Remittance::create([
            'order_id' => 1,
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'seller_id' => 1,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    private function requireStatusLogTable(): void
    {
        if (! Schema::hasTable('delivery_status_logs')) {
            $this->markTestSkipped('The delivery_status_logs table is missing from this database; the positive workflow path cannot be exercised.');
        }
    }

    public function test_platform_admin_and_designated_global_admin_are_global_but_a_company_manager_is_not(): void
    {
        $platform = $this->platformAdministrator();
        $designated = $this->designatedGlobalAdmin();
        $company = $this->company('Manager Company');
        $manager = $this->companyManager($company);

        $this->assertTrue($platform->isAdmin());
        $this->assertFalse($platform->isLogisticsAdmin());
        $this->assertTrue(LogisticsAccess::isGlobal($platform));

        $this->assertTrue($designated->isLogisticsAdmin());
        $this->assertFalse($designated->isAdmin());
        $this->assertTrue(LogisticsAccess::isGlobal($designated));

        $this->assertFalse($manager->isAdmin());
        $this->assertTrue($manager->isCompanyManager());
        $this->assertTrue($manager->isLogisticsStaff());
        $this->assertFalse(LogisticsAccess::isGlobal($manager));
        $this->assertSame($company->id, LogisticsAccess::companyId($manager));
        $this->assertNull(LogisticsAccess::centerId($manager));
    }

    public function test_company_manager_cannot_reach_platform_or_network_administration(): void
    {
        $manager = $this->companyManager($this->company('Platform Boundary Company'));

        $this->actingAs($manager)->get('/admin/manage-accounts')->assertForbidden();
        $this->actingAs($manager)->get('/logistics/companies')->assertForbidden();
        $this->actingAs($manager)->get('/logistics/centers')->assertForbidden();
    }

    public function test_company_manager_sees_every_hub_in_own_company_and_nothing_else(): void
    {
        $companyA = $this->company('Manager Company A');
        $companyB = $this->company('Manager Company B');
        $hubA1 = $this->center('Manager A Hub One', $companyA->id);
        $hubA2 = $this->center('Manager A Hub Two', $companyA->id);
        $hubB = $this->center('Manager B Hub', $companyB->id);

        $this->delivery($hubA1);
        $this->delivery($hubA2);
        $this->delivery($hubB);

        $response = $this->actingAs($this->companyManager($companyA))
            ->get('/logistics/deliveries')
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            [$hubA1->id, $hubA2->id],
            $response->viewData('deliveries')->pluck('center_id')->unique()->values()->all()
        );
        $this->assertEqualsCanonicalizing(
            [$hubA1->id, $hubA2->id],
            $response->viewData('filterCenters')->pluck('id')->all()
        );
    }

    public function test_company_a_staff_only_see_their_own_hub_records_and_options(): void
    {
        $companyA = $this->company('Staff Company A');
        $companyB = $this->company('Staff Company B');
        $hubA1 = $this->center('Staff A Hub One', $companyA->id);
        $hubA2 = $this->center('Staff A Hub Two', $companyA->id);
        $hubB = $this->center('Staff B Hub', $companyB->id);

        $this->delivery($hubA1);
        $this->delivery($hubA2);
        $this->delivery($hubB);
        $ownRider = $this->rider($hubA1);
        $this->rider($hubA2);
        $this->rider($hubB);

        $response = $this->actingAs($this->staff($hubA1, $companyA->id))
            ->get('/logistics/deliveries')
            ->assertOk();

        $this->assertSame(1, $response->viewData('deliveries')->total());
        $this->assertSame($hubA1->id, (int) $response->viewData('deliveries')->first()->center_id);
        $this->assertSame([$hubA1->id], $response->viewData('filterCenters')->pluck('id')->all());
        $this->assertSame([$ownRider->id], $response->viewData('filterRiders')->pluck('id')->all());
    }

    public function test_staff_without_a_company_or_center_assignment_get_no_records(): void
    {
        $company = $this->company('Unassigned Company');
        $hub = $this->center('Unassigned Hub', $company->id);
        $this->delivery($hub);
        $this->rider($hub);

        $unassigned = $this->user('logistics_staff');

        $this->assertSame(0, LogisticsAccess::companyId($unassigned));
        $this->assertSame(0, LogisticsAccess::centerId($unassigned));

        $deliveries = $this->actingAs($unassigned)->get('/logistics/deliveries')->assertOk();
        $this->assertSame(0, $deliveries->viewData('deliveries')->total());
        $this->assertCount(0, $deliveries->viewData('filterCenters'));
        $this->assertCount(0, $deliveries->viewData('filterRiders'));

        $transactions = $this->actingAs($unassigned)->get('/logistics/transactions')->assertOk();
        $this->assertSame(0, $transactions->viewData('transactions')->total());

        $remittances = $this->actingAs($unassigned)->get('/logistics/remittances')->assertOk();
        $this->assertSame(0, $remittances->viewData('remittances')->total());

        $this->assertSame(0, PickupRequest::visibleTo($unassigned)->count());
    }

    public function test_staff_cannot_sort_into_another_companys_center(): void
    {
        $companyA = $this->company('Sort Company A');
        $companyB = $this->company('Sort Company B');
        $hubA = $this->center('Sort A Hub', $companyA->id);
        $hubB = $this->center('Sort B Hub', $companyB->id);
        $areaB = $this->area($hubB);
        $delivery = $this->delivery($hubA, ['parcel_status' => 'scanned', 'scanned_at' => now()]);

        $this->actingAs($this->staff($hubA, $companyA->id))
            ->post(route('deliveries.sort', $delivery), [
                'destination_center_id' => $hubB->id,
                'service_area_id' => $areaB->id,
            ])
            ->assertForbidden();

        $this->assertNull($delivery->fresh()->destination_center_id);
        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    public function test_company_manager_cannot_sort_into_another_companys_center(): void
    {
        $companyA = $this->company('Sort Manager A');
        $companyB = $this->company('Sort Manager B');
        $hubA = $this->center('Sort Manager Hub A', $companyA->id);
        $hubB = $this->center('Sort Manager Hub B', $companyB->id);
        $areaB = $this->area($hubB);
        $delivery = $this->delivery($hubA, ['parcel_status' => 'scanned', 'scanned_at' => now()]);

        $this->actingAs($this->companyManager($companyA))
            ->post(route('deliveries.sort', $delivery), [
                'destination_center_id' => $hubB->id,
                'service_area_id' => $areaB->id,
            ])
            ->assertForbidden();
    }

    public function test_sorting_inside_the_same_company_still_works(): void
    {
        $this->requireStatusLogTable();

        $company = $this->company('Intra Company Sort');
        $hubA1 = $this->center('Intra Hub One', $company->id);
        $hubA2 = $this->center('Intra Hub Two', $company->id);
        $area = $this->area($hubA2);
        $delivery = $this->delivery($hubA1, ['parcel_status' => 'scanned', 'scanned_at' => now()]);

        $this->actingAs($this->staff($hubA1, $company->id))
            ->post(route('deliveries.sort', $delivery), [
                'destination_center_id' => $hubA2->id,
                'service_area_id' => $area->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($hubA2->id, (int) $delivery->fresh()->destination_center_id);
    }

    public function test_staff_cannot_receive_another_companys_parcel(): void
    {
        $companyA = $this->company('Receive Company A');
        $companyB = $this->company('Receive Company B');
        $hubA = $this->center('Receive A Hub', $companyA->id);
        $hubB = $this->center('Receive B Hub', $companyB->id);
        $staffA = $this->staff($hubA, $companyA->id);

        $otherCenterParcel = $this->delivery($hubB, ['parcel_status' => 'pending_arrival']);
        $this->actingAs($staffA)
            ->post(route('deliveries.receive', $otherCenterParcel), ['center_id' => $hubA->id])
            ->assertForbidden();
        $this->assertSame($hubB->id, (int) $otherCenterParcel->fresh()->center_id);

        $unroutedParcel = $this->delivery($hubA, ['center_id' => null, 'parcel_status' => 'pending_arrival']);
        $this->actingAs($staffA)
            ->post(route('deliveries.receive', $unroutedParcel), ['center_id' => $hubB->id])
            ->assertForbidden();
        $this->assertNull($unroutedParcel->fresh()->center_id);
    }

    public function test_staff_can_receive_an_unrouted_parcel_at_their_own_hub(): void
    {
        $this->requireStatusLogTable();

        $company = $this->company('Receive Own Company');
        $hub = $this->center('Receive Own Hub', $company->id);
        $delivery = $this->delivery($hub, ['center_id' => null, 'parcel_status' => 'pending_arrival']);

        $this->actingAs($this->staff($hub, $company->id))
            ->post(route('deliveries.receive', $delivery), ['center_id' => $hub->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($hub->id, (int) $delivery->fresh()->center_id);
    }

    public function test_dispatch_is_blocked_when_the_assigned_rider_belongs_to_another_company(): void
    {
        $companyA = $this->company('Dispatch Company A');
        $companyB = $this->company('Dispatch Company B');
        $hubA = $this->center('Dispatch A Hub', $companyA->id);
        $hubB = $this->center('Dispatch B Hub', $companyB->id);
        $foreignRider = $this->rider($hubB);
        $delivery = $this->delivery($hubA, [
            'parcel_status' => 'sorted',
            'sorted_at' => now(),
            'rider_id' => $foreignRider->id,
        ]);

        $this->actingAs($this->staff($hubA, $companyA->id))
            ->post(route('deliveries.dispatch', $delivery))
            ->assertForbidden();

        $this->actingAs($this->companyManager($companyA))
            ->post(route('deliveries.dispatch', $delivery))
            ->assertForbidden();

        $this->assertSame('sorted', $delivery->fresh()->parcel_status);
    }

    public function test_transactions_and_remittances_are_company_scoped(): void
    {
        $companyA = $this->company('Ledger Company A');
        $companyB = $this->company('Ledger Company B');
        $hubA = $this->center('Ledger A Hub', $companyA->id);
        $hubB = $this->center('Ledger B Hub', $companyB->id);
        $deliveryA = $this->delivery($hubA);
        $deliveryB = $this->delivery($hubB);
        $this->transactionFor($deliveryA, $hubA, 50);
        $this->transactionFor($deliveryB, $hubB, 90);
        $this->remittanceFor($deliveryA, $this->rider($hubA), 100);
        $this->remittanceFor($deliveryB, $this->rider($hubB), 200);

        $staffA = $this->staff($hubA, $companyA->id);
        $staffB = $this->staff($hubB, $companyB->id);

        $transactionsA = $this->actingAs($staffA)->get('/logistics/transactions')->assertOk();
        $this->assertSame(1, $transactionsA->viewData('transactions')->total());
        $this->assertEquals(50.0, (float) $transactionsA->viewData('totals')->total_amount);

        $transactionsB = $this->actingAs($staffB)->get('/logistics/transactions')->assertOk();
        $this->assertSame(1, $transactionsB->viewData('transactions')->total());
        $this->assertEquals(90.0, (float) $transactionsB->viewData('totals')->total_amount);

        $remittancesA = $this->actingAs($staffA)->get('/logistics/remittances')->assertOk();
        $this->assertSame(1, $remittancesA->viewData('remittances')->total());
        $this->assertSame(100.0, $remittancesA->viewData('totals')['total']);

        $remittancesB = $this->actingAs($staffB)->get('/logistics/remittances')->assertOk();
        $this->assertSame(1, $remittancesB->viewData('remittances')->total());
        $this->assertSame(200.0, $remittancesB->viewData('totals')['total']);
    }

    public function test_pickup_request_visibility_follows_global_company_and_hub_scope(): void
    {
        $companyA = $this->company('Pickup Company A');
        $companyB = $this->company('Pickup Company B');
        $hubA = $this->center('Pickup A Hub', $companyA->id);
        $hubB = $this->center('Pickup B Hub', $companyB->id);
        $deliveryA = $this->delivery($hubA);
        $deliveryB = $this->delivery($hubB);
        $this->pickupRequest($deliveryA, $hubA);
        $this->pickupRequest($deliveryB, $hubB);

        // Counts are limited to this fixture so pre-existing rows in the
        // shared test database cannot mask the scope.
        $visible = fn (User $user): int => PickupRequest::visibleTo($user)
            ->whereIn('delivery_id', [$deliveryA->id, $deliveryB->id])
            ->count();

        $this->assertSame(2, $visible($this->platformAdministrator()));
        $this->assertSame(2, $visible($this->designatedGlobalAdmin()));
        $this->assertSame(1, $visible($this->companyManager($companyA)));
        $this->assertSame(1, $visible($this->staff($hubA, $companyA->id)));
        $this->assertSame(0, $visible($this->user('logistics_staff')));
    }

    public function test_center_reassignment_is_blocked_by_a_pending_pickup_request(): void
    {
        $platform = $this->platformAdministrator();
        $companyA = $this->company('Reassign Company A');
        $companyB = $this->company('Reassign Company B');
        $hub = $this->center('Reassign Hub', $companyA->id);
        $delivery = $this->delivery($hub, ['status' => 'delivered', 'delivered_at' => now()]);
        $pickup = $this->pickupRequest($delivery, $hub);

        $payload = [
            'logistics_company_id' => $companyB->id,
            'evidence' => 'Ownership review evidence.',
            'reason' => 'Ownership transfer requested by the network team.',
        ];

        $this->actingAs($platform)
            ->put(route('companies.centers.assign', $hub), $payload)
            ->assertStatus(409);

        $this->assertSame($companyA->id, (int) $hub->fresh()->logistics_company_id);

        $pickup->update(['status' => 'completed', 'reviewed_at' => now()]);

        $this->actingAs($platform)
            ->put(route('companies.centers.assign', $hub), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($companyB->id, (int) $hub->fresh()->logistics_company_id);
    }

    public function test_center_reassignment_is_blocked_by_active_deliveries_and_riders(): void
    {
        $platform = $this->platformAdministrator();
        $companyA = $this->company('Reassign Active A');
        $companyB = $this->company('Reassign Active B');
        $hub = $this->center('Reassign Active Hub', $companyA->id);
        $this->delivery($hub, ['status' => 'assigned']);
        $this->rider($hub);

        $this->actingAs($platform)
            ->put(route('companies.centers.assign', $hub), [
                'logistics_company_id' => $companyB->id,
                'evidence' => 'Ownership review evidence.',
                'reason' => 'Ownership transfer requested by the network team.',
            ])
            ->assertStatus(409);

        $this->assertSame($companyA->id, (int) $hub->fresh()->logistics_company_id);
    }

    public function test_center_reassignment_is_blocked_by_service_areas_and_active_hub_transfers(): void
    {
        if (! Schema::hasTable('hub_transfers')) {
            $this->markTestSkipped('The hub transfer tables are not present in this database.');
        }

        $platform = $this->platformAdministrator();
        $companyA = $this->company('Reassign Transfer A');
        $companyB = $this->company('Reassign Transfer B');
        $hub = $this->center('Reassign Transfer Hub', $companyA->id);
        $otherHub = $this->center('Reassign Transfer Other Hub', $companyA->id);
        $this->area($hub);

        $this->actingAs($platform)
            ->put(route('companies.centers.assign', $hub), [
                'logistics_company_id' => $companyB->id,
                'evidence' => 'Ownership review evidence.',
                'reason' => 'Ownership transfer requested by the network team.',
            ])
            ->assertStatus(409);

        ServiceArea::where('logistics_center_id', $hub->id)->delete();

        HubTransfer::create([
            'reference' => 'HT-'.uniqid(),
            'logistics_company_id' => $companyA->id,
            'origin_center_id' => $hub->id,
            'destination_center_id' => $otherHub->id,
            'status' => 'pending',
            'created_by' => $platform->id,
        ]);

        $this->actingAs($platform)
            ->put(route('companies.centers.assign', $hub), [
                'logistics_company_id' => $companyB->id,
                'evidence' => 'Ownership review evidence.',
                'reason' => 'Ownership transfer requested by the network team.',
            ])
            ->assertStatus(409);

        $this->assertSame($companyA->id, (int) $hub->fresh()->logistics_company_id);
    }
}
