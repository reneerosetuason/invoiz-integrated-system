<?php

namespace App\Services;

use App\Models\HubTransfer;
use App\Models\LogisticsCenter;
use App\Models\LogisticsTruck;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ReturnSettlement;
use App\Models\ReturnWorkflow;
use App\Support\EscrowEarnings;
use Illuminate\Validation\ValidationException;

/** One distance-and-weight reverse truck payment per new quote; retain agreed quotes. */
class ReturnTripPricingService
{
    public static function quote(ReturnRequest $case, ReturnSettlement $agreement, LogisticsCenter $from, LogisticsCenter $to, array $rates): array
    {
        $policy = SellerProtectionService::policy($case->order, (int) $case->seller_id);
        $item = $policy['lines'][$case->order_item_id] ?? null;
        if (! $item) {
            $line = $case->order->items()->whereKey($case->order_item_id)->firstOrFail();
            $product = Product::withTrashed()->findOrFail($line->product_id);
            $variant = $line->product_variant_id ?: $line->variant_id;
            $variantWeight = $variant ? ProductVariant::withTrashed()->find($variant)?->weight : null;
            $item = ['quantity' => $line->quantity, 'dimensions' => $product->dimensions, 'weight' => $variantWeight ?: $product->weight];
        }
        if (! $item || $case->quantity <= 0 || $case->quantity > $item['quantity']) {
            throw ValidationException::withMessages(['return' => 'The return must match the original purchased quantity and measurement snapshot.']);
        }
        $parcel = ['id' => $case->id, 'weight' => (ShipmentWeightService::kilograms($item['weight']) ?? 0) * $case->quantity];
        // Older products may have no measurements or may have been edited since
        // purchase. Reuse the recorded shipment instead of inventing a weight.
        $delivery = EscrowEarnings::deliveryForSeller($case->order, (int) $case->seller_id);
        $original = $delivery?->only(['weight']) ?? [];
        if ($delivery) {
            foreach (EscrowEarnings::transferLegs($delivery)->reverse() as $leg) {
                if ($leg->status !== 'received') continue;
                $recorded = collect($leg->payment_snapshot['parcels'] ?? [])->firstWhere('id', $delivery->id);
                if ($recorded) { $original = array_replace($original, $recorded); break; }
            }
        }
        $sellerQuantity = (int) $case->order->items()->where('seller_id', $case->seller_id)->sum('quantity');
        $wholeShipment = (int) $case->quantity === $sellerQuantity;
        $recordedWeight = ShipmentWeightService::kilograms($original['weight'] ?? null);
        if ($recordedWeight && $wholeShipment) {
            $parcel['weight'] = $recordedWeight;
        }
        if ($parcel['weight'] <= 0) {
            throw ValidationException::withMessages(['return' => 'Return parcel weight is unavailable from the original shipment and product. Logistics must review the weight before quoting inter-hub transport.']);
        }
        $trucks = LogisticsTruck::with('driver')->where('logistics_company_id', $from->logistics_company_id)
            ->where('verification_status', 'approved')->where('status', 'available')
            ->whereHas('driver', fn ($q) => $q->where('status', 'available'))
            ->whereDoesntHave('transfers', fn ($q) => $q->whereIn('status', HubTransfer::ACTIVE_STATUSES))->orderBy('capacity_kg')->get();
        $route = app(HubRoadDistanceService::class)->between($from, $to);
        foreach ($trucks as $truck) {
            if (! $truck->isAssignable() || $truck->driver->isBlocked()) {
                continue;
            }
            try {
                $calculation = app(ParcelInterHubPricing::class)->calculate($truck->only(['capacity_kg', 'parcel_capacity', 'cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm']), [$parcel], $route['distance'], $rates);
            } catch (ValidationException $e) {
                continue;
            }

            return $calculation + ['truck_id' => $truck->id, 'driver_id' => $truck->driver->id,
                'origin_center_id' => $from->id, 'destination_center_id' => $to->id,
                'reference' => 'RET-TRIP-'.$agreement->id.'-V'.$agreement->version, 'route_distance_source' => $route['source']];
        }
        throw ValidationException::withMessages(['return' => 'Register an available approved return truck and paired driver with sufficient weight capacity before quoting this return trip.']);
    }

    public static function assertAssignment(ReturnRequest $case, ReturnSettlement $agreement, array $data): void
    {
        $snapshot = $agreement->fee_snapshot['return_trip'] ?? null;
        if (! $snapshot) {
            return;
        }
        $truck = LogisticsTruck::whereKey($snapshot['truck_id'])->lockForUpdate()->firstOrFail();
        $driver = $truck->driver;
        abort_unless((int) ($data['driver_id'] ?? 0) === (int) $snapshot['driver_id'] && (int) $driver?->id === (int) $snapshot['driver_id'], 422, 'Assign the truck and driver in the reviewed return-trip quote. Changed fleet needs a revised agreement.');
        abort_unless($truck->isAssignable() && $truck->status === 'available'
            && ! $truck->transfers()->whereIn('status', HubTransfer::ACTIVE_STATUSES)->exists(), 422, 'The quoted return truck is no longer available.');
        $fields = ($snapshot['pricing_version'] ?? 1) >= 3 ? ['capacity_kg', 'parcel_capacity'] : ['capacity_kg', 'parcel_capacity', 'cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm'];
        abort_unless($truck->only($fields) == array_intersect_key($snapshot['truck'], array_flip($fields)), 422, 'The quoted truck capacity changed; revise the return agreement.');
        abort_if(ReturnWorkflow::where('driver_id', $driver->id)->where('stage', 'in_transfer')->where('return_request_id', '!=', $case->id)->exists(), 422, 'This driver already has a return trip in transit.');
        abort_if(\Illuminate\Support\Facades\Schema::hasTable('failed_delivery_cases')
            && \App\Models\FailedDeliveryCase::where('return_driver_id', $driver->id)->whereIn('stage', ['in_transfer', 'hub_arrival'])->exists(),
            422, 'This driver already has a failed-parcel return in transit.');
    }
}
