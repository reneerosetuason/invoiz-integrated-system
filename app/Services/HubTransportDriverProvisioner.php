<?php

namespace App\Services;

use App\Mail\RiderAccountApprovedMail;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\RiderApplication;
use App\Models\RiderApplicationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Hub Transport Driver Account Provisioning.
 *
 * Takes a reviewed rider_application with application_type = 'hub_transport_driver'
 * and, on admin approval, atomically provisions a working Hub Transport Driver record:
 *
 *   - Creates or links a User record and a HubTransportDriver record linked to the logistics company.
 *   - Sets status to 'available' (active).
 *   - Does NOT automatically assign a truck (logistics_truck_id = null) so Fleet Management
 *     can assign available four-wheel trucks afterward.
 *   - Never overwrites an existing driver's password on repeated approval.
 *   - Prevents duplicate driver records if approval is submitted more than once.
 *   - The application is marked approved with an audit log.
 *   - Notifications and credential delivery emails are sent safely outside the transaction.
 */
class HubTransportDriverProvisioner
{
    /**
     * Approve a hub transport driver application and provision their driver account.
     *
     * @param  RiderApplication  $application  The pending application.
     * @param  User  $admin  The admin performing the approval.
     * @param  array{center_id: int, password?: string|null, notes?: string|null}  $settings
     * @return array{driver: HubTransportDriver, mail_sent: bool, kept_password: bool}
     */
    public function approve(RiderApplication $application, User $admin, array $settings): array
    {
        abort_unless($admin->canManageRiders() && $admin->isLogisticsAdmin(), 403,
            'Applications are managed by logistics administrators.');

        $result = DB::transaction(function () use ($application, $admin, $settings) {
            $application = RiderApplication::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            $isRepeatedApproval = $application->status === 'approved';
            if (! $isRepeatedApproval && $application->status !== 'pending') {
                abort(422, 'Only pending applications can be approved.');
            }

            $center = LogisticsCenter::whereKey($settings['center_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $center) {
                throw ValidationException::withMessages([
                    'center_id' => 'Choose an active logistics center.',
                ]);
            }

            $companyId = $center->logistics_company_id;
            if (! $companyId) {
                throw ValidationException::withMessages([
                    'center_id' => 'The selected logistics center must belong to an active logistics company.',
                ]);
            }

            $email = strtolower(trim((string) $application->email));

            // Prevent account conflicts with existing marketplace accounts (buyers/sellers)
            $existingMarketplace = User::where('email', $email)->whereIn('role', ['buyer', 'seller'])->first();
            if ($existingMarketplace) {
                throw ValidationException::withMessages([
                    'email' => "This email is registered to a {$existingMarketplace->role} account on INVOIZ and cannot be used for a driver account.",
                ]);
            }

            // 1. Link or create the applicant's user account
            $plainPassword = ! empty($settings['password']) ? $settings['password'] : Str::random(12);
            $user = User::where('email', $email)->first();
            if (! $user) {
                $nameParts = array_values(array_filter(array_map('trim', explode(' ', $application->name))));
                $first = $nameParts[0] ?? $application->name;
                $last = $nameParts[1] ?? $application->name;

                $user = User::create([
                    'name' => $application->name,
                    'first_name' => $first,
                    'last_name' => $last,
                    'middle_initial' => $application->middle_initial,
                    'sex' => $application->sex ?: 'other',
                    'email' => $email,
                    'password' => Hash::make($plainPassword),
                    'role' => 'rider',
                    'status' => 'active',
                    'account_status' => 'active',
                    'email_verified_at' => now(),
                    'center_id' => $center->id,
                    'logistics_company_id' => $companyId,
                ]);
            }

            // 2. Find any existing driver already associated with this application or email
            $existingDriver = null;
            if ($application->hub_transport_driver_id) {
                $existingDriver = HubTransportDriver::whereKey($application->hub_transport_driver_id)->lockForUpdate()->first();
            }
            if (! $existingDriver) {
                $existingDriver = HubTransportDriver::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            }

            if (! $isRepeatedApproval) {
                if ($existingDriver && (! $application->hub_transport_driver_id || $existingDriver->id !== $application->hub_transport_driver_id)) {
                    throw ValidationException::withMessages([
                        'email' => "A Hub Transport Driver account with email {$email} already exists.",
                    ]);
                }
            }

            // 3. Create or update Hub Transport Driver account (DO NOT automatically assign a truck)
            $keptPassword = false;

            if (! $existingDriver) {
                $driver = HubTransportDriver::create([
                    'user_id' => $user->id,
                    'logistics_company_id' => $companyId,
                    'name' => $application->name,
                    'email' => $email,
                    'phone' => $application->phone,
                    'license_number' => $application->license_number,
                    'logistics_truck_id' => null, // Explicitly null: truck assigned via Fleet Management
                    'status' => 'available',
                    'is_online' => false,
                    'password' => Hash::make($plainPassword),
                    'remarks' => $settings['notes'] ?? "Approved via Hub Transport Driver application #{$application->id}.",
                ]);
            } else {
                // Repeated approval: NEVER overwrite an existing driver's password and prevent duplicate records
                $keptPassword = true;
                $driver = $existingDriver;

                $driver->update([
                    'user_id' => $driver->user_id ?: $user->id,
                    'name' => $application->name,
                    'phone' => $application->phone,
                    'license_number' => $application->license_number ?: $driver->license_number,
                    'logistics_company_id' => $companyId,
                    'status' => $driver->status === 'inactive' ? 'available' : $driver->status,
                ]);
            }

            // 4. Register driver-owned truck if provided in application
            if ($application->vehicle_ownership === 'driver_owned' && ! empty($application->license_plate)) {
                $docPath = $application->supportingDocuments()
                    ->where('document_type', 'vehicle_registration')
                    ->value('stored_path');

                $truck = \App\Models\LogisticsTruck::firstOrCreate(
                    [
                        'logistics_company_id' => $companyId,
                        'plate_number' => $application->license_plate,
                    ],
                    [
                        'ownership_type' => 'driver_owned',
                        'hub_transport_driver_id' => $driver->id,
                        'model' => $application->vehicle_model ?: $application->vehicle_name ?: 'Four-Wheel Truck',
                        'capacity_kg' => $application->capacity_kg ?: 1000.00,
                        'status' => 'available',
                        'verification_status' => 'pending_review',
                        'vehicle_registration' => $application->vehicle_registration,
                        'document_path' => $docPath,
                    ]
                );

                if ($truck->wasRecentlyCreated || ! $truck->hub_transport_driver_id) {
                    $truck->update([
                        'ownership_type' => 'driver_owned',
                        'hub_transport_driver_id' => $driver->id,
                        'vehicle_registration' => $truck->vehicle_registration ?: $application->vehicle_registration,
                        'document_path' => $truck->document_path ?: $docPath,
                    ]);
                }
            }

            // 5. Mark application approved
            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'approved_by' => $admin->id,
                'center_id' => $center->id,
                'hub_transport_driver_id' => $driver->id,
                'logistics_truck_id' => $driver->logistics_truck_id,
                'provisioned_at' => $application->provisioned_at ?: now(),
                'notes' => $settings['notes'] ?? $application->notes,
            ]);

            // 6. Audit log
            RiderApplicationLog::create([
                'rider_application_id' => $application->id,
                'previous_status' => $isRepeatedApproval ? 'approved' : 'pending',
                'new_status' => 'approved',
                'reason' => $settings['notes'] ?? ($isRepeatedApproval ? 'Repeated approval confirmed.' : 'Hub Transport Driver application approved.'),
                'changed_by' => $admin->id,
            ]);

            return [
                'driver' => $driver,
                'password' => $plainPassword,
                'kept_password' => $keptPassword,
                'is_repeated' => $isRepeatedApproval,
            ];
        });

