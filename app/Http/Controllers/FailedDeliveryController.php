<?php

namespace App\Http\Controllers;

use App\Models\{Delivery, FailedDeliveryCase, FailedDeliveryFee, FailedDeliveryPayment, HubTransportDriver, LogisticsTruck, Rider, User};
use App\Services\{FailedDeliveryService, ReturnRouting};
use App\Support\{LogisticsAccess, PayoutAccounts};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema, Storage};

class FailedDeliveryController extends Controller
{
    private function actor(Request $request)
    {
        $actor = $request->user();
        abort_unless(($actor instanceof User && $actor->isActive())
            || ($actor instanceof HubTransportDriver && ! $actor->isBlocked() && (! $actor->user || $actor->user->isActive())), 403);
        abort_unless(FailedDeliveryService::available(), 503, 'Failed-parcel resolution is not available yet. Contact logistics support.');
        return $actor;
    }

    public function index(Request $request, FailedDeliveryService $service)
    {
        $actor = $this->actor($request);
        $query = FailedDeliveryCase::with(['delivery', 'fees'])->latest();
        if (! ($actor instanceof User && $actor->isAdmin())) {
            $centers = $actor instanceof User && $actor->isLogisticsStaff()
                ? ReturnRouting::options()->filter(fn ($hub) => LogisticsAccess::canAccessCenter($actor, $hub->id))->pluck('id') : collect();
            $driverId = $actor instanceof HubTransportDriver ? $actor->id
                : ($actor instanceof User && Schema::hasTable('hub_transport_drivers') ? $actor->hubTransportDriver?->id : null);
            $query->where(function ($q) use ($actor, $centers, $driverId) {
                $q->whereRaw('1 = 0');
                if ($actor instanceof User) {
                    $q->orWhere('seller_id', $actor->id)->orWhereHas('fees', fn ($f) => $f->where('recipient_user_id', $actor->id));
                    if ($actor->role === 'rider') $q->orWhere('return_rider_id', $actor->rider?->id ?? 0);
                }
                if ($centers->isNotEmpty()) $q->orWhereIn('center_id', $centers)->orWhereIn('seller_center_id', $centers);
                if ($driverId) $q->orWhere('return_driver_id', $driverId);
            });
        }
        $cases = $query->paginate(20);
        if ($request->expectsJson()) return response()->json(['cases' => $cases->getCollection()->map(fn ($case) => $this->payload($case, $actor)),
            'current_page' => $cases->currentPage(), 'last_page' => $cases->lastPage()])->header('Cache-Control', 'private, no-store');
        return response()->view('shared.failed-deliveries.index', compact('cases'))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Delivery $delivery, FailedDeliveryService $service)
    {
        $actor = $this->actor($request); $case = FailedDeliveryService::caseFor($delivery);
        if (! $case) {
            abort_unless($delivery->deliveryAttemptsExhausted() && $delivery->returned_at && $actor instanceof User
                && ((int) $delivery->seller_id === (int) $actor->id || LogisticsAccess::canAccessDelivery($actor, $delivery)), 403);
        } else abort_unless($service->canView($case, $actor), 403);
        $seller = $actor instanceof User && $actor->isSeller() && (int) $delivery->seller_id === (int) $actor->id;
        $admin = $actor instanceof User && $actor->isAdmin();
        $staff = $actor instanceof User && LogisticsAccess::canProcessParcels($actor);
        $settings = ($seller || $admin) && Schema::hasTable('gcash_escrow_settings') ? DB::table('gcash_escrow_settings')->first() : null;
        $due = $case ? $service->outstanding($case) : 0;
        $centers = $staff ? ReturnRouting::options() : collect();
        $riders = $staff && $case ? Rider::where('center_id', $case->center_id)->whereNotNull('approved_at')->get() : collect();
        $trucks = $staff && $case && Schema::hasTable('logistics_trucks') ? LogisticsTruck::where('logistics_company_id', $case->center->logistics_company_id)->get() : collect();
        if ($request->expectsJson()) return response()->json(['case' => $case ? $this->payload($case, $actor) : null,
            'outstanding_cents' => ($seller || $admin) ? $due : null])->header('Cache-Control', 'private, no-store');
        return response()->view('shared.failed-deliveries.show', compact('case', 'delivery', 'actor', 'seller', 'admin', 'staff', 'settings', 'due', 'centers', 'riders', 'trucks'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Delivery $delivery, FailedDeliveryService $service)
    {
        $actor = $this->actor($request);
        $data = $request->validate(['action' => 'required|string|in:open,decide,quote,accept_quote,deposit,review_payment,driver_accept,depart,arrive,receive,assign_rider,rider_collect,seller_arrival,seller_receive',
            'decision' => 'sometimes|in:return,unclaimed', 'version' => 'sometimes|integer|min:1',
            'seller_center_id' => 'nullable|integer', 'truck_id' => 'nullable|integer', 'rider_id' => 'nullable|integer',
            'payment_id' => 'sometimes|integer', 'approved' => 'sometimes|boolean', 'reference' => 'nullable|string|max:150',
            'note' => 'nullable|string|max:1000', 'proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'confirm_unclaimed' => 'sometimes|accepted', 'confirm_quote' => 'sometimes|accepted', 'confirm_verified' => 'sometimes|accepted']);
        if ($data['action'] === 'open') {
            abort_unless($actor instanceof User && ((int) $delivery->seller_id === (int) $actor->id
                || (LogisticsAccess::canProcessParcels($actor) && LogisticsAccess::canAccessDelivery($actor, $delivery))), 403);
            abort_unless($service->open($delivery), 409);
        } else {
            $case = FailedDeliveryService::caseFor($delivery); abort_unless($case && $service->canView($case, $actor), 403);
            $path = null;
            try {
                if ($request->hasFile('proof')) {
                    if (in_array($data['action'], ['arrive', 'seller_arrival'], true)) $request->validate(['proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192']);
                    $data['proof_path'] = $path = $request->file('proof')->store('failed-delivery-proofs', 'private');
                }
                $service->act($delivery, $actor, $data['action'], $data);
            } catch (\Throwable $e) {
                if ($path) Storage::disk('private')->delete($path);
                throw $e;
            }
        }
        return $request->expectsJson() ? response()->json(['message' => 'Failed-delivery resolution updated.'])
            : redirect()->route('failed-deliveries.show', $delivery)->with('success', 'Failed-delivery resolution updated.');
    }

    public function payout(Request $request, FailedDeliveryFee $fee, FailedDeliveryService $service)
    {
        $actor = $this->actor($request);
        abort_unless($actor instanceof User && $actor->isAdmin(), 403);
        $data = $request->validate(['reference' => 'required|string|max:150', 'proof' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192', 'confirm_sent' => 'required|accepted']);
        $path = $request->file('proof')->store('failed-delivery-proofs', 'private');
        try { $service->payout($fee, $actor, false, $data + ['proof_path' => $path]); }
        catch (\Throwable $e) { Storage::disk('private')->delete($path); throw $e; }
        return $request->expectsJson() ? response()->json(['message' => 'Transfer recorded. Recipient must confirm receipt.']) : back()->with('success', 'Transfer recorded. Recipient must confirm receipt.');
    }

    public function proof(Request $request, string $kind, int $id, FailedDeliveryService $service)
    {
        $actor = $this->actor($request);
        if ($kind === 'deposit') {
            $record = FailedDeliveryPayment::findOrFail($id);
            abort_unless($actor instanceof User && ($actor->isAdmin() || (int) $actor->id === (int) $record->payer_user_id), 403);
            $path = $record->proof_path;
        } elseif ($kind === 'payout') {
            $record = FailedDeliveryFee::findOrFail($id);
            abort_unless(($actor instanceof User && $actor->isAdmin()) || $record->canAccept($actor), 403);
            $path = $record->proof_path;
        } else {
            $record = FailedDeliveryCase::findOrFail($id); abort_unless($service->canViewHandover($record, $actor), 403);
            $path = match($kind) {'driver' => $record->driver_proof_path, 'seller' => $record->seller_proof_path, default => $record->handover_proof_path};
        }
        abort_unless($path && Storage::disk('private')->exists($path), 404);
        if ($kind === 'deposit' && $actor instanceof User && $actor->isAdmin() && $record->status === 'submitted') {
            $record->update(['receipt_viewed_by' => $actor->id, 'receipt_viewed_at' => now()]);
        }
        return Storage::disk('private')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function payload(FailedDeliveryCase $case, $actor): array
    {
        $driverId = $actor instanceof HubTransportDriver ? $actor->id
            : ($actor instanceof User && Schema::hasTable('hub_transport_drivers') ? $actor->hubTransportDriver?->id : null);
        $ownsRider = $actor instanceof User && (int) $case->rider?->user_id === (int) $actor->id;
        $sellerDetails = $ownsRider || ($actor instanceof User && ($actor->isAdmin() || $actor->isLogisticsStaff() || (int) $actor->id === (int) $case->seller_id));
        return $case->only(['id', 'delivery_id', 'decision', 'stage', 'center_id', 'seller_center_id', 'return_rider_id', 'return_driver_id'])
            + ['tracking_number' => $case->delivery->tracking_number, 'seller_name' => $sellerDetails ? $case->delivery->sender_name : null,
                'seller_address' => $sellerDetails ? $case->delivery->sender_address : null, 'funded' => $case->funded_at !== null,
                'center_name' => $case->center?->name, 'seller_center_name' => $case->sellerCenter?->name,
                'driver_accepted' => $case->driver_accepted_at !== null,
                'is_driver' => $driverId && (int) $driverId === (int) $case->return_driver_id,
                'is_rider' => $ownsRider, 'center_address' => $case->center?->address,
                'seller_center_address' => $case->sellerCenter?->address];
    }
}
