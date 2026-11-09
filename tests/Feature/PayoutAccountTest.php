<?php

namespace Tests\Feature;

use App\Models\{EscrowRemittance, HubTransportDriver, PayoutAccount, ReturnAllocation, User};
use App\Support\PayoutAccounts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class PayoutAccountTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('logistics_company_id')->nullable());
        foreach ([
            'logistics_centers' => 'name logistics_company_id is_active',
            'hub_transport_drivers' => 'user_id name status deleted_at',
            'riders' => 'user_id name',
            'escrow_remittances' => 'recipient_role recipient_user_id rider_id logistics_center_id hub_transport_driver_id amount status',
            'return_allocations' => 'role recipient_user_id rider_id driver_id center_id amount_cents status receiving_destination',
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) $table->text($column)->nullable();
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_11_09_080000_create_payout_accounts.php'))->up();
        DB::table('logistics_centers')->insert([
            ['id' => 11, 'name' => 'Own hub', 'logistics_company_id' => 1, 'is_active' => 1],
            ['id' => 12, 'name' => 'Second own hub', 'logistics_company_id' => 1, 'is_active' => 1],
            ['id' => 13, 'name' => 'Another company hub', 'logistics_company_id' => 2, 'is_active' => 1],
        ]);
    }

    private function worker(string $role = 'rider', array $attributes = []): User
    {
        return User::create(array_merge(['name' => 'Test worker', 'email' => uniqid().'@example.test', 'password' => 'test',
            'role' => $role, 'status' => 'active', 'account_status' => 'active', 'email_verified_at' => now()], $attributes));
    }

    private function login($actor): self
    {
        Auth::forgetGuards();
        Sanctum::actingAs($actor, ['*']);
        return $this;
    }

    private function save($actor, string $key, array $details = [])
    {
        return $this->login($actor)->postJson('/api/remittances/payout-account', array_merge([
            'target' => $key, 'provider' => 'Test bank', 'holder' => 'Test Account Holder', 'account' => '000123456789',
        ], $details));
    }

    private function allocation(array $attributes): ReturnAllocation
    {
        // Read-only lookup fixture: do not cache this reduced table's mass-assignment columns
        // on the production model for the settlement tests that run after this class.
        $id = DB::table('return_allocations')->insertGetId($attributes + ['created_at' => now(), 'updated_at' => now()]);
        return ReturnAllocation::findOrFail($id);
    }

    public function test_sellers_and_riders_save_once_and_edit_the_same_encrypted_profile(): void
    {
        foreach (['seller', 'rider'] as $role) {
            $actor = $this->worker($role);
            $key = 'user-'.$actor->id;
            $this->login($actor)->getJson('/api/remittances/payout-account')->assertOk()->assertJsonPath('accounts.0.details', null);
            $this->save($actor, $key)->assertOk()->assertJsonPath('accounts.0.details.account', '000123456789');
            $saved = PayoutAccount::where('owner_id', $actor->id)->firstOrFail();
            $this->assertStringNotContainsString('000123456789', DB::table('payout_accounts')->where('id', $saved->id)->value('details'));
            $this->assertArrayNotHasKey('details', $saved->toArray());
            $this->save($actor, $key, ['provider' => 'Updated wallet', 'account' => '009988776655'])->assertOk();
            $this->assertSame(1, PayoutAccount::where('owner_type', 'user')->where('owner_id', $actor->id)->count());
            $this->getJson('/api/remittances/payout-account')->assertOk()->assertJsonPath('accounts.0.details.account', '009988776655')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_admin_reads_latest_details_for_both_order_and_return_without_changing_money(): void
    {
        $actor = $this->worker('seller');
        $admin = $this->worker('admin', ['is_admin' => true]);
        $order = EscrowRemittance::create(['recipient_role' => 'seller', 'recipient_user_id' => $actor->id, 'amount' => 99, 'status' => 'pending_remittance']);
        $return = $this->allocation(['role' => 'collection_rider', 'recipient_user_id' => $actor->id, 'amount_cents' => 3000, 'status' => 'eligible']);
        $snapshot = [$order->fresh()->getAttributes(), $return->fresh()->getAttributes()];
        $this->save($actor, 'user-'.$actor->id)->assertOk();
        foreach (['order' => $order->id, 'return' => $return->id] as $source => $id) {
            $this->login($admin)->getJson('/admin/payments/payout-account/'.$source.'/'.$id)->assertOk()
                ->assertJsonPath('account.details.account', '000123456789')->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->save($actor, 'user-'.$actor->id, ['account' => '001234UPDATED'])->assertOk();
        foreach (['order' => $order->id, 'return' => $return->id] as $source => $id) {
            $this->login($admin)->getJson('/admin/payments/payout-account/'.$source.'/'.$id)->assertOk()->assertJsonPath('account.details.account', '001234UPDATED');
        }
        $this->assertSame($snapshot, [$order->fresh()->getAttributes(), $return->fresh()->getAttributes()]);
        $this->assertDatabaseCount('payout_accounts', 1);
    }

    public function test_hub_details_are_shared_by_its_staff_and_order_return_shares_but_not_other_companies(): void
    {
        $staff = $this->worker('staff', ['center_id' => 11, 'logistics_company_id' => 1]);
        $colleague = $this->worker('logistics_staff', ['center_id' => 11, 'logistics_company_id' => 1]);
        $other = $this->worker('staff', ['center_id' => 13, 'logistics_company_id' => 2]);
        $this->save($staff, 'hub-11')->assertOk();
        $this->login($colleague)->getJson('/api/remittances/payout-account')->assertOk()->assertJsonPath('accounts.0.key', 'hub-11')
            ->assertJsonPath('accounts.0.details.account', '000123456789')->assertJsonCount(1, 'accounts');
        $this->save($other, 'hub-11')->assertForbidden();
        $this->save($staff, 'hub-13')->assertForbidden();
        $manager = $this->worker('admin', ['is_admin' => false, 'logistics_company_id' => 1]);
        $this->login($manager)->getJson('/api/remittances/payout-account')->assertOk()->assertJsonCount(2, 'accounts');
        $this->save($manager, 'hub-12')->assertOk();
        $this->save($manager, 'hub-13')->assertForbidden();
        $this->save($colleague, 'hub-11', ['account' => '000999888777'])->assertOk();
        $order = EscrowRemittance::create(['recipient_role' => 'origin_hub', 'recipient_user_id' => $manager->id, 'logistics_center_id' => 11]);
        $return = $this->allocation(['role' => 'collection_hub', 'center_id' => 11]);
        $this->assertSame('000999888777', PayoutAccounts::forRemittance($order)['details']['account']);
        $this->assertSame('000999888777', PayoutAccounts::forRemittance($return)['details']['account']);
    }

    public function test_driver_and_linked_login_share_one_profile_and_both_ledgers_use_it(): void
    {
        $user = $this->worker('buyer');
        $driver = HubTransportDriver::create(['user_id' => $user->id, 'name' => 'Test driver', 'status' => 'active']);
        $key = 'driver-'.$driver->id;
        $this->save($driver, $key)->assertOk();
        $this->login($user)->getJson('/api/remittances/payout-account')->assertOk()->assertJsonPath('accounts.0.key', $key);
        $this->save($user, $key, ['account' => '000DRIVERUPDATED'])->assertOk();
        $order = EscrowRemittance::create(['recipient_role' => 'inter_hub_driver', 'hub_transport_driver_id' => $driver->id]);
        $return = $this->allocation(['role' => 'inter_hub_driver', 'driver_id' => $driver->id]);
        $this->assertSame('000DRIVERUPDATED', PayoutAccounts::forRemittance($order)['details']['account']);
        $this->assertSame('000DRIVERUPDATED', PayoutAccounts::forRemittance($return)['details']['account']);
        $this->assertDatabaseCount('payout_accounts', 1);
        $user->update(['account_status' => 'suspended']);
        $this->login($driver->fresh())->getJson('/api/remittances/payout-account')->assertForbidden();
    }

    public function test_other_workers_buyers_and_inactive_users_cannot_read_or_change_another_account(): void
    {
        $owner = $this->worker();
        $other = $this->worker();
        $admin = $this->worker('admin', ['is_admin' => true]);
        $this->save($owner, 'user-'.$owner->id)->assertOk();
        $this->save($other, 'user-'.$owner->id)->assertForbidden();
        $this->login($other)->getJson('/api/remittances/payout-account')->assertOk()->assertDontSee('000123456789');
        $row = EscrowRemittance::create(['recipient_role' => 'pickup_rider', 'recipient_user_id' => $owner->id]);
        $this->getJson('/admin/payments/payout-account/order/'.$row->id)->assertForbidden();
        $this->save($admin, 'user-'.$owner->id)->assertForbidden();
        $this->login($this->worker('buyer'))->getJson('/api/remittances/payout-account')->assertForbidden();
        $owner->update(['account_status' => 'suspended']);
        $this->login($owner)->getJson('/api/remittances/payout-account')->assertForbidden();
        $this->assertSame('000123456789', PayoutAccount::firstOrFail()->details['account']);
    }

    public function test_invalid_details_do_not_replace_a_saved_account_and_missing_accounts_are_explicit(): void
    {
        $actor = $this->worker();
        $key = 'user-'.$actor->id;
        $row = EscrowRemittance::create(['recipient_role' => 'last_mile_rider', 'recipient_user_id' => $actor->id]);
        $this->assertNull(PayoutAccounts::forRemittance($row)['details']);
        $this->save($actor, $key)->assertOk();
        foreach ([['provider' => ''], ['holder' => ''], ['account' => 12345], ['account' => '123'], ['account' => "123\n456"], ['account' => '----']] as $bad) {
            $this->save($actor, $key, $bad)->assertUnprocessable();
        }
        $this->assertSame('000123456789', PayoutAccounts::saved('user', $actor->id)->details['account']);
        $this->assertDatabaseCount('payout_accounts', 1);
    }

    public function test_web_form_is_one_saved_configuration_for_the_recipient(): void
    {
        $actor = $this->worker('seller');
        $this->save($actor, 'user-'.$actor->id)->assertOk();
        $html = view('shared.remittances.payout-account', ['errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('Save payout details', $html);
        $this->assertStringContainsString('000123456789', $html);
        $this->assertSame(1, substr_count($html, 'name="target"'));
        $this->assertStringNotContainsString('return_settlement', $html);
        if (getenv('PAYOUT_UI_PREVIEW')) {
            file_put_contents(base_path('.tools/payout-account-worker.html'), '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/shared/css/payout-accounts.css"></head><body style="margin:16px;font-family:Segoe UI,sans-serif">'.$html.'</body></html>');
        }
        Auth::forgetGuards();
        $this->actingAs($actor, 'web')->from('/seller/remittances')->post('/remittances/payout-account', [
            'target' => 'user-'.$actor->id, 'provider' => 'Updated bank', 'holder' => 'Test holder', 'account' => '000WEBUPDATED',
        ])->assertRedirect('/seller/remittances')->assertSessionHas('payout_account_saved');
        $this->assertSame('000WEBUPDATED', PayoutAccounts::saved('user', $actor->id)->details['account']);
    }
}
