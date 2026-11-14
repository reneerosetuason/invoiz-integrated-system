<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\Logistics\Conversation;
use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Actor;
use Illuminate\Support\Facades\Schema;

/** One customer and one current parcel handler. Never a shared rider/staff chat. */
final class ParcelChat
{
    public static function holder(Delivery $parcel): ?array
    {
        if (! $parcel->archived_at && $parcel->order_id && Schema::hasTable('return_workflows')) {
            $flows = \App\Models\ReturnWorkflow::whereHas('returnRequest', fn ($q) => $q->where('order_id', $parcel->order_id)
                ->where('seller_id', $parcel->seller_id)->where('status', 'approved'))->limit(2)->get();
            if ($flows->isNotEmpty()) {
                // Partial returns may be separate physical parcels. Never guess
                // one handler when several simultaneous returns exist.
                if ($flows->count() !== 1) return null;
                $flow = $flows->first();
                if (in_array($flow->stage, ['pickup_assigned', 'collected', 'sent_to_seller', 'seller_arrival'], true)) {
                    return self::riderHolder((int) $flow->rider_id, 'Chat return rider');
                }
                if ($flow->stage === 'in_transfer') {
                    $driver = $flow->driver_id ? HubTransportDriver::find($flow->driver_id) : null;
                    return $driver && MessagingAccess::active($driver) ? ['kind' => 'd', 'id' => (int) $driver->id, 'name' => $driver->name, 'label' => 'Chat return transport driver'] : null;
                }
                if ($flow->stage === 'at_hub') return self::hubHolder((int) $flow->center_id);
                return null;
            }
        }
        if ($parcel->archived_at || $parcel->delivered_at || in_array($parcel->status, ['delivered', 'cancelled', 'returned_to_seller', 'lost'], true)) return null;
        if (Schema::hasTable('hub_transfer_parcels') && Schema::hasTable('hub_transfers')) {
            $leg = HubTransferParcel::where('delivery_id', $parcel->id)->where('loading_status', '!=', 'removed')
                ->where('receiving_status', '!=', 'received')->whereHas('transfer', fn ($q) => $q
                    ->whereIn('status', ['in_transit', 'arrived', 'receiving', 'exception'])
                    ->orWhere(fn ($held) => $held->where('status', 'on_hold')->whereNotNull('dispatched_at')))->with('transfer.driver')->latest('id')->first();
            if ($leg) {
                $driver = $leg->transfer->driver;
                return $driver && MessagingAccess::active($driver) ? ['kind' => 'd', 'id' => (int) $driver->id, 'name' => $driver->name, 'label' => 'Chat transport driver'] : null;
            }
        }
        $riderHasParcel = $parcel->sorting_center_pickup_at
            || in_array($parcel->status, ['out_for_delivery', 'arrived_at_customer'], true);
        // The assigned pickup rider is the coordination contact before collection.
        $pickup = ! $parcel->received_at && ! $parcel->sorting_center_handoff_at;
        if ($parcel->rider_id && ($riderHasParcel || $pickup)) {
            return self::riderHolder((int) $parcel->rider_id, $pickup ? 'Chat pickup rider' : 'Chat rider');
        }
        if ($parcel->received_at || $parcel->sorting_center_handoff_at || in_array($parcel->parcel_status, ['received', 'scanned', 'sorted'], true)) {
            return self::hubHolder((int) $parcel->center_id);
        }
        return null;
    }

    private static function riderHolder(int $id, string $label): ?array
    {
        $rider = Rider::find($id);
        return $rider?->user && $rider->status !== 'inactive' && MessagingAccess::active($rider->user) && $rider->user->hasApprovedRiderProfile()
            ? ['kind' => 'r', 'id' => (int) $rider->id, 'name' => $rider->name ?: $rider->user->displayName(), 'label' => $label] : null;
    }

    private static function hubHolder(int $id): ?array
    {
        $hub = $id ? LogisticsCenter::find($id) : null;
        return $hub && ($hub->is_active === null || $hub->is_active) && ($hub->company?->status ?? 'active') === 'active'
            ? ['kind' => 'h', 'id' => (int) $hub->id, 'name' => $hub->name, 'label' => 'Chat logistics hub'] : null;
    }

    public static function customer(User $user, Delivery $parcel): bool
    {
        return (int) $parcel->seller_id === (int) $user->id || (int) $parcel->order?->buyer_id === (int) $user->id;
    }

