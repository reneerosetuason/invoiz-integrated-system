<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsSettingsAndProfileTest extends TestCase
{
    use DatabaseTransactions;

    private function createStaff(): User
    {
        $company = LogisticsCompany::create([
            'name' => 'Speedy Express Co.',
            'registration_number' => 'SEC-123456',
            'status' => 'active',
        ]);

        $center = LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Laguna Central Sorting Hub',
            'address' => '100 Expressway Ave',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'phone' => '0491234567',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Staff Member Juan',
            'email' => 'staff.' . bin2hex(random_bytes(4)) . '@test.local',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $center->id,
            'logistics_company_id' => $company->id,
            'phone' => '09171234567',
        ]);

        return $user;
    }

    public function test_settings_page_renders_without_appearance_or_region(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->get(route('settings.index'));

        $response->assertOk();
        $response->assertSee('Settings');
        $response->assertSee('Delivery Rules &amp; Operations', false);
        $response->assertSee('In-System &amp; Email Notifications', false);
        $response->assertSee('Recent Login Activity', false);

        // Confirm Appearance and Language/Region were removed
        $response->assertDontSee('data-section="appearance', false);
        $response->assertDontSee('data-section="language region', false);
        $response->assertDontSee('update-appearance');
        $response->assertDontSee('update-region');
    }

    public function test_staff_can_update_delivery_preferences(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->put(route('settings.update-delivery'), [
            'require_proof' => '1',
            'max_attempts' => '3',
            'allow_reassignment' => '1',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        $settings = LogisticsSetting::forUser($staff->id);
        $this->assertEquals(3, $settings->delivery['max_attempts']);
        $this->assertTrue($settings->delivery['require_proof']);
        $this->assertTrue($settings->delivery['allow_reassignment']);
    }

    public function test_staff_can_update_notification_preferences(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->put(route('settings.update-notifications'), [
            'rider_applications' => '1',
            'failed_deliveries' => '1',
            'email_notifications' => '1',
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        $settings = LogisticsSetting::forUser($staff->id);
        $this->assertTrue($settings->notificationEnabled('rider_applications'));
        $this->assertTrue($settings->notificationEnabled('failed_deliveries'));
        $this->assertTrue($settings->email_notifications);
    }

    public function test_profile_page_displays_details_and_admin_controlled_company_and_center(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->get(route('profile.show'));

        $response->assertOk();
        $response->assertSee('Logistics Profile');
        $response->assertSee('Staff Member Juan');
        $response->assertSee($staff->email);
        $response->assertSee('09171234567');
        $response->assertSee('Speedy Express Co.');
        $response->assertSee('Laguna Central Sorting Hub');
        $response->assertSee('Admin-Controlled');
        $response->assertSee('Protected Assignment Fields');
    }

    public function test_staff_can_update_permitted_profile_information(): void
    {
        $staff = $this->createStaff();

        $response = $this->actingAs($staff)->patch(route('logistics.profile.update'), [
            'name' => 'Staff Juan Dela Cruz',
            'email' => 'juan.updated@test.local',
            'phone' => '09187654321',
        ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');

        $staff->refresh();
        $this->assertEquals('Staff Juan Dela Cruz', $staff->name);
        $this->assertEquals('juan.updated@test.local', $staff->email);
        $this->assertEquals('09187654321', $staff->phone);
    }

    public function test_staff_cannot_change_role_company_or_center_via_profile_update(): void
    {
        $staff = $this->createStaff();
        $originalRole = $staff->role;
        $originalCompanyId = $staff->logistics_company_id;
        $originalCenterId = $staff->center_id;

        $response = $this->actingAs($staff)->patch(route('logistics.profile.update'), [
            'name' => 'Attempted Tamper',
            'email' => $staff->email,
            'role' => 'admin',
            'logistics_company_id' => 999,
            'center_id' => 888,
        ]);

        $response->assertRedirect(route('profile.show'));

        $staff->refresh();
        $this->assertEquals($originalRole, $staff->role);
        $this->assertEquals($originalCompanyId, $staff->logistics_company_id);
        $this->assertEquals($originalCenterId, $staff->center_id);
    }

    public function test_staff_can_upload_profile_photo(): void
    {
        $staff = $this->createStaff();
        Storage::fake('public');

        $photo = UploadedFile::fake()->create('staff-avatar.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($staff)->patch(route('logistics.profile.update'), [
            'name' => $staff->name,
            'email' => $staff->email,
            'photo' => $photo,
        ]);

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('success');

        $settings = LogisticsSetting::forUser($staff->id);
        $this->assertNotNull($settings->photo_path);
        $this->assertFileExists(public_path($settings->photo_path));

        // Clean up created file
        if (file_exists(public_path($settings->photo_path))) {
            @unlink(public_path($settings->photo_path));
        }
    }
}
