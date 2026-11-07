<?php

namespace Tests\Feature;

use App\Models\EscrowRemittance;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class HubRemittanceAcceptanceTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::table('users', fn (Blueprint $table) => $table->integer('logistics_company_id')->nullable());
        foreach ([
            'logistics_centers' => 'name logistics_company_id is_active',
            'escrow_remittances' => 'order_id delivery_id recipient_role recipient_user_id rider_id logistics_center_id amount status bank_reference remitted_at notes rule_applied',
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
            });
        }
        DB::table('logistics_centers')->insert([
            ['id' => 11, 'name' => 'Lumban Hub', 'logistics_company_id' => 1, 'is_active' => 1],
            ['id' => 12, 'name' => 'Another Hub', 'logistics_company_id' => 1, 'is_active' => 1],
            ['id' => 13, 'name' => 'Other Company Hub', 'logistics_company_id' => 2, 'is_active' => 1],
        ]);
    }

    private function user(array $attributes = []): User
    {
        return User::create(array_merge(['name' => 'Hub Staff', 'email' => uniqid().'@example.test', 'password' => 'Secret123!', 'role' => 'logistics_staff',
            'status' => 'active', 'account_status' => 'active', 'email_verified_at' => now(), 'center_id' => 11, 'logistics_company_id' => 1], $attributes));
    }

    private function payout(array $attributes = []): EscrowRemittance
    {
        return EscrowRemittance::create(array_merge(['recipient_role' => 'origin_hub', 'logistics_center_id' => 11, 'amount' => 30, 'status' => 'remitted',
            'bank_reference' => 'HUB-TRANSFER-123', 'remitted_at' => now()], $attributes));
    }

    public function test_staff_can_view_hub_payouts_and_only_accepted_amounts_are_earnings(): void
    {
        $this->payout();
        $this->payout(['status' => 'accepted', 'amount' => 40]);
        $this->payout(['status' => 'held_in_escrow', 'amount' => 50]);
        $foreign = $this->payout(['logistics_center_id' => 12]);
        $this->actingAs($this->user())->get(route('remittances.hub-payouts.index'))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals === ['accepted' => 40.0, 'remitted' => 30.0, 'awaiting_release' => 50.0])
            ->assertSee('Confirm received')->assertSee('Received · recorded in hub earnings')->assertSee('Hub remittances')->assertDontSee('data-payout-id="'.$foreign->id.'"', false);
    }

    public function test_both_origin_and_destination_cuts_require_receipt_acceptance(): void
    {
        $this->actingAs($this->user());
        foreach (['origin_hub', 'destination_hub'] as $role) {
            $payout = $this->payout(['recipient_role' => $role]);
            $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertRedirect()->assertSessionHas('success');
            $this->assertSame('accepted', $payout->fresh()->status);
            $this->assertSame(30.0, (float) $payout->fresh()->amount);
            $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertRedirect()->assertSessionHas('info');
        }
        $this->get(route('remittances.hub-payouts.index'))->assertViewHas('totals', fn ($totals) => $totals['accepted'] === 60.0);
    }

    public function test_unreleased_payments_and_missing_receipt_confirmation_cannot_be_accepted(): void
    {
        $this->actingAs($this->user());
        $released = $this->payout();
        $this->postJson(route('remittances.hub-payouts.accept', $released->id))->assertStatus(422)->assertJsonValidationErrors('confirm_received');
        $this->assertSame('remitted', $released->fresh()->status);
        foreach (['held_in_escrow', 'pending_remittance', 'voided'] as $status) {
            $payout = $this->payout(['status' => $status]);
            $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertSessionHas('error');
            $this->assertSame($status, $payout->fresh()->status);
        }
    }

    public function test_staff_cannot_accept_other_hubs_or_worker_cuts_even_with_their_user_id(): void
    {
        $staff = $this->user();
        $this->actingAs($staff);
        foreach ([['logistics_center_id' => 12, 'recipient_user_id' => $staff->id], ['recipient_role' => 'pickup_rider', 'recipient_user_id' => $staff->id]] as $attributes) {
            $payout = $this->payout($attributes);
            $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertNotFound();
            $this->assertSame('remitted', $payout->fresh()->status);
        }
    }

    public function test_company_manager_can_confirm_company_hubs_but_not_another_company(): void
    {
        $manager = $this->user(['role' => 'admin', 'is_admin' => false, 'center_id' => null]);
        $own = $this->payout(['logistics_center_id' => 12]);
        $foreign = $this->payout(['logistics_center_id' => 13]);
        $this->actingAs($manager)->get(route('remittances.hub-payouts.index'))->assertOk()->assertSee('Another Hub')->assertDontSee('Other Company Hub');
        $this->post(route('remittances.hub-payouts.accept', $own->id), ['confirm_received' => 1])->assertSessionHas('success');
        $this->post(route('remittances.hub-payouts.accept', $foreign->id), ['confirm_received' => 1])->assertNotFound();
    }

    public function test_global_admin_has_oversight_but_cannot_confirm_receipt_for_a_hub(): void
    {
        $payout = $this->payout();
        $this->actingAs($this->user(['role' => 'admin', 'is_admin' => true, 'center_id' => null]))
            ->get(route('remittances.hub-payouts.index'))->assertOk()->assertSee('Lumban Hub')->assertDontSee('Confirm received</button>', false);
        $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertForbidden();
    }

    public function test_unassigned_staff_see_no_company_wide_financial_data(): void
    {
        $this->payout();
        $this->actingAs($this->user(['center_id' => null]))->get(route('remittances.hub-payouts.index'))->assertOk()
            ->assertSee('No hub remittances found')->assertDontSee('HUB-TRANSFER-123');
    }

    public function test_receipt_confirmation_cannot_bypass_unfunded_gcash_escrow(): void
    {
        Schema::create('gcash_escrow_deposits', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->timestamp('confirmed_at')->nullable();
        });
        $order = Order::create(['status' => 'pending', 'total_amount' => 100, 'payment_status' => 'pending']);
        DB::table('payments')->insert(['order_id' => $order->id, 'method' => 'gcash', 'amount' => 100, 'status' => 'pending']);
        $payout = $this->payout(['order_id' => $order->id]);
        $this->actingAs($this->user())->postJson(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('gcash');
        $this->assertSame('remitted', $payout->fresh()->status);
        $order->update(['payment_status' => 'paid']);
        DB::table('payments')->where('order_id', $order->id)->update(['status' => 'paid']);
        DB::table('gcash_escrow_deposits')->insert(['order_id' => $order->id, 'status' => 'funded', 'amount' => 100, 'confirmed_at' => now()]);
        $this->post(route('remittances.hub-payouts.accept', $payout->id), ['confirm_received' => 1])->assertSessionHas('success');
        $this->assertSame('accepted', $payout->fresh()->status);
    }
}
