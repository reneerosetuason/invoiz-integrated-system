<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\DeliveryProof;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ParcelLiability as ParcelLiabilityModel;
use App\Models\Seller;
use App\Models\SellerNotification;
use App\Models\User;
use App\Services\ReturnToSellerService;
use App\Services\ReturnWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ParcelLiability
{
    /**
     * Classify where damage occurred using existing data:
     * - Destination hub received the parcel with condition = good -> damage happened in LAST-MILE rider custody.
     * - Received at a hub with condition = damaged -> damage happened BEFORE that hub (linehaul/origin).
     * - No hub record -> unknown; treat as last-mile if a last-mile rider was assigned, else 'undetermined'.
     */
    public static function classifyCustody(Delivery $delivery): array
    {
        $transferEntry = HubTransferParcel::where('delivery_id', $delivery->id)
            ->where('receiving_status', 'received')
            ->latest('id')
            ->first();

        if ($transferEntry) {
            if ($transferEntry->condition === 'good') {
                $centerId = $delivery->destination_center_id ?: $delivery->center_id;
                $companyId = $centerId ? LogisticsCenter::find($centerId)?->logistics_company_id : null;

                return [
                    'custody' => ParcelLiabilityModel::CUSTODY_LAST_MILE_RIDER,
                    'center_id' => $centerId,
                    'company_id' => $companyId,
                    'rider_id' => $delivery->rider_id,
                    'explanation' => 'Destination hub received parcel in good condition; damage occurred during last-mile courier custody.',
                ];
            }

            if ($transferEntry->condition === 'damaged') {
                $transfer = $transferEntry->hubTransfer;
                $centerId = $transfer?->origin_center_id ?: $delivery->origin_center_id ?: $delivery->center_id;
                $companyId = $centerId ? LogisticsCenter::find($centerId)?->logistics_company_id : null;

                return [
                    'custody' => ParcelLiabilityModel::CUSTODY_LINEHAUL,
                    'center_id' => $centerId,
                    'company_id' => $companyId,
                    'rider_id' => null,
                    'explanation' => 'Parcel was received damaged at hub; damage occurred prior to destination hub custody (linehaul / transit).',
                ];
            }
        }

        // Single-hub local shipment or no transfer record
        if ($delivery->rider_id) {
            $centerId = $delivery->center_id;
            $companyId = $centerId ? LogisticsCenter::find($centerId)?->logistics_company_id : null;

            return [
                'custody' => ParcelLiabilityModel::CUSTODY_LAST_MILE_RIDER,
                'center_id' => $centerId,
                'company_id' => $companyId,
                'rider_id' => $delivery->rider_id,
                'explanation' => 'Local delivery assignment; damage occurred while assigned to last-mile rider.',
            ];
        }

        $centerId = $delivery->center_id;
        $companyId = $centerId ? LogisticsCenter::find($centerId)?->logistics_company_id : null;

        return [
            'custody' => ParcelLiabilityModel::CUSTODY_UNDETERMINED,
            'center_id' => $centerId,
            'company_id' => $companyId,
            'rider_id' => null,
            'explanation' => 'Undetermined custody; no hub transfer scan record available.',
        ];
    }

    /**
     * Calculate merchandise amount (order item subtotal for that seller's items in this shipment).
     */
    public static function calculateMerchandiseAmount(Delivery $delivery): float
    {
        $order = $delivery->order;
        if (! $order) {
            return 0.0;
        }

        $sellerId = (int) $delivery->seller_id;
        $amount = (float) $order->sellerSubtotal($sellerId);

        if ($amount <= 0 && $delivery->relationLoaded('items') && $delivery->items->isNotEmpty()) {
            $amount = (float) $delivery->items->sum(function ($it) {
                return (float) ($it->price ?? 0) * (int) ($it->quantity ?? 1);
            });
        }

        return round(max(0.0, $amount), 2);
    }

    /**
     * Propose a liability record when damage is reported.
     */
    public static function propose(Delivery $delivery, ?string $evidencePath = null, ?User $actor = null): ParcelLiabilityModel
    {
        if (! Schema::hasTable('parcel_liabilities')) {
            throw new \RuntimeException('parcel_liabilities table is missing');
        }

        return DB::transaction(function () use ($delivery, $evidencePath, $actor) {
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $classification = self::classifyCustody($lockedDelivery);
            $amount = self::calculateMerchandiseAmount($lockedDelivery);

            $proofPath = $evidencePath;
            if (! $proofPath) {
                $proof = DeliveryProof::where('delivery_id', $lockedDelivery->id)
                    ->where('type', 'damage_evidence')
                    ->latest('id')
                    ->first();
                $proofPath = $proof?->file_path;
            }

            $liability = ParcelLiabilityModel::updateOrCreate(
                [
                    'delivery_id' => $lockedDelivery->id,
                ],
                [
                    'order_id' => $lockedDelivery->order_id,
                    'seller_id' => $lockedDelivery->seller_id,
                    'logistics_company_id' => $classification['company_id'],
                    'logistics_center_id' => $classification['center_id'],
                    'rider_id' => $classification['rider_id'],
                    'custody_classification' => $classification['custody'],
                    'merchandise_amount' => $amount,
                    'status' => ParcelLiabilityModel::STATUS_PROPOSED,
                    'damage_evidence_path' => $proofPath,
                    'proposed_at' => now(),
                    'notes' => $classification['explanation'],
                ]
            );

            // Notify center/admin
            Notification::create([
                'type' => 'parcel_liability_proposed',
                'title' => 'Damage Liability Proposed',
                'message' => "Liability of ₱".number_format($amount, 2)." proposed for damaged parcel {$lockedDelivery->tracking_number} ({$classification['custody']}).",
                'icon' => '!',
                'priority' => 'high',
                'center_id' => $classification['center_id'],
                'audience' => $classification['center_id'] ? 'center' : 'admin',
                'link' => route('deliveries.show', $lockedDelivery->id),
            ]);

            // Notify seller
            if ($lockedDelivery->seller_id) {
                SellerNotification::create([
                    'seller_id' => Seller::userIdFor((int) $lockedDelivery->seller_id),
                    'order_id' => $lockedDelivery->order_id,
                    'type' => 'liability_proposed',
                    'title' => 'Damage Compensation Proposed',
                    'body' => "Logistics liability proposed in the amount of ₱".number_format($amount, 2)." for damaged parcel {$lockedDelivery->tracking_number}. You may dispute this report within 7 days of return confirmation if needed.",
                ]);
            }

            if (class_exists(AuditLogger::class)) {
                app(AuditLogger::class)->record(
                    $actor,
                    'parcel_liability.proposed',
                    'parcel_liability',
                    $liability->id,
                    [],
                    ['status' => ParcelLiabilityModel::STATUS_PROPOSED, 'amount' => $amount, 'custody' => $classification['custody']],
                    $classification['explanation'],
                );
            }

            return $liability;
        });
    }

    /**
     * Validate the damage report: accepts the liability and immediately queues buyer refund for prepaid/GCash orders.
     */
    public static function validateReport(Delivery $delivery, ?User $actor = null): ParcelLiabilityModel
    {
        return DB::transaction(function () use ($delivery, $actor) {
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $order = Order::with(['payment', 'items'])->whereKey($lockedDelivery->order_id)->lockForUpdate()->first();

            $liability = ParcelLiabilityModel::where('delivery_id', $lockedDelivery->id)->lockForUpdate()->first();
            if (! $liability) {
                $liability = self::propose($lockedDelivery, null, $actor);
            }

            if ($liability->status === ParcelLiabilityModel::STATUS_PROPOSED) {
                $liability->update([
                    'status' => ParcelLiabilityModel::STATUS_ACCEPTED,
                    'accepted_at' => now(),
                ]);
            }

            // Decouple buyer refund from physical return:
            // "but the seller is only responsible to return the payment of the buyer if it is paid in gcash"
            // Prepaid / GCash orders get queued immediately; COD orders collect nothing and need no refund.
            if ($order && Schema::hasTable('return_workflows')) {
                $isPrepaidOrGcash = ($order->payment?->method === 'gcash' && $order->payment?->proof_path)
                    || in_array($order->payment_status, ['paid', 'partially_refunded'], true);

                if ($isPrepaidOrGcash) {
                    $tx = Payments::transactionFor($order);
                    $remaining = round(max(0, (float) $order->total_amount - (float) ($tx?->refunded_amount ?? 0)), 2);
                    if ($remaining > 0 && ! $order->returnRequests()->whereIn('status', ['requested', 'approved', 'item_received'])->exists()) {
                        ReturnWorkflowService::queueRefund(
                            $order,
                            $remaining,
                            'Damaged shipment in logistics custody: immediate buyer refund queued upon damage validation.'
                        );

                        // Notify buyer through OrderStatusHistory timeline
                        OrderStatusHistory::create([
                            'order_id' => $order->id,
                            'from_status' => $order->status,
                            'to_status' => $order->status,
                            'note' => 'Parcel damaged in transit: your refund has been queued immediately.',
                        ]);
                    }
                }
            }

            // Notifications
            Notification::create([
                'type' => 'parcel_liability_accepted',
                'title' => 'Damage Report Validated',
                'message' => "Damage report validated for {$lockedDelivery->tracking_number}. Liability accepted for ₱".number_format($liability->merchandise_amount, 2).".",
                'icon' => '!',
                'priority' => 'high',
                'center_id' => $liability->logistics_center_id,
                'audience' => $liability->logistics_center_id ? 'center' : 'admin',
                'link' => route('deliveries.show', $lockedDelivery->id),
            ]);

            if ($lockedDelivery->seller_id) {
                SellerNotification::create([
                    'seller_id' => Seller::userIdFor((int) $lockedDelivery->seller_id),
                    'order_id' => $lockedDelivery->order_id,
                    'type' => 'liability_accepted',
                    'title' => 'Damage Compensation Accepted',
                    'body' => "Damage report for {$lockedDelivery->tracking_number} has been validated. Compensation of ₱".number_format($liability->merchandise_amount, 2)." is approved for settlement.",
                ]);
            }

            if (class_exists(AuditLogger::class)) {
                app(AuditLogger::class)->record(
                    $actor,
                    'parcel_liability.validated',
                    'parcel_liability',
                    $liability->id,
                    ['status' => ParcelLiabilityModel::STATUS_PROPOSED],
                    ['status' => $liability->status, 'accepted_at' => now()->toDateTimeString()],
                    'Damage report validated; buyer refund queued if prepaid.',
                );
            }

            return $liability->fresh();
        });
    }

    /**
     * Settle liability: credits the seller merchandise amount.
     */
    public static function settle(ParcelLiabilityModel $liability, ?User $actor = null): ParcelLiabilityModel
    {
        return DB::transaction(function () use ($liability, $actor) {
            $locked = ParcelLiabilityModel::query()->whereKey($liability->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ParcelLiabilityModel::STATUS_SETTLED) {
                return $locked;
            }

            if ($locked->status === ParcelLiabilityModel::STATUS_DISPUTED) {
                throw ValidationException::withMessages([
                    'liability' => 'Cannot settle a disputed liability. Resolve the dispute first.',
                ]);
            }

            if ($locked->status === ParcelLiabilityModel::STATUS_REVERSED) {
                throw ValidationException::withMessages([
                    'liability' => 'Cannot settle a reversed liability.',
                ]);
            }

            $locked->update([
                'status' => ParcelLiabilityModel::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by' => $actor?->id,
                'settlement_reference' => 'LIABILITY-SETTLED-'.$locked->id,
            ]);

            // Notify seller
            if ($locked->seller_id) {
                SellerNotification::create([
                    'seller_id' => Seller::userIdFor((int) $locked->seller_id),
                    'order_id' => $locked->order_id,
                    'type' => 'liability_settled',
                    'title' => 'Damage Compensation Settled',
                    'body' => "₱".number_format($locked->merchandise_amount, 2)." has been credited to your store balance for damaged parcel #".($locked->delivery?->tracking_number ?? $locked->delivery_id).".",
                ]);
            }

            // Notify logistics center / admin
            Notification::create([
                'type' => 'parcel_liability_settled',
                'title' => 'Logistics Liability Settled',
                'message' => "Liability of ₱".number_format($locked->merchandise_amount, 2)." settled for parcel #".($locked->delivery?->tracking_number ?? $locked->delivery_id).".",
                'icon' => '!',
                'priority' => 'normal',
                'center_id' => $locked->logistics_center_id,
                'audience' => $locked->logistics_center_id ? 'center' : 'admin',
            ]);

            if (class_exists(AuditLogger::class)) {
                app(AuditLogger::class)->record(
                    $actor,
                    'parcel_liability.settled',
                    'parcel_liability',
                    $locked->id,
                    [],
                    ['status' => ParcelLiabilityModel::STATUS_SETTLED, 'amount' => $locked->merchandise_amount],
                    'Logistics liability settled; seller balance credited.',
                );
            }

            return $locked->fresh();
        });
    }

    /**
     * Seller disputes the damage report within the 7-day window from return confirmation.
     */
    public static function dispute(
        Delivery $delivery,
        User $sellerUser,
        string $reason,
        ?string $notes = null,
        ?UploadedFile $evidence = null,
    ): ParcelLiabilityModel {
        $evidencePath = $evidence?->store('dispute-evidence', 'public');

        try {
            return DB::transaction(function () use ($delivery, $sellerUser, $reason, $notes, $evidencePath) {
                $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
                $liability = ParcelLiabilityModel::where('delivery_id', $lockedDelivery->id)->lockForUpdate()->first();

                if (! $liability) {
                    $liability = self::propose($lockedDelivery);
                }

                // Check 7-day dispute window from return confirmation
                $receivedLog = DeliveryStatusLog::where('delivery_id', $lockedDelivery->id)
                    ->where('status', ReturnToSellerService::RECEIVED)
                    ->latest('id')
                    ->first();

                if (! $receivedLog && $lockedDelivery->status !== 'returned_to_seller') {
                    throw ValidationException::withMessages([
                        'dispute' => 'You can dispute the damage report after confirming return receipt of the parcel.',
                    ]);
                }

                $confirmedAt = $receivedLog?->created_at ?: $lockedDelivery->returned_at ?: $lockedDelivery->updated_at;
                if ($confirmedAt && now()->diffInDays($confirmedAt) > 7) {
                    throw ValidationException::withMessages([
                        'dispute' => 'The dispute window of 7 days from return confirmation has expired.',
                    ]);
                }

                if (in_array($liability->status, [ParcelLiabilityModel::STATUS_SETTLED, ParcelLiabilityModel::STATUS_REVERSED], true)) {
                    throw ValidationException::withMessages([
                        'dispute' => "Cannot dispute a liability that is already {$liability->status}.",
                    ]);
                }

                $liability->update([
                    'status' => ParcelLiabilityModel::STATUS_DISPUTED,
                    'disputed_at' => now(),
                    'dispute_reason' => $reason,
                    'dispute_notes' => $notes,
                    'dispute_evidence_path' => $evidencePath ?: $liability->dispute_evidence_path,
                ]);

                // Pauses settlement and notifies center/admin
                Notification::create([
                    'type' => 'liability_dispute_raised',
                    'title' => 'Damage Report Disputed by Seller',
                    'message' => "Seller disputed damage report for parcel {$lockedDelivery->tracking_number}: {$reason}. Liability settlement paused.",
                    'icon' => '!',
                    'priority' => 'high',
                    'center_id' => $liability->logistics_center_id,
                    'audience' => $liability->logistics_center_id ? 'center' : 'admin',
                    'link' => route('deliveries.show', $lockedDelivery->id),
                ]);

                if (class_exists(AuditLogger::class)) {
                    app(AuditLogger::class)->record(
                        $sellerUser,
                        'parcel_liability.disputed',
                        'parcel_liability',
                        $liability->id,
                        ['status' => $liability->getOriginal('status')],
                        ['status' => ParcelLiabilityModel::STATUS_DISPUTED, 'reason' => $reason],
                        $notes,
                        ['evidence' => $evidencePath !== null],
                    );
                }

                return $liability->fresh();
            });
        } catch (\Throwable $e) {
            if ($evidencePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($evidencePath);
            }
            throw $e;
        }
    }

    /**
     * Admin resolves dispute:
     * - uphold: settles liability and credits seller.
     * - overturn: reverses liability and restores saleable stock via Stock::restore.
     */
    public static function resolveDispute(
        ParcelLiabilityModel $liability,
        string $outcome,
        ?string $resolutionNotes = null,
        ?User $admin = null,
    ): ParcelLiabilityModel {
        if (! in_array($outcome, ['uphold', 'overturn'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Outcome must be uphold or overturn.']);
        }

        return DB::transaction(function () use ($liability, $outcome, $resolutionNotes, $admin) {
            $locked = ParcelLiabilityModel::query()->whereKey($liability->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ParcelLiabilityModel::STATUS_DISPUTED) {
                throw ValidationException::withMessages([
                    'liability' => 'Only disputed liabilities can be resolved through this action.',
                ]);
            }

            $order = Order::with('items')->whereKey($locked->order_id)->lockForUpdate()->first();
            $delivery = $locked->delivery;

            if ($outcome === 'uphold') {
                $locked->update([
                    'status' => ParcelLiabilityModel::STATUS_SETTLED,
                    'resolution_outcome' => 'upheld',
                    'resolved_at' => now(),
                    'resolved_by' => $admin?->id,
                    'resolution_notes' => $resolutionNotes,
                    'settled_at' => now(),
                    'settled_by' => $admin?->id,
                    'settlement_reference' => 'LIABILITY-UPHELD-'.$locked->id,
                ]);

                // Notify seller
                if ($locked->seller_id) {
                    SellerNotification::create([
                        'seller_id' => Seller::userIdFor((int) $locked->seller_id),
                        'order_id' => $locked->order_id,
                        'type' => 'dispute_upheld',
                        'title' => 'Damage Dispute Upheld',
                        'body' => "Your dispute for parcel #".($delivery?->tracking_number ?? $locked->delivery_id)." was upheld. Merchandise compensation of ₱".number_format($locked->merchandise_amount, 2)." has been settled to your store balance.",
                    ]);
                }

                // Notify center / admin
                Notification::create([
                    'type' => 'liability_dispute_resolved',
                    'title' => 'Damage Dispute Upheld',
                    'message' => "Dispute for parcel #".($delivery?->tracking_number ?? $locked->delivery_id)." was upheld. Liability settled.",
                    'icon' => '!',
                    'priority' => 'normal',
                    'center_id' => $locked->logistics_center_id,
                    'audience' => $locked->logistics_center_id ? 'center' : 'admin',
                ]);
            } else {
                // Overturn: reverse liability and restore saleable stock
                $locked->update([
                    'status' => ParcelLiabilityModel::STATUS_REVERSED,
                    'resolution_outcome' => 'overturned',
                    'resolved_at' => now(),
                    'resolved_by' => $admin?->id,
                    'resolution_notes' => $resolutionNotes,
                ]);

                // Restore saleable stock
                if ($order) {
                    $sellerItems = $order->items->where('seller_id', $locked->seller_id);
                    foreach ($sellerItems as $item) {
                        if ($item instanceof OrderItem) {
                            Stock::restore($item);
                        }
                    }
                }

                // Notify seller
                if ($locked->seller_id) {
                    SellerNotification::create([
                        'seller_id' => Seller::userIdFor((int) $locked->seller_id),
                        'order_id' => $locked->order_id,
                        'type' => 'dispute_overturned',
                        'title' => 'Damage Dispute Overturned',
                        'body' => "Dispute for parcel #".($delivery?->tracking_number ?? $locked->delivery_id)." was overturned. Logistics liability is reversed and saleable stock has been restored to your inventory.",
                    ]);
                }

                Notification::create([
                    'type' => 'liability_dispute_resolved',
                    'title' => 'Damage Dispute Overturned',
                    'message' => "Dispute for parcel #".($delivery?->tracking_number ?? $locked->delivery_id)." was overturned. Stock restored.",
                    'icon' => '!',
                    'priority' => 'normal',
                    'center_id' => $locked->logistics_center_id,
                    'audience' => $locked->logistics_center_id ? 'center' : 'admin',
                ]);
            }

            if (class_exists(AuditLogger::class)) {
                app(AuditLogger::class)->record(
                    $admin,
                    'parcel_liability.dispute_resolved',
                    'parcel_liability',
                    $locked->id,
                    ['status' => ParcelLiabilityModel::STATUS_DISPUTED],
                    ['status' => $locked->status, 'outcome' => $outcome],
                    $resolutionNotes,
                );
            }

            return $locked->fresh();
        });
    }
}
