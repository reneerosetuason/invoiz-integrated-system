<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsNotificationScopeTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "notification-{$role}-{$this->seq}@test.local",
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
            'address' => '1 Notification Street',
            'city' => 'Notification City',
            'is_active' => true,
        ]);
    }

    private function notice(string $title, string $audience, ?LogisticsCenter $center = null): Notification
    {
        return Notification::create([
            'type' => 'new_delivery_request',
            'title' => $title,
            'message' => $title.' message',
            'icon' => 'pickup',
            'priority' => 'normal',
            'center_id' => $center?->id,
            'audience' => $audience,
        ]);
    }

    public function test_center_staff_see_global_and_own_center_notifications_only(): void
    {
        $centerA = $this->center('Scope Center A');
        $centerB = $this->center('Scope Center B');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $own = $this->notice('Own center notice', 'center', $centerA);
        $this->notice('Other center notice', 'center', $centerB);
        $this->notice('Admin only notice', 'admin');
        $this->notice('Global notice', 'global');

        $response = $this->actingAs($staffA)
            ->getJson('/logistics/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Own center notice'])
            ->assertJsonFragment(['title' => 'Global notice'])
            ->assertJsonMissing(['title' => 'Other center notice'])
            ->assertJsonMissing(['title' => 'Admin only notice']);

        $this->assertSame(
            Notification::query()->visibleTo($staffA)->unread()->count(),
            $response->json('unread_count')
        );

        $this->actingAs($staffA)
            ->get('/logistics/notifications')
            ->assertOk()
            ->assertSee('Own center notice')
            ->assertSee('Global notice')
            ->assertDontSee('Other center notice')
            ->assertDontSee('Admin only notice');

        $this->actingAs($staffA)
            ->patchJson("/logistics/notifications/{$own->id}/read")
            ->assertOk();
    }

    public function test_center_staff_cannot_mark_another_centers_notification_read(): void
    {
        $centerA = $this->center('Scope Center A');
        $centerB = $this->center('Scope Center B');
        $staffA = $this->user('logistics_staff', $centerA->id);
        $other = $this->notice('Other center notice', 'center', $centerB);

        $this->actingAs($staffA)
            ->patchJson("/logistics/notifications/{$other->id}/read")
            ->assertNotFound();

        $this->assertFalse($other->fresh()->is_read);
    }

    public function test_mark_all_read_only_touches_visible_notifications(): void
    {
        $centerA = $this->center('Scope Center A');
        $centerB = $this->center('Scope Center B');
        $staffA = $this->user('logistics_staff', $centerA->id);

        $own = $this->notice('Own center notice', 'center', $centerA);
        $global = $this->notice('Global notice', 'global');
        $other = $this->notice('Other center notice', 'center', $centerB);
        $adminOnly = $this->notice('Admin only notice', 'admin');

        $this->actingAs($staffA)
            ->patchJson('/logistics/notifications/read-all')
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        $this->assertTrue($own->fresh()->is_read);
        $this->assertTrue($global->fresh()->is_read);
        $this->assertFalse($other->fresh()->is_read);
        $this->assertFalse($adminOnly->fresh()->is_read);
    }

    public function test_global_admin_sees_every_audience(): void
    {
        $centerA = $this->center('Scope Center A');
        $admin = $this->user('logistics_admin');

        $this->notice('Own center notice', 'center', $centerA);
        $this->notice('Admin only notice', 'admin');
        $this->notice('Global notice', 'global');

        $this->actingAs($admin)
            ->getJson('/logistics/notifications')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Own center notice'])
            ->assertJsonFragment(['title' => 'Admin only notice'])
            ->assertJsonFragment(['title' => 'Global notice']);
    }
}
