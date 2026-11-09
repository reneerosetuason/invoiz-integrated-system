<?php

namespace App\Http\Controllers;

use App\Models\{ReturnRequest, ReturnFundPayment, ReturnAllocation, User, HubTransportDriver, LogisticsCenter, Rider};
use App\Services\{ReturnSettlementService, ReturnWorkflowService, ReturnFundingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage, Schema};

class ReturnSettlementController extends Controller
{
    private function admin($actor): bool { return $actor instanceof User && $actor->isActive() && $actor->isAdmin(); }
    private function blockedDriver($actor): bool { return $actor instanceof HubTransportDriver && $actor->isBlocked(); }
    private function layout($actor): string
    {
        return $this->admin($actor) ? 'admin.layout' : ($actor instanceof User && ($actor->isLogisticsStaff() || $actor->role === 'rider')
            ? 'logistics.layouts.app' : ($actor instanceof User && $actor->isSeller() ? 'seller.layouts.app' : 'buyer.layouts.website'));
    }

    public function show(Request $request, ReturnRequest $returnRequest)
    {
        $user = $request->user();
        abort_unless(ReturnSettlementService::canView($user, $returnRequest), 403);
        $agreement = ReturnSettlementService::ensure($returnRequest);
        abort_unless($agreement, 404, 'This earlier payment adjustment uses the existing refund process.');
        $agreement->load(['payments', 'allocations']);
        $buyer = $user instanceof User && (int) $user->id === (int) $returnRequest->buyer_id;
        $seller = $user instanceof User && (int) $user->id === (int) $returnRequest->seller_id;
        $admin = $this->admin($user);
        $destination = $buyer || ($admin && !$agreement->sellerDirectRefund()) || ($seller && $agreement->sellerDirectRefund() && $agreement->buyer_accepted_at && $agreement->seller_accepted_at) ? $agreement->refund_destination : null;
        $refundReceipt=$agreement->keepItemDirect() && ($buyer||$seller)?\App\Services\KeepItemRefundService::receipt($returnRequest):null;
        $settings = Schema::hasTable('gcash_escrow_settings') ? DB::table('gcash_escrow_settings')->first() : null;
        $fundingPlan = ReturnFundingService::preview($returnRequest, $agreement);
        $funding = $fundingPlan['funding'];
        $budgetOutstanding = $fundingPlan['budget_outstanding_cents'];
        $lastIssue=collect($agreement->events??[])->where('action','dispute')->last();
        $can_resolve_issue=$agreement->physicalDirectRefund() && $agreement->dispute_note && ($buyer||$seller) && (int)($lastIssue['actor_id']??0)===(int)$user->id;
        if ($request->expectsJson()) {
            $payload = $agreement->toArray();
            // Payment proofs, bank details and payer references are private.
            $payload['payments'] = $agreement->payments->filter(fn ($p) => $admin || ($user instanceof User && (int) $p->payer_user_id === (int) $user->id))->values();
            return response()->json(['case' => $returnRequest->only(['id', 'order_id', 'quantity', 'reason', 'reason_details', 'status', 'refund_amount']),
                'agreement' => $payload, 'progress' => $returnRequest->workflow, 'refund_destination' => $destination, 'funding' => $funding, 'budget_outstanding_cents' => $budgetOutstanding,
                'receiving_account' => $settings ? ['account_name' => $settings->account_name, 'account_details' => $settings->account_details,
                    'qr_url' => $settings->qr_path ? asset('storage/'.$settings->qr_path) : null] : null,
                'funding_plan' => $fundingPlan, 'refund_receipt'=>$refundReceipt, 'permissions' => compact('buyer', 'seller', 'admin','can_resolve_issue')])->header('Cache-Control', 'private, no-store');
        }
        $layout = $this->layout($user);
        $centers = ($admin || ($user instanceof User && $user->isLogisticsStaff())) ? LogisticsCenter::where('is_active', true)->orderBy('name')->get() : collect();
        $feePreview=$agreement->fee_snapshot;$feeError=null;
        if($agreement->directAgreement() && !$agreement->quoted_at){
            try{$feePreview=\App\Services\ReturnFeeService::calculate($returnRequest,$agreement);}
            catch(\Illuminate\Validation\ValidationException $e){$feeError=$e->validator?->errors()->first() ?? 'Select active return hubs in the proposal.';}
        }
        if($agreement->directAgreement() && $seller)$centers=\App\Services\ReturnRouting::options();
        return response()->view($agreement->keepItemDirect()?'shared.returns.keep-item-agreement':($agreement->physicalDirectRefund()?'shared.returns.buyer-fault-agreement':($agreement->directAgreement()?'shared.returns.direct-agreement':'shared.returns.agreement')), compact('returnRequest', 'agreement', 'user', 'buyer', 'seller', 'admin', 'destination', 'settings', 'funding', 'budgetOutstanding', 'fundingPlan', 'layout', 'centers','feePreview','feeError','refundReceipt'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        $user = $request->user();
        abort_unless($user instanceof User && ReturnSettlementService::canView($user, $returnRequest), 403);
        // Browser datetime-local inputs have no offset. Interpret the displayed
        // Philippine pickup time in the active request/database timezone.
        if ($request->input('action') === 'schedule' && is_string($request->input('collection_at'))
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $request->input('collection_at'))) {
            try {
                $request->merge(['collection_at' => \Carbon\CarbonImmutable::parse($request->input('collection_at'), 'Asia/Manila')->timezone(config('app.timezone', 'UTC'))->toIso8601String()]);
            } catch (\Throwable $error) {
                throw \Illuminate\Validation\ValidationException::withMessages(['collection_at' => 'Enter a valid Philippine pickup date and time.']);
            }
        }
        $data = $request->validate([
            'action' => 'required|in:propose,quote,accept,decide,resolve_issue,dispute,comment,decline,destination,unlock_destination,reserve,schedule,ready,pay,replacement_dispatch,replacement_received,close_unused,complete_no_cash',
            'version' => 'required|integer|min:1',
            'fault' => 'required_if:action,propose|in:seller,buyer,logistics',
            'resolution' => 'required_if:action,propose|in:return_refund,refund_only,partial_refund',
            'payer' => 'nullable|in:seller,buyer,platform',
            'funding_method' => 'nullable|in:deposit,deduct_refund',
            'funding_preference' => 'nullable|in:auto,deposit,deduct_refund',
            'merchandise_amount' => 'required_if:action,propose|numeric|min:0|max:99999999|decimal:0,2',
            'shipping_amount' => 'nullable|numeric|min:0|max:99999999|decimal:0,2',
            'compensation_amount' => 'nullable|numeric|min:0|max:99999999|decimal:0,2',
            'replacement_variant_id' => 'nullable|integer|min:1',
            'replacement_description' => 'nullable|string|min:5|max:500',
            'note' => 'nullable|string|max:1500',
            'collection_center_id' => 'nullable|required_if:action,quote|integer|min:1',
            'seller_center_id' => 'nullable|required_if:action,quote|integer|min:1',
            'allocations' => 'required_if:action,quote|array|max:7',
            'allocations.*.role' => 'required_with:allocations|string',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0|max:1000000|decimal:0,2',
            'provider' => 'required_if:action,destination|string|max:100',
            'holder' => 'required_if:action,destination|string|max:150',
            'account' => 'required_if:action,destination|string|min:4|max:100',
            'collection_at' => 'required_if:action,schedule|date|after:now',
            'purpose' => 'required_if:action,pay|in:return_budget,refund_recovery',
            'amount' => 'required_if:action,pay|numeric|min:0.01|max:99999999|decimal:0,2',
            'reference' => 'required_if:action,pay,replacement_dispatch|string|max:150',
            'proof' => 'required_if:action,pay,replacement_dispatch|file|mimes:jpg,jpeg,png,webp|max:5120',
            'rider_id' => 'nullable|integer|min:1',
            'confirm' => 'required|accepted',
            'receipt_id' => 'nullable|integer|min:1', 'receipt_version'=>'nullable|integer|min:1',
        ]);
        $path = null;
        try {
            if ($request->hasFile('proof')) {
                $path = $request->file('proof')->store('return-settlement-proofs', 'private');
                $data['proof_path'] = $path;
            }
            if ($data['action'] === 'pay') ReturnSettlementService::submitPayment($returnRequest, $user, $data);
            else ReturnSettlementService::change($returnRequest, $user, $data['action'], $data);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('private')->delete($path);
            throw $error;
        }
        $message=$data['action']==='pay'?'Receipt submitted. The payment is awaiting admin verification.':'Return agreement updated. Review the current status below.';
        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('success', $message);
    }

    public function review(Request $request, ReturnFundPayment $payment)
    {
        abort_unless($this->admin($request->user()), 403);
        $data = $request->validate(['decision' => 'required|in:verify,reject', 'note' => 'required|string|min:10|max:1500', 'confirm' => 'required|accepted']);
        ReturnSettlementService::reviewPayment($payment, $request->user(), $data['decision'] === 'verify', $data['note']);
        return $request->expectsJson() ? response()->json(['message' => 'Deposit reviewed.']) : back()->with('success', 'Deposit reviewed.');
    }

    public function remittances(Request $request)
    {
        if(!$request->expectsJson() && !$request->boolean('refunds_only')){
            $actor=$request->user();
            if($actor instanceof User && !$actor->isAdmin() && !$actor->isLogisticsStaff() && $actor->role!=='rider'
                && ReturnAllocation::whereNotIn('role',\App\Support\ReturnRemittanceInbox::ROLES)->get()->contains(fn($line)=>$line->canAccept($actor)))
                return redirect()->route('returns.remittances',['refunds_only'=>1]);
            return redirect()->route('remittances.incoming');
        }
        $user = $request->user();
        abort_unless(($user instanceof User && $user->isActive()) || ($user instanceof HubTransportDriver && ! $user->isBlocked()), 403);
        $admin = $this->admin($user);
        $query = ReturnAllocation::with(['settlement.returnRequest.item']);
        if($request->boolean('refunds_only'))$query->whereNotIn('role',\App\Support\ReturnRemittanceInbox::ROLES);
        $filters=$request->validate(['return_id'=>'nullable|integer|min:1','delivery_only'=>'nullable|boolean','distributed_only'=>'nullable|boolean']);
        if($request->boolean('distributed_only'))$query->whereIn('status',['remitted','accepted'])->whereIn('role',\App\Support\ReturnRemittanceInbox::ROLES);
        if($filters['return_id']??null)$query->whereHas('settlement',fn($q)=>$q->where('return_request_id',$filters['return_id']));
        if($request->boolean('delivery_only'))$query->whereIn('role',ReturnAllocation::DELIVERY_ROLES);
        if (! $admin) {
            $ids = ReturnAllocation::get()->filter(fn ($line) => $line->canAccept($user))->pluck('id');
            $query->whereIn('id', $ids);
        }
        $ownTotal = (clone $query)->where('status', 'accepted')->whereIn('role', ReturnAllocation::ROLES)->get()->filter(fn ($line) => $line->canAccept($user))->sum('amount_cents');
        $awaiting=(clone $query)->where('status','remitted')->get()->filter(fn($line)=>$line->canAccept($user))->sum('amount_cents');
        $rows = $query->latest('id')->paginate(12)->withQueryString();
        if ($request->expectsJson()) return response()->json(['accepted_earnings_cents' => $ownTotal, 'awaiting_confirmation_cents'=>$awaiting, 'allocations' => $rows->through(function ($line) use ($user, $admin) {
            return $line->toArray() + ['can_accept' => $line->canAccept($user),
                'receiving_destination' => ($admin || $line->canAccept($user)) ? $line->receiving_destination : null];
        })])->header('Cache-Control', 'private, no-store');
        $layout = $this->layout($user);
        return response()->view('shared.returns.remittances', compact('rows', 'ownTotal', 'user', 'admin', 'layout'))->header('Cache-Control', 'private, no-store');
    }

    public function deliveryEscrow(Request $request)
    {
        abort_unless($this->admin($request->user()),403);
        return redirect('/admin/payments#escrow-remittances');
    }

    public function payout(Request $request, ReturnAllocation $allocation)
    {
        abort_if($this->blockedDriver($request->user()), 403);
        $data = $request->validate([
            'action' => 'required|in:destination,remit,release,accept', 'confirm' => 'required|accepted',
            'provider' => 'required_if:action,destination|string|max:100', 'holder' => 'required_if:action,destination|string|max:150',
            'account' => 'required_if:action,destination|string|min:4|max:100',
            'reference' => 'required_if:action,remit|string|min:4|max:150',
            'proof' => 'required_if:action,remit|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        abort_unless(in_array($data['action'],['remit','release'],true) ? $this->admin($request->user()) : $allocation->canAccept($request->user()), 403);
        $path = null;
        try {
            if ($request->hasFile('proof')) {
                abort_unless($this->admin($request->user()),403);
                $data['proof_path'] = $path = $request->file('proof')->store('return-remittance-proofs', 'private');
            }
            ReturnSettlementService::payout($allocation, $request->user(), $data['action'], $data);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('private')->delete($path);
            throw $error;
        }
        return $request->expectsJson() ? response()->json(['message' => 'Return remittance updated.']) : back()->with('success', 'Return remittance updated.');
    }

    public function proof(Request $request, string $kind, int $id)
    {
        $user = $request->user();
        abort_if($this->blockedDriver($user), 403);
        if ($kind === 'deposit') {
            $record = ReturnFundPayment::findOrFail($id);
            abort_unless($this->admin($user) || ($user instanceof User && $user->isActive() && (int) $record->payer_user_id === (int) $user->id), 403);
            $path = $record->proof_path;
        } elseif ($kind === 'remittance') {
            $record = ReturnAllocation::findOrFail($id);
            abort_unless($this->admin($user) || $record->canAccept($user), 403);
            $path = $record->proof_path;
        } elseif ($kind === 'replacement') {
            $record = \App\Models\ReturnSettlement::findOrFail($id);
            abort_unless(ReturnSettlementService::canView($user, $record->returnRequest), 403);
            $path = $record->replacement_proof_path;
        } elseif ($kind === 'custody') {
            $record = ReturnRequest::findOrFail($id);
            abort_unless(ReturnSettlementService::canView($user, $record), 403);
            $index = $request->validate(['event'=>'required|integer|min:0'])['event'];
            $event = ($record->workflow?->events ?? [])[$index] ?? [];
            abort_unless(in_array($event['action'] ?? '', ['pickup', 'deliver_seller','driver_arrived'], true), 404);
            $path = $event['proof_path'] ?? null;
        } else abort(404);
        abort_unless($path && Storage::disk('private')->exists($path), 404);
        if($kind==='deposit' && $this->admin($user))ReturnSettlementService::viewPaymentReceipt($record,$user);
        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
