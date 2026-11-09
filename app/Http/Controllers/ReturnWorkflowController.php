<?php

namespace App\Http\Controllers;

use App\Models\{ReturnRequest, Rider};
use App\Services\ReturnWorkflowService;
use App\Support\LogisticsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage,Schema};

class ReturnWorkflowController extends Controller
{
    public function riderJobs(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isActive() && $user->role === 'rider' && $user->rider, 403);
        $rows = ReturnRequest::with(['workflow', 'order', 'item'])->where('status', 'approved')
            ->whereHas('workflow', fn ($q) => $q->where('rider_id', $user->rider->id)->whereIn('stage', ['pickup_assigned', 'collected', 'sent_to_seller', 'seller_arrival']))->get();
        return response()->json(['returns' => $rows->map(function ($case) {
            $delivery = \App\Support\EscrowEarnings::deliveryForSeller($case->order, (int) $case->seller_id);
            return ['id' => $case->id, 'order_id' => $case->order_id, 'product' => $case->item?->product_name,
                'quantity' => $case->quantity, 'stage' => $case->workflow->stage, 'center_id' => $case->workflow->center_id,
                'buyer_name' => $delivery?->recipient_name, 'buyer_phone' => $delivery?->recipient_phone,
                'collection_address' => $delivery?->recipient_address, 'seller_address' => $delivery?->sender_address,
                'seller_name' => $delivery?->sender_name, 'seller_phone' => $delivery?->sender_phone,
                'amount_to_collect' => 0];
        })]);
    }

    public function riderPickup(Request $request, ReturnRequest $returnRequest)
    {
        abort_unless($request->user()->rider && (int) $returnRequest->workflow?->rider_id === (int) $request->user()->rider->id, 403);
        $managed = \App\Services\ReturnSettlementService::ensure($returnRequest);
        $data = $request->validate(['action' => 'nullable|in:pickup,deliver_seller', 'proof' => [($managed ? 'required' : 'nullable'), 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $path = null;
        try {
            if ($request->hasFile('proof')) $data['proof_path'] = $path = $request->file('proof')->store('return-handover-proofs', 'private');
            ReturnWorkflowService::change($returnRequest, $request->user(), $data['action'] ?? 'pickup', $data);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('private')->delete($path);
            throw $error;
        }
        return response()->json(['message' => 'Return custody recorded. The receiving party must confirm the handover.']);
    }

    private function canView($user, ReturnRequest $return): bool
    {
        if ($user->isAdmin() || (int) $return->buyer_id === (int) $user->id || (int) $return->seller_id === (int) $user->id) { return true; }
        $flow = $return->workflow;
        if (! $flow) { return false; }
        if ($user->isLogisticsAdmin()) { return true; }
        if ($user->role === 'rider') { return $user->rider && (int) $flow->rider_id === (int) $user->rider->id; }
        return $user->isLogisticsStaff() && (LogisticsAccess::canAccessCenter($user, $flow->center_id)
            || ($flow->stage === 'in_transfer' && LogisticsAccess::canAccessCenter($user, $flow->destination_center_id)));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isActive(), 403);
        $query = ReturnRequest::with(['workflow.rider','workflow.center', 'order', 'item', 'buyer']);
        if (! $user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
                if ($user->role === 'rider' && $user->rider) {
                    $q->orWhereHas('workflow', fn ($w) => $w->where('rider_id', $user->rider->id));
                } elseif ($user->isLogisticsAdmin()) {
                    $q->orWhereHas('workflow');
                } elseif ($user->isLogisticsStaff()) {
                    $centers = \App\Models\LogisticsCenter::all()->filter(fn ($c) => LogisticsAccess::canAccessCenter($user, $c->id))->pluck('id');
                    $q->orWhereHas('workflow', fn ($w) => $w->whereIn('center_id', $centers)
                        ->orWhere(fn ($t) => $t->where('stage', 'in_transfer')->whereIn('destination_center_id', $centers)));
                }
            });
        }
        $returns = $query->latest()->paginate(12);
        $escrowIssues = $user->isAdmin() ? \App\Models\Order::whereHas('payment', fn ($q) => $q->where('method', 'gcash'))
            ->whereIn('status', ['confirmed', 'processing', 'ready_for_delivery', 'shipped', 'out_for_delivery', 'delivered'])
            ->whereDoesntHave('gcashEscrowDeposit', fn ($q) => $q->where('status', 'funded')->whereNotNull('confirmed_at'))
            ->select(['id', 'order_number', 'status'])->get() : collect();
        $layout = $user->isAdmin() ? 'admin.layout' : ($user->isLogisticsStaff() || $user->role === 'rider'
            ? 'logistics.layouts.app' : ($user->isSeller() ? 'seller.layouts.app' : 'buyer.layouts.website'));
        return view('shared.returns.index', compact('returns', 'layout', 'user', 'escrowIssues'));
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        abort_unless($this->canView($request->user(), $returnRequest), 403);
        $data = $request->validate([
            'action' => 'required|in:route_hub,assign,pickup,intake,transfer,receive_transfer,dispatch_seller,deliver_seller,receive,refund,confirm_refund',
            'rider_id' => 'nullable|integer|min:1', 'destination_center_id' => 'nullable|integer|min:1',
            'driver_id' => 'nullable|integer|min:1',
            'reference' => 'nullable|string|max:150', 'restock' => 'nullable|boolean',
            'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'recovery_amount' => 'nullable|numeric|min:0|max:9999999999', 'recovery_reference' => 'nullable|string|max:150',
            'recovery_proof' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'confirm_transfer' => 'exclude_unless:action,refund|required|accepted',
        ]);
        // Only the marketplace admin handles money and private transfer files.
        $agreement=Schema::hasTable('return_settlements')?$returnRequest->settlement:null;
        $sellerDirect=$agreement?->buyerFaultDirect() && (int)$request->user()->id===(int)$returnRequest->seller_id;
        if ($data['action'] === 'refund') { abort_unless($agreement?->buyerFaultDirect() ? $sellerDirect : $request->user()->isAdmin(), 403); }
        $paths = [];
        try {
            foreach (['proof' => 'proof_path', 'recovery_proof' => 'recovery_proof_path'] as $field => $key) {
                if ($request->hasFile($field)) {
                    abort_unless((($request->user()->isAdmin() || ($sellerDirect && $field==='proof')) && $data['action'] === 'refund')
                        || ($field === 'proof' && in_array($data['action'], ['pickup', 'deliver_seller']) && $request->user()->role === 'rider'), 403);
                    $data[$key] = $request->file($field)->store('refund-proofs', 'private');
                    $paths[] = $data[$key];
                }
            }
            ReturnWorkflowService::change($returnRequest, $request->user(), $data['action'], $data);
        } catch (\Throwable $e) {
            foreach ($paths as $path) { Storage::disk('private')->delete($path); }
            throw $e;
        }
        return $request->expectsJson() ? response()->json(['message' => 'Return updated.', 'return' => $returnRequest->fresh()])
            : back()->with('success', 'Return updated.');
    }

    public function proof(Request $request, ReturnRequest $returnRequest)
    {
        abort_unless($request->user()->isActive() && ($request->user()->isAdmin() || (int) $returnRequest->buyer_id === (int) $request->user()->id
            || (Schema::hasTable('return_settlements') && $returnRequest->settlement?->buyerFaultDirect() && (int)$returnRequest->seller_id===(int)$request->user()->id)), 403);
        $path = $returnRequest->workflow?->refund_proof_path;
        abort_unless($path && Storage::disk('private')->exists($path), 404);
        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