    public static function deliveries(User $user)
    {
        return Delivery::where(fn ($q) => $q->where('seller_id', $user->id)->orWhereHas('order', fn ($q) => $q->where('buyer_id', $user->id)))
            ->whereNull('archived_at')->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['delivered', 'cancelled', 'returned_to_seller', 'lost'])
                ->orWhereHas('order.returnRequests', fn ($r) => $r->where('status', 'approved')->whereColumn('seller_id', 'deliveries.seller_id')))->with('order')->get();
    }

    public static function matches(Actor $actor, array $holder): bool
    {
        if (! MessagingAccess::active($actor)) return false;
        return match ($holder['kind']) {
            'r' => $actor instanceof User && (int) $actor->rider?->id === $holder['id'],
            'd' => $actor instanceof HubTransportDriver && (int) $actor->id === $holder['id'],
            'h' => $actor instanceof User && LogisticsAccess::canProcessParcels($actor)
                && (int) LogisticsAccess::centerId($actor) === $holder['id']
                && LogisticsAccess::canAccessCenter($actor, $holder['id']),
            default => false,
        };
    }

    public static function related(User $customer, Actor $handler): ?Delivery
    {
        return self::deliveries($customer)->first(fn ($parcel) => ($holder = self::holder($parcel)) && self::matches($handler, $holder));
    }

    public static function parse(?string $key): ?array
    {
        if (! preg_match('/^p:([1-9][0-9]*):([1-9][0-9]*):([rhd]):([1-9][0-9]*)$/', $key ?? '', $m)) return null;
        return ['delivery_id' => (int) $m[1], 'customer_id' => (int) $m[2], 'kind' => $m[3], 'id' => (int) $m[4]];
    }

    public static function open(User $customer, Delivery $parcel): Conversation
    {
        abort_unless(self::customer($customer, $parcel) && MessagingAccess::active($customer), 403);
        $holder = self::holder($parcel);
        abort_unless($holder, 409, 'No delivery handler is currently available for this parcel.');
        // Reuse an existing private legacy rider chat only when one parcel is
        // unambiguous and no staff ever joined it. Mixed histories stay archived.
        if ($holder['kind'] === 'r' && $parcel->order_id
            && Delivery::where('order_id', $parcel->order_id)->where('rider_id', $holder['id'])->count() === 1) {
            $legacy = Conversation::whereNull('direct_thread_key')->where('order_id', $parcel->order_id)
                ->where('rider_id', $holder['id'])->whereIn('participant_type', ['buyer', 'seller'])->where('participant_id', $customer->id)
                ->whereDoesntHave('messages', fn ($q) => $q->where('sender_type', 'logistics'))->first();
            if ($legacy) return $legacy;
        }
        return Conversation::firstOrCreate(['direct_thread_key' => 'p:'.$parcel->id.':'.$customer->id.':'.$holder['kind'].':'.$holder['id']], [
            'order_id' => $parcel->order_id, 'rider_id' => $holder['kind'] === 'r' ? $holder['id'] : null,
            'participant_type' => (int) $parcel->order?->buyer_id === (int) $customer->id ? 'buyer' : 'seller',
            'participant_id' => $customer->id, 'participant_name' => $customer->displayName(),
            'subject' => $holder['label'].' · '.$parcel->tracking_number, 'last_message_at' => now(),
        ]);
    }

    public static function readable(Actor $actor, Conversation $thread, array $identity): bool
    {
        if ((int) $thread->participant_id !== $identity['customer_id'] || ! in_array($thread->participant_type, ['buyer', 'seller'], true)) return false;
        return ($actor instanceof User && (int) $actor->id === $identity['customer_id']) || self::matches($actor, $identity);
    }

    public static function writable(Actor $actor, Conversation $thread, array $identity): bool
    {
        if (! self::readable($actor, $thread, $identity)) return false;
        $customer = User::find($identity['customer_id']);
        $parcel = Delivery::find($identity['delivery_id']);
        if (! $customer || ! MessagingAccess::active($customer) || ! $parcel || ! self::customer($customer, $parcel)) return false;
        $holder = self::holder($parcel);
        if ($holder && $holder['kind'] === 'r') {
            $riderUser = Rider::find($holder['id'])?->user;
            if (! $riderUser || \App\Models\ChatBlock::blocksBetween($customer, $riderUser)) return false;
        }
        return $holder && $holder['kind'] === $identity['kind'] && $holder['id'] === $identity['id'];
    }

    /** Called inside each message transaction, in the custody writers' lock order. */
    public static function lockAndAuthorize(Actor $actor, Conversation $thread): void
    {
        if (! ($identity = self::parse($thread->direct_thread_key))) return;
        $parcel = Delivery::findOrFail($identity['delivery_id']);
        if ($parcel->order_id) \App\Models\Order::whereKey($parcel->order_id)->lockForUpdate()->firstOrFail();
        Delivery::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
        abort_unless(MessagingAccess::canSendLogistics($actor, $thread), 403, 'This parcel has moved to another handler. Open its current delivery chat.');
    }

    public static function sync(User $customer): void
    {
        foreach (self::deliveries($customer) as $parcel) {
            if (self::holder($parcel)) self::open($customer, $parcel);
        }
    }
}
