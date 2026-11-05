<?php

namespace Tests\Feature;

use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransportDriver;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class InterHubRemittanceAcceptanceTest extends TestCase
{
    use BuyerMobileDatabase;

    private HubTransportDriver $driver;

    private HubTransfer $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        foreach ([
            'logistics_companies' => 'name status deleted_at',
            'hub_transport_drivers' => 'name email password status is_online logistics_company_id user_id logistics_truck_id deleted_at',
            'logistics_trucks' => 'hub_transport_driver_id deleted_at',
            'hub_transfers' => 'reference logistics_company_id driver_id status received_at origin_center_id destination_center_id truck_id',
            'hub_transfer_parcels' => 'hub_transfer_id delivery_id loading_status receiving_status',
            'hub_transfer_events' => 'hub_transfer_id',
            'vehicle_incidents' => 'hub_transport_driver_id incident_date deleted_at',
            'hub_transfer_earnings' => 'logistics_company_id hub_transfer_id delivery_id hub_transport_driver_id recipient_role base_amount vehicle_allowance vehicle_ownership amount earned_on description',
            'escrow_remittances' => 'order_id delivery_id hub_transfer_id hub_transport_driver_id recipient_role recipient_user_id rider_id logistics_center_id amount status bank_reference remitted_at notes',
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) {
                    if (str_ends_with($column, '_id')) {
                        $table->integer($column)->nullable();
                    } else {
                        $table->text($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
        DB::table('logistics_companies')->insert(['id' => 1, 'name' => 'Test Logistics', 'status' => 'active']);
        $this->driver = HubTransportDriver::create(['logistics_company_id' => 1, 'name' => 'Interhub Driver', 'email' => 'driver@example.test', 'password' => 'Secret123!', 'status' => 'available']);
        $this->trip = HubTransfer::create(['reference' => 'HT-TEST', 'logistics_company_id' => 1, 'driver_id' => $this->driver->id, 'status' => 'received', 'received_at' => now()]);
        HubTransferEarning::create(['logistics_company_id' => 1, 'hub_transfer_id' => $this->trip->id, 'hub_transport_driver_id' => $this->driver->id,
            'recipient_role' => 'inter_hub_driver', 'base_amount' => 20, 'vehicle_allowance' => 15, 'amount' => 35, 'earned_on' => now()]);
        Sanctum::actingAs($this->driver, ['hub-courier']);
    }

    private function payout(string $status = 'remitted', array $attributes = []): EscrowRemittance
    {
        return EscrowRemittance::create(array_merge(['hub_transfer_id' => $this->trip->id, 'hub_transport_driver_id' => $this->driver->id,
            'recipient_role' => 'inter_hub_driver', 'amount' => 20, 'status' => $status, 'bank_reference' => 'TRANSFER-TEST', 'remitted_at' => now()], $attributes));
    }

    public function test_completed_trips_and_admin_release_do_not_credit_unaccepted_earnings(): void
    {
        $this->payout();
        $this->getJson('/api/logistics/courier/earnings')->assertOk()->assertJsonPath('summary.total_earnings', 0)->assertJsonCount(0, 'earnings');
        $this->getJson('/api/logistics/courier/dashboard')->assertOk()->assertJsonPath('earnings_summary.total_earnings', 0)->assertJsonPath('earnings_summary.completed_trips_count', 1);
        $this->assertCount(0, $this->driver->earnings);
    }

    public function test_driver_acceptance_confirms_earnings_without_changing_payment_amounts(): void
    {
        $payout = $this->payout();
        $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertOk()->assertJsonPath('remittance.status', 'accepted');
        $this->getJson('/api/logistics/courier/earnings')->assertOk()->assertJsonPath('summary.total_earnings', 35)->assertJsonPath('summary.vehicle_allowance', 15)->assertJsonCount(1, 'earnings');
        $this->getJson('/api/logistics/courier/dashboard')->assertOk()->assertJsonPath('earnings_summary.total_earnings', 35);
        $this->assertSame(20.0, (float) $payout->fresh()->amount);
        $this->assertDatabaseCount('hub_transfer_earnings', 1);
        $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertStatus(409);
        $this->assertDatabaseCount('hub_transfer_earnings', 1);
    }

    public function test_all_trip_remittances_must_be_accepted_before_trip_pay_is_visible(): void
    {
        $first = $this->payout();
        $second = $this->payout();
        $this->postJson("/api/logistics/courier/remittances/{$first->id}/accept")->assertOk();
        $this->getJson('/api/logistics/courier/earnings')->assertJsonPath('summary.total_earnings', 0);
        $this->postJson("/api/logistics/courier/remittances/{$second->id}/accept")->assertOk();
        $this->getJson('/api/logistics/courier/earnings')->assertJsonPath('summary.total_earnings', 35);
    }

    public function test_driver_cannot_accept_held_pending_voided_or_another_drivers_payout(): void
    {
        foreach (['held_in_escrow', 'pending_remittance', 'voided'] as $status) {
            $payout = $this->payout($status);
            $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertStatus(409);
            $this->assertSame($status, $payout->fresh()->status);
        }
        $other = $this->payout('remitted', ['hub_transport_driver_id' => 999]);
        $this->postJson("/api/logistics/courier/remittances/{$other->id}/accept")->assertNotFound();
        $wrongRole = $this->payout('remitted', ['recipient_role' => 'origin_hub']);
        $this->postJson("/api/logistics/courier/remittances/{$wrongRole->id}/accept")->assertNotFound();
    }

    public function test_listing_is_private_and_exposes_reference_and_acceptance_state(): void
    {
        $this->payout();
        $this->payout('held_in_escrow');
        $this->payout('remitted', ['hub_transport_driver_id' => 999]);
        $this->getJson('/api/logistics/courier/remittances')->assertOk()->assertJsonCount(2, 'remittances')
            ->assertJsonPath('remittances.0.can_accept', false)->assertJsonPath('remittances.1.can_accept', true)
            ->assertJsonPath('remittances.1.bank_reference', 'TRANSFER-TEST')->assertJsonPath('remittances.1.transfer_reference', 'HT-TEST');
    }

    public function test_hub_staff_cannot_accept_the_drivers_money_on_their_behalf(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'Secret123!', 'role' => 'logistics_staff', 'center_id' => 11]);
        $payout = $this->payout('remitted', ['logistics_center_id' => 11]);
        $this->expectException(HttpException::class);
        $payout->accept($staff);
    }

    public function test_old_delivery_credits_match_their_own_remittance_and_not_another_parcel(): void
    {
        HubTransferEarning::query()->update(['delivery_id' => 90]);
        $this->payout('accepted', ['delivery_id' => 91]);
        $this->assertSame(0, HubTransferEarning::acceptedRemittances()->count());
        $this->payout('accepted', ['delivery_id' => 90]);
        $this->assertSame(1, HubTransferEarning::acceptedRemittances()->count());
    }

    public function test_gcash_acceptance_requires_confirmed_full_escrow_funding(): void
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
        $payout = $this->payout('remitted', ['order_id' => $order->id]);
        $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertStatus(422)->assertJsonValidationErrors('gcash');
        $this->assertSame('remitted', $payout->fresh()->status);
        $order->update(['payment_status' => 'paid']);
        DB::table('payments')->where('order_id', $order->id)->update(['status' => 'paid']);
        DB::table('gcash_escrow_deposits')->insert(['order_id' => $order->id, 'status' => 'funded', 'amount' => 100, 'confirmed_at' => now()]);
        $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertOk();
        $this->assertSame('accepted', $payout->fresh()->status);
    }

    public function test_other_authenticated_account_types_cannot_use_driver_acceptance(): void
    {
        $payout = $this->payout();
        $buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => 'Secret123!', 'role' => 'buyer']);
        Sanctum::actingAs($buyer, ['hub-courier']);
        $this->postJson("/api/logistics/courier/remittances/{$payout->id}/accept")->assertForbidden();
        $this->assertSame('remitted', $payout->fresh()->status);
    }
}
