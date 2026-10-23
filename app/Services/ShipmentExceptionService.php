<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\DeliveryProof;
use App\Models\DeliveryStatusLog;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\SellerNotification;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ShipmentExceptionService
{
    public const TYPES = ['lost', 'damaged'];

    private const REPORTABLE_STATUSES = ['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivery_failed'];

    private const BLOCKED_STATUSES = ['delivered', 'cancelled', 'lost', 'damaged', 'returned_to_seller'];

    public function report(
        Delivery $delivery,
        string $type,
        string $reason,
        ?string $notes = null,
        ?UploadedFile $photo = null,
        ?User $actor = null,
        string $actorRole = 'staff',
    ): Delivery {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Choose lost or damaged.']);
        }

        if ($type === 'damaged' && ! $photo) {
            throw ValidationException::withMessages(['photo' => 'A photo is required when reporting a damaged parcel.']);
        }

        $proofPath = $photo?->store('delivery-evidence', 'public');

        try {
            return DB::transaction(function () use ($delivery, $type, $reason, $notes, $proofPath, $actor, $actorRole) {
                $locked = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

                if (in_array($locked->status, self::BLOCKED_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'type' => "This parcel is already {$locked->status} and cannot receive another exception report.",
                    ]);
                }

                if (! in_array($locked->status, self::REPORTABLE_STATUSES, true)) {
                    throw ValidationException::withMessages([
                        'type' => 'A parcel can only be reported lost or damaged after it has been picked up.',
                    ]);
                }

                if (! $locked->rider_id) {
                    throw ValidationException::withMessages([
                        'type' => 'A rider must be assigned before a lost or damaged report can be recorded.',
                    ]);
                }

                $now = now();
                $label = ucfirst($type);

                $updates = [
                    'status' => $type,
                    'failed_at' => $now,
                    'failure_reason' => $reason,
                    'reattempt_requested_at' => null,
                ];

                if ($type === 'lost') {
                    $updates['returned_at'] = null;
                    $updates['return_reason'] = null;
                }

                $locked->update($updates);

                DeliveryFailure::create([
                    'delivery_id' => $locked->id,
                    'rider_id' => $locked->rider_id,
                    'reason' => $label.': '.$reason,
                    'notes' => $notes,
                    'reported_at' => $now,
                ]);

                if ($proofPath) {
                    DeliveryProof::create([
                        'delivery_id' => $locked->id,
                        'rider_id' => $locked->rider_id,
                        'type' => $type === 'damaged' ? 'damage_evidence' : 'parcel_evidence',
                        'file_path' => $proofPath,
                        'latitude' => null,
                        'longitude' => null,
                        'verified_at' => $now,
                    ]);
                }

                DeliveryStatusLog::create([
                    'delivery_id' => $locked->id,
                    'status' => $type,
                    'notes' => $label.' parcel reported by '.$actorRole.': '.$reason.($notes ? ' — '.$notes : ''),
                    'changed_by' => $actor?->id,
                ]);

                Notification::create([
                    'type' => 'parcel_'.$type,
                    'title' => $label.' Parcel Reported',
                    'message' => "{$locked->tracking_number} was reported {$type}: {$reason}",
                    'icon' => '!',
                    'priority' => 'high',
                    'center_id' => $locked->center_id,
                    'audience' => $locked->center_id ? 'center' : 'admin',
                    'link' => route('deliveries.show', $locked),
                ]);

                if ($locked->seller_id) {
                    SellerNotification::create([
                        'seller_id' => Seller::userIdFor((int) $locked->seller_id),
                        'order_id' => $locked->order_id,
                        'type' => 'parcel_'.$type,
                        'title' => $label.' Parcel Reported',
                        'body' => "Parcel {$locked->tracking_number} was reported {$type}: {$reason}",
                    ]);
                }

                app(AuditLogger::class)->record(
                    $actor,
                    'delivery.'.$type.'.reported',
                    'delivery',
                    $locked->id,
                    ['status' => $delivery->status],
                    ['status' => $type, 'reason' => $reason],
                    $reason,
                    ['actor_role' => $actorRole, 'photo' => $proofPath !== null],
                );

                return $locked->fresh();
            }, 3);
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            throw $e;
        }
    }
}
