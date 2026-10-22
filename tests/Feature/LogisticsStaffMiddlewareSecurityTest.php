<?php

namespace Tests\Feature;

use App\Http\Middleware\Logistics\EnsureLogisticsStaff;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsStaffMiddlewareSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function center(): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => 'Middleware Center '.$this->seq,
            'address' => '1 Security Street',
            'city' => 'Security City',
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?int $centerId = null, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "phase-one-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function rider(LogisticsCenter $center): Rider
    {
        $this->seq++;

        return Rider::create([
            'center_id' => $center->id,
            'name' => 'Middleware Rider '.$this->seq,
            'email' => "phase-one-rider-{$this->seq}@test.local",
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

    public function test_buyer_with_a_center_id_is_denied(): void
    {
        $center = $this->center();
        $buyer = $this->user('buyer', $center->id);

        $this->actingAs($buyer)
            ->get('/logistics/dashboard')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_seller_with_a_center_id_is_denied(): void
    {
        $center = $this->center();
        $seller = $this->user('seller', $center->id);

        $this->actingAs($seller)
            ->get('/logistics/dashboard')
            ->assertRedirect('/logistics/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_authorized_logistics_staff_remains_allowed(): void
    {
        $center = $this->center();
        $staff = $this->user('logistics_staff', $center->id);

        $this->actingAs($staff)
            ->get('/logistics/dashboard')
            ->assertOk();
    }

    public function test_logistics_admins_remain_allowed(): void
    {
        foreach (['admin', 'logistics_admin'] as $role) {
            $admin = $this->user($role);

            $this->actingAs($admin)
                ->get('/logistics/dashboard')
                ->assertOk();
        }
    }

    public function test_active_logistics_admins_can_access_admin_routes(): void
    {
        foreach (['admin', 'logistics_admin'] as $role) {
            $admin = $this->user($role);

            $this->actingAs($admin)
                ->get('/logistics/centers')
                ->assertOk();
        }
    }

    public function test_suspended_logistics_admins_are_denied_by_the_admin_middleware(): void
    {
        foreach (['admin', 'logistics_admin'] as $role) {
            $admin = $this->user($role, null, [
                'account_status' => 'suspended',
                'status' => 'suspended',
            ]);

            $this->actingAs($admin)
                ->withoutMiddleware(EnsureLogisticsStaff::class)
                ->get('/logistics/centers')
                ->assertRedirect('/logistics/login')
                ->assertSessionHas('error');

            $this->assertGuest();
        }
    }

    public function test_suspended_logistics_admins_receive_json_403_from_the_admin_middleware(): void
    {
        $admin = $this->user('logistics_admin', null, [
            'account_status' => 'suspended',
            'status' => 'suspended',
        ]);

        $this->actingAs($admin)
            ->withoutMiddleware(EnsureLogisticsStaff::class)
            ->getJson('/logistics/centers')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not active.');
    }

    public function test_center_scoped_rider_access_still_works(): void
    {
        $centerA = $this->center();
        $centerB = $this->center();
        $staffA = $this->user('logistics_staff', $centerA->id);
        $ownRider = $this->rider($centerA);
        $otherRider = $this->rider($centerB);

        $this->actingAs($staffA)
            ->get("/logistics/riders/{$ownRider->id}")
            ->assertOk();

        $this->actingAs($staffA)
            ->get("/logistics/riders/{$otherRider->id}")
            ->assertForbidden();
    }
}