        $driver = $result['driver'];
        $plainPassword = $result['password'];
        $keptPassword = $result['kept_password'];

        // Send approval / credential notification
        $mailSent = false;
        try {
            Mail::to($driver->email)->send(
                new RiderAccountApprovedMail($application->fresh(), $keptPassword ? null : $plainPassword)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            report($e);
        }

        Notification::create([
            'type' => 'hub_transport_driver_approved',
            'title' => 'Hub Transport Driver Approved',
            'message' => "Hub Transport Driver {$driver->name} ({$driver->email}) was approved. Vehicle assignment is pending in Fleet Management.",
            'icon' => 'truck',
            'priority' => 'high',
            'audience' => 'admin',
            'link' => route('fleet.index'),
        ]);

        return [
            'driver' => $driver,
            'mail_sent' => $mailSent,
            'kept_password' => $keptPassword,
        ];
    }

    /**
     * Resend the approval notification for an already provisioned Hub Transport Driver.
     *
     * Never creates or duplicates a user, driver, application, or profile. The
     * existing password is kept by default; when a rotation is requested a new
     * temporary password is generated, hashed, and reverted if delivery fails.
     *
     * @return array{mail_sent: bool, kept_password: bool, recipient: string|null, error: string|null}
     */
    public function resendNotification(RiderApplication $application, bool $rotatePassword = false): array
    {
        abort_unless($application->isHubTransportDriver(), 422,
            'This application is not a Hub Transport Driver application.');

        abort_unless($application->status === 'approved' && $application->provisioned_at !== null, 422,
            'Only approved and provisioned Hub Transport Driver accounts can receive a resent approval email.');

        $driver = null;
        if ($application->hub_transport_driver_id) {
            $driver = HubTransportDriver::whereKey($application->hub_transport_driver_id)->first();
        }
        if (! $driver) {
            $driver = HubTransportDriver::whereRaw('LOWER(email) = ?', [
                strtolower(trim((string) $application->email)),
            ])->first();
        }

        abort_unless($driver, 422, 'No provisioned Hub Transport Driver account exists for this application.');

        // Prefer the registered application email, falling back to the
        // driver's login email for older records.
        $recipient = strtolower(trim((string) $application->email));
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $recipient = strtolower(trim((string) $driver->email));
        }
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'mail_sent' => false,
                'kept_password' => ! $rotatePassword,
                'recipient' => null,
                'error' => 'This application has no valid email address. Update the applicant email and try again.',
            ];
        }

        $plainPassword = null;
        $previousDriverPassword = $driver->password;
        $previousUserPassword = $driver->user?->password;

        if ($rotatePassword) {
            $plainPassword = Str::random(12);
            $driver->update(['password' => $plainPassword]);
            if ($driver->user_id) {
                User::whereKey($driver->user_id)->update(['password' => $plainPassword]);
            }
        }

        $mailSent = false;
        try {
            Mail::to($recipient)->send(
                new RiderAccountApprovedMail($application->fresh(), $plainPassword)
            );
            $mailSent = true;
        } catch (\Throwable $e) {
            if ($rotatePassword) {
                // A failed retry must not invalidate a working login.
                HubTransportDriver::whereKey($driver->id)->update(['password' => $previousDriverPassword]);
                if ($driver->user_id && $previousUserPassword !== null) {
                    User::whereKey($driver->user_id)->update(['password' => $previousUserPassword]);
                }
            }
            report($e);
        }

        return [
            'mail_sent' => $mailSent,
            'kept_password' => ! $rotatePassword,
            'recipient' => $recipient,
            'error' => $mailSent ? null : 'The approval email could not be sent. Please retry when the mail service is available.',
        ];
    }
}
