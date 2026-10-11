<?php

namespace App\Services;

use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderApplicationLog;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Rider Account Provisioning.
 *
 * Takes a reviewed rider_application and, on admin approval, atomically
 * provisions a working rider login:
 *
 *   - a User record (role=rider, status=active) with a login password
 *   - a linked Rider profile (center / service area / verification flags)
 *   - the application marked approved with an audit log
 *
 * This closes the gap where riders could apply (mobile) but no admin path
 * existed to turn an approved application into an account that can log in.
 * It reuses the existing Rider + User models and the existing
 * AuthController::login flow (users.email + Hash::check), so it introduces
 * no new authentication mechanism and touches none of the Logistics
 * transaction / delivery / authorization rules.
 */
class RiderAccountProvisioner
{
    /**
     * Approve an application and provision the rider login.
     *
     * @param  RiderApplication  $application  The pending application.
     * @param  User  $admin  The admin performing the approval.
     * @param  array{password: string, center_id: int, service_area_id: int, notes?: string}  $settings
     */
    public function approve(RiderApplication $application, User $admin, array $settings): Rider
    {
        return DB::transaction(function () use ($application, $admin, $settings) {
            if ($application->status !== 'pending') {
                abort(422, 'Only pending applications can be approved.');
            }

            $center = LogisticsCenter::findOrFail($settings['center_id']);
            $area = ServiceArea::where('logistics_center_id', $center->id)
                ->findOrFail($settings['service_area_id']);

            $email = $application->email;

            $nameParts = array_values(array_filter(array_map('trim', explode(' ', $application->name))));
            $first = $nameParts[0] ?? $application->name;
            $last = $nameParts[1] ?? $application->name;

            $user = User::where('email', $email)->first();
            if (! $user) {
                $user = User::create([
                    'name' => $application->name,
                    'first_name' => $first,
                    'last_name' => $last,
                    'middle_initial' => $application->middle_initial,
                    'sex' => $application->sex ?: 'other',
                    'email' => $email,
                    'password' => Hash::make($settings['password']),
                    'phone' => $application->phone,
                    'birthday' => $application->birthday?->format('Y-m-d') ?? '1970-01-01',
                    'age' => $application->age ?? 0,
                    'role' => 'rider',
                    'status' => 'active',
                    'center_id' => $center->id,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->update([
                    'first_name' => $user->first_name ?: $first,
                    'last_name' => $user->last_name ?: $last,
                    'role' => 'rider',
                    'status' => 'active',
                    'center_id' => $center->id,
                    'password' => Hash::make($settings['password']),
                ]);
            }

            $rider = Rider::where('email', $email)->first();
            if (! $rider) {
                $rider = Rider::create([
                    'user_id' => $user->id,
                    'center_id' => $center->id,
                    'service_area_id' => $area->id,
                    'name' => $application->name,
                    'email' => $email,
                    'phone' => $application->phone,
                    'vehicle_type' => $application->vehicle_type,
                    'license_plate' => $application->license_plate,
                    'status' => 'available',
                    'is_verified' => true,
                    'approved_at' => now(),
                    'vehicle_verification' => 'verified',
                ]);
            } else {
                $rider->update([
                    'user_id' => $user->id,
                    'center_id' => $center->id,
                    'service_area_id' => $area->id,
                    'name' => $application->name,
                    'phone' => $application->phone,
                    'status' => 'available',
                    'is_verified' => true,
                    'approved_at' => now(),
                    'vehicle_verification' => 'verified',
                ]);
            }

            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'approved_by' => $admin->id,
                'center_id' => $center->id,
                'service_area_id' => $area->id,
                'provisioned_at' => now(),
                'notes' => $settings['notes'] ?? ($application->notes),
            ]);

            RiderApplicationLog::create([
                'rider_application_id' => $application->id,
                'previous_status' => 'pending',
                'new_status' => 'approved',
                'changed_by' => $admin->id,
                'reason' => $settings['notes'] ?? 'Approved and rider account provisioned.',
            ]);

            Notification::create([
                'type' => 'rider_approved',
                'title' => 'Rider Approved',
                'message' => "{$application->name} was approved and a rider account was provisioned.",
                'icon' => 'check',
                'priority' => 'high',
                'link' => null,
            ]);

            return $rider;
        });
    }

    /**
     * Reject an application without provisioning an account.
     */
    public function reject(RiderApplication $application, User $admin, string $reason): void
    {
        DB::transaction(function () use ($application, $admin, $reason) {
            if ($application->status !== 'pending') {
                abort(422, 'Only pending applications can be rejected.');
            }

            $application->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'approved_by' => $admin->id,
                'notes' => $reason,
            ]);

            RiderApplicationLog::create([
                'rider_application_id' => $application->id,
                'previous_status' => 'pending',
                'new_status' => 'rejected',
                'changed_by' => $admin->id,
                'reason' => $reason,
            ]);

            Notification::create([
                'type' => 'rider_rejected',
                'title' => 'Rider Application Rejected',
                'message' => "{$application->name}'s rider application was rejected.",
                'icon' => 'close',
                'priority' => 'normal',
                'link' => null,
            ]);
        });
    }
}
