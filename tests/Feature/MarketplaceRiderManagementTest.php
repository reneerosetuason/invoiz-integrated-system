<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCenterApplication;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\Seller;
use App\Models\User;
use App\Services\RiderAccountProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MarketplaceRiderManagementTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role = 'buyer', bool $isAdmin = false, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Rider Access '.$this->seq,
            'email' => 'rider-access-'.$this->seq.'@test.local',
            'password' => 'TestPassword123!',
            'role' => $role,
            'is_admin' => $isAdmin,
            'center_id' => $centerId,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function center(): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => 'Rider Access Center '.(++$this->seq),
            'address' => '1 Test Street', 'city' => 'Test City', 'is_active' => true,
        ]);
    }

    private function rider(User $user, ?LogisticsCenter $center = null, string $status = 'available'): Rider
    {
        return Rider::create([
            'user_id' => $user->id, 'center_id' => $center?->id,
            'name' => $user->name, 'email' => $user->email,
            'phone' => '09171234567', 'vehicle_type' => 'motorcycle',
            'status' => $status, 'is_verified' => true, 'approved_at' => now(),
            'vehicle_verification' => 'verified', 'license_expiry' => now()->addYear(),
        ]);
    }

    public function test_marketplace_directories_show_riders_without_account_actions(): void
    {
        $admin = $this->user('admin', true);
        $riderUser = $this->user('rider');
        $linkedBuyer = $this->user('buyer');
        $this->rider($linkedBuyer);

        $this->actingAs($admin)->get('/admin/riders')->assertOk()
            ->assertSee('Rider Directory')->assertSee($riderUser->email)
            ->assertDontSee('/admin/manage-accounts/'.$riderUser->id.'/action', false);

        $this->get('/admin/manage-accounts')->assertOk()
            ->assertSee('Managed by logistics')
            ->assertDontSee('/admin/manage-accounts/'.$riderUser->id.'/action', false)
            ->assertDontSee('/admin/manage-accounts/'.$linkedBuyer->id.'/action', false);
    }

    public function test_marketplace_admin_cannot_change_rider_or_linked_account_status(): void
    {
        $admin = $this->user('admin', true);
        foreach (['rider', 'buyer', 'seller'] as $role) {
            foreach (['available', 'inactive'] as $riderStatus) {
                $target = $this->user($role);
                $rider = $this->rider($target, status: $riderStatus);
                foreach (['activate', 'suspend', 'deactivate'] as $action) {
                    $this->actingAs($admin)->postJson('/admin/manage-accounts/'.$target->id.'/action', [
                        'action' => $action,
                    ])->assertForbidden();
                    $this->assertSame('active', $target->fresh()->account_status);
                    $this->assertSame('active', $target->fresh()->status);
                    $this->assertSame($riderStatus, $rider->fresh()->status);
                }
            }
        }
        // Legacy rider identities are protected even before a profile is linked.
        $legacy = $this->user('rider');
        $this->actingAs($admin)->postJson('/admin/manage-accounts/'.$legacy->id.'/action', [
            'action' => 'suspend',
        ])->assertForbidden();
        $this->assertSame('active', $legacy->fresh()->status);
    }

    public function test_marketplace_admin_can_still_manage_accounts_without_rider_profiles(): void
    {
        $admin = $this->user('admin', true);
        foreach (['buyer', 'seller'] as $role) {
            $target = $this->user($role);
            $this->actingAs($admin)->from('/admin/manage-accounts')
                ->post('/admin/manage-accounts/'.$target->id.'/action', ['action' => 'suspend'])
                ->assertRedirect('/admin/manage-accounts');
            $this->assertSame('suspended', $target->fresh()->status);
        }
    }

    public function test_store_suspension_and_reinstatement_do_not_change_shared_rider_access(): void
    {
        $admin = $this->user('admin', true);
        foreach (['buyer', 'seller', 'rider'] as $role) {
            $target = $this->user($role);
            $rider = $this->rider($target);
            $seller = Seller::create([
                'user_id' => $target->id, 'store_name' => 'Shared Rider Store '.$target->id,
                'line_of_business' => 'Retail', 'approval_status' => 'approved', 'status' => 'active',
            ]);
            $this->actingAs($admin)->postJson('/admin/sellers/'.$seller->id.'/suspend')->assertOk();
            $this->assertSame('inactive', $seller->fresh()->status);
            $this->assertSame('active', $target->fresh()->status);
            $this->assertSame('available', $rider->fresh()->status);

            // A store reinstatement must also preserve a logistics suspension.
            $target->update(['account_status' => 'suspended']);
            $rider->update(['status' => 'inactive']);
            $this->postJson('/admin/sellers/'.$seller->id.'/reinstate')->assertOk();
            $this->assertSame('active', $seller->fresh()->status);
            $this->assertSame('suspended', $target->fresh()->status);
            $this->assertSame('inactive', $rider->fresh()->status);
        }
    }

    public function test_staff_management_cannot_bypass_shared_rider_account_protection(): void
    {
        $admin = $this->user('admin', true);
        foreach (['staff', 'logistics_staff'] as $role) {
            $target = $this->user($role);
            $rider = $this->rider($target);
            $this->actingAs($admin)->putJson('/logistics/staff/'.$target->id, [])->assertForbidden();
            $this->deleteJson('/logistics/staff/'.$target->id)->assertForbidden();
            $this->postJson('/logistics/staff/'.$target->id.'/activate')->assertForbidden();
            $this->getJson('/logistics/staff/'.$target->id.'/edit')->assertForbidden();
            $this->get('/logistics/staff/'.$target->id)->assertOk()
                ->assertDontSee('/logistics/staff/'.$target->id.'/edit', false);
            $this->assertSame('active', $target->fresh()->status);
            $this->assertSame('available', $rider->fresh()->status);
        }
        $regularStaff = $this->user('logistics_staff');
        $this->actingAs($admin)->delete('/logistics/staff/'.$regularStaff->id)->assertRedirect();
        $this->assertSame('inactive', $regularStaff->fresh()->status);
    }

    public function test_marketplace_admin_cannot_mutate_riders_through_logistics_routes(): void
    {
        Mail::fake();
        $riderUser = $this->user('rider');
        $rider = $this->rider($riderUser);
        $password = $riderUser->password;
        foreach ([['admin', true], ['admin', false], ['buyer', true], ['logistics_admin', true]] as [$role, $flag]) {
            $admin = $this->user($role, $flag);
            foreach (['activate', 'deactivate', 'reverify', 'reset-credentials'] as $action) {
                $this->actingAs($admin)->postJson('/logistics/riders/'.$rider->id.'/'.$action, [
                    'reason' => 'An administrator must not change this rider.',
                ])->assertForbidden();
            }
        }
        $this->assertSame('available', $rider->fresh()->status);
        $this->assertSame($password, $riderUser->fresh()->password);
        Mail::assertNothingSent();
    }

    public function test_marketplace_admin_can_only_inspect_the_logistics_rider_directory(): void
    {
        $admin = $this->user('admin', true);
        $rider = $this->rider($this->user('rider'));
        $this->actingAs($admin)->get('/logistics/riders')->assertOk()
            ->assertDontSee('/logistics/riders/'.$rider->id.'/deactivate', false)
            ->assertDontSee('/logistics/rider-applications', false);
        $this->get('/logistics/riders/'.$rider->id)->assertOk()
            ->assertDontSee('/logistics/riders/'.$rider->id.'/reset-credentials', false)
            ->assertDontSee('/logistics/riders/'.$rider->id.'/reverify', false);
    }

    public function test_marketplace_admin_cannot_review_or_provision_rider_applications(): void
    {
        $admin = $this->user('admin', true);
        $application = RiderApplication::create([
            'name' => 'Pending Rider', 'email' => 'pending-rider-access@test.local',
            'phone' => '09171234567', 'address' => '1 Test Street',
            'rider_type' => 'full_time', 'vehicle_type' => 'motorcycle',
            'license_plate' => 'TEST-123', 'status' => 'pending',
        ]);
        $this->actingAs($admin)->getJson('/logistics/rider-applications')->assertForbidden();
        $this->getJson('/logistics/rider-applications/'.$application->id)->assertForbidden();
        foreach (['approve', 'reject', 'resend-credentials'] as $action) {
            $this->postJson('/logistics/rider-applications/'.$application->id.'/'.$action)->assertForbidden();
        }
        $document = RiderApplicationDocument::create([
            'rider_application_id' => $application->id, 'document_type' => 'valid_id',
            'original_filename' => 'private-id.pdf', 'stored_path' => 'private-id.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 100,
        ]);
        foreach (['view', 'download'] as $action) {
            $this->getJson('/logistics/rider-applications/documents/'.$document->id.'/'.$action)
                ->assertForbidden();
        }
        $service = app(RiderAccountProvisioner::class);
        foreach (['approve', 'reject'] as $action) {
            try {
                $action === 'approve'
                    ? $service->approve($application, $admin, [])
                    : $service->reject($application, $admin, 'Not authorized');
                $this->fail('Marketplace administrator reached rider provisioning.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
    }

    public function test_marketplace_admin_cannot_assign_riders_to_deliveries(): void
    {
        $admin = $this->user('admin', true);
        $rider = $this->rider($this->user('rider'));
        $delivery = Delivery::create([
            'tracking_number' => 'TRK-RIDER-ACCESS',
            'sender_name' => 'Test Sender', 'sender_address' => 'Test Street',
            'recipient_name' => 'Test Recipient', 'recipient_address' => 'Test Street',
            'status' => 'waiting_for_rider', 'weight' => 1,
        ]);
        $this->actingAs($admin)->postJson('/logistics/deliveries/'.$delivery->id.'/assign-rider', [
            'rider_id' => $rider->id,
        ])->assertForbidden();
        $this->postJson('/logistics/deliveries/'.$delivery->id.'/auto-assign')->assertForbidden();
        $this->postJson('/logistics/deliveries/bulk-assign-rider', [
            'rider_id' => $rider->id, 'delivery_ids' => [$delivery->id],
        ])->assertForbidden();
        $this->assertNull($delivery->fresh()->rider_id);
        $this->assertSame('waiting_for_rider', $delivery->fresh()->status);
        $this->get('/logistics/deliveries/'.$delivery->id)->assertOk()
            ->assertDontSee('/logistics/deliveries/'.$delivery->id.'/assign-rider', false)
            ->assertDontSee('/logistics/deliveries/'.$delivery->id.'/auto-assign', false);
        $this->get('/logistics/deliveries')->assertOk()->assertDontSee('id="bulk-assign-form"', false);
    }

    public function test_logistics_personnel_can_manage_riders_and_staff_stay_center_scoped(): void
    {
        $center = $this->center();
        $otherCenter = $this->center();
        foreach (['logistics_admin', 'logistics_staff', 'staff', 'buyer'] as $role) {
            $manager = $this->user($role, centerId: $center->id);
            if ($role === 'buyer') {
                LogisticsCenterApplication::create([
                    'business_name' => 'Approved Center', 'owner_name' => $manager->name,
                    'email' => $manager->email, 'phone' => '09171234567',
                    'address' => '1 Test Street', 'status' => 'approved', 'provisioned_at' => now(),
                ]);
            }
            $rider = $this->rider($this->user('rider'), $center, 'inactive');
            $this->actingAs($manager)->from('/logistics/riders')
                ->post('/logistics/riders/'.$rider->id.'/activate')->assertRedirect();
            $this->assertSame('available', $rider->fresh()->status);
            if ($role !== 'logistics_admin') {
                $otherRider = $this->rider($this->user('rider'), $otherCenter, 'inactive');
                $this->postJson('/logistics/riders/'.$otherRider->id.'/activate')->assertForbidden();
                $this->assertSame('inactive', $otherRider->fresh()->status);
            }
        }
    }
}
