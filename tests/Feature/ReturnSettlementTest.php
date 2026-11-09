<?php

namespace Tests\Feature;

use App\Models\{Order, Payment, ReturnRequest, ReturnSettlement, ReturnAllocation, User, Rider, Delivery, GcashEscrowDeposit};
use App\Services\{ReturnSettlementService as Settlements, ReturnWorkflowService};
use App\Support\Payments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB, Storage};
use Illuminate\Validation\ValidationException;

class ReturnSettlementTest extends GcashEscrowWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('riders',fn(Blueprint $t)=>$t->boolean('is_online')->default(false));
        Schema::table('return_requests', function (Blueprint $t) {
            $t->text('decision_note')->nullable(); $t->timestamp('resolved_at')->nullable();
            $t->string('evidence_path')->nullable(); $t->string('evidence_type')->nullable();
        });
        Schema::table('payment_disputes', fn (Blueprint $t) => $t->timestamp('resolved_at')->nullable());
        (require database_path('migrations/2026_11_05_180000_create_return_workflows.php'))->up();
        (require database_path('migrations/2026_11_06_180000_create_return_settlement_ledgers.php'))->up();
        (require database_path('migrations/2026_11_08_160000_add_automatic_return_funding.php'))->up();
        (require database_path('migrations/2026_11_08_180000_add_direct_return_agreements.php'))->up();
        (require database_path('migrations/2026_11_08_200000_add_return_receipt_preview_tracking.php'))->up();
        (require database_path('migrations/2026_11_08_220000_add_seller_direct_buyer_fault_returns.php'))->up();
        (require database_path('migrations/2026_11_08_230000_add_return_transport_assignment.php'))->up();
        (require database_path('migrations/2026_11_09_100000_create_direct_refund_transfers.php'))->up();
        Schema::create('logistics_centers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->boolean('is_active')->default(true); $t->unsignedBigInteger('logistics_company_id'); $t->timestamps();
        });
        DB::table('logistics_centers')->insert([['id'=>1,'name'=>'Return hub','is_active'=>true,'logistics_company_id'=>1], ['id'=>2,'name'=>'Other hub','is_active'=>true,'logistics_company_id'=>1]]);
        Storage::fake('private'); Storage::disk('private')->put('test-proof.png', 'fixture');
    }

    private function fixture(): array
    {
        $order = Order::firstOrFail();
        $seller = User::where('role','seller')->firstOrFail(); $buyer = User::where('role','buyer')->firstOrFail();
        $admin = User::where('is_admin',true)->firstOrFail();
        Payment::where('order_id',$order->id)->update(['status'=>'paid']);
        GcashEscrowDeposit::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'amount'=>150,'status'=>'funded','confirmed_at'=>now()]);
        $order->update(['payment_status'=>'paid']);
        Delivery::first()->update(['status'=>'delivered','center_id'=>1,'delivered_at'=>now()]);
        $order->update(['payment_status'=>'paid','status'=>'delivered']); Payments::settleForDelivery($order);
        $product = \App\Models\Product::create(['seller_id'=>$seller->id,'name'=>'Replacement fixture','price'=>100,'stock'=>10,'status'=>'active']);
        $item = $order->items()->first(); $item->update(['product_id'=>$product->id]);
        $case = ReturnRequest::create(['order_id'=>$order->id,'order_item_id'=>$item->id,'buyer_id'=>$buyer->id,'seller_id'=>$seller->id,
            'product_id'=>$item->product_id,'quantity'=>1,'reason'=>'wrong_item','status'=>'requested','refund_amount'=>100]);
        Settlements::ensure($case);
        $case->settlement()->update(['workflow_policy'=>'legacy','policy_version'=>1]);
        $staff = User::create(['name'=>'Return staff','email'=>uniqid().'@test.local','password'=>'test','role'=>'staff','center_id'=>1,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        $riderUser = User::create(['name'=>'Return rider','email'=>uniqid().'@test.local','password'=>'test','role'=>'rider','center_id'=>1,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        $rider = Rider::create(['user_id'=>$riderUser->id,'name'=>'Return rider','center_id'=>1,'status'=>'available','is_verified'=>true,'approved_at'=>now(),'is_online'=>true]);
        return compact('order','seller','buyer','admin','case','staff','rider','riderUser');
    }

    private function change(array $f, string $actor, string $action, array $data = []): void
    {
        Settlements::change($f['case'], $f[$actor], $action, ['version'=>$f['case']->fresh()->settlement->version]+$data);
    }

    private function proposal(array $f, string $resolution = 'refund_only', string $payer = 'seller'): void
    {
        $this->change($f,'seller','propose',['fault'=>$payer === 'buyer' ? 'buyer' : 'seller','resolution'=>$resolution,'payer'=>$payer,
            'funding_method'=>$payer === 'buyer' ? 'deduct_refund' : 'deposit','merchandise_amount'=>$resolution === 'exchange' ? 0 : 100,'note'=>'Compared purchased variant and the submitted buyer evidence.', 'replacement_description'=>'Agreed replacement in the correct size and color']);
        if (in_array($resolution,['return_refund','exchange'])) {
            $lines = [['role'=>'collection_rider','amount'=>10],['role'=>'collection_hub','amount'=>5],['role'=>'seller_delivery_rider','amount'=>5]];
            if ($resolution === 'exchange') $lines[] = ['role'=>'replacement_delivery_rider','amount'=>5];
            $this->change($f,'admin','quote',['collection_center_id'=>1,'seller_center_id'=>1,'allocations'=>$lines]);
        }
        $this->change($f,'seller','accept'); $this->change($f,'buyer','accept');
        $this->change($f,'admin','decide',['note'=>'Admin reviewed the evidence, responsibility and all quoted charges.']);
    }

    private function deposit(array $f, string $purpose, float $amount): void
    {
        $p = Settlements::submitPayment($f['case'],$f['seller'],['version'=>$f['case']->fresh()->settlement->version,'purpose'=>$purpose,
            'amount'=>$amount,'reference'=>uniqid('BANK-'),'proof_path'=>'test-proof.png']);
        Settlements::viewPaymentReceipt($p,$f['admin']);
        Settlements::reviewPayment($p,$f['admin'],true,'Verified the actual incoming bank transaction and account.');
    }

    private function fund(array $f): void
    {
        $s=$f['case']->fresh()->settlement;
        if ($s->net_refund_cents > 0) $this->change($f,'buyer','destination',['provider'=>'Test wallet','holder'=>'Buyer','account'=>'09170000000']);
        if ($s->funding_method === 'deposit' && $s->budgetCents()>0) $this->deposit($f,'return_budget',$s->budgetCents()/100);
        $need=ReturnWorkflowService::fundingSummary($f['order'],($s->net_refund_cents+($s->funding_method==='deduct_refund'?$s->budgetCents():0))/100)['shortfall'];
        if ($need>0) $this->deposit($f,'refund_recovery',$need);
        if ($s->funding_method==='deduct_refund') $this->change($f,'admin','reserve');
        $this->change($f,'admin','ready');
    }

    private function physical(array $f): void
    {
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        ReturnWorkflowService::change($f['case'],$f['staff'],'dispatch_seller',['reference'=>'RETURN-HANDOVER','rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['seller'],'receive');
    }

    public function test_refund_only_never_fakes_a_physical_return_or_creates_transport_work(): void
    {
        $f=$this->fixture();$this->proposal($f);$this->fund($f);
        $this->assertSame('approved',$f['case']->fresh()->status);
        $this->assertSame('refund_pending',$f['case']->fresh()->workflow->stage);
        $this->assertDatabaseCount('return_allocations',0);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'BUYER-REFUND','proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        $this->assertSame('refunded_return_waived',$f['case']->fresh()->settlement->status);
        $this->assertFalse((bool)$f['case']->fresh()->workflow->stock_restored);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_buyer_deduction_is_reserved_once_and_net_refund_reverses_the_full_merchandise(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund','buyer');$this->fund($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(2000,(int)$s->reserved_cents);$this->assertSame(8000,(int)$s->net_refund_cents);
        $this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'NET-REFUND','proof_path'=>'test-proof.png']);
        $this->assertEquals(80,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertEquals(100,$f['case']->fresh()->merchandiseRefundAmount());
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->platform_commission);
    }

    public function test_return_earnings_require_verified_work_actual_remittance_and_recipient_acceptance(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=ReturnAllocation::where('role','collection_rider')->firstOrFail();
        $this->assertSame('eligible',$line->status);
        Settlements::payout($line,$f['riderUser'],'destination',['provider'=>'Bank','holder'=>'Rider','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'RETURN-PAYOUT','proof_path'=>'test-proof.png']);
        $this->assertSame(0,ReturnAllocation::where('status','accepted')->sum('amount_cents'));
        Settlements::payout($line,$f['riderUser'],'accept',[]);
        Settlements::payout($line,$f['riderUser'],'accept',[]);
        $this->assertSame(1000,(int)ReturnAllocation::where('status','accepted')->sum('amount_cents'));
        $this->assertDatabaseCount('rider_earnings',0);
    }

    public function test_order_style_return_release_requires_no_separate_account_or_receipt_and_uses_the_common_inbox(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=$f['case']->settlement->allocations()->where('role','collection_rider')->firstOrFail();
        $originalCount=\App\Models\EscrowRemittance::count();
        $this->actingAs($f['admin'])->postJson('/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertOk();
        $this->assertSame('remitted',$line->fresh()->status);$this->assertNull($line->fresh()->receiving_destination);$this->assertNull($line->fresh()->proof_path);
        $this->assertStringStartsWith('RETURN-RELEASE-',$line->fresh()->reference);
        $this->actingAs($f['admin'])->postJson('/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertOk();
        \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($f['riderUser'],['rider']);
        $this->getJson('/api/remittances/incoming')->assertOk()->assertJsonPath('remittances.0.source','return')
            ->assertJsonPath('remittances.0.return_id',$f['case']->id)->assertJsonPath('summary.remitted',10);
        $path='/api/remittances/incoming/return/'.$line->id.'/accept';
        $this->postJson($path,[])->assertUnprocessable();
        $this->postJson($path,['confirm_received'=>1])->assertOk();
        $this->postJson($path,['confirm_received'=>1])->assertOk();
        $this->getJson('/api/remittances/incoming')->assertOk()->assertJsonPath('summary.accepted',10)->assertJsonPath('summary.remitted',0);
        $this->getJson('/api/logistics/rider/earnings')->assertOk()->assertJsonPath('today',10)->assertJsonPath('this_week',10)
            ->assertJsonPath('history.0.breakdown.0.return_id',$f['case']->id);
        $this->assertSame($originalCount,\App\Models\EscrowRemittance::count());
    }

    public function test_unified_return_release_still_blocks_unfunded_or_unperformed_work_and_other_recipients(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $line=$f['case']->settlement->allocations()->where('role','collection_hub')->firstOrFail();
        $this->actingAs($f['admin'])->postJson('/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertUnprocessable();
        $this->fund($f);
        $this->actingAs($f['admin'])->postJson('/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertUnprocessable();
        $this->physical($f);
        $this->actingAs($f['buyer'])->postJson('/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertForbidden();
        $this->actingAs($f['admin'])->postJson('/return-remittances/'.$line->id,['action'=>'release'])->assertUnprocessable();
        $this->assertSame('eligible',$line->fresh()->status);
    }

    public function test_orders_and_returns_with_the_same_numeric_id_cannot_confirm_each_other(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=$f['case']->settlement->allocations()->where('role','collection_rider')->firstOrFail();
        Settlements::payout($line,$f['admin'],'release',[]);
        $orderPayout=\App\Models\EscrowRemittance::findOrFail($line->id);
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','pickup_rider')->whereKeyNot($orderPayout->id)->delete();
        $orderPayout->update(['recipient_role'=>'pickup_rider','rider_id'=>$f['rider']->id,'recipient_user_id'=>$f['riderUser']->id,'amount'=>25,'status'=>'remitted','bank_reference'=>'ORIGINAL-ONLY-REFERENCE','remitted_at'=>now()]);
        \Laravel\Sanctum\Sanctum::actingAs($f['riderUser'],['rider']);
        $response=$this->getJson('/api/remittances/incoming')->assertOk()->assertJsonCount(3,'remittances');
        $lastMile=$f['case']->settlement->allocations()->where('role','seller_delivery_rider')->value('id');
        $this->assertEqualsCanonicalizing(['order-'.$line->id,'return-'.$line->id,'return-'.$lastMile],array_column($response->json('remittances'),'key'));
        $this->postJson('/api/remittances/incoming/return/'.$line->id.'/accept',['confirm_received'=>1])->assertOk();
        $this->assertSame('accepted',$line->fresh()->status);$this->assertSame('remitted',$orderPayout->fresh()->status);
        $this->postJson('/api/remittances/incoming/order/'.$line->id.'/accept',['confirm_received'=>1])->assertOk();
        $this->assertSame('accepted',$orderPayout->fresh()->status);
    }

    public function test_all_five_return_services_use_order_style_release_and_the_common_recipient_confirmation(): void
    {
        $f=$this->returnTransportFixture();
        $receivingStaff=User::create(['name'=>'Receiving staff','email'=>'unified-receiving@test.local','password'=>'test','role'=>'staff','center_id'=>2,'account_status'=>'active','status'=>'active']);
        $lastUser=User::create(['name'=>'Final return rider','email'=>'unified-last-rider@test.local','password'=>'test','role'=>'rider','center_id'=>2,'account_status'=>'active','status'=>'active']);
        $last=Rider::create(['user_id'=>$lastUser->id,'name'=>'Final return rider','center_id'=>2,'status'=>'available','is_verified'=>true,'approved_at'=>now(),'is_online'=>true]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$f['driver']->id,'reference'=>'UNIFIED-RETURN-TRANSPORT']);
        ReturnWorkflowService::change($f['case'],$receivingStaff,'receive_transfer');
        ReturnWorkflowService::change($f['case'],$receivingStaff,'dispatch_seller',['rider_id'=>$last->id,'reference'=>'UNIFIED-SELLER-DELIVERY']);
        ReturnWorkflowService::change($f['case'],$lastUser,'deliver_seller',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['seller'],'receive');
        $payment=$f['case']->fresh()->settlement->payments()->firstOrFail();
        if(getenv('UNIFIED_REMITS_PREVIEW'))file_put_contents(base_path('.tools/unified-remittances-pending.html'),$this->actingAs($f['admin'])->get('/admin/payments')->getContent());
        Settlements::viewPaymentReceipt($payment,$f['admin']);Settlements::reviewPayment($payment,$f['admin'],true,'Confirmed the incoming service escrow deposit.');
        if(getenv('UNIFIED_REMITS_PREVIEW'))file_put_contents(base_path('.tools/unified-remittances-ready.html'),$this->actingAs($f['admin'])->get('/admin/payments')->getContent());
        $originalCount=\App\Models\EscrowRemittance::count();
        $recipients=['collection_rider'=>$f['riderUser'],'collection_hub'=>$f['staff'],'inter_hub_driver'=>$f['driver'],
            'seller_hub'=>$receivingStaff,'seller_delivery_rider'=>$lastUser];
        foreach($recipients as $role=>$recipient){
            $line=$f['case']->fresh()->settlement->allocations()->where('role',$role)->firstOrFail();
            \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($f['admin']);
            $this->postJson('/api/return-remittances/'.$line->id,['action'=>'release','confirm'=>1])->assertOk();
            $this->assertNull($line->fresh()->receiving_destination);$this->assertNull($line->fresh()->proof_path);
            if(getenv('UNIFIED_REMITS_PREVIEW') && $role==='collection_hub'){
                \Illuminate\Support\Facades\Auth::forgetGuards();
                file_put_contents(base_path('.tools/unified-remittances-hub-pending.html'),$this->actingAs($recipient)->get(route('remittances.hub-payouts.index'))->getContent());
            }
            \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($recipient,['*']);
            $this->getJson('/api/remittances/incoming')->assertOk()->assertJsonFragment(['key'=>'return-'.$line->id]);
            $this->postJson('/api/remittances/incoming/return/'.$line->id.'/accept',['confirm_received'=>1])->assertOk();
            $this->assertSame('accepted',$line->fresh()->status);
        }
        $this->assertSame(16000,(int)$f['case']->fresh()->settlement->allocations()->where('status','accepted')->sum('amount_cents'));
        $this->assertSame($originalCount,\App\Models\EscrowRemittance::count());$this->assertDatabaseCount('rider_earnings',0);
        if(getenv('UNIFIED_REMITS_PREVIEW')){
            \Illuminate\Support\Facades\Auth::forgetGuards();
            file_put_contents(base_path('.tools/unified-remittances-hub.html'),$this->actingAs($f['staff'])->get(route('remittances.hub-payouts.index'))->getContent());
        }
        foreach(['base_amount','vehicle_allowance'] as $column)if(!Schema::hasColumn('hub_transfer_earnings',$column))Schema::table('hub_transfer_earnings',fn(Blueprint $t)=>$t->decimal($column,12,2)->default(0));
        \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($f['driver'],['hub-courier']);
        $this->getJson('/api/logistics/courier/earnings')->assertOk()->assertJsonPath('summary.total_earnings',20)
            ->assertJsonPath('earnings.0.source','return')->assertJsonPath('earnings.0.return_id',$f['case']->id);
    }

    public function test_sent_return_rider_fees_wait_in_the_regular_inbox_until_receipt_is_accepted(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $originalCount=\App\Models\EscrowRemittance::count();
        \Laravel\Sanctum\Sanctum::actingAs($f['riderUser'],['rider']);
        $this->getJson('/api/return-remittances?distributed_only=1')->assertOk()->assertJsonCount(0,'allocations.data');
        foreach(['collection_rider','seller_delivery_rider'] as $role){
            $line=$f['case']->settlement->allocations()->where('role',$role)->firstOrFail();
            Settlements::payout($line,$f['riderUser'],'destination',['provider'=>'Bank','holder'=>'Rider','account'=>'INBOX-PRIVATE-ACCOUNT']);
            Settlements::payout($line,$f['admin'],'remit',['reference'=>'INBOX-'.$role,'proof_path'=>'test-proof.png']);
        }
        $this->getJson('/api/return-remittances?distributed_only=1')->assertOk()->assertJsonCount(2,'allocations.data')
            ->assertJsonPath('accepted_earnings_cents',0)->assertJsonPath('awaiting_confirmation_cents',1500);
        $inbox=\App\Support\ReturnRemittanceInbox::page($f['riderUser']);
        $this->assertCount(2,$inbox['rows']);$this->assertSame(1500,$inbox['awaiting_cents']);$this->assertSame(0,$inbox['accepted_cents']);
        $line=$f['case']->settlement->allocations()->where('role','collection_rider')->firstOrFail();
        $this->postJson('/api/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertOk();
        $this->postJson('/api/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertOk();
        $this->getJson('/api/return-remittances?distributed_only=1')->assertOk()->assertJsonPath('accepted_earnings_cents',1000)
            ->assertJsonPath('awaiting_confirmation_cents',500);
        $this->assertSame($originalCount,\App\Models\EscrowRemittance::count());$this->assertDatabaseCount('rider_earnings',0);
        $this->assertSame('accepted',$line->fresh()->status);
    }

    public function test_return_hub_payments_appear_in_regular_hub_remittances_and_cannot_be_claimed_by_another_hub(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=$f['case']->settlement->allocations()->where('role','collection_hub')->firstOrFail();
        Settlements::payout($line,$f['staff'],'destination',['provider'=>'Bank','holder'=>'Hub','account'=>'INBOX-HUB-PRIVATE']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'INBOX-HUB-TRANSFER','proof_path'=>'test-proof.png']);
        $response=$this->actingAs($f['staff'])->get(route('remittances.hub-payouts.index'));
        $response->assertOk()->assertSee('Hub remittances')->assertSee('data-payout-source="return"',false)
            ->assertSee('Confirm received')->assertSee('Return origin hub')->assertDontSee('Return-delivery remittances')->assertDontSee('INBOX-HUB-PRIVATE');
        if(getenv('RETURN_INBOX_PREVIEW'))file_put_contents(base_path('.tools/return-inbox-hub.html'),$response->getContent());
        $other=User::create(['name'=>'Other hub staff','email'=>'return-inbox-other@test.local','password'=>'test','role'=>'staff','center_id'=>2,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        $this->assertSame(0,\App\Support\ReturnRemittanceInbox::query($other)->count());
        $this->actingAs($other)->postJson('/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertForbidden();
        $this->actingAs($f['staff'])->post('/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertRedirect();
        $this->assertSame('accepted',$line->fresh()->status);
        $this->assertSame(500,\App\Support\ReturnRemittanceInbox::page($f['staff'])['accepted_cents']);
    }

    public function test_driver_return_remittance_inbox_contains_only_its_released_transfer_fee(): void
    {
        $f=$this->returnTransportFixture();
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$f['driver']->id,'reference'=>'INBOX-RETURN-TRANSFER']);
        $receiver=User::create(['name'=>'Receiving staff','email'=>'return-inbox-receiver@test.local','password'=>'test','role'=>'staff','center_id'=>2,'account_status'=>'active','status'=>'active']);
        ReturnWorkflowService::change($f['case'],$receiver,'receive_transfer');
        $payment=$f['case']->fresh()->settlement->payments()->firstOrFail();
        Settlements::viewPaymentReceipt($payment,$f['admin']);Settlements::reviewPayment($payment,$f['admin'],true,'Verified the incoming return delivery transfer.');
        $line=$f['case']->fresh()->settlement->allocations()->where('role','inter_hub_driver')->firstOrFail();
        Settlements::payout($line,$f['driver'],'destination',['provider'=>'Bank','holder'=>'Driver','account'=>'DRIVER-INBOX-ACCOUNT']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'INBOX-DRIVER-TRANSFER','proof_path'=>'test-proof.png']);
        $other=\App\Models\HubTransportDriver::create(['name'=>'Different driver','logistics_company_id'=>1,'status'=>'available','is_online'=>true]);
        $this->assertSame(0,\App\Support\ReturnRemittanceInbox::query($other)->count());
        $this->assertSame(2000,\App\Support\ReturnRemittanceInbox::page($f['driver'])['awaiting_cents']);
        \Laravel\Sanctum\Sanctum::actingAs($f['driver']);
        $this->getJson('/api/return-remittances?distributed_only=1')->assertOk()->assertJsonPath('allocations.data.0.id',$line->id);
        $this->postJson('/api/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertOk();
        $this->assertSame(2000,\App\Support\ReturnRemittanceInbox::page($f['driver'])['accepted_cents']);
        $this->assertSame(0,\App\Support\ReturnRemittanceInbox::page($f['driver'])['awaiting_cents']);
    }

    public function test_screenshots_cannot_unlock_pickup_and_nonadmins_cannot_verify_funding(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $p=Settlements::submitPayment($f['case'],$f['seller'],['version'=>$f['case']->fresh()->settlement->version,'purpose'=>'return_budget','amount'=>20,'reference'=>'PENDING-RECEIPT','proof_path'=>'test-proof.png']);
        try {$this->change($f,'admin','ready');$this->fail('Screenshot unlocked funding.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->actingAs($f['buyer'])->post('/return-deposits/'.$p->id.'/review',['decision'=>'verify','note'=>'A buyer cannot approve their own receipt.','confirm'=>1])->assertForbidden();
    }

    public function test_private_refund_account_is_encrypted_and_hidden_from_seller_and_strangers(): void
    {
        $f=$this->fixture();$this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $raw=DB::table('return_settlements')->where('return_request_id',$f['case']->id)->value('refund_destination');
        $this->assertStringNotContainsString('123456789',$raw);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertJsonPath('refund_destination',null);
        $this->actingAs($f['buyer'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertJsonPath('refund_destination.account','123456789');
    }

    public function test_stale_quotes_and_buyer_charges_for_seller_fault_are_rejected(): void
    {
        $f=$this->fixture();$this->proposal($f);
        try {Settlements::change($f['case'],$f['buyer'],'accept',['version'=>1]);$this->fail('Accepted stale quote.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        try {$this->change($f,'seller','propose',['fault'=>'seller','resolution'=>'return_refund','payer'=>'buyer','merchandise_amount'=>100,'note'=>'Attempt to charge buyer for seller mistake.']);$this->fail('Charged buyer.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
    }

    public function test_exchange_has_no_cash_refund_and_waits_for_actual_replacement_receipt(): void
    {
        $f=$this->fixture();$this->proposal($f,'exchange');$this->fund($f);$this->physical($f);
        $this->assertSame('item_received',$f['case']->fresh()->status);
        $this->change($f,'staff','replacement_dispatch',['reference'=>'REPLACEMENT-001','proof_path'=>'test-proof.png','rider_id'=>$f['rider']->id]);
        $this->assertSame('reserved',ReturnAllocation::where('role','replacement_delivery_rider')->value('status'));
        $this->change($f,'buyer','replacement_received');
        $this->assertSame('eligible',ReturnAllocation::where('role','replacement_delivery_rider')->value('status'));
        $this->assertSame('exchange_completed',$f['case']->fresh()->settlement->status);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_cancelled_deduction_releases_original_reservation_without_giving_buyer_free_money(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund','buyer');$this->fund($f);
        $this->change($f,'admin','close_unused',['note'=>'Buyer and seller cancelled before physical collection.']);
        $this->assertEquals(0,$f['case']->fresh()->settlement->reserved_cents);
        $this->assertDatabaseMissing('return_allocations',['role'=>'unused_budget_refund']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('cancelled',$f['case']->fresh()->settlement->status);
    }

    public function test_cancelled_separate_payment_returns_budget_to_the_payer_without_counting_it_as_earnings(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $this->change($f,'admin','close_unused',['note'=>'Cancelled by agreement before any collection work occurred.']);
        $line=ReturnAllocation::where('role','unused_budget_refund')->firstOrFail();
        $this->assertEquals($f['seller']->id,$line->recipient_user_id);$this->assertEquals(2000,$line->amount_cents);
        Settlements::payout($line,$f['seller'],'destination',['provider'=>'Bank','holder'=>'Seller','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'UNUSED-BUDGET','proof_path'=>'test-proof.png']);
        Settlements::payout($line,$f['seller'],'accept',[]);
        $this->actingAs($f['seller'])->getJson('/return-remittances')->assertOk()->assertJsonPath('accepted_earnings_cents',0);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_funding_activation_is_not_replayed_after_receipt_and_disputes_stop_new_custody(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        try {$this->change($f,'admin','ready');$this->fail('Activated twice.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'buyer','dispute',['note'=>'The promised replacement and charges need admin review.']);
        try {$this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);$this->fail('Scheduled disputed return.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'admin','decide',['note'=>'The parties clarified the issue; the agreed financial terms stay unchanged.']);
        $this->assertSame('funded',$f['case']->fresh()->settlement->status);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        $this->assertSame('collection_scheduled',$f['case']->fresh()->settlement->status);
    }

    public function test_agreement_and_remittance_pages_render_for_each_participant_without_bank_leaks(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $this->change($f,'buyer','destination',['provider'=>'Private bank','holder'=>'Buyer','account'=>'SECRET-123456789']);
        foreach (['seller','buyer','admin','staff'] as $actor) {
            $response=$this->actingAs($f[$actor])->withSession(['active_role'=>$actor==='seller'?'seller':'buyer'])->get('/return-center/'.$f['case']->id.'/agreement');
            $response->assertOk()->assertSee('Return agreement');
            if (!in_array($actor,['buyer','admin'])) $response->assertDontSee('SECRET-123456789');
            if (getenv('RETURN_UI_PREVIEW')) file_put_contents(base_path('.tools/return-agreement-'.$actor.'.html'),$response->getContent());
        }
        $this->actingAs($f['admin'])->get('/return-remittances')->assertRedirect(route('remittances.incoming'));
        $response=$this->get('/admin/payments');$response->assertOk()->assertSee('Escrow remittances')->assertSee('Return #'.$f['case']->id);
        if (getenv('RETURN_UI_PREVIEW')) file_put_contents(base_path('.tools/unified-remittances-admin.html'),$response->getContent());
        $this->actingAs($f['seller'])->withSession(['active_role'=>'seller'])->get('/remittances/incoming')->assertRedirect(route('seller.remittances.index'));
    }

    public function test_rejected_receipt_can_be_corrected_without_reusing_verified_money(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $data=['version'=>$f['case']->fresh()->settlement->version,'purpose'=>'return_budget','amount'=>20,'reference'=>'CORRECTED-TRANSFER','proof_path'=>'test-proof.png'];
        $p=Settlements::submitPayment($f['case'],$f['seller'],$data);
        Settlements::reviewPayment($p,$f['admin'],false,'Receipt image was unreadable; check and resubmit the same transfer.');
        $corrected=Settlements::submitPayment($f['case'],$f['seller'],$data);
        $this->assertEquals($p->id,$corrected->id);
        Settlements::viewPaymentReceipt($corrected,$f['admin']);
        Settlements::reviewPayment($corrected,$f['admin'],true,'Actual incoming transfer verified in the platform bank account.');
        $this->assertDatabaseCount('return_fund_payments',1);
        $this->assertEquals(2000,Settlements::poolCents($f['case']->fresh()->settlement));
    }

    public function test_unused_recovery_is_returned_once_without_creating_refund_credit(): void
    {
        $f=$this->fixture();$this->proposal($f);
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->update(['status'=>'remitted']);
        $this->assertEquals(100,ReturnWorkflowService::fundingSummary($f['order'],100)['shortfall']);
        $this->deposit($f,'refund_recovery',100);
        $this->change($f,'admin','close_unused',['note'=>'Cancelled before activation; return the verified unused recovery.']);
        $line=ReturnAllocation::whereNotNull('fund_payment_id')->firstOrFail();
        $this->assertEquals(10000,$line->amount_cents);
        Settlements::payout($line,$f['seller'],'destination',['provider'=>'Bank','holder'=>'Seller','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'UNUSED-RECOVERY','proof_path'=>'test-proof.png']);
        $this->assertEquals(10000,\App\Models\ReturnFundPayment::first()->returned_cents);
        $this->assertEquals(0,ReturnWorkflowService::fundingSummary($f['order'],0)['available']);
        $this->change($f,'admin','close_unused',['note'=>'Rechecking a reconciled cancellation must not duplicate money.']);
        $this->assertDatabaseCount('return_allocations',1);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_unreconciled_original_payment_cannot_unlock_a_resolution(): void
    {
        $f=$this->fixture();$this->proposal($f);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'submitted','confirmed_at'=>null]);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->expectException(ValidationException::class);
        $this->change($f,'admin','ready');
    }

    public function test_exchange_stock_is_reserved_once_and_restored_when_cancelled_before_collection(): void
    {
        $f=$this->fixture();$this->proposal($f,'exchange');$this->fund($f);
        $product=$f['case']->product;
        $this->assertEquals(9,$product->fresh()->stock);
        try {$this->change($f,'admin','ready');$this->fail('Reserved the replacement twice.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->assertEquals(9,$product->fresh()->stock);
        $this->change($f,'admin','close_unused',['note'=>'Agreed exchange cancelled before any collection occurred.']);
        $this->assertEquals(10,$product->fresh()->stock);
        $this->change($f,'admin','close_unused',['note'=>'Reconciliation must never restore replacement stock twice.']);
        $this->assertEquals(10,$product->fresh()->stock);
    }

    public function test_recipient_bank_details_and_acceptance_cannot_be_changed_by_another_participant(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=ReturnAllocation::where('role','collection_rider')->firstOrFail();
        $this->actingAs($f['seller'])->postJson('/return-remittances/'.$line->id,[
            'action'=>'destination','confirm'=>1,'provider'=>'Wrong bank','holder'=>'Seller','account'=>'123456789',
        ])->assertForbidden();
        $this->actingAs($f['buyer'])->postJson('/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertForbidden();
        $this->assertSame('eligible',$line->fresh()->status);
    }

    public function test_existing_in_transit_legacy_returns_are_not_retroactively_quoted(): void
    {
        $f=$this->fixture();$f['case']->settlement()->delete();
        $f['case']->workflow()->update(['stage'=>'in_transit']);
        $this->assertNull(Settlements::ensure($f['case']->fresh()));
        $this->assertFalse($f['case']->fresh()->has_return_agreement);
        $this->assertDatabaseCount('return_settlements',0);
    }

    public function test_pickup_form_uses_philippine_time_instead_of_server_time(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $pickup=now()->addDay()->timezone('Asia/Manila')->startOfMinute();
        $this->actingAs($f['seller'])->post('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'schedule','version'=>$f['case']->fresh()->settlement->version,'collection_at'=>$pickup->format('Y-m-d\TH:i'),'confirm'=>1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($pickup->timestamp,$f['case']->fresh()->settlement->collection_at->timestamp);
    }

    public function test_legacy_seller_reject_action_cannot_close_a_case_holding_verified_return_funds(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->deposit($f,'return_budget',20);
        $this->actingAs($f['seller'])->withSession(['active_role'=>'seller'])->post(route('seller.returns.update',$f['case']->id),[
            'action'=>'reject','note'=>'Seller disputes this case after a verified incoming payment.',
        ])->assertRedirect(route('returns.agreement',$f['case']));
        $this->assertSame('requested',$f['case']->fresh()->status);
        $this->assertSame('disputed',$f['case']->fresh()->settlement->status);
        $this->assertEquals(2000,Settlements::poolCents($f['case']->fresh()->settlement));
    }

    private function multiItemFundingFixture(): array
    {
        $f = $this->fixture();
        \App\Support\EscrowEarnings::updateRates(['origin_hub_fee'=>30, 'pickup_rider_per_item'=>30, 'last_mile_rider_fee'=>50]);
        $f['order']->items()->first()->update(['quantity'=>3, 'total_price'=>300]);
        $f['order']->update(['total_amount'=>350, 'sub_total'=>300]);
        Payment::where('order_id',$f['order']->id)->update(['amount'=>350]);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['amount'=>350]);
        Payments::settleForDelivery($f['order']->fresh(), true);
        $f['case'] = $f['case']->fresh();
        return $f;
    }

    private function automaticProposal(array $f, string $payer = 'buyer', float $budget = 20, string $preference = 'auto'): void
    {
        $this->change($f,'seller','propose',['fault'=>$payer==='buyer'?'buyer':'seller', 'payer'=>$payer,
            'resolution'=>'return_refund', 'funding_preference'=>$preference, 'merchandise_amount'=>100,
            'note'=>'Reviewed the purchased item and agreed responsibility for this return.']);
        $this->change($f,'admin','quote',['collection_center_id'=>1, 'seller_center_id'=>1,
            'allocations'=>[['role'=>'collection_rider','amount'=>$budget]]]);
        $this->change($f,'seller','accept');
        $this->change($f,'buyer','accept');
        $this->change($f,'admin','decide',['note'=>'Evidence, payer and the complete financial breakdown were reviewed.']);
    }

    public function test_automatic_buyer_funding_deducts_and_reserves_once_after_approval(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('deduct_refund',$s->funding_method);
        $this->assertSame(8000,(int)$s->net_refund_cents);
        $this->assertSame(2000,$s->reserved_cents);
        $this->assertDatabaseCount('return_fund_payments',0);
        $this->change($f,'buyer','accept');$this->change($f,'admin','reserve');
        $this->assertSame(2000,$f['case']->fresh()->settlement->reserved_cents);
        $this->actingAs($f['buyer'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('budget_outstanding_cents',0)->assertJsonPath('agreement.net_refund_cents',8000);
    }

    public function test_automatic_buyer_funding_falls_back_to_deposit_without_verified_escrow(): void
    {
        $f=$this->multiItemFundingFixture();
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'submitted','confirmed_at'=>null]);
        $this->automaticProposal($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('deposit',$s->funding_method);
        $this->assertSame(10000,(int)$s->net_refund_cents);
        $this->assertSame(0,$s->reserved_cents);
        $this->expectException(ValidationException::class);
        $this->change($f,'admin','ready');
    }

    public function test_automatic_cod_deduction_requires_reconciled_original_cash(): void
    {
        $f=$this->multiItemFundingFixture();
        $f['order']->payment->update(['method'=>'cash_on_delivery']);
        Delivery::where('order_id',$f['order']->id)->update(['payment_method'=>'cash_on_delivery']);
        Payments::transactionFor($f['order'])->update(['amount'=>$f['order']->total_amount,'status'=>'pending']);
        $this->automaticProposal($f);
        $this->assertSame('deposit',$f['case']->fresh()->settlement->funding_method);
        $this->assertSame(0,$f['case']->fresh()->settlement->reserved_cents);
        Payments::transactionFor($f['order'])->update(['status'=>'successful']);
        $this->automaticProposal($f);
        $this->assertSame('deduct_refund',$f['case']->fresh()->settlement->funding_method);
        $this->assertSame(2000,$f['case']->fresh()->settlement->reserved_cents);
    }

    public function test_explicit_separate_payment_stays_available_when_refund_could_cover_fees(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'buyer',20,'deposit');
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('deposit',$s->funding_method);
        $this->assertSame(10000,(int)$s->net_refund_cents);
        $this->assertSame(0,$s->reserved_cents);
        $this->actingAs($f['buyer'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('budget_outstanding_cents',2000);
    }

    public function test_newly_reconciled_seller_funds_can_unlock_without_mutating_a_page_preview(): void
    {
        $f=$this->multiItemFundingFixture();
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'submitted','confirmed_at'=>null]);
        $this->automaticProposal($f,'seller');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'funded','confirmed_at'=>now()]);
        $this->actingAs($f['admin'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('funding_plan.prospective_reservation_cents',2000);
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->actingAs($f['admin'])->get('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertSee('Confirm funding &amp; unlock resolution',false)
            ->assertDontSee('type="submit" disabled>Confirm funding',false);
        $this->change($f,'admin','ready');
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(2000,$s->seller_reserved_cents);
        $this->assertSame(10000,$s->refund_reserved_cents);
        $this->assertNotNull($s->funded_at);
    }

    public function test_seller_escrow_reservation_preserves_buyer_refund_and_original_logistics(): void
    {
        $f=$this->multiItemFundingFixture();
        $fees=\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->whereNotIn('recipient_role',['seller','admin'])->pluck('amount','recipient_role')->all();
        $this->automaticProposal($f,'seller');
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(2000,$s->seller_reserved_cents);
        $this->assertSame(10000,(int)$s->net_refund_cents);
        $this->assertSame(0,$s->reserved_cents);
        $this->assertDatabaseCount('return_fund_payments',0);
        $this->assertSame($fees,\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->whereNotIn('recipient_role',['seller','admin'])->pluck('amount','recipient_role')->all());
        $calc=\App\Support\EscrowEarnings::calculateForSeller($f['order']->fresh(),$f['seller']->id);
        $this->assertEquals(20,$calc['return_funding']);$this->assertTrue($calc['conserved']);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->change($f,'admin','ready');
        $this->assertSame(10000,$f['case']->fresh()->settlement->refund_reserved_cents);
    }

    public function test_late_verified_seller_funds_reduce_the_deposit_before_submission(): void
    {
        $f=$this->multiItemFundingFixture();
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'submitted','confirmed_at'=>null]);
        $this->automaticProposal($f,'seller',100);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'funded','confirmed_at'=>now()]);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('funding_plan.payment_due_cents',4000);
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->deposit($f,'return_budget',40);
        $this->assertSame(6000,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertSame(10000,Settlements::poolCents($f['case']->fresh()->settlement));
    }

    public function test_seller_pays_only_the_shortfall_after_a_partial_escrow_reservation(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller',100);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(6000,$s->seller_reserved_cents);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('budget_outstanding_cents',4000);
        $this->deposit($f,'return_budget',40);
        $this->assertSame(10000,Settlements::poolCents($f['case']->fresh()->settlement));
        $this->assertSame(6000,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertDatabaseCount('return_fund_payments',1);
    }

    public function test_released_seller_money_cannot_be_reserved_again(): void
    {
        $f=$this->multiItemFundingFixture();
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','seller')->update(['status'=>'remitted']);
        $this->automaticProposal($f,'seller');
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('budget_outstanding_cents',2000);
    }

    public function test_cancelled_seller_reservation_is_released_without_an_extra_cash_refund(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller');
        $this->assertSame(2000,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->change($f,'admin','close_unused',['note'=>'Both parties cancelled before any collection or payment occurred.']);
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertDatabaseMissing('return_allocations',['role'=>'unused_budget_refund']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertEquals(0,\App\Support\EscrowEarnings::calculateForSeller($f['order']->fresh(),$f['seller']->id)['return_funding']);
    }

    public function test_revision_releases_internal_holds_and_requires_fresh_approval(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller');
        $this->change($f,'seller','propose',['fault'=>'seller','payer'=>'seller','resolution'=>'refund_only',
            'funding_preference'=>'auto','merchandise_amount'=>100,'note'=>'Seller waives physical return and the unused transport budget.']);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(0,$s->seller_reserved_cents);$this->assertSame(0,$s->reserved_cents);
        $this->assertNull($s->buyer_accepted_at);$this->assertNull($s->admin_decided_at);
    }

    public function test_full_refund_cannot_use_the_same_seller_money_twice_for_transport(): void
    {
        $f=$this->fixture();$this->automaticProposal($f,'seller');
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertJsonPath('budget_outstanding_cents',2000);
    }

    public function test_mixed_seller_funding_cancellation_returns_only_the_external_deposit(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller',100);
        $this->deposit($f,'return_budget',40);
        $this->change($f,'admin','close_unused',['note'=>'Cancelled before collection; release internal funds and return the external deposit.']);
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertSame(4000,(int)ReturnAllocation::where('role','unused_budget_refund')->firstOrFail()->amount_cents);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_funded_refund_is_protected_from_another_return_on_the_same_order(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->change($f,'admin','ready');
        $other=ReturnRequest::create(['order_id'=>$f['order']->id,'order_item_id'=>$f['case']->order_item_id,
            'buyer_id'=>$f['buyer']->id,'seller_id'=>$f['seller']->id,'product_id'=>$f['case']->product_id,
            'quantity'=>1,'reason'=>'wrong_item','status'=>'requested','refund_amount'=>100]);
        Settlements::ensure($other);$other->settlement()->update(['workflow_policy'=>'legacy']);$next=$f;$next['case']=$other;
        $this->automaticProposal($next,'seller');
        $this->assertSame(0,$other->fresh()->settlement->seller_reserved_cents);
        $this->assertSame(10000,$f['case']->fresh()->settlement->refund_reserved_cents);
        $this->assertGreaterThan(0,ReturnWorkflowService::fundingSummary($f['order'],100,$other->settlement->id)['shortfall']);
    }

    public function test_seller_funded_refund_releases_its_hold_only_when_actual_transfer_is_recorded(): void
    {
        $f=$this->multiItemFundingFixture();$this->automaticProposal($f,'seller');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->change($f,'admin','ready');$this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'ACTUAL-SELLER-FUNDED-REFUND','proof_path'=>'test-proof.png']);
        $this->assertSame(0,$f['case']->fresh()->settlement->refund_reserved_cents);
        $this->assertSame(2000,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $calc=\App\Support\EscrowEarnings::calculateForSeller($f['order']->fresh(),$f['seller']->id);
        $this->assertEquals(20,$calc['admin_commission']);$this->assertEquals(40,$calc['seller_earning']);
        $this->assertTrue($calc['conserved']);
    }

    public function test_other_sellers_escrow_is_not_used_for_an_automatic_refund_deduction(): void
    {
        $f=$this->multiItemFundingFixture();
        $other=User::create(['name'=>'Other store','email'=>uniqid().'@test.local','role'=>'seller','password'=>'test','status'=>'active','account_status'=>'active']);
        \App\Models\Seller::create(['user_id'=>$other->id,'store_name'=>'Other store','status'=>'active','approval_status'=>'approved']);
        $f['order']->items()->create(['seller_id'=>$other->id,'product_name'=>'Other purchase','quantity'=>1,'price'=>1000,'unit_price'=>1000,'total_price'=>1000]);
        Delivery::create(['order_id'=>$f['order']->id,'seller_id'=>$other->id,'status'=>'delivered','payment_method'=>'gcash','center_id'=>1]);
        $f['order']->update(['total_amount'=>1400,'sub_total'=>1300,'shipping_fee'=>100]);
        Payment::where('order_id',$f['order']->id)->update(['amount'=>1400]);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['amount'=>1400]);
        Payments::settleForDelivery($f['order']->fresh(),true);
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('seller_id',$f['seller']->id)
            ->whereIn('recipient_role',['seller','admin'])->update(['status'=>'remitted']);
        $this->assertGreaterThan(100,ReturnWorkflowService::fundingSummary($f['order']->fresh(),100)['available']);
        $this->automaticProposal($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('deposit',$s->funding_method);$this->assertSame(0,$s->reserved_cents);
        $this->assertEquals(100,ReturnWorkflowService::fundingSummary($f['order']->fresh(),100,$s->id)['shortfall']);
    }

    private function buyerFaultFixture(bool $crossHub=false): array
    {
        $f=$this->directFixture();
        $f['order']->items()->first()->update(['quantity'=>1,'unit_price'=>4599,'total_price'=>4599]);
        $f['order']->update(['total_amount'=>4649,'sub_total'=>4599]);
        Payment::where('order_id',$f['order']->id)->update(['amount'=>4649]);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['amount'=>4649]);
        $f['case']->update(['refund_amount'=>4599]);
        $f['case']->settlement()->update(['maximum_refund_cents'=>459900,'merchandise_cents'=>459900,'net_refund_cents'=>459900,'policy_version'=>2]);
        if($crossHub){
            if(!Schema::hasColumn('deliveries','origin_center_id'))Schema::table('deliveries',fn(Blueprint $t)=>$t->unsignedBigInteger('origin_center_id')->nullable());
            Delivery::where('order_id',$f['order']->id)->update(['origin_center_id'=>2]);
        }
        Payments::settleForDelivery($f['order']->fresh(),true);
        if($crossHub){
            // A completed original transfer's recorded ledger, as in order #88.
            $rows=\App\Models\EscrowRemittance::where('order_id',$f['order']->id);
            $rows->where('recipient_role','inter_hub_driver')->update(['amount'=>20]);
            \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','destination_hub')->update(['amount'=>30]);
            \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','seller')->update(['amount'=>4029.10]);
        }
        $f['case']=$f['case']->fresh();
        return $f;
    }

    private function buyerFaultProposal(array $f,array $extra=[]): void
    {
        $this->directProposal($f,'buyer',$extra+['merchandise_amount'=>4599]);
    }

    private function authorizeBuyerFault(array $f): \App\Models\ReturnFundPayment
    {
        $this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $s=$f['case']->fresh()->settlement;
        return Settlements::submitPayment($f['case'],$f['seller'],['version'=>$s->version,'purpose'=>'return_budget',
            'amount'=>$s->transport_cents/100,'reference'=>uniqid('SELLER-DELIVERY-'),'proof_path'=>'test-proof.png']);
    }

    public function test_buyer_fault_recovers_original_seller_costs_without_charging_original_shipping_twice(): void
    {
        $f=$this->buyerFaultFixture(true);$this->buyerFaultProposal($f,['seller_center_id'=>2]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('buyer_fault_direct',$s->workflow_policy);
        $this->assertSame('seller',$s->payer);$this->assertSame(56990,(int)$s->compensation_cents);
        $this->assertSame(5000,$s->cost_snapshot['buyer_shipping_credit_cents']);
        $this->assertSame(61990,$s->cost_snapshot['original_allocated_costs_cents']);
        $this->assertSame(16000,(int)$s->transport_cents);$this->assertSame(386910,(int)$s->net_refund_cents);
        $this->assertSame(16000,$s->budgetCents());
        $this->assertEqualsCanonicalizing(ReturnAllocation::DELIVERY_ROLES,$s->allocations()->pluck('role')->all());
        $this->assertStringContainsString('directly to the buyer',$s->deduction_note);
    }

    public function test_new_buyer_fault_collects_without_admin_customer_approval_but_cannot_pay_unverified_escrow(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$p=$this->authorizeBuyerFault($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertNotNull($s->collection_authorized_at);$this->assertNull($s->funded_at);
        $this->assertSame(0,Settlements::poolCents($s));$this->assertSame(0,$s->refund_reserved_cents);
        $this->assertSame('submitted',$p->status);$this->physical($f);
        $line=$s->allocations()->where('role','collection_rider')->firstOrFail();
        Settlements::payout($line,$f['riderUser'],'destination',['provider'=>'Bank','holder'=>'Rider','account'=>'12345678']);
        $this->actingAs($f['admin'])->get('/admin/payments')->assertOk()
            ->assertSee('Incoming escrow receipts')->assertDontSee('name="action" value="release"',false);
        try{Settlements::payout($line,$f['admin'],'remit',['reference'=>'UNFUNDED-PAYOUT','proof_path'=>'test-proof.png']);$this->fail('Unverified cash paid out.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        Settlements::viewPaymentReceipt($p,$f['admin']);Settlements::reviewPayment($p,$f['admin'],true,'Checked actual bank funds for completed delivery work.');
        $this->assertSame('refund_pending',$f['case']->fresh()->workflow->stage);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'FUNDED-PAYOUT','proof_path'=>'test-proof.png']);
        $this->assertSame('remitted',$line->fresh()->status);
        Settlements::payout($line,$f['riderUser'],'accept',[]);$this->assertSame('accepted',$line->fresh()->status);
    }

    public function test_buyer_fault_direct_refund_requires_seller_receipt_and_buyer_confirmation_and_preserves_original_earnings(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$p=$this->authorizeBuyerFault($f);
        $original=\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->get()->pluck('amount','recipient_role')->all();
        try{ReturnWorkflowService::change($f['case'],$f['seller'],'refund',['reference'=>'TOO-EARLY','proof_path'=>'test-proof.png']);$this->fail('Refunded before physical return.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['seller'],'refund',['reference'=>'SELLER-NET-REFUND','proof_path'=>'test-proof.png']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('seller_direct',$f['case']->fresh()->workflow->refund_source);
        $this->assertSame($f['seller']->id,(int)$f['case']->fresh()->workflow->refund_sender_user_id);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        $net=$f['case']->fresh()->settlement->net_refund_cents/100;
        $this->assertEquals($net,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('completed',$f['case']->fresh()->settlement->status);
        $this->assertSame(0.0,$f['case']->fresh()->merchandiseRefundAmount());
        $this->assertEquals($original,\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->get()->pluck('amount','recipient_role')->all());
        $funding=ReturnWorkflowService::fundingSummary($f['order']->fresh(),0);
        $this->assertEquals($net,$funding['seller_direct_refunds']);$this->assertEquals(0,$funding['shortfall']);
        // Delivery deposits can be verified after the customer's direct refund is complete.
        Settlements::viewPaymentReceipt($p,$f['admin']);Settlements::reviewPayment($p,$f['admin'],true,'Incoming return delivery fee is confirmed in bank.');
        $this->assertNotNull($f['case']->fresh()->settlement->funded_at);
    }

    public function test_buyer_fault_buyer_cannot_pay_fee_or_fake_refund_and_admin_cannot_handle_customer_refund(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$this->authorizeBuyerFault($f);$this->physical($f);
        $path='/return-center/'.$f['case']->id;
        foreach(['buyer','admin'] as $role)$this->actingAs($f[$role])->postJson($path,['action'=>'refund','reference'=>'FAKE-TRANSFER','confirm_transfer'=>true])->assertForbidden();
        $this->actingAs($f['admin'])->postJson($path.'/agreement',['action'=>'ready','version'=>$f['case']->fresh()->settlement->version,'confirm'=>true])->assertUnprocessable();
        $this->actingAs($f['buyer'])->postJson($path.'/agreement',$this->receiptUpload($f))->assertForbidden();
    }

    public function test_buyer_fault_account_is_shared_only_with_accepted_seller_and_customer_ui_has_no_admin_forms(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['seller'])->getJson($path)->assertOk()->assertJsonPath('refund_destination',null);
        $this->change($f,'buyer','accept');
        $this->actingAs($f['seller'])->getJson($path)->assertOk()->assertJsonPath('refund_destination.account','123456789');
        $this->actingAs($f['admin'])->getJson($path)->assertOk()->assertJsonPath('refund_destination',null);
        $this->actingAs($f['buyer'])->get($path)->assertOk()->assertSee('You do not pay a separate return fee')->assertDontSee('Submit delivery deposit receipt');
        $this->actingAs($f['admin'])->get($path)->assertOk()->assertDontSee('Record transfer verification')->assertDontSee('Record direct refund sent')->assertSee('Manage Payments');
        $this->actingAs($f['admin'])->get(route('admin.return-delivery-escrow'))->assertRedirect('/admin/payments#escrow-remittances');
        $this->actingAs($f['buyer'])->get(route('admin.return-delivery-escrow'))->assertForbidden();
    }

    public function test_buyer_fault_new_rates_do_not_change_recorded_original_compensation_and_manual_compensation_is_rejected(): void
    {
        $f=$this->buyerFaultFixture();
        \App\Support\EscrowEarnings::updateRates(['origin_hub_fee'=>7,'pickup_rider_per_item'=>8,'last_mile_rider_fee'=>9]);
        $this->buyerFaultProposal($f);$s=$f['case']->fresh()->settlement;
        $this->assertSame(51990,(int)$s->compensation_cents);$this->assertSame(2400,(int)$s->transport_cents);
        $this->expectException(ValidationException::class);$this->buyerFaultProposal($f,['compensation_amount'=>999]);
    }

    public function test_buyer_fault_seller_deposits_only_delivery_and_cannot_send_item_refund_into_return_escrow(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$this->change($f,'buyer','accept');
        $s=$f['case']->fresh()->settlement;
        $this->expectException(ValidationException::class);
        Settlements::submitPayment($f['case'],$f['seller'],['version'=>$s->version,'purpose'=>'refund_recovery','amount'=>4599,'reference'=>'WRONG-POOL','proof_path'=>'test-proof.png']);
    }

    public function test_buyer_fault_rejected_delivery_receipt_pauses_custody_until_seller_corrects_it(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$p=$this->authorizeBuyerFault($f);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        Settlements::reviewPayment($p,$f['admin'],false,'The reference did not match an actual incoming bank payment.');
        $this->assertFalse($f['case']->fresh()->settlement->collectionReady());
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);$this->fail('Rejected receipt allowed parcel collection.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $s=$f['case']->fresh()->settlement;
        Settlements::submitPayment($f['case'],$f['seller'],['version'=>$s->version,'purpose'=>'return_budget','amount'=>110,'reference'=>$p->reference,'proof_path'=>'test-proof.png']);
        $this->assertTrue($f['case']->fresh()->settlement->collectionReady());
        $this->assertDatabaseCount('return_fund_payments',1);
    }

    public function test_buyer_fault_disagreement_is_resolved_by_reporting_party_without_admin_customer_approval(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);
        $this->change($f,'buyer','comment',['note'=>'Please explain the original seller cost breakdown first.']);
        $this->assertSame('revision_requested',$f['case']->fresh()->settlement->status);
        $this->buyerFaultProposal($f);$this->authorizeBuyerFault($f);
        $this->change($f,'buyer','dispute',['note'=>'Please confirm the agreed collection time with me first.']);
        $this->assertFalse($f['case']->fresh()->settlement->collectionReady());
        $path='/return-center/'.$f['case']->id.'/agreement';$version=$f['case']->fresh()->settlement->version;
        $this->actingAs($f['admin'])->postJson($path,['action'=>'resolve_issue','version'=>$version,'note'=>'Admin cannot replace the customers agreement.','confirm'=>1])->assertForbidden();
        $this->change($f,'buyer','resolve_issue',['note'=>'The seller and I confirmed the collection time together.']);
        $this->assertTrue($f['case']->fresh()->settlement->collectionReady());
    }

    public function test_seller_fault_return_refunds_full_paid_amount_and_funds_only_configured_delivery(): void
    {
        $f=$this->buyerFaultFixture(true);
        $this->directProposal($f,'seller',['merchandise_amount'=>4599,'shipping_amount'=>0,'seller_center_id'=>2]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('seller_fault_direct',$s->workflow_policy);
        $this->assertSame(459900,(int)$s->merchandise_cents);$this->assertSame(5000,(int)$s->shipping_cents);
        $this->assertSame(464900,(int)$s->net_refund_cents);$this->assertSame(0,(int)$s->compensation_cents);
        $this->assertSame(16000,$s->budgetCents());$this->assertSame('seller',$s->payer);
        $this->assertNull($s->cost_snapshot);$this->assertTrue($s->sellerDirectRefund());
        $this->assertEqualsCanonicalizing(ReturnAllocation::DELIVERY_ROLES,$s->allocations()->pluck('role')->all());
        // Commission settings still control the route; client-supplied delivery fees have no effect.
        \App\Support\EscrowEarnings::updateRates(['inter_hub_driver_fee'=>25]);
        $this->directProposal($f,'seller',['merchandise_amount'=>4599,'seller_center_id'=>2,'transport_amount'=>1]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(16500,$s->budgetCents());$this->assertSame(464900,(int)$s->net_refund_cents);
    }

    public function test_seller_fault_return_uses_mutual_agreement_and_seller_payment_without_admin_customer_approval(): void
    {
        $f=$this->buyerFaultFixture();$this->directProposal($f,'seller',['merchandise_amount'=>4599]);
        $this->change($f,'buyer','comment',['note'=>'Please confirm that my refund includes original paid shipping.']);
        $this->assertSame('revision_requested',$f['case']->fresh()->settlement->status);
        $this->directProposal($f,'seller',['merchandise_amount'=>4599]);
        $p=$this->authorizeBuyerFault($f);$s=$f['case']->fresh()->settlement;
        $this->assertTrue($s->agreed());$this->assertTrue($s->collectionReady());$this->assertNull($s->admin_decided_at);
        $this->assertNull($s->funded_at);$this->assertSame(0,Settlements::poolCents($s));$this->assertSame(0,$s->refund_reserved_cents);
        $this->assertSame('submitted',$p->status);
        $path='/return-center/'.$f['case']->id;
        foreach(['ready','decide'] as $action)$this->actingAs($f['admin'])->postJson($path.'/agreement',[
            'action'=>$action,'version'=>$s->version,'note'=>'No admin approval is needed for this customer agreement.','confirm'=>true])->assertUnprocessable();
        $this->actingAs($f['buyer'])->postJson($path.'/agreement',$this->receiptUpload($f))->assertForbidden();
        try{Settlements::submitPayment($f['case'],$f['seller'],['version'=>$s->version,'purpose'=>'refund_recovery','amount'=>4649,'reference'=>'WRONG-POOL','proof_path'=>'test-proof.png']);$this->fail('Customer refund was deposited into escrow.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'buyer','dispute',['note'=>'Please arrange a collection time with me before pickup.']);
        $this->assertFalse($f['case']->fresh()->settlement->collectionReady());
        $this->change($f,'buyer','resolve_issue',['note'=>'The seller and I agreed to the collection time together.']);
        $this->assertTrue($f['case']->fresh()->settlement->collectionReady());
    }

    public function test_seller_fault_direct_full_refund_requires_physical_receipt_and_buyer_confirmation_preserving_earned_cuts(): void
    {
        $f=$this->buyerFaultFixture();$this->directProposal($f,'seller',['merchandise_amount'=>4599]);
        $p=$this->authorizeBuyerFault($f);
        $original=\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->get()->pluck('amount','recipient_role')->all();
        try{ReturnWorkflowService::change($f['case'],$f['seller'],'refund',['reference'=>'TOO-EARLY','proof_path'=>'test-proof.png']);$this->fail('Refunded before physical receipt.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->physical($f);$path='/return-center/'.$f['case']->id;
        foreach(['buyer','admin'] as $actor)$this->actingAs($f[$actor])->postJson($path,['action'=>'refund','reference'=>'NOT-SENDER','confirm_transfer'=>true])->assertForbidden();
        ReturnWorkflowService::change($f['case'],$f['seller'],'refund',['reference'=>'FULL-SELLER-REFUND','proof_path'=>'test-proof.png']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('seller_direct',$f['case']->fresh()->workflow->refund_source);
        $this->actingAs($f['buyer'])->get(route('returns.proof',$f['case']))->assertOk();
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        $this->assertEquals(4649,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('completed',$f['case']->fresh()->settlement->status);
        $this->assertEquals($original,\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->get()->pluck('amount','recipient_role')->all());
        $this->assertEquals(4649,ReturnWorkflowService::fundingSummary($f['order']->fresh(),0)['seller_direct_refunds']);
        // Admin verifies only the incoming delivery funds, even after customer refund completion.
        Settlements::viewPaymentReceipt($p,$f['admin']);Settlements::reviewPayment($p,$f['admin'],true,'Confirmed incoming return delivery money in the platform bank.');
        $line=$f['case']->fresh()->settlement->allocations()->where('role','collection_rider')->firstOrFail();
        Settlements::payout($line,$f['riderUser'],'destination',['provider'=>'Bank','holder'=>'Rider','account'=>'12345678']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'RETURN-DELIVERY-ONLY','proof_path'=>'test-proof.png']);
        $this->assertSame('remitted',$line->fresh()->status);
        Settlements::payout($line,$f['riderUser'],'accept',[]);$this->assertSame('accepted',$line->fresh()->status);
    }

    public function test_seller_fault_ui_shares_refund_account_only_with_agreed_seller_and_has_no_customer_admin_steps(): void
    {
        $f=$this->buyerFaultFixture();$this->directProposal($f,'seller',['merchandise_amount'=>4599]);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['seller'])->getJson($path)->assertJsonPath('refund_destination',null);
        $this->change($f,'buyer','accept');
        $this->actingAs($f['seller'])->getJson($path)->assertJsonPath('refund_destination.account','123456789');
        $this->actingAs($f['admin'])->getJson($path)->assertJsonPath('refund_destination',null)
            ->assertJsonPath('funding_plan.seller_direct_refund',true)->assertJsonPath('funding.shortfall',0);
        $this->actingAs($f['buyer'])->get($path)->assertOk()->assertSee('no deductions')->assertDontSee('Less original seller-funded costs')->assertDontSee('Less new return delivery')->assertDontSee('Submit refund-funding receipt');
        $this->actingAs($f['admin'])->get($path)->assertOk()->assertDontSee('Record direct refund sent')->assertSee('Manage Payments');
        if(getenv('SELLER_FAULT_UI_PREVIEW'))foreach(['buyer','seller','admin'] as $role)file_put_contents(base_path('.tools/seller-fault-'.$role.'.html'),$this->actingAs($f[$role])->get($path)->getContent());
    }

    public function test_seller_fault_shipping_is_per_seller_and_returned_quantity_without_refunding_another_store_shipping(): void
    {
        $f=$this->buyerFaultFixture();
        $item=$f['order']->items()->first();$item->update(['quantity'=>3,'unit_price'=>1000,'total_price'=>3000]);
        $other=User::create(['name'=>'Other seller','email'=>'other-seller@test.local','password'=>'test','role'=>'seller']);
        $copy=$item->replicate();$copy->seller_id=$other->id;$copy->quantity=1;$copy->save();
        $f['order']->update(['shipping_fee'=>100]);$f['case']->settlement()->update(['maximum_refund_cents'=>100000]);
        $this->directProposal($f,'seller',['merchandise_amount'=>1000,'shipping_amount'=>100]);
        $this->change($f,'buyer','accept');$first=$f['case']->fresh()->settlement;
        $this->assertSame(1667,(int)$first->shipping_cents);$this->assertSame(101667,(int)$first->net_refund_cents);
        $second=$f['case']->replicate();$second->quantity=2;$second->refund_amount=2000;$second->save();
        Settlements::ensure($second);$g=$f;$g['case']=$second;
        $this->directProposal($g,'seller',['merchandise_amount'=>2000]);$this->change($g,'buyer','accept');
        $this->assertSame(3333,(int)$second->fresh()->settlement->shipping_cents);
        $this->assertSame(5000,(int)$first->shipping_cents+(int)$second->fresh()->settlement->shipping_cents);
    }

    public function test_seller_fault_never_reimburses_free_shipping_or_accepts_manual_cost_deductions(): void
    {
        $f=$this->buyerFaultFixture();$f['order']->update(['shipping_fee'=>0]);
        $this->directProposal($f,'seller',['merchandise_amount'=>4599,'shipping_amount'=>50]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(0,(int)$s->shipping_cents);$this->assertSame(459900,(int)$s->net_refund_cents);
        $this->expectException(ValidationException::class);
        $this->directProposal($f,'seller',['merchandise_amount'=>4599,'compensation_amount'=>100]);
    }

    public function test_partial_buyer_fault_costs_share_shipping_credit_and_cannot_recover_more_than_original_costs(): void
    {
        $f=$this->buyerFaultFixture();
        $item=$f['order']->items()->first();$item->update(['quantity'=>3,'unit_price'=>1000,'total_price'=>3000]);
        $f['order']->update(['sub_total'=>3000,'total_amount'=>3050]);Payment::where('order_id',$f['order']->id)->update(['amount'=>3050]);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['amount'=>3050]);Payments::settleForDelivery($f['order']->fresh(),true);
        $f['case']->settlement()->update(['maximum_refund_cents'=>100000]);$this->buyerFaultProposal($f,['merchandise_amount'=>1000]);
        $this->change($f,'buyer','accept');$first=$f['case']->fresh()->settlement;
        $this->assertSame(14000,(int)$first->compensation_cents);$this->assertSame(1667,$first->cost_snapshot['buyer_shipping_credit_cents']);
        $this->assertSame(75000,(int)$first->net_refund_cents);
        $second=$f['case']->replicate();$second->quantity=2;$second->refund_amount=2000;$second->save();
        $s=Settlements::ensure($second);$s->workflow_policy='buyer_fault_direct';$s->save();
        $cost=\App\Services\BuyerFaultReturnService::originalCosts($second,$s);
        $this->assertSame(3333,$cost['buyer_shipping_credit_cents']);$this->assertSame(28000,$cost['seller_funded_costs_cents']);
        $this->assertSame(42000,$cost['seller_funded_costs_cents']+$first->compensation_cents);
    }

    public function test_buyer_fault_cost_snapshot_conflict_requires_a_new_proposal_instead_of_silent_deduction_change(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','admin')->update(['amount'=>400]);
        $this->expectException(ValidationException::class);$this->change($f,'buyer','accept');
    }

    public function test_buyer_fault_reverse_transfer_uses_collection_company_driver_before_new_receiving_hub(): void
    {
        $f=$this->buyerFaultFixture(true);
        DB::table('logistics_centers')->where('id',2)->update(['logistics_company_id'=>2]);
        Schema::create('logistics_companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('status');$t->softDeletes();$t->timestamps();});
        DB::table('logistics_companies')->insert([['id'=>1,'name'=>'Buyer-side company','status'=>'active'],['id'=>2,'name'=>'Seller-side company','status'=>'active']]);
        Schema::create('hub_transport_drivers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->unsignedBigInteger('logistics_company_id');$t->string('name');$t->string('status');$t->boolean('is_online')->default(false);$t->softDeletes();$t->timestamps();});
        $right=DB::table('hub_transport_drivers')->insertGetId(['logistics_company_id'=>1,'name'=>'Collection-company driver','status'=>'active','is_online'=>true]);
        $wrong=DB::table('hub_transport_drivers')->insertGetId(['logistics_company_id'=>2,'name'=>'Original origin driver','status'=>'active','is_online'=>true]);
        $this->buyerFaultProposal($f,['seller_center_id'=>2]);$this->authorizeBuyerFault($f);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$wrong,'reference'=>'WRONG-COMPANY']);$this->fail('Wrong company assigned.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        DB::table('hub_transport_drivers')->where('id',$right)->update(['is_online'=>false]);
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$right,'reference'=>'OFFLINE-DRIVER']);$this->fail('Offline driver assigned.');}
        catch(ValidationException $e){$this->assertStringContainsString('offline',$e->errors()['return'][0]);}
        $this->assertSame('at_hub',$f['case']->fresh()->workflow->stage);
        DB::table('hub_transport_drivers')->where('id',$right)->update(['is_online'=>true]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$right,'reference'=>'NEW-REVERSE-LEG']);
        $receiver=User::create(['name'=>'Seller-side staff','email'=>uniqid().'@test.local','password'=>'test','role'=>'staff','center_id'=>2,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        ReturnWorkflowService::change($f['case'],$receiver,'receive_transfer');
        $this->assertSame(2,(int)$f['case']->fresh()->workflow->center_id);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame($right,(int)$s->allocations()->where('role','inter_hub_driver')->value('driver_id'));
        $this->assertSame('eligible',$s->allocations()->where('role','inter_hub_driver')->value('status'));
        $this->assertSame(0,Settlements::poolCents($s));
    }

    public function test_buyer_fault_mobile_api_confirms_seller_refund_and_private_receipt_without_admin(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);$this->authorizeBuyerFault($f);$this->physical($f);
        $proof=\Illuminate\Http\UploadedFile::fake()->createWithContent('refund.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='));
        $this->actingAs($f['seller'])->postJson('/return-center/'.$f['case']->id,['action'=>'refund','reference'=>'SELLER-BANK-TRANSFER','proof'=>$proof,'confirm_transfer'=>true])->assertOk();
        \Laravel\Sanctum\Sanctum::actingAs($f['buyer']);
        $this->get('/api/returns/'.$f['case']->id.'/proof')->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->postJson('/api/returns/'.$f['case']->id.'/confirm')->assertOk();
        $this->getJson('/api/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertJsonPath('agreement.status','completed')->assertJsonPath('progress.refund_source','seller_direct');
        $this->assertSame('refunded',$f['case']->fresh()->status);
    }

    public function test_buyer_fault_cannot_make_unreconciled_original_cod_appear_funded_through_an_external_refund(): void
    {
        $f=$this->buyerFaultFixture();
        Payment::where('order_id',$f['order']->id)->update(['method'=>'cash_on_delivery','status'=>'pending']);
        Payments::transactionFor($f['order']->fresh())->update(['status'=>'pending']);
        try{$this->buyerFaultProposal($f);$this->fail('Unreconciled COD used as original paid earnings.');}
        catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->assertSame('draft',$f['case']->fresh()->settlement->status);
        $this->assertEquals(0,ReturnWorkflowService::fundingSummary($f['order']->fresh(),0)['funded']);
    }

    public function test_buyer_fault_acceptance_allows_mysql_json_key_normalization_without_repricing(): void
    {
        $f=$this->buyerFaultFixture();$this->buyerFaultProposal($f);
        $s=$f['case']->fresh()->settlement;$snapshot=$s->cost_snapshot;
        $snapshot['shares_cents']=array_reverse($snapshot['shares_cents'],true);
        $snapshot['original_cents']=array_reverse($snapshot['original_cents'],true);
        $s->update(['cost_snapshot'=>array_reverse($snapshot,true)]);
        $this->change($f,'buyer','accept');
        $this->assertNotNull($f['case']->fresh()->settlement->buyer_accepted_at);
        $this->assertSame(396910,(int)$f['case']->fresh()->settlement->net_refund_cents);
    }

    public function test_return_last_mile_assignment_blocks_offline_rider_without_advancing_custody_or_payee(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        $f['rider']->update(['is_online'=>false]);
        $this->actingAs($f['staff'])->postJson('/return-center/'.$f['case']->id,['action'=>'dispatch_seller','reference'=>'RETURN-LAST-MILE','rider_id'=>$f['rider']->id])
            ->assertUnprocessable()->assertJsonValidationErrors('return');
        $this->assertSame('at_hub',$f['case']->fresh()->workflow->stage);
        $this->assertNull($f['case']->fresh()->settlement->allocations()->where('role','seller_delivery_rider')->value('recipient_user_id'));
        $f['rider']->update(['is_online'=>true]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'dispatch_seller',['reference'=>'RETURN-LAST-MILE','rider_id'=>$f['rider']->id]);
        $this->assertSame('sent_to_seller',$f['case']->fresh()->workflow->stage);
        $f['rider']->update(['is_online'=>false]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'deliver_seller',['proof_path'=>'test-proof.png']);
        $this->assertSame('seller_arrival',$f['case']->fresh()->workflow->stage);
    }

    public function test_replacement_dispatch_requires_an_online_rider_and_preserves_the_undispatched_agreement(): void
    {
        $f=$this->fixture();$this->proposal($f,'exchange');$this->fund($f);$this->physical($f);
        $f['rider']->update(['is_online'=>false]);
        try{$this->change($f,'staff','replacement_dispatch',['reference'=>'ONLINE-REPLACEMENT','proof_path'=>'test-proof.png','rider_id'=>$f['rider']->id]);$this->fail('Offline replacement rider assigned.');}
        catch(ValidationException $e){$this->assertStringContainsString('offline',$e->errors()['return'][0]);}
        $this->assertNull($f['case']->fresh()->settlement->replacement_reference);
        $this->assertNull($f['case']->fresh()->settlement->allocations()->where('role','replacement_delivery_rider')->value('rider_id'));
        $f['rider']->update(['is_online'=>true]);
        $this->change($f,'staff','replacement_dispatch',['reference'=>'ONLINE-REPLACEMENT','proof_path'=>'test-proof.png','rider_id'=>$f['rider']->id]);
        $this->assertSame('replacement_dispatched',$f['case']->fresh()->settlement->status);
    }

    public function test_zero_priced_return_rider_services_cannot_bypass_online_assignment_checks(): void
    {
        $f=$this->directFixture();
        \App\Support\EscrowEarnings::updateRates(['pickup_rider_per_item'=>0,'last_mile_rider_fee'=>0,'origin_hub_fee'=>20]);
        $this->directProposal($f,'seller');$this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);$this->directPayment($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(0,$s->allocations()->whereIn('role',['collection_rider','seller_delivery_rider'])->count());
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        $f['rider']->update(['is_online'=>false]);
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);$this->fail('Zero-fee offline pickup assigned.');}
        catch(ValidationException $e){$this->assertStringContainsString('offline',$e->errors()['return'][0]);}
        $f['rider']->update(['is_online'=>true]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');$f['rider']->update(['is_online'=>false]);
        $this->expectException(ValidationException::class);
        ReturnWorkflowService::change($f['case'],$f['staff'],'dispatch_seller',['reference'=>'ZERO-FEE-RETURN','rider_id'=>$f['rider']->id]);
    }

    public function test_return_center_renders_the_online_only_selector_for_logistics(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $f['rider']->update(['is_online'=>false]);
        $this->actingAs($f['staff'])->get('/return-center')->assertOk()->assertSee('No online riders available')
            ->assertSee('Ask a rider to go online')->assertDontSee('Return rider (#'.$f['rider']->id.')');
        $f['rider']->update(['is_online'=>true]);
        $this->actingAs($f['staff'])->get('/return-center')->assertOk()->assertSee('Choose an online rider')
            ->assertSee('Return rider (#'.$f['rider']->id.')');
    }

    private function returnTransportFixture(bool $zeroDriverFee=false): array
    {
        $f=$this->buyerFaultFixture(true);
        Schema::create('logistics_companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('status');$t->softDeletes();$t->timestamps();});
        foreach([1,2,3] as $id)DB::table('logistics_companies')->insert(['id'=>$id,'name'=>'Return company '.$id,'status'=>'active']);
        Schema::create('hub_transport_drivers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->unsignedBigInteger('logistics_company_id');$t->string('name');$t->string('status');$t->boolean('is_online')->default(false);$t->softDeletes();$t->timestamps();});
        $f['driver']=\App\Models\HubTransportDriver::create(['name'=>'Return transport driver','logistics_company_id'=>1,'status'=>'available','is_online'=>true]);
        if($zeroDriverFee)\App\Support\EscrowEarnings::updateRates(['inter_hub_driver_fee'=>0]);
        $this->buyerFaultProposal($f,['seller_center_id'=>2]);$this->authorizeBuyerFault($f);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        return $f;
    }

    public function test_pickup_assignment_is_returned_to_the_exact_rider_and_next_step_is_not_a_dropdown(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        $this->actingAs($f['staff'])->get('/return-center')->assertOk()->assertSee('Reassign collection rider')->assertDontSee('<select name="action"',false);
        \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($f['riderUser'],['rider']);
        $this->getJson('/api/logistics/rider/returns')->assertOk()->assertJsonPath('returns.0.id',$f['case']->id)->assertJsonPath('returns.0.stage','pickup_assigned');
        $other=User::create(['name'=>'Other pickup rider','email'=>'other-return@test.local','password'=>'test','role'=>'rider','account_status'=>'active','status'=>'active']);
        Rider::create(['user_id'=>$other->id,'name'=>'Other pickup rider','center_id'=>1,'status'=>'available','is_online'=>true,'is_verified'=>true,'approved_at'=>now()]);
        \Illuminate\Support\Facades\Auth::forgetGuards();\Laravel\Sanctum\Sanctum::actingAs($other,['rider']);$this->getJson('/api/logistics/rider/returns')->assertOk()->assertJsonCount(0,'returns');
    }

    public function test_suspended_original_hub_can_be_replaced_without_changing_fees_or_paying_the_old_hub(): void
    {
        $f=$this->returnTransportFixture();$s=$f['case']->fresh()->settlement;$net=$s->net_refund_cents;$budget=$s->transport_cents;
        DB::table('logistics_centers')->insert(['id'=>3,'name'=>'Alternative receiving hub','is_active'=>true,'logistics_company_id'=>3]);
        DB::table('logistics_centers')->where('id',2)->update(['is_active'=>false]);
        $this->actingAs($f['staff'])->get('/return-center')->assertOk()->assertSee('Alternative receiving hub')->assertSee('unavailable for this route');
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>3,'driver_id'=>$f['driver']->id,'reference'=>'ALTERNATIVE-RETURN-ROUTE']);
        $s=$s->fresh();$this->assertSame(3,(int)$s->seller_center_id);$this->assertEquals($net,$s->net_refund_cents);$this->assertEquals($budget,$s->transport_cents);
        $this->assertSame(3,(int)$s->allocations()->where('role','seller_hub')->value('center_id'));
        $this->assertSame(3,(int)$s->allocations()->where('role','seller_delivery_rider')->value('center_id'));
        $this->assertSame($f['driver']->id,(int)$f['case']->fresh()->workflow->driver_id);
        $receiver=User::create(['name'=>'Alternative hub staff','email'=>'alternative-hub@test.local','password'=>'test','role'=>'staff','center_id'=>3,'account_status'=>'active','status'=>'active']);
        ReturnWorkflowService::change($f['case'],$receiver,'receive_transfer');
        $this->assertSame(3,(int)$f['case']->fresh()->workflow->center_id);
    }

    public function test_original_seller_hub_is_recommended_and_suspended_companies_are_not_choices(): void
    {
        $f=$this->returnTransportFixture();
        $html=view('shared.returns.partials.destination-select',['returnRequest'=>$f['case'],'excludeCenter'=>1,'selectedCenter'=>2])->render();
        $this->assertStringContainsString('Recommended: original seller hub',$html);
        $this->assertMatchesRegularExpression('/value="2"\s+selected/',$html);
        DB::table('logistics_companies')->where('id',1)->update(['status'=>'suspended']);
        $this->assertFalse(\App\Services\ReturnRouting::operational(\App\Models\LogisticsCenter::find(1)));
        DB::table('logistics_companies')->where('id',2)->update(['status'=>'suspended']);
        $this->assertFalse(\App\Services\ReturnRouting::options()->contains('id',2));
    }

    public function test_inter_hub_return_job_has_its_own_driver_api_and_arrival_never_fakes_hub_receipt(): void
    {
        $f=$this->returnTransportFixture();
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'driver_id'=>$f['driver']->id,'reference'=>'RETURN-DRIVER-JOB']);
        \Laravel\Sanctum\Sanctum::actingAs($f['driver']);
        $path='/api/logistics/courier/returns/'.$f['case']->id;
        $this->getJson('/api/logistics/courier/returns')->assertOk()->assertJsonPath('returns.0.id',$f['case']->id)->assertJsonPath('returns.0.destination_hub','Other hub');
        $this->postJson($path,['action'=>'accept','confirm'=>true])->assertOk();
        $this->postJson($path,['action'=>'arrive','confirm'=>true])->assertUnprocessable()->assertJsonValidationErrors('proof');
        $proof=\Illuminate\Http\UploadedFile::fake()->createWithContent('arrival.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='));
        $this->postJson($path,['action'=>'arrive','confirm'=>true,'proof'=>$proof])->assertOk();
        $flow=$f['case']->fresh()->workflow;$this->assertNotNull($flow->driver_arrived_at);$this->assertSame('in_transfer',$flow->stage);
        $this->assertSame('reserved',$f['case']->fresh()->settlement->allocations()->where('role','inter_hub_driver')->value('status'));
        $other=\App\Models\HubTransportDriver::create(['name'=>'Other driver','logistics_company_id'=>1,'status'=>'available','is_online'=>true]);
        \Laravel\Sanctum\Sanctum::actingAs($other);$this->getJson('/api/logistics/courier/returns')->assertOk()->assertJsonCount(0,'returns');
        $this->postJson($path,['action'=>'accept','confirm'=>true])->assertForbidden();
    }

    public function test_zero_priced_return_transfer_still_requires_an_online_driver_and_appears_in_the_app(): void
    {
        $f=$this->returnTransportFixture(true);
        $this->assertFalse($f['case']->fresh()->settlement->allocations()->where('role','inter_hub_driver')->exists());
        $data=['destination_center_id'=>2,'reference'=>'ZERO-PRICED-TRANSFER'];
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',$data);$this->fail('A transfer without a driver was allowed.');}
        catch(ValidationException $e){$this->assertStringContainsString('Choose an online inter-hub driver',$e->errors()['return'][0]);}
        $f['driver']->update(['is_online'=>false]);
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',$data+['driver_id'=>$f['driver']->id]);$this->fail('An offline zero-fee driver was allowed.');}
        catch(ValidationException $e){$this->assertStringContainsString('offline',$e->errors()['return'][0]);}
        $this->assertSame('at_hub',$f['case']->fresh()->workflow->stage);
        $f['driver']->update(['is_online'=>true]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',$data+['driver_id'=>$f['driver']->id]);
        \Laravel\Sanctum\Sanctum::actingAs($f['driver']);
        $this->getJson('/api/logistics/courier/returns')->assertOk()->assertJsonPath('returns.0.id',$f['case']->id);
    }

    public function test_receiving_hub_cannot_be_changed_after_its_work_is_eligible(): void
    {
        $f=$this->returnTransportFixture();
        DB::table('logistics_centers')->insert(['id'=>3,'name'=>'Alternative hub','is_active'=>true,'logistics_company_id'=>3]);
        $f['case']->settlement->allocations()->where('role','seller_hub')->update(['status'=>'eligible']);
        try{ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>3,'driver_id'=>$f['driver']->id,'reference'=>'DO-NOT-RETARGET-EARNED-WORK']);$this->fail('Earned receiving work was retargeted.');}
        catch(ValidationException $e){$this->assertStringContainsString('already started or been paid',$e->errors()['return'][0]);}
        $this->assertSame(2,(int)$f['case']->fresh()->settlement->seller_center_id);
        $this->assertSame('at_hub',$f['case']->fresh()->workflow->stage);
    }

    private function directFixture(): array
    {
        $f=$this->multiItemFundingFixture();
        $f['case']->settlement()->update(['workflow_policy'=>'direct','funding_preference'=>'deposit']);
        return $f;
    }

    private function keepItemFixture(string $resolution='refund_only'): array
    {
        $f=$this->directFixture();
        $f['case']->settlement()->update(['policy_version'=>2]);
        $this->directProposal($f,'seller',['resolution'=>$resolution,'merchandise_amount'=>$resolution==='partial_refund'?60:100]);
        $this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Test bank','holder'=>'Buyer','account'=>'000123456']);
        $f['case']=$f['case']->fresh();return $f;
    }

    private function keepItemSend(array $f,array $extra=[]): array
    {
        ReturnWorkflowService::change($f['case'],$f['seller'],'refund',$extra+[
            'amount'=>$f['case']->fresh()->settlement->net_refund_cents/100,'reference'=>'KEEP-ITEM-TRANSFER','proof_path'=>'test-proof.png']);
        $r=\App\Services\KeepItemRefundService::receipt($f['case']->fresh());
        return ['receipt_id'=>$r['id'],'receipt_version'=>$r['version']];
    }

    public function test_keep_item_direct_seller_refund_requires_no_platform_money_and_closes_only_on_buyer_confirmation(): void
    {
        $f=$this->keepItemFixture();$s=$f['case']->settlement;
        $this->assertTrue($s->keepItemDirect());$this->assertNull($s->funded_at);$this->assertNull($s->admin_decided_at);
        $this->assertSame('refund_pending',$f['case']->workflow->stage);
        $this->assertDatabaseCount('return_fund_payments',0);$this->assertDatabaseCount('return_allocations',0);
        $rows=\App\Models\EscrowRemittance::orderBy('id')->get()->toArray();
        $stock=$f['case']->product->stock;
        $receipt=$this->keepItemSend($f);
        $this->assertStringNotContainsString('000123456',DB::table('direct_refund_transfers')->value('receipt_versions'));
        $this->assertArrayNotHasKey('receipt_versions',\App\Models\DirectRefundTransfer::firstOrFail()->toArray());
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['buyer'])->getJson($path)->assertOk()->assertJsonPath('refund_receipt.status','pending');
        $this->get($path)->assertOk()->assertSee('View seller refund receipt')->assertSee('Decline receipt / payment not received')->assertDontSee('Submit refund-funding receipt');
        $this->actingAs($f['seller'])->getJson($path)->assertOk()->assertJsonPath('refund_destination.account','000123456');
        $this->actingAs($f['admin'])->getJson($path)->assertOk()->assertJsonPath('refund_destination',null)->assertJsonPath('refund_receipt',null);
        $this->actingAs($f['staff'])->getJson($path)->assertForbidden();
        $this->actingAs($f['buyer'])->postJson('/return-center/'.$f['case']->id,['action'=>'confirm_refund'])->assertUnprocessable();
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$receipt);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$receipt);
        $this->assertSame('refunded_return_waived',$f['case']->fresh()->settlement->status);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertEquals($stock,$f['case']->product->fresh()->stock);
        $this->assertFalse((bool)$f['case']->fresh()->workflow->stock_restored);
        $this->assertSame($rows,\App\Models\EscrowRemittance::orderBy('id')->get()->toArray());
        $this->assertEquals(100,\App\Services\BuyerFaultReturnService::externalRefundTotal($f['order']->fresh()));
        if(getenv('KEEP_ITEM_UI_PREVIEW'))file_put_contents(base_path('.tools/keep-item-completed.html'),$this->actingAs($f['buyer'])->get($path)->getContent());
    }

    public function test_keep_item_direct_decline_and_messages_preserve_terms_and_allow_corrected_receipts_without_admin(): void
    {
        $f=$this->keepItemFixture();$receipt=$this->keepItemSend($f);
        $this->change($f,'buyer','decline',$receipt+['note'=>'The transfer did not reach my bank. Please check the status.']);
        $this->change($f,'seller','comment',['note'=>'I am checking the transfer with my bank and will send the correct proof.']);
        $s=$f['case']->fresh()->settlement;
        $this->assertNotNull($s->buyer_accepted_at);$this->assertNotNull($s->seller_accepted_at);
        $this->assertSame('refund_disputed',$s->status);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        if(getenv('KEEP_ITEM_UI_PREVIEW'))foreach(['buyer','seller'] as $role)file_put_contents(base_path('.tools/keep-item-'.$role.'.html'),$this->actingAs($f[$role])->get('/return-center/'.$f['case']->id.'/agreement')->getContent());
        Storage::disk('private')->put('corrected-proof.png','corrected proof');
        $new=$this->keepItemSend($f,$receipt+['proof_path'=>'corrected-proof.png']);
        $this->assertSame(2,$new['receipt_version']);$this->assertSame($receipt['receipt_id'],$new['receipt_id']);
        $this->actingAs($f['buyer'])->postJson('/return-center/'.$f['case']->id,['action'=>'confirm_refund']+$receipt)->assertUnprocessable();
        $old='/return-center/'.$f['case']->id.'/proof?receipt='.$receipt['receipt_id'].'&revision=1';
        $this->get($old)->assertOk();
        $this->actingAs($f['admin'])->get($old)->assertForbidden();
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$new);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertCount(2,\App\Models\DirectRefundTransfer::firstOrFail()->receipt_versions);
        $this->assertStringNotContainsString('000123456',json_encode($f['case']->fresh()->settlement->events));
    }

    public function test_keep_item_direct_partial_refund_uses_agreed_amount_and_rejects_platform_deposits_and_admin_transfers(): void
    {
        $f=$this->keepItemFixture('partial_refund');
        $s=$f['case']->settlement;
        $this->assertTrue($s->keepItemDirect());$this->assertNull($s->admin_decided_at);$this->assertNull($s->funded_at);
        $this->assertNotNull($s->seller_accepted_at);$this->assertNotNull($s->buyer_accepted_at);
        $this->assertSame(0,(int)$s->transport_cents);$this->assertDatabaseCount('return_allocations',0);
        $this->actingAs($f['buyer'])->get('/return-center/'.$f['case']->id.'/agreement')->assertOk()
            ->assertSee('Partial refund — buyer keeps item')->assertSee('Agreed partial item refund')->assertDontSee('awaiting admin verification');
        $this->actingAs($f['seller'])->postJson('/return-center/'.$f['case']->id,['action'=>'refund','amount'=>61,'confirm_transfer'=>1,'reference'=>'WRONG-AMOUNT'])->assertUnprocessable();
        $this->actingAs($f['admin'])->postJson('/return-center/'.$f['case']->id,['action'=>'refund','amount'=>60,'confirm_transfer'=>1])->assertForbidden();
        $this->actingAs($f['seller'])->postJson('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'pay','version'=>$f['case']->settlement->version,'confirm'=>1,'purpose'=>'refund_recovery','amount'=>60,'reference'=>'NO-PLATFORM',
            'proof'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII=')),
        ])->assertUnprocessable();
        $receipt=$this->keepItemSend($f);
        $this->actingAs($f['staff'])->postJson('/return-center/'.$f['case']->id,['action'=>'confirm_refund']+$receipt)->assertForbidden();
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$receipt);
        $this->assertEquals(60,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertDatabaseCount('return_fund_payments',0);
    }

    public function test_partial_refund_requires_a_positive_item_amount_below_the_full_value(): void
    {
        $f=$this->directFixture();$f['case']->settlement()->update(['policy_version'=>2]);
        $version=$f['case']->settlement->version;
        foreach([0,-1,100,101] as $invalid){
            try{$this->directProposal($f,'seller',['resolution'=>'partial_refund','merchandise_amount'=>$invalid]);$this->fail('Invalid partial amount accepted.');}
            catch(ValidationException $error){$this->assertArrayHasKey('return',$error->errors());}
            $this->assertSame($version,$f['case']->fresh()->settlement->version);
        }
        $this->directProposal($f,'seller',['resolution'=>'partial_refund','merchandise_amount'=>0.01]);
        $this->assertSame(1,(int)$f['case']->fresh()->settlement->merchandise_cents);
    }

    public function test_partial_refund_negotiation_receipt_decline_and_correction_end_in_only_the_agreed_payment(): void
    {
        $f=$this->directFixture();$f['case']->settlement()->update(['policy_version'=>2]);
        $this->directProposal($f,'seller',['resolution'=>'partial_refund','merchandise_amount'=>40]);
        $this->change($f,'buyer','comment',['note'=>'Please refund sixty instead of forty for the damaged item.']);
        $this->assertNull($f['case']->fresh()->settlement->buyer_accepted_at);
        $this->directProposal($f,'seller',['resolution'=>'partial_refund','merchandise_amount'=>60]);
        if(getenv('PARTIAL_UI_PREVIEW'))file_put_contents(base_path('.tools/partial-proposal.html'),$this->actingAs($f['seller'])->get('/return-center/'.$f['case']->id.'/agreement')->getContent());
        $this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Test bank','holder'=>'Buyer','account'=>'000PARTIAL']);
        $f['case']=$f['case']->fresh();$stock=$f['case']->product->stock;
        $rows=\App\Models\EscrowRemittance::orderBy('id')->get()->toArray();
        $receipt=$this->keepItemSend($f,['reference'=>'PARTIAL-DIRECT-60']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->change($f,'buyer','decline',$receipt+['note'=>'The partial refund has not arrived. Please check the transfer.']);
        $this->change($f,'seller','comment',['note'=>'The transfer was delayed. Here is the corrected receipt.']);
        $receipt=$this->keepItemSend($f,$receipt+['reference'=>'PARTIAL-DIRECT-60']);
        if(getenv('PARTIAL_UI_PREVIEW'))foreach(['buyer','seller'] as $role)file_put_contents(base_path('.tools/partial-'.$role.'.html'),$this->actingAs($f[$role])->get('/return-center/'.$f['case']->id.'/agreement')->getContent());
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$receipt);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund',$receipt);
        $this->assertEquals(60,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('partially_refunded',Payments::transactionFor($f['order']->fresh())->status);
        $this->assertSame('refunded_return_waived',$f['case']->fresh()->settlement->status);
        $this->assertSame($rows,\App\Models\EscrowRemittance::orderBy('id')->get()->toArray());
        $this->assertEquals($stock,$f['case']->product->fresh()->stock);
        $this->assertDatabaseCount('return_allocations',0);$this->assertDatabaseCount('return_fund_payments',0);
    }

    public function test_keep_item_direct_cannot_reuse_another_cases_historical_transfer_reference(): void
    {
        $f=$this->keepItemFixture();
        \App\Models\DirectRefundTransfer::create(['return_request_id'=>999,'reference'=>'HISTORICAL-OTHER-REFUND','amount_cents'=>10000,'revision'=>1,'status'=>'declined','receipt_versions'=>[]]);
        try{$this->keepItemSend($f,['reference'=>'HISTORICAL-OTHER-REFUND']);$this->fail('Another refund reference was reused.');}
        catch(ValidationException $error){$this->assertArrayHasKey('return',$error->errors());}
        $this->assertNull($f['case']->fresh()->workflow->refund_sent_at);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertDatabaseCount('direct_refund_transfers',1);
    }

    private function directProposal(array $f,string $fault='buyer',array $extra=[]): void
    {
        $this->change($f,'seller','propose',$extra+['fault'=>$fault,'resolution'=>'return_refund','merchandise_amount'=>100,
            'note'=>'Compared the ordered variation with the evidence and proposed this resolution.']);
    }

    private function directPayment(array $f): void
    {
        $s=$f['case']->fresh()->settlement;
        $p=Settlements::submitPayment($f['case'],$f[$s->payer],['version'=>$s->version,'purpose'=>'return_budget',
            'amount'=>$s->budgetCents()/100,'reference'=>uniqid('DIRECT-'),'proof_path'=>'test-proof.png']);
        Settlements::viewPaymentReceipt($p,$f['admin']);
        Settlements::reviewPayment($p,$f['admin'],true,'Checked the actual incoming transfer in the receiving account.');
    }

    private function receiptUpload(array $f,string $reference='RETURN-FEE-TRANSFER'): array
    {
        return ['action'=>'pay','version'=>$f['case']->fresh()->settlement->version,'confirm'=>true,
            'purpose'=>'return_budget','amount'=>110,'reference'=>$reference,
            'proof'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='))];
    }

    public function test_return_fee_upload_is_private_pending_and_requires_admin_receipt_preview_before_success(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        $this->actingAs($f['buyer'])->postJson('/return-center/'.$f['case']->id.'/agreement',$this->receiptUpload($f))->assertOk()
            ->assertJsonPath('message','Receipt submitted. The payment is awaiting admin verification.');
        $p=$f['case']->fresh()->settlement->payments()->firstOrFail();
        $this->assertSame('submitted',$p->status);$this->assertNull($p->verified_at);
        Storage::disk('private')->assertExists($p->proof_path);
        $review=route('returns.deposit.review',$p);
        $data=['decision'=>'verify','note'=>'Checked the incoming transfer against the platform receiving account.','confirm'=>true];
        $this->actingAs($f['admin'])->postJson($review,$data)->assertUnprocessable();
        $this->assertSame('submitted',$p->fresh()->status);
        $this->actingAs($f['buyer'])->get(route('returns.file',['kind'=>'deposit','id'=>$p->id]))->assertOk();
        $this->assertNull($p->fresh()->receipt_viewed_at);
        $this->actingAs($f['seller'])->get(route('returns.file',['kind'=>'deposit','id'=>$p->id]))->assertForbidden();
        $this->actingAs($f['admin'])->get(route('returns.file',['kind'=>'deposit','id'=>$p->id]))->assertOk()
            ->assertHeader('Cache-Control','no-store, private');
        $this->assertSame($f['admin']->id,(int)$p->fresh()->receipt_viewed_by);
        $this->assertNotNull($p->fresh()->receipt_viewed_at);
        $this->actingAs($f['admin'])->postJson($review,$data)->assertOk();
        $this->assertSame('verified',$p->fresh()->status);
        $this->assertNotNull($p->fresh()->verified_at);
    }

    public function test_return_receipt_rejection_and_correction_requires_a_fresh_preview(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['buyer'])->postJson($path,$this->receiptUpload($f))->assertOk();
        $p=$f['case']->fresh()->settlement->payments()->firstOrFail();
        $this->actingAs($f['admin'])->get(route('returns.file',['kind'=>'deposit','id'=>$p->id]))->assertOk();
        $this->actingAs($f['admin'])->postJson(route('returns.deposit.review',$p),[
            'decision'=>'reject','note'=>'Receipt was unreadable. Upload a clearer screenshot of this same transfer.','confirm'=>true])->assertOk();
        $this->actingAs($f['buyer'])->postJson($path,$this->receiptUpload($f))->assertOk();
        $this->assertDatabaseCount('return_fund_payments',1);
        $this->assertNull($p->fresh()->receipt_viewed_by);$this->assertNull($p->fresh()->receipt_viewed_at);
        $this->actingAs($f['admin'])->postJson(route('returns.deposit.review',$p),[
            'decision'=>'verify','note'=>'Checked the corrected receipt against the actual incoming bank transaction.','confirm'=>true])->assertUnprocessable();
    }

    public function test_return_fee_cannot_be_uploaded_without_a_receipt_and_duplicate_pending_upload_is_hidden(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        $payload=$this->receiptUpload($f);unset($payload['proof']);
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['buyer'])->postJson($path,$payload)->assertUnprocessable()->assertJsonValidationErrors('proof');
        $this->actingAs($f['buyer'])->postJson($path,$this->receiptUpload($f))->assertOk();
        $this->actingAs($f['buyer'])->get($path)->assertOk()->assertSee('awaiting admin verification')
            ->assertDontSee('Submit return-fee receipt')->assertSee('View uploaded receipt');
        $this->actingAs($f['admin'])->get($path)->assertOk()->assertSee('Open receipt before verifying')->assertSee('Record transfer verification');
    }

    public function test_seller_refund_funding_has_a_separate_explanation_and_upload_form(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->update(['status'=>'remitted']);
        $path='/return-center/'.$f['case']->id.'/agreement';
        $this->actingAs($f['seller'])->get($path)->assertOk()->assertSee('Seller funds for the buyer’s item refund')
            ->assertSee('Submit refund-funding receipt')->assertDontSee('Submit return-fee receipt');
        $this->actingAs($f['buyer'])->get($path)->assertOk()->assertSee('Submit return-fee receipt')
            ->assertDontSee('Submit refund-funding receipt');
        $this->actingAs($f['admin'])->get($path)->assertOk()->assertDontSee('Submit refund-funding receipt');
    }

    public function test_direct_same_hub_fees_include_all_configured_services_without_manual_input(): void
    {
        $f=$this->directFixture();$this->directProposal($f,'buyer',['payer'=>'seller','funding_method'=>'deduct_refund',
            'allocations'=>[['role'=>'collection_rider','amount'=>1]]]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame('buyer',$s->payer);$this->assertSame('deposit',$s->funding_method);
        $this->assertSame('deposit',$s->funding_preference);$this->assertSame(11000,(int)$s->transport_cents);
        $this->assertSame(10000,(int)$s->net_refund_cents);$this->assertNotNull($s->seller_accepted_at);
        $this->assertNull($s->buyer_accepted_at);$this->assertNull($s->admin_decided_at);
        $this->assertEqualsCanonicalizing(['collection_rider','collection_hub','seller_delivery_rider'],$s->allocations()->pluck('role')->all());
        $this->assertSame(0,$s->reserved_cents);$this->assertSame(0,$s->seller_reserved_cents);
    }

    public function test_direct_cross_hub_quote_uses_commission_rates_and_returned_quantity(): void
    {
        $f=$this->directFixture();$f['case']->update(['quantity'=>2]);
        \App\Support\EscrowEarnings::updateRates(['origin_hub_fee'=>11,'pickup_rider_per_item'=>12,'inter_hub_driver_fee'=>13,'destination_hub_fee'=>14,'last_mile_rider_fee'=>15]);
        $this->directProposal($f,'seller',['seller_center_id'=>2]);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(7700,(int)$s->transport_cents);
        $this->assertSame(2400,(int)$s->allocations()->where('role','collection_rider')->value('amount_cents'));
        $this->assertSame(5,$s->allocations()->count());$this->assertSame('seller',$s->payer);
        \App\Support\EscrowEarnings::updateRates(['pickup_rider_per_item'=>99]);
        $this->assertSame(7700,(int)$s->fresh()->transport_cents);
        $this->assertEquals(12,$s->fee_snapshot['rates']['pickup_rider_per_item']);
    }

    public function test_direct_return_can_reverse_the_original_delivery_between_companies(): void
    {
        $f=$this->directFixture();
        if(!Schema::hasColumn('deliveries','origin_center_id'))Schema::table('deliveries',fn(Blueprint $t)=>$t->unsignedBigInteger('origin_center_id')->nullable());
        Delivery::where('order_id',$f['order']->id)->update(['origin_center_id'=>2]);
        DB::table('logistics_centers')->where('id',2)->update(['logistics_company_id'=>2]);
        if(!Schema::hasTable('logistics_companies'))Schema::create('logistics_companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('status');$t->softDeletes();$t->timestamps();});
        DB::table('logistics_companies')->updateOrInsert(['id'=>1],['name'=>'Collection company','status'=>'active']);
        DB::table('logistics_companies')->updateOrInsert(['id'=>2],['name'=>'Receiving company','status'=>'active']);
        if(!Schema::hasTable('hub_transport_drivers'))Schema::create('hub_transport_drivers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id')->nullable();$t->unsignedBigInteger('logistics_company_id');$t->string('name');$t->string('status');$t->boolean('is_online')->default(false);$t->softDeletes();$t->timestamps();});
        $driver=DB::table('hub_transport_drivers')->insertGetId(['logistics_company_id'=>1,'name'=>'Return driver','status'=>'active','is_online'=>true]);
        $this->directProposal($f,'buyer',['seller_center_id'=>2]);
        $this->assertSame(16000,(int)$f['case']->fresh()->settlement->transport_cents);
        $this->change($f,'buyer','accept');$this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->directPayment($f);$this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        ReturnWorkflowService::change($f['case'],$f['staff'],'transfer',['destination_center_id'=>2,'reference'=>'ORIGINAL-ROUTE-REVERSED','driver_id'=>$driver]);
        $this->assertSame('in_transfer',$f['case']->fresh()->workflow->stage);
        $receiver=User::create(['name'=>'Receiving staff','email'=>uniqid().'@test.local','password'=>'test','role'=>'staff','center_id'=>2,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        ReturnWorkflowService::change($f['case'],$receiver,'receive_transfer');
        $this->assertSame(2,(int)$f['case']->fresh()->workflow->center_id);
        $this->assertSame('eligible',$f['case']->fresh()->settlement->allocations()->where('role','inter_hub_driver')->value('status'));
        $this->assertSame('eligible',$f['case']->fresh()->settlement->allocations()->where('role','seller_hub')->value('status'));
    }

    public function test_direct_quote_allows_an_active_alternative_company_destination(): void
    {
        $f=$this->directFixture();DB::table('logistics_centers')->where('id',2)->update(['logistics_company_id'=>2]);
        $this->directProposal($f,'buyer',['seller_center_id'=>2]);
        $this->assertSame(2,(int)$f['case']->fresh()->settlement->seller_center_id);
        $this->assertSame(16000,(int)$f['case']->fresh()->settlement->transport_cents);
    }

    public function test_direct_buyer_agreement_then_separate_payment_unlocks_without_admin_decision(): void
    {
        $f=$this->directFixture();$this->directProposal($f);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->change($f,'buyer','accept');
        $s=$f['case']->fresh()->settlement;$this->assertTrue($s->agreed());$this->assertNull($s->funded_at);
        $this->directPayment($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertNotNull($s->funded_at);$this->assertNull($s->admin_decided_at);
        $this->assertSame(10000,(int)$s->net_refund_cents);$this->assertSame(10000,$s->refund_reserved_cents);
        $this->assertSame(0,$s->reserved_cents);$this->assertSame(0,$s->seller_reserved_cents);
        $this->assertSame('approved',$f['case']->fresh()->status);
        $this->assertSame('awaiting_collection',$f['case']->fresh()->workflow->stage);
        $this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'DIRECT-ACTUAL-REFUND','proof_path'=>'test-proof.png']);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame(0,$f['case']->fresh()->settlement->refund_reserved_cents);
    }

    public function test_direct_seller_fault_requires_separate_seller_payment_and_never_spends_held_earnings(): void
    {
        $f=$this->directFixture();$this->directProposal($f,'seller');$this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $before=\App\Support\EscrowEarnings::calculateForSeller($f['order']->fresh(),$f['seller']->id)['seller_earning'];
        $this->assertSame(0,$f['case']->fresh()->settlement->seller_reserved_cents);
        $this->assertNull($f['case']->fresh()->settlement->funded_at);
        $this->directPayment($f);
        $this->assertNotNull($f['case']->fresh()->settlement->funded_at);
        $this->assertEquals($before,\App\Support\EscrowEarnings::calculateForSeller($f['order']->fresh(),$f['seller']->id)['seller_earning']);
        $this->assertSame($f['seller']->id,(int)$f['case']->fresh()->settlement->payments()->first()->payer_user_id);
    }

    public function test_direct_comments_and_declines_require_a_new_seller_proposal_and_fresh_acceptance(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$old=$f['case']->fresh()->settlement->version;
        $this->change($f,'buyer','comment',['note'=>'I selected the correct size. Please review the supplied item.']);
        $this->assertSame('revision_requested',$f['case']->fresh()->settlement->status);
        try{$this->change($f,'buyer','accept');$this->fail('Old proposal was accepted after a change request.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->directProposal($f,'seller');$s=$f['case']->fresh()->settlement;
        $this->assertGreaterThan($old,$s->version);$this->assertNull($s->buyer_accepted_at);
        $this->assertSame('seller',$s->payer);$this->assertSame('Buyer',collect($s->events)->firstWhere('action','comment')['actor_label']);
        try{Settlements::change($f['case'],$f['buyer'],'accept',['version'=>$old]);$this->fail('Stale acceptance passed.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'buyer','decline',['note'=>'I do not agree with this proposed remedy and would like a revision.']);
        $this->assertSame('declined',$f['case']->fresh()->settlement->status);
    }

    public function test_direct_fees_cannot_be_manually_changed_by_admin(): void
    {
        $f=$this->directFixture();$this->directProposal($f);
        $this->expectException(ValidationException::class);
        $this->change($f,'admin','quote',['collection_center_id'=>1,'seller_center_id'=>1,'allocations'=>[['role'=>'collection_rider','amount'=>999]]]);
    }

    public function test_direct_payment_is_blocked_before_buyer_acceptance(): void
    {
        $f=$this->directFixture();$this->directProposal($f);
        $this->expectException(ValidationException::class);$this->directPayment($f);
    }

    public function test_direct_seller_proposal_http_accepts_no_payer_or_manual_fee_fields(): void
    {
        $f=$this->directFixture();$s=$f['case']->fresh()->settlement;
        $this->actingAs($f['seller'])->postJson('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'propose','version'=>$s->version,'confirm'=>true,'fault'=>'buyer','resolution'=>'return_refund',
            'merchandise_amount'=>100,'collection_center_id'=>null,'seller_center_id'=>null,'note'=>'Please return the incorrectly selected size.'])
            ->assertOk();
        $this->assertSame(11000,(int)$s->fresh()->transport_cents);
        $this->assertSame('buyer',$s->fresh()->payer);
    }

    public function test_direct_admin_cannot_replace_the_sellers_proposal(): void
    {
        $f=$this->directFixture();$s=$f['case']->fresh()->settlement;
        $this->actingAs($f['admin'])->postJson('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'propose','version'=>$s->version,'confirm'=>true,'fault'=>'buyer','resolution'=>'return_refund',
            'merchandise_amount'=>100,'note'=>'Admin cannot decide terms for the parties.'])->assertForbidden();
    }

    public function test_direct_buyer_cannot_pay_the_sellers_fee_and_admin_cannot_approve_fault(): void
    {
        $f=$this->directFixture();$this->directProposal($f,'seller');$this->change($f,'buyer','accept');
        $s=$f['case']->fresh()->settlement;
        $this->actingAs($f['buyer'])->postJson('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'pay','version'=>$s->version,'confirm'=>true,'purpose'=>'return_budget','amount'=>110,
            'reference'=>'WRONG-PAYER','proof'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('proof.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='))])->assertForbidden();
        $this->expectException(ValidationException::class);
        $this->change($f,'admin','decide',['note'=>'This responsibility belongs to the buyer and seller to agree.']);
    }

    public function test_direct_payment_then_refund_account_unlocks_automatically_once(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');$this->directPayment($f);
        $this->assertNull($f['case']->fresh()->settlement->funded_at);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $s=$f['case']->fresh()->settlement;$this->assertNotNull($s->funded_at);
        $this->assertSame(10000,$s->refund_reserved_cents);$this->assertSame(1,$s->payments()->count());
        $this->expectException(ValidationException::class);
        $this->change($f,'buyer','comment',['note'=>'A funded return cannot be silently re-negotiated.']);
    }

    public function test_direct_submitted_receipt_locks_proposal_but_does_not_unlock_collection(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        $s=$f['case']->fresh()->settlement;
        Settlements::submitPayment($f['case'],$f['buyer'],['version'=>$s->version,'purpose'=>'return_budget','amount'=>110,'reference'=>'UNVERIFIED-DIRECT','proof_path'=>'test-proof.png']);
        $this->assertNull($s->fresh()->funded_at);
        $this->expectException(ValidationException::class);$this->directProposal($f,'seller');
    }

    public function test_direct_funded_account_correction_preserves_fees_and_does_not_require_fault_approval(): void
    {
        $f=$this->directFixture();$this->directProposal($f);$this->change($f,'buyer','accept');
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->directPayment($f);
        $this->change($f,'admin','unlock_destination',['note'=>'Buyer reported an incorrect receiving account before any refund.']);
        $this->assertFalse($f['case']->fresh()->settlement->agreed());
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'987654321']);
        $this->change($f,'admin','resolve_issue',['note'=>'The buyer corrected and verified the receiving account before payment.']);
        $s=$f['case']->fresh()->settlement;
        $this->assertTrue($s->agreed());$this->assertNull($s->admin_decided_at);
        $this->assertSame(11000,(int)$s->transport_cents);$this->assertSame(10000,$s->refund_reserved_cents);
        $this->assertSame(1,$s->payments()->count());
    }

    public function test_direct_keep_item_refund_has_no_transport_or_admin_approval_step(): void
    {
        $f=$this->directFixture();$this->directProposal($f,'seller',['resolution'=>'refund_only']);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->change($f,'buyer','accept');$s=$f['case']->fresh()->settlement;
        $this->assertSame(0,(int)$s->transport_cents);$this->assertSame(0,$s->allocations()->count());
        $this->assertNotNull($s->funded_at);$this->assertNull($s->admin_decided_at);
        $this->assertSame('refund_pending',$f['case']->fresh()->workflow->stage);
    }

    public function test_direct_pages_show_no_manual_fee_or_responsibility_approval_forms(): void
    {
        $f=$this->directFixture();$this->directProposal($f);
        foreach(['buyer','seller','admin','staff'] as $role){
            $response=$this->actingAs($f[$role])->get('/return-center/'.$f['case']->id.'/agreement')->assertOk();
            $response->assertSee('Automatic return fees')->assertDontSee('Save transport quote')->assertDontSee('Record admin decision')
                ->assertDontSee('name="funding_preference"',false)->assertDontSee('name="allocations[',false);
        }
        $this->change($f,'buyer','accept');
        $this->actingAs($f['buyer'])->get('/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertSee('Submit return-fee receipt');
    }

    public function test_original_earned_logistics_can_be_released_after_verified_refund_recovery(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund','buyer');$this->fund($f);$this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'RECOVERED-REFUND','proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        $line=\App\Models\EscrowRemittance::where('order_id',$f['order']->id)->where('recipient_role','pickup_rider')->firstOrFail();
        $line->update(['status'=>'pending_remittance','recipient_user_id'=>$f['riderUser']->id,'rider_id'=>$f['rider']->id]);
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit',$line),['bank_reference'=>'ORIGINAL-PICKUP-AFTER-RECOVERY'])
            ->assertSessionHasNoErrors();
        $this->assertSame('remitted',$line->fresh()->status);
        $this->assertSame(2000,$f['case']->fresh()->settlement->reserved_cents);
        $this->assertEquals(0,ReturnWorkflowService::fundingSummary($f['order']->fresh(),0)['shortfall']);
    }
}
