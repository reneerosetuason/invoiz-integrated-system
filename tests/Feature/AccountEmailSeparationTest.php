<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\SeparateAccountEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class AccountEmailSeparationTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        (require database_path('migrations/2026_09_06_000001_create_rider_email_verifications_table.php'))->up();
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        foreach (['riders', 'hub_transport_drivers', 'rider_applications', 'logistics_center_applications'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->string('status')->nullable();
                $table->string('phone')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function test_every_logistics_identity_blocks_web_mobile_and_legacy_buyer_registration(): void
    {
        foreach (['riders', 'hub_transport_drivers', 'rider_applications', 'logistics_center_applications'] as $index => $table) {
            $email = "logistics{$index}@example.test";
            DB::table($table)->insert(['email' => strtoupper($email), 'status' => 'approved']);
            foreach (['/register', '/api/mobile/buyer/register', '/api/register'] as $url) {
                $this->postJson($url, ['email' => $email])->assertStatus(422)->assertJsonValidationErrors('email');
            }
            $this->assertFalse(User::where('email', $email)->exists());
        }
        Mail::assertNothingSent();
    }

    public function test_buyer_identity_blocks_rider_and_center_code_requests_and_applications(): void
    {
        $email = 'buyer@example.test';
        User::create(['name' => 'Buyer', 'email' => $email, 'password' => 'Password123!', 'role' => 'buyer']);
        foreach (['/api/logistics/rider/email/request-code', '/logistics/logistics-center/verification/send', '/api/logistics/rider/apply', '/api/logistics/center/apply'] as $url) {
            $this->postJson($url, ['email' => strtoupper($email)])->assertStatus(422)->assertJsonValidationErrors('email');
        }
        Mail::assertNothingSent();
    }

    public function test_existing_logistics_user_and_pending_applications_block_marketplace(): void
    {
        User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Password123!', 'role' => 'logistics_staff']);
        $this->assertTrue(Validator::make(['email' => ' STAFF@example.test '], ['email' => [new SeparateAccountEmail('marketplace')]])->fails());
        DB::table('rider_applications')->insert(['email' => 'pending@example.test', 'status' => 'pending']);
        $this->assertTrue(SeparateAccountEmail::logisticsExists('pending@example.test'));
        DB::table('rider_applications')->where('email', 'pending@example.test')->update(['status' => 'rejected']);
        $this->assertFalse(SeparateAccountEmail::logisticsExists('pending@example.test'));
    }

    public function test_profile_email_changes_cannot_cross_account_families(): void
    {
        DB::table('hub_transport_drivers')->insert(['email' => 'driver@example.test', 'status' => 'inactive']);
        $buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->assertTrue(Validator::make(['email' => 'driver@example.test'], ['email' => [new SeparateAccountEmail('marketplace', $buyer->id)]])->fails());
        $this->assertTrue(Validator::make(['email' => $buyer->email], ['email' => [new SeparateAccountEmail('logistics')]])->fails());
        $this->assertFalse(Validator::make(['email' => $buyer->email], ['email' => [new SeparateAccountEmail('marketplace', $buyer->id)]])->fails());
        $this->actingAs($buyer)->postJson('/profile/email/request-code', ['email' => 'driver@example.test'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/profile/email/confirm', ['email' => 'driver@example.test', 'email_code' => '123456'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame('buyer@example.test', $buyer->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_fleet_driver_registration_rejects_an_existing_buyer_email(): void
    {
        Schema::create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status');
            $table->softDeletes();
        });
        DB::table('logistics_companies')->insert(['name' => 'Test Logistics', 'status' => 'active']);
        $buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'Password123!', 'role' => 'buyer']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'Password123!', 'role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($admin)->postJson(route('fleet.drivers.store'), ['email' => $buyer->email])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('hub_transport_drivers', 0);
    }

    public function test_free_emails_and_existing_marketplace_accounts_can_apply_as_sellers(): void
    {
        User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'Password123!', 'role' => 'buyer']);
        $this->assertFalse(Validator::make(['email' => 'buyer@example.test'], ['email' => [new SeparateAccountEmail('marketplace')]])->fails());
        $this->assertFalse(Validator::make(['email' => 'new@example.test'], ['email' => [new SeparateAccountEmail('logistics')]])->fails());
    }
}
