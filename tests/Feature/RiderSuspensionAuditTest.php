<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\RiderNotification;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class RiderSuspensionAuditTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function center(string $name = 'Suspension Center'): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Suspension Street',
            'city' => 'Suspension City',
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "phase-one-suspension-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ]);
    }

    private function rider(LogisticsCenter $center, array $overrides = []): array
    {
        $this->seq++;
        $email = "phase-one-suspension-rider-{$this->seq}@test.local";

        $user = User::create([
            'name' => 'Suspension Rider '.$this->seq,
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $center->id,
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create(array_merge([
            'user_id' => $user->id,
            'center_id' => $center->id,
            'name' => 'Suspension Rider '.$this->seq,
            'email' => $email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 50,
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'is_online' => true,
        ], $overrides));

        return [$user, $rider];
    }

    private function delivery(Rider $rider, string $status = 'assigned', array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'PHASE1-'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'rider_id' => $rider->id,
            'center_id' => $rider->center_id,
            'sender_name' => 'Suspension Sender',
            'recipient_name' => 'Suspension Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'status' => $status,
            'parcel_status' => 'pending_arrival',
            'weight' => 1,
            'assigned_at' => now(),
            'picked_up_at' => $status === 'picked_up' ? now() : null,
        ], $overrides));
    }

    public function test_missing_reason_is_rejected(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);
        $delivery = $this->delivery($rider);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate")
            ->assertSessionHasErrors('reason');

        $this->assertSame('available', $rider->fresh()->status);
        $this->assertSame($rider->id, $delivery->fresh()->rider_id);
    }

    public function test_blank_reason_is_rejected(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => '          '])
            ->assertSessionHasErrors('reason');

        $this->assertSame('available', $rider->fresh()->status);
    }

    public function test_valid_suspension_records_reason_unassigns_pre_pickup_and_audits(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);
        $assigned = $this->delivery($rider, 'assigned');
        $pickedUp = $this->delivery($rider, 'picked_up');
        $reason = 'Repeated unsafe driving reports.';

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => $reason])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertSame('inactive', $rider->status);
        $this->assertFalse($rider->is_online);

        $assigned->refresh();
        $this->assertNull($assigned->rider_id);
        $this->assertSame('waiting_for_rider', $assigned->status);
        $this->assertNull($assigned->assigned_at);

        $pickedUp->refresh();
        $this->assertSame($rider->id, $pickedUp->rider_id);
        $this->assertSame('picked_up', $pickedUp->status);

        $audit = AuditLog::query()
            ->where('action', 'rider.suspended')
            ->where('entity', Rider::class)
            ->where('entity_id', $rider->id)
            ->firstOrFail();

        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame($admin->role, $audit->meta['actor_role']);
        $this->assertSame($reason, $audit->meta['reason']);
        $this->assertSame('available', $audit->meta['old_values']['status']);
        $this->assertTrue($audit->meta['old_values']['is_online']);
        $this->assertContains($assigned->id, $audit->meta['old_values']['active_delivery_ids']);
        $this->assertContains($pickedUp->id, $audit->meta['old_values']['active_delivery_ids']);
        $this->assertSame('inactive', $audit->meta['new_values']['status']);
        $this->assertFalse($audit->meta['new_values']['is_online']);
        $this->assertContains($assigned->id, $audit->meta['new_values']['unassigned_delivery_ids']);
        $this->assertContains($pickedUp->id, $audit->meta['new_values']['preserved_delivery_ids']);

        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $assigned->id,
            'status' => 'waiting_for_rider',
            'changed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('rider_notifications', [
            'rider_id' => $rider->id,
            'type' => 'system',
            'title' => 'Account suspended',
        ]);
    }

    public function test_picked_up_delivery_remains_untouched(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);
        $pickedUp = $this->delivery($rider, 'out_for_delivery');

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Administrative suspension reason.'])
            ->assertSessionHasNoErrors();

        $pickedUp->refresh();
        $this->assertSame($rider->id, $pickedUp->rider_id);
        $this->assertSame('out_for_delivery', $pickedUp->status);
        $this->assertDatabaseMissing('delivery_status_logs', [
            'delivery_id' => $pickedUp->id,
            'status' => 'waiting_for_rider',
        ]);
    }

    public function test_suspended_rider_cannot_go_online(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [$user, $rider] = $this->rider($center);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Suspension for policy violation.'])
            ->assertSessionHasNoErrors();

        Sanctum::actingAs($user);

        $this->patchJson('/api/logistics/rider/status', ['status' => 'online'])
            ->assertForbidden();

        $this->assertFalse($rider->fresh()->is_online);
    }

    public function test_suspended_rider_cannot_receive_new_assignments(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Suspension for policy violation.'])
            ->assertSessionHasNoErrors();

        $delivery = $this->delivery($rider, 'waiting_for_rider', ['rider_id' => null]);

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/assign-rider", ['rider_id' => $rider->id])
            ->assertSessionHasErrors('rider_id');

        $this->assertNull($delivery->fresh()->rider_id);
    }

    public function test_cross_center_suspension_is_denied(): void
    {
        $centerA = $this->center('Staff Center');
        $centerB = $this->center('Other Center');
        $staff = $this->user('logistics_staff', $centerA->id);
        [, $rider] = $this->rider($centerB);

        $this->actingAs($staff)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Cross-center attempt.'])
            ->assertForbidden();

        $this->assertSame('available', $rider->fresh()->status);
    }

    public function test_repeated_suspension_is_safe(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'First suspension reason.'])
            ->assertSessionHasNoErrors();

        $auditCount = AuditLog::where('action', 'rider.suspended')->count();

        $this->actingAs($admin)
            ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Second suspension reason.'])
            ->assertSessionHas('error');

        $this->assertSame('inactive', $rider->fresh()->status);
        $this->assertSame($auditCount, AuditLog::where('action', 'rider.suspended')->count());
    }

    public function test_audit_failure_rolls_back_suspension_and_assignment_changes(): void
    {
        $center = $this->center();
        $admin = $this->user('logistics_admin');
        [, $rider] = $this->rider($center);
        $delivery = $this->delivery($rider, 'assigned');

        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)
                ->post("/logistics/riders/{$rider->id}/deactivate", ['reason' => 'Suspension with failing audit.']);
            $this->fail('Expected the audit failure to abort the suspension.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }

        $rider->refresh();
        $delivery->refresh();

        $this->assertSame('available', $rider->status);
        $this->assertTrue($rider->is_online);
        $this->assertSame($rider->id, $delivery->rider_id);
        $this->assertSame('assigned', $delivery->status);
        $this->assertSame(0, AuditLog::where('entity_id', $rider->id)->count());
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
        $this->assertSame(0, RiderNotification::where('rider_id', $rider->id)->count());
        $this->assertSame(0, Notification::where('link', route('riders.show', $rider))->count());
    }

    public function test_no_audit_routes_are_exposed(): void
    {
        $buyer = $this->user('buyer');

        $this->actingAs($buyer)
            ->get('/logistics/audit-logs')
            ->assertNotFound();

        $this->actingAs($buyer)
            ->delete('/logistics/audit-logs/1')
            ->assertNotFound();

        $auditRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit'));

        $this->assertCount(0, $auditRoutes);
    }
}
