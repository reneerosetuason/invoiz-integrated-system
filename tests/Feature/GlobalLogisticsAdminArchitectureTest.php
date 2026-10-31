<?php

namespace Tests\Feature;

use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GlobalLogisticsAdminArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private function globalAdmin(): User
    {
        $user = new User([
            'name' => 'INVOIZ Global Logistics Admin',
            'email' => 'logistics_admin@test.com',
            'password' => Hash::make('Preserve-This-Hash-123!'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => null,
        ]);
        $user->id = 18;
        $user->save();

        return $user;
    }

    private function company(string $name, string $registration): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name,
            'registration_number' => $registration,
            'status' => 'active',
        ]);
    }

    public function test_platform_administrator_is_not_the_global_logistics_admin(): void
    {
        $logisticsAdmin = $this->globalAdmin();
        $platformAdmin = User::create([
            'name' => 'Platform Administrator', 'email' => 'platform@test.local',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'is_admin' => true,
            'status' => 'active', 'account_status' => 'active',
        ]);

        $this->assertTrue($logisticsAdmin->isLogisticsAdmin());
        $this->assertFalse($logisticsAdmin->isAdmin());
        $this->assertTrue($platformAdmin->isAdmin());
        $this->assertFalse($platformAdmin->isLogisticsAdmin());
    }

    public function test_designated_identity_is_the_only_active_global_logistics_admin(): void
    {
        $admin = $this->globalAdmin();

        $this->assertSame(18, $admin->id);
        $this->assertSame('logistics_admin@test.com', $admin->email);
        $this->assertSame('logistics_admin', $admin->role);
        $this->assertFalse((bool) $admin->is_admin);
        $this->assertTrue($admin->isActive());
        $this->assertNull($admin->logistics_company_id);
        $this->assertTrue($admin->isLogisticsAdmin());
    }

    public function test_duplicate_logistics_admin_role_does_not_grant_global_access(): void
    {
        $this->globalAdmin();

        $duplicate = User::create([
            'name' => 'Duplicate Logistics Admin',
            'email' => 'duplicate-logistics-admin@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
        ]);
        DB::table('users')->where('id', $duplicate->id)->update(['role' => 'logistics_admin']);
        $duplicate->refresh();

        $this->assertFalse($duplicate->isLogisticsAdmin());
        $this->assertFalse($duplicate->isLogisticsStaff());
    }

    public function test_model_rejects_creation_or_promotion_to_duplicate_logistics_admin(): void
    {
        $user = User::create([
            'name' => 'Ordinary User',
            'email' => 'ordinary-user@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
        ]);

        try {
            $user->update(['role' => 'logistics_admin']);
            $this->fail('A duplicate Logistics Admin role assignment should be rejected.');
        } catch (AuthorizationException) {
            $this->assertSame('buyer', $user->fresh()->role);
        }

        $this->expectException(AuthorizationException::class);
        User::create([
            'name' => 'Second Logistics Admin',
            'email' => 'second-logistics-admin@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
        ]);
    }

    public function test_public_registration_always_creates_a_buyer_even_when_role_is_spoofed(): void
    {
        $this->post(route('register.post'), [
            'first_name' => 'Public',
            'last_name' => 'Applicant',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'email' => 'public-applicant@test.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => '1',
            'role' => 'logistics_admin',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'public-applicant@test.local',
            'role' => 'buyer',
        ]);
    }

    public function test_company_creation_needs_no_company_admin_and_preserves_global_account(): void
    {
        $admin = $this->globalAdmin();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'North Island Logistics Corporation',
            'registration_number' => 'SEC-NILC-2026',
            // A legacy admin_user_id input is deliberately ignored.
            'admin_user_id' => 999,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('logistics_companies', [
            'name' => 'North Island Logistics Corporation',
            'registration_number' => 'SEC-NILC-2026',
        ]);
        $this->assertSame(1, User::where('role', 'logistics_admin')->count());
        $this->assertSame(18, $admin->fresh()->id);
        $this->assertSame('logistics_admin@test.com', $admin->fresh()->email);
        $this->assertSame('logistics_admin', $admin->fresh()->role);
        $this->assertNull($admin->fresh()->logistics_company_id);
        $this->assertFalse((bool) $admin->fresh()->is_admin);
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertSame('active', $admin->fresh()->account_status);
    }

    public function test_global_admin_sees_company_fleet_across_multiple_companies(): void
    {
        $admin = $this->globalAdmin();
        $first = $this->company('First Logistics', 'REG-FIRST');
        $second = $this->company('Second Logistics', 'REG-SECOND');

        $response = $this->actingAs($admin)->get(route('fleet.index'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('companies'));
        $this->assertNull($admin->fresh()->logistics_company_id);
    }

    public function test_global_admin_can_manage_centers_across_companies(): void
    {
        $admin = $this->globalAdmin();
        $first = $this->company('Center Network One', 'REG-CNO');
        $second = $this->company('Center Network Two', 'REG-CNT');
        $centerA = LogisticsCenter::create([
            'name' => 'Network One Hub', 'address' => '1 Main Road', 'city' => 'Manila',
            'is_active' => true, 'logistics_company_id' => $first->id,
        ]);
        $centerB = LogisticsCenter::create([
            'name' => 'Network Two Hub', 'address' => '2 Main Road', 'city' => 'Cebu',
            'is_active' => true, 'logistics_company_id' => $second->id,
        ]);

        $this->actingAs($admin)->get(route('centers.index'))
            ->assertOk()->assertSee('Network One Hub')->assertSee('Network Two Hub');
        $this->actingAs($admin)->get(route('centers.show', $centerB))->assertOk();
        $this->assertNull($admin->fresh()->logistics_company_id);
    }

    public function test_global_admin_can_create_a_hub_then_record_its_company_assignment(): void
    {
        $admin = $this->globalAdmin();
        $company = $this->company('New Hub Network', 'REG-NHN');

        $this->actingAs($admin)->post(route('centers.store'), [
            'name' => 'Newly Registered Hub', 'address' => '3 Main Road',
            'city' => 'Davao', 'province' => 'Davao del Sur',
        ])->assertRedirect(route('centers.index'));

        $center = LogisticsCenter::where('name', 'Newly Registered Hub')->firstOrFail();
        $this->actingAs($admin)->put(route('companies.centers.assign', $center), [
            'logistics_company_id' => $company->id,
            'evidence' => 'Verified company registration and hub operating documents.',
            'reason' => 'Initial verified company assignment.',
        ])->assertSessionHasNoErrors();

        $this->assertSame($company->id, $center->fresh()->logistics_company_id);
        $this->assertDatabaseHas('logistics_company_ownership_histories', [
            'logistics_center_id' => $center->id,
            'to_company_id' => $company->id,
            'action' => 'ownership_assigned',
        ]);
    }

    public function test_company_approval_requires_selected_company_and_never_promotes_applicant(): void
    {
        Mail::fake();
        $admin = $this->globalAdmin();
        $company = $this->company('Verified Center Network', 'REG-VCN');
        $applicant = User::create([
            'name' => 'Center Applicant', 'email' => 'center-applicant@test.local',
            'password' => Hash::make('Applicant-Password-123!'), 'role' => 'buyer',
            'status' => 'active', 'account_status' => 'active',
        ]);
        $application = LogisticsCenterApplication::create([
            'business_name' => 'Verified Center', 'owner_name' => 'Center Applicant',
            'email' => $applicant->email, 'phone' => '09171234567',
            'address' => '1 Test Street', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('center-applications.approve', $application), [
            'logistics_company_id' => $company->id,
            'ownership_evidence' => 'Reviewed SEC registration REG-VCN and center deed.',
        ])->assertRedirect();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('buyer', $applicant->fresh()->role);
        $this->assertSame($company->id, $applicant->fresh()->logistics_company_id);
        $this->assertSame(1, User::where('role', 'logistics_admin')->count());
        $this->assertNull($admin->fresh()->logistics_company_id);
    }

    public function test_platform_administrator_keeps_network_permissions_without_becoming_logistics_admin(): void
    {
        $platformAdmin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'platform-network-admin@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
        ]);

        $this->assertTrue($platformAdmin->isAdmin());
        $this->assertFalse($platformAdmin->isLogisticsAdmin());

        $this->actingAs($platformAdmin)->post(route('companies.store'), [
            'name' => 'Platform Managed Logistics',
            'registration_number' => 'REG-PLATFORM-MANAGED',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('logistics_companies', [
            'registration_number' => 'REG-PLATFORM-MANAGED',
        ]);
        $this->assertFalse($platformAdmin->fresh()->isLogisticsAdmin());
    }

    public function test_designated_admin_cannot_be_deactivated_through_user_management(): void
    {
        $admin = $this->globalAdmin();
        $platformAdmin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'platform-status-admin@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
        ]);

        $this->actingAs($platformAdmin)
            ->post(route('admin.manage-accounts.action', $admin), ['action' => 'deactivate'])
            ->assertForbidden();

        $this->assertSame('active', $admin->fresh()->status);
        $this->assertSame('active', $admin->fresh()->account_status);
    }

    public function test_designated_admin_cannot_be_deleted_or_assigned_to_a_company(): void
    {
        $admin = $this->globalAdmin();
        $company = $this->company('Protected Assignment Network', 'REG-PROTECTED');

        try {
            $admin->update(['role' => 'buyer']);
            $this->fail('The designated account role must not be changed.');
        } catch (AuthorizationException) {
            $this->assertSame('logistics_admin', $admin->fresh()->role);
        }

        try {
            $admin->update(['logistics_company_id' => $company->id]);
            $this->fail('The designated account must not be assigned to a company.');
        } catch (AuthorizationException) {
            $this->assertNull($admin->fresh()->logistics_company_id);
        }

        try {
            $admin->update(['otp' => '123456', 'otp_expires_at' => now()->addMinutes(10)]);
            $this->fail('The designated account verification credentials must not be changed through ordinary workflows.');
        } catch (AuthorizationException) {
            $this->assertSame('logistics_admin@test.com', $admin->fresh()->email);
        }

        try {
            $admin->delete();
            $this->fail('The designated account must not be deleted.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('users', [
                'id' => User::GLOBAL_LOGISTICS_ADMIN_ID,
                'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            ]);
        }
    }

    public function test_center_approval_cannot_assign_the_designated_admin_to_a_company(): void
    {
        Mail::fake();
        $admin = $this->globalAdmin();
        $company = $this->company('No Admin Assignment Network', 'REG-NO-ADMIN');
        $application = LogisticsCenterApplication::create([
            'business_name' => 'Attempted Admin Center',
            'owner_name' => 'INVOIZ Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'phone' => '09171234567',
            'address' => '1 Test Street',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('center-applications.approve', $application), [
            'logistics_company_id' => $company->id,
            'ownership_evidence' => 'Reviewed ownership documents.',
        ])->assertStatus(422);

        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($admin->fresh()->logistics_company_id);
    }

    public function test_logistics_staff_are_limited_to_their_company_and_hub(): void
    {
        $companyA = $this->company('Staff Company A', 'REG-STAFF-A');
        $companyB = $this->company('Staff Company B', 'REG-STAFF-B');
        $hubA = LogisticsCenter::create([
            'name' => 'Staff Hub A', 'address' => '1 Main Road', 'city' => 'Manila',
            'is_active' => true, 'logistics_company_id' => $companyA->id,
        ]);
        $hubB = LogisticsCenter::create([
            'name' => 'Staff Hub B', 'address' => '2 Main Road', 'city' => 'Cebu',
            'is_active' => true, 'logistics_company_id' => $companyB->id,
        ]);
        $staff = User::create([
            'name' => 'Company A Staff', 'email' => 'company-a-staff@test.local',
            'password' => Hash::make('secret123'), 'role' => 'logistics_staff',
            'status' => 'active', 'account_status' => 'active',
            'center_id' => $hubA->id, 'logistics_company_id' => $companyA->id,
        ]);

        $response = $this->actingAs($staff)->get(route('sorting-center.index', ['center_id' => $hubB->id]));

        $response->assertOk();
        $this->assertSame($hubA->id, $response->viewData('staffCenterId'));
        $this->assertSame([$hubA->id], $response->viewData('centers')->pluck('id')->all());
    }

    public function test_company_schema_has_no_per_company_admin_pointer_or_history(): void
    {
        $this->assertFalse(Schema::hasColumn('logistics_companies', 'logistics_admin_user_id'));
        $this->assertFalse(Schema::hasTable('logistics_company_admin_histories'));
    }

    public function test_staff_cannot_register_company_or_promote_a_global_admin(): void
    {
        $staff = User::create([
            'name' => 'Logistics Staff', 'email' => 'network-staff@test.local',
            'password' => Hash::make('secret123'), 'role' => 'logistics_staff',
            'status' => 'active', 'account_status' => 'active',
        ]);

        $this->actingAs($staff)->post(route('companies.store'), [
            'name' => 'Unauthorized Logistics', 'registration_number' => 'UNAUTH-REG',
            'role' => 'logistics_admin', 'admin_user_id' => 18,
        ])->assertForbidden();

        $this->actingAs($staff)->post(route('staff.store'), [
            'name' => 'Attempted Admin', 'email' => 'attempted-admin@test.local',
            'role' => 'logistics_admin', 'password' => 'Secret-123!',
        ])->assertForbidden();

        $this->assertDatabaseMissing('logistics_companies', ['registration_number' => 'UNAUTH-REG']);
        $this->assertDatabaseMissing('users', ['email' => 'attempted-admin@test.local']);
        $this->assertSame(0, User::where('role', 'logistics_admin')->count());
    }

    public function test_staff_management_cannot_promote_a_user_to_logistics_admin(): void
    {
        $admin = $this->globalAdmin();
        $company = $this->company('Staff Promotion Network', 'REG-STAFF-PROMOTION');
        $center = LogisticsCenter::create([
            'name' => 'Staff Promotion Hub', 'address' => '1 Main Road', 'city' => 'Manila',
            'is_active' => true, 'logistics_company_id' => $company->id,
        ]);

        $this->actingAs($admin)->post(route('staff.store'), [
            'first_name' => 'New',
            'last_name' => 'Staff',
            'sex' => 'other',
            'email' => 'new-staff@test.local',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'terms' => '1',
            'phone' => '09171234567',
            'birthday' => '1990-01-01',
            'center_id' => $center->id,
            'role' => 'logistics_admin',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new-staff@test.local',
            'role' => 'staff',
        ]);
        $this->assertSame(1, User::where('role', 'logistics_admin')->count());
    }

    public function test_pending_migration_checks_designated_admin_without_updating_that_user(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_30_190000_create_logistics_companies_and_scope_centers.php'));

        $this->assertStringContainsString('verifyGlobalLogisticsAdmin', $migration);
        $this->assertStringContainsString("where('id', 18)", $migration);
        $this->assertStringNotContainsString("DB::table('users')->where('id', 18)->update", $migration);
        $this->assertStringNotContainsString("where('email', 'logistics_admin@test.com')->update", $migration);
        $this->assertStringNotContainsString('logistics_admin_user_id', $migration);
        $this->assertStringNotContainsString('logistics_company_admin_histories', $migration);
    }
}
