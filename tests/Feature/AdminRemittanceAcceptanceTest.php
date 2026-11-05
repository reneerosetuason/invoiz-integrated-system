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

class AdminRemittanceAcceptanceTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('escrow_remittances', function (Blueprint $table) {
            $table->id();
            foreach (explode(' ', 'order_id delivery_id recipient_role recipient_user_id rider_id logistics_center_id recipient_name amount status bank_reference remitted_at settlement_mode notes rule_applied') as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
    }

    private function admin(array $attributes = []): User
    {
        return User::create(array_merge(['name' => 'Platform Admin', 'email' => uniqid().'@example.test', 'password' => 'Secret123!',
            'role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active', 'email_verified_at' => now()], $attributes));
    }

    private function payout(array $attributes = []): EscrowRemittance
    {
        return EscrowRemittance::create(array_merge(['recipient_role' => 'admin', 'recipient_name' => 'Platform', 'amount' => 10,
            'status' => 'remitted', 'settlement_mode' => 'platform_retained', 'remitted_at' => now()], $attributes));
    }

    public function test_commission_is_counted_only_after_admin_accepts_and_repeat_does_not_double_count(): void
    {
        $payout = $this->payout();
        $this->payout(['recipient_role' => 'seller', 'status' => 'accepted', 'amount' => 90]);
        $this->assertEquals(0, EscrowRemittance::acceptedAdminCommission()->sum('amount'));
        $this->actingAs($this->admin())->post(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('accepted', $payout->fresh()->status);
        $notes = $payout->fresh()->notes;
        $this->post(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertSessionHas('error');
        $this->assertSame($notes, $payout->fresh()->notes);
        $this->assertEquals(10, EscrowRemittance::acceptedAdminCommission()->sum('amount'));
    }

    public function test_unreleased_voided_and_unconfirmed_receipts_cannot_be_accepted(): void
    {
        $this->actingAs($this->admin());
        $released = $this->payout();
        $this->postJson(route('admin.remittances.accept', $released->id))->assertUnprocessable()->assertJsonValidationErrors('confirm_received');
        foreach (['held_in_escrow', 'pending_remittance', 'voided'] as $status) {
            $payout = $this->payout(['status' => $status]);
            $this->post(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertSessionHas('error');
            $this->assertSame($status, $payout->fresh()->status);
        }
    }

    public function test_non_platform_users_cannot_accept_even_when_named_as_recipient(): void
    {
        foreach (['buyer', 'logistics_staff', 'admin'] as $role) {
            $user = $this->admin(['role' => $role, 'is_admin' => false]);
            $payout = $this->payout(['recipient_user_id' => $user->id]);
            $this->actingAs($user)->postJson(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertForbidden();
            try {
                $payout->accept($user);
                $this->fail('Unauthorized admin commission acceptance');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
            $this->assertSame('remitted', $payout->fresh()->status);
        }
    }

    public function test_admin_acceptance_endpoint_cannot_accept_worker_cuts_or_zero_amounts(): void
    {
        $this->actingAs($this->admin());
        foreach ([['recipient_role' => 'origin_hub'], ['amount' => 0]] as $attributes) {
            $payout = $this->payout($attributes);
            $this->post(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertNotFound();
            $this->assertSame('remitted', $payout->fresh()->status);
        }
    }

    public function test_gcash_acceptance_requires_full_escrow_funding(): void
    {
        Schema::create('gcash_escrow_deposits', function (Blueprint $table) {
            $table->id(); $table->integer('order_id'); $table->string('status'); $table->decimal('amount', 12, 2); $table->timestamp('confirmed_at')->nullable();
        });
        $order = Order::create(['status' => 'delivered', 'total_amount' => 100, 'payment_status' => 'pending']);
        DB::table('payments')->insert(['order_id' => $order->id, 'method' => 'gcash', 'amount' => 100, 'status' => 'pending']);
        $payout = $this->payout(['order_id' => $order->id]);
        $this->actingAs($this->admin())->postJson(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertUnprocessable()->assertJsonValidationErrors('gcash');
        $this->assertSame('remitted', $payout->fresh()->status);
        $order->update(['payment_status' => 'paid']);
        DB::table('payments')->update(['status' => 'paid']);
        DB::table('gcash_escrow_deposits')->insert(['order_id' => $order->id, 'status' => 'funded', 'amount' => 100, 'confirmed_at' => now()]);
        $this->post(route('admin.remittances.accept', $payout->id), ['confirm_received' => 1])->assertSessionHas('status');
    }

    public function test_manage_payments_contains_admin_remittances_and_one_sidebar_entry(): void
    {
        $this->payout();
        $this->payout(['status' => 'accepted', 'amount' => 25]);
        $this->actingAs($this->admin())->get('/admin/payments')->assertOk()->assertSee('Admin remittances')
            ->assertSee('Accept admin remittance')->assertDontSee('COD Remittances')
            ->assertViewHas('stats', fn ($stats) => $stats['commission'] === 25.0)
            ->assertViewHas('adminRemittanceTotals', fn ($totals) => $totals['remitted'] === 10.0 && $totals['accepted'] === 25.0);
    }

    public function test_financial_report_includes_only_accepted_platform_commission(): void
    {
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id(); $table->string('status'); $table->decimal('amount', 12, 2);
        });
        $order = Order::create(['status' => 'delivered', 'payment_status' => 'paid', 'total' => 100, 'commission_amount' => 99]);
        $this->payout(['order_id' => $order->id, 'status' => 'accepted', 'amount' => 7]);
        $this->payout(['order_id' => $order->id, 'status' => 'remitted', 'amount' => 3]);
        $this->payout(['order_id' => $order->id, 'recipient_role' => 'seller', 'status' => 'accepted', 'amount' => 90]);
        $method = new \ReflectionMethod(\App\Http\Controllers\Admin\ReportController::class, 'financialData');
        $report = $method->invoke(app(\App\Http\Controllers\Admin\ReportController::class), \Illuminate\Http\Request::create('/admin/reports'));
        $this->assertSame(7.0, $report['breakdown']->first()->commission);
        $this->assertSame(\App\Support\Money::format(7), $report['cards']['Confirmed Commissions']);
    }
}
