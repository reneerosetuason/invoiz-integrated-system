<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Logistics\TransactionController;
use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\DeliveryProof;
use App\Models\Notification;
use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Services\DeliveryOtpService;
use App\Services\ShipmentExceptionService;
use App\Support\Cod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class RiderDeliveryController extends Controller
{
    private const ACTIVE_STATUSES = [
        'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop',
        'picked_up', 'out_for_delivery', 'arrived_at_customer',
    ];

    private const TRANSITIONS = [
        'assigned' => ['accepted', 'cancelled'],
        'accepted' => ['going_to_pickup'],
        'going_to_pickup' => ['arrived_at_shop', 'picked_up'],
        'arrived_at_shop' => ['picked_up'],
        'picked_up' => ['out_for_delivery'],
        'out_for_delivery' => ['arrived_at_customer'],
        'arrived_at_customer' => ['delivered'],
    ];

    private const PRE_PICKUP_STATUSES = [
        'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop',
    ];


    /**
     * List the rider's deliveries, optionally filtered.
     */
    public function index(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $validated = $request->validate([
            'status' => 'sometimes|string|in:new,accepted,pickup,in_transit,delivered,failed,cancelled,all',
        ]);

        $query = Delivery::with(['items', 'rider', 'order:id,buyer_id'])->where('rider_id', $rider->id);


        switch ($validated['status'] ?? 'all') {
            case 'new':
                $query->where('status', 'assigned');
                break;
            case 'accepted':
                $query->where('status', 'accepted');
                break;
            case 'pickup':
                $query->whereIn('status', ['accepted', 'going_to_pickup', 'arrived_at_shop']);
                break;
            case 'in_transit':
                $query->whereIn('status', ['picked_up', 'out_for_delivery', 'arrived_at_customer']);
                break;
            case 'delivered':
                $query->where('status', 'delivered');
                break;
            case 'failed':
                $query->whereIn('status', ['delivery_failed', 'lost', 'damaged']);
                break;
            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
        }

        $deliveries = $query->latest()->paginate(20);

        return response()->json([
            'deliveries' => collect($deliveries->items())
                ->map(fn ($d) => $this->payload($d))->values(),
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'total' => $deliveries->total(),
                'per_page' => $deliveries->perPage(),
            ],
        ]);
    }

    /**
     * Show a delivery assigned to the rider.
     */
    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        $delivery->load([
            'items',
            'statusLogs' => fn ($q) => $q->latest(),
            'proof',
            'failure',
            'logisticsCenter',
            'destinationCenter',
            'serviceArea',
            'order:id,buyer_id',
        ]);

        return response()->json([
            'delivery' => $this->detailPayload($delivery),
        ]);
    }

    /**
     * Find the rider's delivery by shipping-label tracking number.
     *
     * Lookup only: scanning never assigns, claims, or mutates anything.
     * Authorization is identical to show() — the delivery must already be
     * assigned to the authenticated rider. A scanned QR code is an
     * identifier, never permission.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:40'],
        ]);

        $delivery = Delivery::where(
            'tracking_number',
            strtoupper(trim($validated['tracking_number']))
        )->first();

        abort_if($delivery === null, 404, 'No parcel found for this tracking number.');

        $this->authorizeDelivery($request, $delivery);

        $delivery->load([
            'items',
            'statusLogs' => fn ($q) => $q->latest(),
            'proof',
            'failure',
            'logisticsCenter',
            'destinationCenter',
            'serviceArea',
            'order:id,buyer_id',
        ]);

        return response()->json([
            'delivery' => $this->detailPayload($delivery),
        ]);
    }

    /**
     * Accept an assigned delivery.
     */
    public function accept(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        if ($blocked = $this->prePickupBlock($request->user()->rider)) {
            return $blocked;
        }

        if (! in_array('accepted', self::TRANSITIONS[$delivery->status] ?? [])) {
            return $this->invalidTransition($delivery);
        }

        $delivery->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->logStatus($delivery, 'accepted', 'Delivery accepted by rider.');
        $this->markRiderDelivering($delivery->rider);

        return response()->json([
            'message' => 'Delivery accepted.',
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Generic status update (enforces the state machine).
     */
    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        $validated = $request->validate([
            'status' => 'required|string|in:going_to_pickup,arrived_at_shop,out_for_delivery,arrived_at_customer',
        ]);

        $next = $validated['status'];

        if (in_array($delivery->status, self::PRE_PICKUP_STATUSES, true)) {
            if ($blocked = $this->prePickupBlock($request->user()->rider)) {
                return $blocked;
            }
        }

        $allowed = self::TRANSITIONS[$delivery->status] ?? [];
        // Sorting-center pickup flow: after the delivery rider has collected the
        // parcel from the center, the next step is out_for_delivery even
        // though the generic seller-pickup graph only allows assigned->accepted.
        if ($next === 'out_for_delivery' && in_array($delivery->status, ['assigned', 'accepted'], true) && $delivery->sorting_center_pickup_at !== null) {
            $allowed[] = 'out_for_delivery';
        }

        if (! in_array($next, $allowed)) {
            return $this->invalidTransition($delivery);
        }

        $delivery->update(['status' => $next]);

        if (in_array($next, DeliveryOtpService::ELIGIBLE_STATUSES, true)) {
            app(DeliveryOtpService::class)->ensureFor($delivery);
        }

        $this->logStatus($delivery, $next, $this->transitionNote($next));

        return response()->json([
            'message' => 'Delivery status updated.',
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }


    /**
     * Confirm pickup at the shop (optionally PIN-verified).
     */
    public function pickup(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        if ($blocked = $this->prePickupBlock($request->user()->rider)) {
            return $blocked;
        }

        if (! in_array($delivery->status, ['going_to_pickup', 'arrived_at_shop'])) {
            return $this->invalidTransition($delivery);
        }

        $validated = $request->validate([
            'pickup_pin' => 'sometimes|string|max:10',
        ]);

        if (! empty($delivery->pickup_pin)) {
            if (empty($validated['pickup_pin']) || $validated['pickup_pin'] !== $delivery->pickup_pin) {
                return response()->json([
                    'message' => 'Invalid pickup PIN. Please verify the PIN with the shop.',
                    'errors' => ['pickup_pin' => ['The pickup PIN is incorrect.']],
                ], 422);
            }
        }

        $delivery->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        $this->logStatus($delivery, 'picked_up', 'Package picked up from shop.');

        return response()->json([
            'message' => 'Pickup confirmed.',
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Complete the delivery with optional proof of delivery + COD settlement.
     */
    public function complete(
        Request $request,
        Delivery $delivery,
        DeliveryOtpService $otpService,
    ): JsonResponse {
        $this->authorizeDelivery($request, $delivery);

        if (! in_array($delivery->status, ['out_for_delivery', 'arrived_at_customer'])) {
            return $this->invalidTransition($delivery);
        }

        $otpRequired = $otpService->requiredFor($delivery);

        $validated = $request->validate([
            'proof_type' => 'sometimes|string|in:photo,signature,otp',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:8192',
            'signature_name' => 'sometimes|string|max:255',
            'otp' => $otpRequired ? 'required|string|digits:6' : 'sometimes|string|max:10',
            'amount_received' => 'sometimes|numeric|min:0',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
        ], $otpRequired ? [
            'otp.required' => 'Enter the 6-digit delivery OTP provided by the buyer.',
            'otp.digits' => 'The delivery OTP must be exactly 6 digits.',
        ] : []);


        if (Cod::isCod($delivery)) {
            $expected = Cod::expected($delivery);

            if ($expected === null) {
                return response()->json([
                    'message' => 'The Cash on Delivery amount could not be determined for this delivery.',
                    'errors' => ['amount_received' => ['The Cash on Delivery amount is not configured.']],
                ], 422);
            }

            if (! array_key_exists('amount_received', $validated) || $validated['amount_received'] === null || $validated['amount_received'] === '') {
                return response()->json([
                    'message' => 'Please record the amount received from the customer (Cash on Delivery).',
                    'errors' => ['amount_received' => ['The amount received is required for COD deliveries.']],
                ], 422);
            }

            // The rider records the actual cash collected. Short or excess
            // amounts are accepted as-is; the variance against the expected
            // amount is written to the audit trail and surfaces through
            // Cod::reconcile for staff reconciliation, matching the web flow.
        }

        $proofPath = null;

        try {
            $result = DB::transaction(function () use ($request, $delivery, $validated, $otpService, $otpRequired, &$proofPath) {
                $locked = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();

                $this->authorizeDelivery($request, $locked);

                if (! in_array($locked->status, ['out_for_delivery', 'arrived_at_customer'], true)) {
                    return ['conflict' => $this->invalidTransition($locked)];
                }

                $otpResult = $otpService->verify($locked, $validated['otp'] ?? null);

                if (! $otpResult['ok']) {
                    return ['otp_error' => $this->otpFailure($otpResult)];
                }

                if (! empty($validated['photo'])) {
                    $proofPath = $request->file('photo')->store('delivery-proofs', 'public');
                }

                if (! empty($validated['proof_type']) || ! empty($validated['photo']) || $locked->payment_method === 'cash_on_delivery') {
                    DeliveryProof::create([
                        'delivery_id' => $locked->id,
                        'rider_id' => $locked->rider_id,
                        'type' => $validated['proof_type'] ?? ($proofPath ? 'photo' : ($locked->payment_method === 'cash_on_delivery' ? 'signature' : 'photo')),
                        'file_path' => $proofPath,
                        'signature_name' => $validated['signature_name'] ?? $locked->recipient_name,
                        'otp' => $otpRequired ? null : ($validated['otp'] ?? null),
                        'latitude' => $validated['latitude'] ?? null,
                        'longitude' => $validated['longitude'] ?? null,
                        'verified_at' => now(),
                    ]);
                }

                $otpService->consume($locked);

                $isCod = Cod::isCod($locked);
                $codExpected = $isCod ? Cod::expected($locked) : null;
                $codReceived = $isCod ? round((float) $validated['amount_received'], 2) : null;

                $updates = [
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ];

                if ($isCod) {
                    $updates['amount_to_collect'] = $locked->amount_to_collect ?? $codExpected;
                    $updates['cod_amount_received'] = $codReceived;
                    $updates['cod_received_at'] = now();
                }

                $locked->update($updates);

                $this->logStatus($locked, 'delivered', 'Package delivered to customer.');

                // Same per-delivery payout rule used by TransactionController:
                // the delivery fee when set, otherwise the flat shipping fee.
                $earned = round((float) ($locked->delivery_fee !== null
                    ? $locked->delivery_fee
                    : Order::SHIPPING_FEE_PER_SELLER), 2);

                // Logistics owns the ledger and remittance records. Any failure
                // here must roll back the delivery transition and status log.
                app(TransactionController::class)->storeForDelivery($locked);
                \App\Support\Remittances::ensureForDeliveryOrFail($locked->fresh());

                RiderEarning::firstOrCreate(
                    ['delivery_id' => $locked->id, 'type' => 'delivery'],
                    [
                        'rider_id' => $locked->rider_id,
                        'amount' => $earned,
                        'earned_on' => now()->toDateString(),
                        'description' => "Delivery {$locked->tracking_number}",
                    ]
                );

                if ($isCod) {
                    app(\App\Support\AuditLogger::class)->record(
                        $request->user(),
                        'cod.collected',
                        'delivery',
                        $locked->id,
                        [],
                        [
                            'amount_to_collect' => $codExpected,
                            'amount_received' => $codReceived,
                            'variance' => round((float) $codReceived - (float) $codExpected, 2),
                        ],
                        null,
                        [
                            'order_id' => $locked->order_id,
                            'rider_id' => $locked->rider_id,
                            'tracking_number' => $locked->tracking_number,
                        ],
                    );
                }

                return ['delivery' => $locked, 'earned' => $earned];
            }, 3);
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            report($e);

            return response()->json([
                'message' => 'Delivery completion failed. No changes were saved.',
            ], 500);
        }

        if (isset($result['otp_error'])) {
            return $result['otp_error'];
        }

        if (isset($result['conflict'])) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            return $result['conflict'];
        }

        /** @var Delivery $delivery */
        $delivery = $result['delivery'];
        $earned = $result['earned'];

        try {
            $this->markRiderAvailableIfIdle($delivery->rider);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Delivery completed successfully.',
            'earned' => $earned,
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Handoff parcel to the sorting center (pickup rider leg).
     *
     * Only the rider who currently owns the delivery (rider_id) and whose
     * delivery is in picked_up may hand over. Idempotent: duplicate is 409.
     */
    public function sortingCenterHandoff(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        // Must have already picked up from seller; preserves seller-pickup history.
        if ($delivery->status !== 'picked_up') {
            return response()->json([
                'message' => 'Parcel must be picked up from the seller before it can be handed over to the sorting center.',
                'errors' => ['status' => ["Invalid status transition from '{$delivery->status}'."]],
            ], 409);
        }

        if ($delivery->sorting_center_handoff_at !== null) {
            return response()->json([
                'message' => 'Parcel has already been handed over to the sorting center.',
                'errors' => ['sorting_center_handoff_at' => ['Already handed over.']],
            ], 409);
        }

        if (in_array($delivery->status, ['delivered', 'delivery_failed', 'cancelled'], true)) {
            return $this->invalidTransition($delivery);
        }

        // Center scoping: if delivery already has a handling center, rider must belong to it.
        $rider = $request->user()->rider;
        if ($delivery->center_id !== null && $rider->center_id !== null && (int) $delivery->center_id !== (int) $rider->center_id) {
            return response()->json([
                'message' => 'This parcel belongs to a different logistics center.',
                'errors' => ['center_id' => ['Wrong logistics center.']],
            ], 403);
        }

        DB::transaction(function () use ($delivery, $rider) {
            $delivery->update([
                'sorting_center_handoff_at' => now(),
                'sorting_center_handoff_rider_id' => $rider->id,
                // Make parcel visible to the rider's center if handling center not yet set.
                'center_id' => $delivery->center_id ?? $rider->center_id,
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => 'sorting_center_handoff',
                'notes' => 'Parcel handed over to sorting center by ' . $rider->name . '.',
            ]);
        });

        return response()->json([
            'message' => 'Parcel handed over to sorting center.',
            'delivery' => $this->detailPayload($delivery->fresh()->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Pick up parcel from the sorting center (delivery rider leg).
     *
     * Requires: sorting center has received/scanned/sorted, parcel is assigned
     * to the requesting rider, ready for dispatch, and not already picked up.
     */
    public function sortingCenterPickup(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        if ($blocked = $this->prePickupBlock($request->user()->rider)) {
            return $blocked;
        }

        if ($delivery->sorting_center_handoff_at === null) {
            return response()->json([
                'message' => 'Parcel has not yet been handed over to the sorting center.',
                'errors' => ['sorting_center_handoff_at' => ['Handoff not yet recorded.']],
            ], 409);
        }

        if ($delivery->sorting_center_pickup_at !== null) {
            return response()->json([
                'message' => 'Parcel has already been picked up from the sorting center.',
                'errors' => ['sorting_center_pickup_at' => ['Already picked up from sorting center.']],
            ], 409);
        }

        if ($delivery->parcel_status !== 'sorted') {
            return response()->json([
                'message' => 'Parcel must be received, scanned and sorted before it can be picked up from the sorting center.',
                'errors' => ['parcel_status' => ["Parcel status is '{$delivery->parcel_status}', expected 'sorted'."]],
            ], 409);
        }

        if ($delivery->parcel_status === 'dispatched') {
            return response()->json([
                'message' => 'Parcel has already been dispatched.',
                'errors' => ['parcel_status' => ['Already dispatched.']],
            ], 409);
        }

        if (! in_array($delivery->status, ['assigned', 'accepted'], true)) {
            return response()->json([
                'message' => 'Parcel must be assigned to you before it can be picked up from the sorting center.',
                'errors' => ['status' => ["Invalid status '{$delivery->status}' for sorting center pickup."]],
            ], 409);
        }

        if (in_array($delivery->status, ['delivered', 'delivery_failed', 'cancelled'], true)) {
            return $this->invalidTransition($delivery);
        }

        $rider = $request->user()->rider;

        // Parcel must be assigned specifically to this rider (authorizeDelivery already guarantees rider_id match).

        DB::transaction(function () use ($delivery, $rider) {
            $now = now();
            $delivery->update([
                'sorting_center_pickup_at' => $now,
                'sorting_center_pickup_rider_id' => $rider->id,
                'parcel_status' => 'dispatched',
                'dispatched_at' => $delivery->dispatched_at ?? $now,
            ]);

            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => 'sorting_center_pickup',
                'notes' => 'Parcel picked up from sorting center by ' . $rider->name . '.',
            ]);

            // Also record canonical dispatched log if not already present (keeps web pipeline consistent).
            if (! DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'dispatched')->exists()) {
                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'dispatched',
                    'notes' => 'Parcel dispatched from handling center (via rider pickup).',
                ]);
            }
        });

        return response()->json([
            'message' => 'Parcel picked up from sorting center.',
            'delivery' => $this->detailPayload($delivery->fresh()->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Report a failed delivery.
     */
    public function failed(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        if (! in_array($delivery->status, ['picked_up', 'out_for_delivery', 'arrived_at_customer'])) {
            return $this->invalidTransition($delivery);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $delivery->update([
            'status' => 'delivery_failed',
            'failed_at' => now(),
            'failure_reason' => $validated['reason'],
            // A fresh failure starts a fresh next-step cycle.
            'reattempt_requested_at' => null,
            'returned_at' => null,
        ]);

        DeliveryFailure::create([
            'delivery_id' => $delivery->id,
            'rider_id' => $delivery->rider_id,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'reported_at' => now(),
        ]);

        $this->logStatus($delivery, 'delivery_failed', 'Delivery failed: ' . $validated['reason']);

        $this->markRiderAvailableIfIdle($delivery->rider);

        return response()->json([
            'message' => 'Failed delivery reported.',
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Report a lost or damaged parcel.
     */
    public function reportException(
        Request $request,
        Delivery $delivery,
        ShipmentExceptionService $exceptions,
    ): JsonResponse {
        $this->authorizeDelivery($request, $delivery);

        $validated = $request->validate([
            'type' => 'required|string|in:lost,damaged',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:8192',
        ]);

        if ($validated['type'] === 'damaged' && ! $request->hasFile('photo')) {
            return response()->json([
                'message' => 'A photo is required when reporting a damaged parcel.',
                'errors' => ['photo' => ['A photo is required when reporting a damaged parcel.']],
            ], 422);
        }

        $delivery = $exceptions->report(
            $delivery,
            $validated['type'],
            $validated['reason'],
            $validated['notes'] ?? null,
            $request->file('photo'),
            $request->user(),
            'rider',
        );

        $this->markRiderAvailableIfIdle($delivery->rider);

        return response()->json([
            'message' => ucfirst($validated['type']).' parcel reported.',
            'delivery' => $this->detailPayload($delivery->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Request a re-attempt of a failed delivery.
     *
     * The delivery record, tracking number, and failure history are
     * preserved; status stays delivery_failed. Logistics reassigns through
     * the existing assign flow (failed → assigned, gated by max_attempts /
     * allow_reassignment). One request per failure cycle; the flag clears
     * on (re)assignment or on each fresh failure.
     */
    public function requestReattempt(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        $result = DB::transaction(function () use ($request, $delivery) {
            // Re-read under a row lock so concurrent requests serialise: the
            // loser observes the committed flag and is rejected instead of
            // writing a duplicate status log and notification. Guard order
            // (state, then this-cycle duplicates) is unchanged.
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            // Re-check ownership against the locked row: a concurrent
            // reassignment must not let a stale request still write.
            $this->authorizeDelivery($request, $locked);

            if ($locked->status !== 'delivery_failed') {
                return ['conflict' => $this->invalidTransition($locked)];
            }
            if ($locked->reattempt_requested_at !== null) {
                return ['conflict' => response()->json([
                    'message' => 'A re-attempt has already been requested for this delivery.',
                    'errors' => ['reattempt' => ['A re-attempt has already been requested for this delivery.']],
                ], 409)];
            }
            if ($locked->returned_at !== null) {
                return ['conflict' => response()->json([
                    'message' => 'This parcel was already returned to the sorting center.',
                    'errors' => ['reattempt' => ['This parcel was already returned to the sorting center.']],
                ], 409)];
            }

            $locked->update(['reattempt_requested_at' => now()]);

            $this->logStatus($locked, 'reattempt_requested', 'Rider requested a delivery re-attempt.');

            Notification::create([
                'type' => 'reattempt_requested',
                'title' => 'Re-attempt Requested',
                'message' => "Rider requested a re-attempt for {$locked->tracking_number}.",
                'icon' => '🔁',
                'priority' => 'normal',
                'center_id' => $locked->center_id,
                'audience' => $locked->center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $locked),
            ]);

            return ['delivery' => $locked];
        });

        if (isset($result['conflict'])) {
            return $result['conflict'];
        }

        return response()->json([
            'message' => 'Re-attempt requested. Logistics will review and reassign the delivery.',
            'delivery' => $this->detailPayload($result['delivery']->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    /**
     * Return a failed parcel to the sorting center.
     *
     * The parcel becomes visible to the center (returned_at) with the
     * failure reason preserved; tracking, order, and history are untouched.
     * Returning supersedes a pending re-attempt request. One return per
     * failure cycle.
     */
    public function returnToCenter(Request $request, Delivery $delivery): JsonResponse
    {
        $this->authorizeDelivery($request, $delivery);

        $result = DB::transaction(function () use ($request, $delivery) {
            // Row lock serialises concurrent returns; see requestReattempt().
            $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            $this->authorizeDelivery($request, $locked);

            if (! in_array($locked->status, ['delivery_failed', 'damaged'], true)) {
                return ['conflict' => $this->invalidTransition($locked)];
            }
            if ($locked->returned_at !== null) {
                return ['conflict' => response()->json([
                    'message' => 'This parcel was already returned to the sorting center.',
                    'errors' => ['return' => ['This parcel was already returned to the sorting center.']],
                ], 409)];
            }

            $locked->update([
                'returned_at' => now(),
                // The failure reason is preserved verbatim as the return reason.
                'return_reason' => $locked->failure_reason,
                // Returning supersedes any pending re-attempt request.
                'reattempt_requested_at' => null,
            ]);

            $this->logStatus($locked, 'returned_to_center', 'Parcel returned to the sorting center.');

            Notification::create([
                'type' => 'parcel_returned_to_center',
                'title' => 'Parcel Returned to Center',
                'message' => "{$locked->tracking_number} was returned to the sorting center.",
                'icon' => '🏢',
                'priority' => 'normal',
                'center_id' => $locked->center_id,
                'audience' => $locked->center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $locked),
            ]);

            return ['delivery' => $locked];
        });

        if (isset($result['conflict'])) {
            return $result['conflict'];
        }

        return response()->json([
            'message' => 'Parcel marked as returned to the sorting center.',
            'delivery' => $this->detailPayload($result['delivery']->load(['items', 'statusLogs', 'proof', 'failure'])),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authorizeDelivery(Request $request, Delivery $delivery): void
    {
        $rider = $request->user()->rider;

        if ((int) $delivery->rider_id !== (int) $rider->id) {
            abort(403, 'This delivery is not assigned to you.');
        }
    }

    /**
     * Suspended/inactive or non-compliant riders keep ownership of parcels
     * already picked up so they can finish them, but they must not start new
     * work (accept, pick up, or advance a pre-pickup delivery).
     */
    private function prePickupBlock(Rider $rider): ?JsonResponse
    {
        $reason = $rider->assignmentBlockReason();

        if ($reason === null) {
            return null;
        }

        return response()->json([
            'message' => 'You cannot start or continue new deliveries. '.$reason.'.',
            'errors' => ['status' => ['You cannot start or continue new deliveries. '.$reason.'.']],
        ], 403);
    }

    private function invalidTransition(Delivery $delivery): JsonResponse
    {
        return response()->json([
            'message' => "Cannot move delivery from '{$this->label($delivery->status)}' to the requested state.",
            'errors' => [
                'status' => ["Invalid status transition from '{$delivery->status}'."],
            ],
        ], 409);
    }

    private function otpFailure(array $result): JsonResponse
    {
        return response()->json([
            'message' => $result['message'],
            'errors' => [
                'otp' => [$result['message']],
            ],
        ], 422);
    }


    private function logStatus(Delivery $delivery, string $status, ?string $note): void
    {
        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => $status,
            'notes' => $note,
        ]);
    }

    private function transitionNote(string $status): string
    {
        return match ($status) {
            'going_to_pickup' => 'Rider is on the way to the shop.',
            'arrived_at_shop' => 'Rider arrived at the shop.',
            'out_for_delivery' => 'Out for delivery.',
            'arrived_at_customer' => 'Rider arrived at the customer.',
            default => $this->label($status),
        };
    }

    private function markRiderDelivering(Rider $rider): void
    {
        $rider->update(['status' => 'delivering']);
    }

    private function markRiderAvailableIfIdle(Rider $rider): void
    {
        $active = Delivery::where('rider_id', $rider->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();

        if ($active === 0) {
            $rider->update(['status' => 'available']);
        }
    }

    private function label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public function detailPayload(Delivery $delivery): array
    {
        $controller = app(RiderController::class);
        $base = $controller->deliveryPayload($delivery);

        // Chronological (oldest first) by actual record timestamps, so every
        // rider endpoint presents history in event order regardless of how
        // the relation was eager-loaded.
        $base['status_logs'] = $delivery->statusLogs
            ->sortBy([['created_at', 'asc'], ['id', 'asc']])
            ->map(fn ($log) => [
                'status' => $log->status,
                'notes' => $log->notes,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values();

        return $base;
    }

    public function payload(Delivery $delivery): array
    {
        return app(RiderController::class)->deliveryPayload($delivery);
    }
}