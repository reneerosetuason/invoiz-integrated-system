<?php

namespace Tests\Feature;

use App\Mail\RiderAccountApprovedMail;
use App\Mail\RiderApplicationRejectedMail;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\RiderAccountProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RiderApplicationApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function manager(): User
    {
        return User::create([
            'name' => 'Approval Manager', 'email' => 'approval-manager-'.(++$this->seq).'@test.local',
            'password' => 'TestManager123!', 'role' => 'logistics_admin', 'is_admin' => false,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
    }

    private function center(string $name = 'Approval Center', bool $active = true): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.(++$this->seq), 'address' => '1 Test Street',
            'city' => 'Test City', 'is_active' => $active,
        ]);
    }

    private function area(LogisticsCenter $center, bool $active = true): ServiceArea
    {
        return ServiceArea::create([
            'name' => 'Coverage Area '.(++$this->seq), 'logistics_center_id' => $center->id,
            'is_active' => $active,
        ]);
    }

    private function application(?string $email = null): RiderApplication
    {
        return RiderApplication::create([
            'name' => 'Approval Rider', 'email' => $email ?? 'approval-rider-'.(++$this->seq).'@test.local',
            'phone' => '09171234567', 'vehicle_type' => 'motorcycle', 'license_plate' => 'TEST-123',
            'license_number' => 'N01-23-456789', 'license_expiry' => now()->addYear(),
            'status' => 'pending', 'submitted_via' => 'mobile',
        ]);
    }

    public function test_mismatched_center_and_area_return_field_validation_instead_of_404(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $wrongArea = $this->area($this->center('Other Center'));
        $application = $this->application();
        $this->actingAs($manager)->from(route('rider-applications.show', $application))
            ->post(route('rider-applications.approve', $application), [
                'center_id' => $center->id, 'service_area_id' => $wrongArea->id,
            ])->assertRedirect(route('rider-applications.show', $application))
            ->assertSessionHasErrors('service_area_id');
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
        $this->assertDatabaseMissing('riders', ['email' => $application->email]);
        Mail::assertNothingSent();
    }

    public function test_inactive_and_missing_assignment_choices_are_validation_errors(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        $inactiveCenter = $this->center('Inactive Center', false);
        $inactiveCenterArea = $this->area($inactiveCenter);
        $inactiveArea = $this->area($center, false);
        $application = $this->application();
        foreach ([
            [$inactiveCenter->id, $inactiveCenterArea->id, 'center_id'],
            [$center->id, $inactiveArea->id, 'service_area_id'],
            [99999999, $area->id, 'center_id'],
            [$center->id, 99999999, 'service_area_id'],
            [$center->id, null, 'service_area_id'],
        ] as [$centerId, $areaId, $field]) {
            $this->actingAs($manager)->postJson(route('rider-applications.approve', $application), [
                'center_id' => $centerId, 'service_area_id' => $areaId,
            ])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
        Mail::assertNothingSent();
    }

    public function test_provisioner_also_rejects_invalid_center_area_pairs_without_partial_accounts(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $wrongArea = $this->area($this->center('Other Center'));
        $application = $this->application();
        try {
            app(RiderAccountProvisioner::class)->approve($application, $manager, [
                'center_id' => $center->id, 'service_area_id' => $wrongArea->id,
                'password' => 'TestPassword123!',
            ]);
            $this->fail('Invalid pair reached rider provisioning.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_area_id', $exception->errors());
        }
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
    }

    public function test_valid_approval_creates_a_working_rider_login_and_redirects_to_the_review(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        $application = $this->application();
        $this->actingAs($manager)->post(route('rider-applications.approve', $application), [
            'center_id' => $center->id, 'service_area_id' => $area->id,
        ])->assertRedirect(route('rider-applications.show', $application))->assertSessionHasNoErrors();
        $application = $application->fresh();
        $this->assertSame('approved', $application->status);
        $this->assertNotNull($application->provisioned_at);
        $this->assertSame($manager->id, $application->approved_by);
        $rider = Rider::where('email', $application->email)->firstOrFail();
        $this->assertSame($center->id, $rider->center_id);
        $this->assertSame($area->id, $rider->service_area_id);
        $user = User::findOrFail($rider->user_id);
        $this->assertSame('rider', $user->role);
        $password = null;
        Mail::assertSent(RiderAccountApprovedMail::class, function ($mail) use (&$password, $user) {
            $password = $mail->temporaryPassword;

            return $mail->hasTo($user->email) && Hash::check($password, $user->password);
        });
        $this->get(route('rider-applications.show', $application))->assertOk()->assertSee('Account Provisioned');
        $this->postJson('/api/logistics/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()->assertJsonPath('user.rider.id', $rider->id);
    }

    public function test_existing_marketplace_logins_are_preserved_when_a_rider_profile_is_linked(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        foreach (['buyer', 'seller'] as $role) {
            $user = User::create([
                'name' => 'Existing User', 'email' => 'existing-approval-'.$role.'@test.local',
                'role' => $role, 'password' => 'ExistingLogin123!', 'status' => 'active',
            ]);
            $hash = $user->password;
            $application = $this->application($user->email);
            $this->actingAs($manager)->post(route('rider-applications.approve', $application), [
                'center_id' => $center->id, 'service_area_id' => $area->id,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($role, $user->fresh()->role);
            $this->assertSame($hash, $user->fresh()->password);
            $this->assertTrue($user->fresh()->hasApprovedRiderProfile());
            $this->assertSame(1, User::where('email', $user->email)->count());
        }
        Mail::assertSent(RiderAccountApprovedMail::class, 2);
        Mail::assertSent(RiderAccountApprovedMail::class, function ($mail) {
            $html = $mail->render();

            return $mail->temporaryPassword === null
                && str_contains($html, 'Use your existing INVOIZ password')
                && ! str_contains($html, 'Temporary Password:');
        });
    }

    public function test_rejection_sends_the_recorded_reason_without_creating_a_login(): void
    {
        $application = $this->application();
        $reason = 'Please upload a readable driver license.';
        $this->actingAs($this->manager())->post(route('rider-applications.reject', $application), [
            'reason' => $reason,
        ])->assertRedirect()->assertSessionHas('success')->assertSessionMissing('warning');
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame($reason, $application->fresh()->notes);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
        Mail::assertSent(RiderApplicationRejectedMail::class, fn ($mail) => $mail->hasTo($application->email)
            && $mail->reason === $reason && str_contains($mail->render(), $reason));
    }

    public function test_rejection_email_can_be_retried_without_changing_the_decision(): void
    {
        $application = $this->application();
        $reason = 'The uploaded license has expired.';
        $application->update(['status' => 'rejected', 'notes' => $reason]);
        $this->actingAs($this->manager())->post(route('rider-applications.resend-rejection', $application))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame(0, $application->logs()->count());
        Mail::assertSent(RiderApplicationRejectedMail::class, fn ($mail) => $mail->reason === $reason);
        $this->get(route('rider-applications.show', $application))->assertOk()->assertSee('Resend Rejection Email');
    }

    public function test_pending_applications_and_marketplace_admins_cannot_retry_rejection_emails(): void
    {
        $application = $this->application();
        $this->actingAs($this->manager())->postJson(route('rider-applications.resend-rejection', $application))
            ->assertUnprocessable();
        $admin = User::create([
            'name' => 'Marketplace Admin', 'email' => 'mail-admin@test.local', 'password' => 'TestPassword123!',
            'role' => 'admin', 'is_admin' => true, 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $application->update(['status' => 'rejected', 'notes' => 'Please update the required documents.']);
        $this->actingAs($admin)->postJson(route('rider-applications.resend-rejection', $application))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_existing_login_approval_email_can_be_resent_without_password_changes(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        $user = User::create([
            'name' => 'Existing Buyer', 'email' => 'approval-retry@test.local', 'password' => 'ExistingLogin123!',
            'role' => 'buyer', 'status' => 'active',
        ]);
        $hash = $user->password;
        $application = $this->application($user->email);
        app(RiderAccountProvisioner::class)->approve($application, $manager, [
            'center_id' => $center->id, 'service_area_id' => $area->id, 'password' => 'UnusedPassword123!',
        ]);
        $this->actingAs($manager)->post(route('rider-applications.resend-credentials', $application))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame($hash, $user->fresh()->password);
        Mail::assertSent(RiderAccountApprovedMail::class, fn ($mail) => $mail->temporaryPassword === null);
        $this->get(route('rider-applications.show', $application))->assertOk()->assertSee('Resend Approval Email');
    }

    public function test_failed_approval_email_keeps_the_account_and_displays_a_warning(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        $application = $this->application();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable during test'));
        $this->actingAs($manager)->post(route('rider-applications.approve', $application), [
            'center_id' => $center->id, 'service_area_id' => $area->id,
        ])->assertRedirect()->assertSessionHas('warning')->assertSessionMissing('success');
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('users', ['email' => $application->email, 'role' => 'rider']);
        $this->get(route('rider-applications.show', $application))->assertOk()->assertSee('credentials email could not be sent');
    }

    public function test_failed_credentials_retry_preserves_the_previous_working_password(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $area = $this->area($center);
        $application = $this->application();
        $rider = app(RiderAccountProvisioner::class)->approve($application, $manager, [
            'center_id' => $center->id, 'service_area_id' => $area->id, 'password' => 'WorkingPassword123!',
        ]);
        $hash = $rider->user->password;
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable during test'));
        $this->actingAs($manager)->post(route('rider-applications.resend-credentials', $application))
            ->assertRedirect()->assertSessionHas('warning')->assertSessionMissing('success');
        $this->assertSame($hash, $rider->user->fresh()->password);
    }

    public function test_failed_rejection_email_can_be_retried_and_does_not_undo_the_rejection(): void
    {
        $manager = $this->manager();
        $application = $this->application();
        Mail::shouldReceive('to')->twice()->andReturnSelf();
        Mail::shouldReceive('send')->twice()->andThrow(new \RuntimeException('SMTP unavailable during test'));
        $this->actingAs($manager)->post(route('rider-applications.reject', $application), [
            'reason' => 'Please upload the required driver license.',
        ])->assertRedirect()->assertSessionHas('warning')->assertSessionMissing('success');
        $this->assertSame('rejected', $application->fresh()->status);
        $this->post(route('rider-applications.resend-rejection', $application))
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(1, $application->logs()->count());
    }

    public function test_review_only_offers_active_choices_and_preserves_the_selected_assignment(): void
    {
        $manager = $this->manager();
        $center = $this->center('Alpha Center');
        $area = $this->area($center);
        $otherCenter = $this->center('Beta Center');
        $otherArea = $this->area($otherCenter);
        $emptyCenter = $this->center('Empty Center');
        $inactiveCenter = $this->center('Inactive Center', false);
        $inactiveCenterArea = $this->area($inactiveCenter);
        $inactiveArea = $this->area($center, false);
        $application = $this->application();
        $response = $this->actingAs($manager)->withSession(['_old_input' => [
            'center_id' => $center->id, 'service_area_id' => $area->id, 'notes' => 'Keep this approval note.',
        ]])->get(route('rider-applications.show', $application))->assertOk()
            ->assertSee('data-rider-approval', false)->assertSee('Keep this approval note.')
            ->assertSee('assets/logistics/js/rider-applications/review.js', false)
            ->assertViewHas('centers', fn ($centers) => ! $centers->contains('id', $inactiveCenter->id))
            ->assertViewHas('areas', fn ($areas) => ! $areas->contains('id', $inactiveArea->id)
                && ! $areas->contains('id', $inactiveCenterArea->id));
        if ($output = getenv('RIDER_REVIEW_PREVIEW')) {
            file_put_contents($output.'/review.html', $response->getContent());
            file_put_contents($output.'/review-data.json', json_encode([
                'application' => $application->id, 'center' => $center->id, 'area' => $area->id,
                'otherCenter' => $otherCenter->id, 'otherArea' => $otherArea->id, 'emptyCenter' => $emptyCenter->id,
            ]));
        }
    }

    public function test_service_area_setup_preselects_the_chosen_center_and_keeps_old_input(): void
    {
        $manager = $this->manager();
        $center = $this->center();
        $otherCenter = $this->center('Other Center');
        $this->actingAs($manager)->get(route('service-areas.create', [
            'logistics_center_id' => $center->id,
        ]))->assertOk()->assertSee('value="'.$center->id.'" selected', false);
        $this->withSession(['_old_input' => ['logistics_center_id' => $otherCenter->id]])
            ->get(route('service-areas.create', ['logistics_center_id' => $center->id]))
            ->assertOk()->assertSee('value="'.$otherCenter->id.'" selected', false);
    }

    public function test_missing_application_remains_a_real_404(): void
    {
        $this->actingAs($this->manager())->postJson('/logistics/rider-applications/99999999/approve')
            ->assertNotFound();
    }
}
