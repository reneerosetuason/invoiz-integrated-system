<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RiderListLegacyAdminScopeTest extends TestCase
{
    use DatabaseTransactions;

    private int $sequence = 0;

    public function test_center_assigned_staff_with_legacy_admin_flag_only_see_own_center_riders(): void
    {
        $ownCenter = $this->center('Own Center');
        $otherCenter = $this->center('Other Center');
        $staff = $this->user('logistics_staff', [
            'center_id' => $ownCenter->id,
            'is_admin' => true,
        ]);

        $ownRider = $this->rider($ownCenter->id);
        $otherRider = $this->rider($otherCenter->id);

        $response = $this->actingAs($staff)
            ->get('/logistics/riders')
            ->assertOk()
            ->assertSee($ownRider->email)
            ->assertDontSee($otherRider->email);

        $this->assertSame(
            [$ownRider->id],
            $response->viewData('riders')->getCollection()->pluck('id')->all()
        );
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

    private function user(string $role, array $overrides = []): User
    {
        $this->sequence++;

        return User::factory()->create(array_merge([
            'name' => ucfirst($role).' '.$this->sequence,
            'email' => "rider-list-scope-{$this->sequence}@test.local",
            'role' => $role,
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
        ], $overrides));
    }

    private function rider(int $centerId): Rider
    {
        $user = $this->user('rider');

        return Rider::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0917'.str_pad((string) $this->sequence, 7, '0', STR_PAD_LEFT),
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'center_id' => $centerId,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);
    }
}
