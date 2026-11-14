<?php

namespace App\Services;

use App\Models\{DirectRefundTransfer, EscrowRemittance, Order, ReturnAllocation, ReturnRequest, ReturnSettlement, ReturnWorkflow, User};
use App\Support\Payments;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Seller-to-buyer keep-item refunds. No platform deposit or transport allocation. */
final class KeepItemRefundService
{
    private static function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['return'=>$message]);
    }

    public static function transfers(ReturnRequest $case)
    {
        return DirectRefundTransfer::where('return_request_id', $case->id);
    }

    public static function current(ReturnRequest $case): ?DirectRefundTransfer
    {
        $ref = $case->workflow?->refund_reference;
        return $ref ? self::transfers($case)->where('reference', $ref)->first() : null;
    }

    public static function event(ReturnSettlement $s, User $actor, string $action, ?string $note): void
    {
        $events=$s->events??[];
        $events[]=['action'=>$action,'actor_id'=>$actor->id,'actor_label'=>(int)$s->returnRequest->buyer_id===(int)$actor->id?'Buyer':'Seller',
            'version'=>$s->version,'at'=>now()->toIso8601String(),'note'=>$note];
        $s->events=$events;
    }

    /** Called with order, case and settlement locked by the normal agreement transaction. */
    public static function activate(ReturnRequest $case, ReturnSettlement $s): void
    {
        if (!$s->keepItemDirect() || !$s->agreed() || !$s->destination_verified_at || $s->closed_at || $case->workflow?->refund_sent_at) return;
        self::check($s->net_refund_cents>0 && $s->payerBudgetCents()===0 && !$s->allocations()->where('role', '!=', 'seller_protection_reimbursement')->exists(), 'A keep-item refund cannot include delivery work or fees.');
        $other=(int)ReturnRequest::where('order_item_id',$case->order_item_id)->whereKeyNot($case->id)
            ->whereIn('status',['approved','item_received','refunded'])->sum('quantity');
        self::check($case->item && $case->item->seller_id==$case->seller_id && $case->quantity>0 && $other+$case->quantity<=$case->item->quantity,
            'This purchased quantity already belongs to another agreed or completed refund.');
        if ($s->platform_subsidy_cents) {
            self::check(ReturnSettlementService::budgetFunded($s), 'Fund the approved platform reimbursement before activating this refund.');
            $s->funded_at = now();
        }
        $s->status='refund_pending';$s->save();
        $case->update(['status'=>'approved','refund_amount'=>$s->net_refund_cents/100]);
        $case->workflow()->update(['stage'=>'refund_pending']);
    }

    /** Post-payment conversation preserves the accepted terms and actual transfer history. */
    public static function respond(ReturnRequest $case, ReturnSettlement $s, User $actor, string $action, array $data): void
    {
        $buyer=(int)$case->buyer_id===(int)$actor->id;
        abort_unless($buyer || (int)$case->seller_id===(int)$actor->id,403);
        self::check(!$s->closed_at,'This refund is already confirmed.');
        self::check(strlen(trim($data['note']??''))>=10,'Explain what happened or suggest a correction (at least 10 characters).');
        $transfer=self::current($case);
        self::check((bool)$transfer,'There is no seller refund receipt to review yet.');
        if (in_array($action,['decline','dispute'],true)) {
            abort_unless($buyer,403);
            self::matchReceipt($transfer,$data);
            self::check($transfer->status!=='confirmed','This receipt is already confirmed.');
            $transfer->update(['status'=>'declined','buyer_note'=>trim($data['note'])]);
            $s->status='refund_disputed';$s->dispute_note=trim($data['note']);
            self::event($s,$actor,'refund_receipt_declined',$data['note']);
        } else {
            self::event($s,$actor,'refund_message',$data['note']);
        }
        $s->response_due_at=now()->addHours(48);$s->save();
    }

    public static function matchReceipt(DirectRefundTransfer $transfer, array $data): void
    {
        self::check((int)($data['receipt_id']??0)===$transfer->id && (int)($data['receipt_version']??0)===$transfer->revision,
            'The seller receipt changed. Reopen the agreement and review the latest receipt before responding.');
    }

    /** Called under ReturnWorkflowService's order/case/flow locks. */
    public static function change(Order $order, ReturnRequest $case, ReturnWorkflow $flow, User $actor, string $action, array $data): void
    {
        $s=ReturnSettlement::lockForUpdate()->where('return_request_id',$case->id)->firstOrFail();
        $buyer=(int)$case->buyer_id===(int)$actor->id;$seller=(int)$case->seller_id===(int)$actor->id;
        if ($action==='refund') {
            abort_unless($seller,403);
            self::check(!$s->closed_at && $s->buyer_accepted_at && $s->seller_accepted_at && $s->destination_verified_at && $s->refund_destination,
                'Both parties must agree and the buyer must verify their receiving account first.');
            self::check($case->status==='approved' && !$s->physicalReturn() && $s->transport_cents==0 && !$flow->buyer_confirmed_at,
                'This keep-item refund is not awaiting a seller payment.');
            $current=self::current($case);
            if ($current) {
                self::matchReceipt($current,$data);
                self::check($current->status==='declined','The buyer must review the current receipt before it can be corrected.');
            } else self::check($s->agreed(),'Resolve the proposal before sending a refund.');
            self::check(ReturnSettlementService::cents($data['amount']??0)===(int)$s->net_refund_cents,'Enter the exact agreed refund amount.');
            self::check(!empty($data['proof_path']) && Storage::disk('private')->exists($data['proof_path']),'Upload the actual seller-to-buyer transfer receipt.');
            $ref=strtoupper(preg_replace('/\s+/','',trim($data['reference']??'')));
            self::check(strlen($ref)>=4 && strlen($ref)<=150,'Enter the complete outgoing refund reference.');
            self::check(!EscrowRemittance::where('bank_reference',$ref)->exists() && !ReturnAllocation::where('reference',$ref)->exists()
                && !\App\Models\ReturnFundPayment::where('reference',$ref)->exists()
                && !ReturnWorkflow::where('refund_reference',$ref)->whereKeyNot($flow->id)->exists(),'That reference belongs to another transfer.');
            $transfer=DirectRefundTransfer::where('reference',$ref)->lockForUpdate()->first();
            self::check(!$transfer || (int)$transfer->return_request_id===$case->id,'That reference was used for another refund.');
            self::check($s->net_refund_cents+ReturnSettlementService::cents(Payments::transactionFor($order)?->refunded_amount??0)
                <=ReturnSettlementService::cents($order->total_amount),'This refund would exceed the original buyer payment.');
            SellerProtectionService::reconcile($order, $s->merchandise_cents / 100, false, $case);
            $versions=$transfer?->receipt_versions??[];
            $versions[]=['proof_path'=>$data['proof_path'],'destination'=>$s->refund_destination,'at'=>now()->toIso8601String()];
            if (!$transfer) $transfer=new DirectRefundTransfer(['return_request_id'=>$case->id,'reference'=>$ref,'amount_cents'=>$s->net_refund_cents]);
            $transfer->fill(['receipt_versions'=>$versions,'revision'=>count($versions),'status'=>'pending','buyer_note'=>null])->save();
            $flow->update(['stage'=>'refund_sent','refund_source'=>'seller_direct','refund_sender_user_id'=>$actor->id,
                'refund_reference'=>$ref,'refund_proof_path'=>$data['proof_path'],'refund_sent_at'=>now()]);
            SellerProtectionService::refreshLedger($order->fresh());
            $s->status='refund_sent';$s->dispute_note=null;
            self::event($s,$actor,$current?'refund_receipt_corrected':'seller_refund_sent','Seller recorded '.number_format($s->net_refund_cents/100,2).' sent directly to the buyer; awaiting buyer confirmation.');
            $s->save();
            return;
        }
        abort_unless($buyer,403);
        $transfer=self::current($case);self::check((bool)$transfer,'The seller has not submitted a refund receipt yet.');
        self::matchReceipt($transfer,$data);
        if ($flow->buyer_confirmed_at) return;
        self::check($flow->refund_sent_at && $flow->refund_source==='seller_direct','There is no direct refund awaiting confirmation.');
        self::check($s->net_refund_cents+ReturnSettlementService::cents(Payments::transactionFor($order)?->refunded_amount??0)
            <=ReturnSettlementService::cents($order->total_amount),'This refund would exceed the original buyer payment.');
        $transfer->update(['status'=>'confirmed','confirmed_at'=>now()]);
        $flow->update(['stage'=>'completed','buyer_confirmed_at'=>now()]);
        $s->fill(['status'=>'refunded_return_waived','dispute_note'=>null,'closed_at'=>now()]);
        self::event($s,$actor,'buyer_confirmed_refund','Buyer confirmed the agreed refund reached their receiving account.');$s->save();
        $case->update(['status'=>'refunded','resolved_at'=>now()]);
        if(!$order->returnRequests()->whereIn('status',['requested','approved','item_received'])->exists()){
            $order->paymentDisputes()->where('status','awaiting_refund')->update(['status'=>'resolved','resolved_at'=>now()]);
        }
        $s->allocations()->where('role','seller_protection_reimbursement')->where('status','reserved')->update(['status'=>'eligible','eligible_at'=>now()]);
        Payments::refund($order,$s->net_refund_cents/100,'Buyer confirmed seller-direct keep-item refund; return #'.$case->id);
        SellerProtectionService::refreshLedger($order->fresh());
        \App\Support\EscrowEarnings::syncRemittancesForOrder($order->fresh());
    }

    public static function receipt(ReturnRequest $case): ?array
    {
        $row=self::current($case);
        return $row?['id'=>$row->id,'version'=>$row->revision,'reference'=>$row->reference,'amount_cents'=>$row->amount_cents,
            'status'=>$row->status,'buyer_note'=>$row->buyer_note,'sent_at'=>$row->updated_at->toIso8601String()]:null;
    }
}
