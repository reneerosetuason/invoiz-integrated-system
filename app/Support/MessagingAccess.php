<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\HubTransportDriver;
use App\Models\Logistics\Conversation as LogisticsConversation;
use App\Models\ReturnAllocation;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Actor;

/** Messaging authorization only; never changes assignments or financial states. */
final class MessagingAccess
{
    public static function active(Actor $actor): bool
    {
        return $actor instanceof User ? $actor->isActive()
            : ($actor instanceof HubTransportDriver
                && ! in_array($actor->status, HubTransportDriver::BLOCKED_STATUSES, true)
                && (! $actor->user || $actor->user->isActive()));
    }

    public static function canContact(Actor $actor, Actor $other): bool
    {
        if (! self::active($actor) || ! self::active($other)
            || ($actor::class === $other::class && (int) $actor->id === (int) $other->id)) {
            return false;
        }
        if ($actor instanceof User && $actor->isAdmin() || $other instanceof User && $other->isAdmin()) {
            return true;
        }
        if ($actor instanceof HubTransportDriver || $other instanceof HubTransportDriver) {
            $driver = $actor instanceof HubTransportDriver ? $actor : $other;
            $staff = $actor instanceof User ? $actor : $other;
            return $staff instanceof User && $staff->isLogisticsStaff()
                && LogisticsAccess::canAccessCompany($staff, (int) $driver->logistics_company_id);
        }
        if ($actor->isLogisticsStaff() && $other->isLogisticsStaff()) {
            $company = LogisticsAccess::companyId($actor);
            $otherCompany = LogisticsAccess::companyId($other);
            return LogisticsAccess::isGlobal($actor) || LogisticsAccess::isGlobal($other)
                || ($company > 0 && $company === $otherCompany);
        }
        foreach ([[$actor, $other], [$other, $actor]] as [$staff, $riderUser]) {
            if ($staff->isLogisticsStaff() && ($riderUser->role === 'rider' || $riderUser->relationLoaded('rider'))) {
                $rider = $riderUser->rider;
                if ($rider && LogisticsAccess::canAccessCenter($staff, $rider->center_id)) {
                    return true;
                }
            }
        }
        // Staff/customer coordination is a delivery-thread reply, never an
        // unsolicited direct message (including accounts with other profiles).
        if ($actor->isLogisticsStaff() || $other->isLogisticsStaff()) return false;
        if ($actor->role === 'rider' || $other->role === 'rider') {
            if ($actor->role === 'rider' && $other->role === 'rider') return false;
            return self::deliveryContact($actor, $other) || self::deliveryContact($other, $actor);
        }
        if ($actor->isSeller() && $other->isSeller()) return false;
        // Store enquiries are allowed before an order exists. Sellers may reply
        // to those buyers, or contact buyers who actually ordered from them.
        foreach ([[$actor, $other], [$other, $actor]] as [$buyer, $seller]) {
            if ($buyer->canUseBuyerArea() && $seller->canSell()) {
                if (($actor->id === $buyer->id && $buyer->isBuyer()) || self::hasThread($buyer, $seller)
                    || \App\Models\OrderItem::where('seller_id', $seller->id)
                        ->whereHas('order', fn ($q) => $q->where('buyer_id', $buyer->id))->exists()) {
                    return true;
                }
            }
        }
        return self::deliveryContact($actor, $other) || self::deliveryContact($other, $actor);
    }

    private static function hasThread(User $a, User $b): bool
    {
        return Conversation::where(fn ($pair) => $pair
            ->where(fn ($q) => $q->where('buyer_id', $a->id)->where('seller_id', $b->id))
            ->orWhere(fn ($q) => $q->where('buyer_id', $b->id)->where('seller_id', $a->id)))
            ->whereHas('messages', fn ($q) => $q->where('sender_id', $a->id))->exists();
    }

    private static function deliveryContact(User $riderUser, User $customer): bool
    {
        if ($riderUser->role !== 'rider' && ! $riderUser->relationLoaded('rider')) {
            return false;
        }
        $rider = $riderUser->rider;
        if (! $rider || ! $riderUser->hasApprovedRiderProfile()) {
            return false;
        }
        if (Delivery::where(fn ($q) => $q->where('rider_id', $rider->id)
                ->orWhere('sorting_center_handoff_rider_id', $rider->id)
                ->orWhere('sorting_center_pickup_rider_id', $rider->id))
            ->where(fn ($q) => $q->where('seller_id', $customer->id)
                ->orWhereHas('order', fn ($order) => $order->where('buyer_id', $customer->id)))
            ->exists()) {
            return true;
        }
        return ReturnAllocation::where('rider_id', $rider->id)
            ->where('recipient_user_id', $riderUser->id)
            ->whereHas('settlement.returnRequest', fn ($q) => $q->where('buyer_id', $customer->id)
                ->orWhere('seller_id', $customer->id))->exists();
    }

    public static function canReadMarketplace(User $actor, Conversation $thread): bool
    {
        return self::active($actor) && in_array((int) $actor->id, [(int) $thread->buyer_id, (int) $thread->seller_id], true);
    }

    public static function canReadLogistics(Actor $actor, LogisticsConversation $thread): bool
    {
        if (! self::active($actor)) {
            return false;
        }
        if ($actor instanceof HubTransportDriver) {
            return $thread->participant_type === 'hub_driver' && (int) $thread->participant_id === (int) $actor->id;
        }
        if ($thread->participant_type === 'logistics') {
            return in_array((int) $actor->id, [(int) $thread->logistics_user_id, (int) $thread->participant_id], true);
        }
        if ($thread->participant_type === 'hub_driver') {
            $driver = HubTransportDriver::find($thread->participant_id);
            return $driver && $actor->isLogisticsStaff()
                && (int) $thread->logistics_user_id === (int) $actor->id
                && LogisticsAccess::canAccessCompany($actor, $driver->logistics_company_id);
        }
        if ($thread->participant_type === 'rider') {
            if ((int) $thread->participant_id === (int) $actor->id) {
                return true;
            }
            $rider = \App\Models\Rider::find($thread->rider_id);
            if (! $rider && $thread->participant_id) {
                $rider = User::find($thread->participant_id)?->rider;
            }
            return $actor->isLogisticsStaff() && $rider
                && LogisticsAccess::canAccessCenter($actor, $rider->center_id);
        }
        if (in_array($thread->participant_type, ['buyer', 'seller'], true)) {
            // Delivery customer threads are private between the assigned rider
            // and that customer. They are never part of a shared staff inbox.
            if ((int) $thread->participant_id === (int) $actor->id) {
                return true;
            }
            return ($actor->rider && (int) $thread->rider_id === (int) $actor->rider->id)
                || self::canReplyToDelivery($actor, $thread);
        }
        return false;
    }

    public static function canSendLogistics(Actor $actor, LogisticsConversation $thread): bool
    {
        if (! self::canReadLogistics($actor, $thread)) {
            return false;
        }
        if (in_array($thread->participant_type, ['buyer', 'seller'], true)) {
            if (self::canReplyToDelivery($actor, $thread)) {
                return User::find($thread->participant_id)?->isActive() ?? false;
            }
            $rider = \App\Models\Rider::find($thread->rider_id);
            $customer = User::find($thread->participant_id);
            return $rider?->user && $customer && self::canContact($rider->user, $customer)
                && self::deliveryForThread($thread)->exists();
        }
        if ($thread->participant_type === 'logistics') {
            $otherId = (int) $thread->participant_id === (int) $actor->id ? $thread->logistics_user_id : $thread->participant_id;
            $other = User::find($otherId);
            return $other && self::canContact($actor, $other);
        }
        if ($thread->participant_type === 'hub_driver') {
            $driver = HubTransportDriver::find($thread->participant_id);
            $staff = User::find($thread->logistics_user_id);
            return $driver && $staff && self::canContact($driver, $staff);
        }
        return true;
    }

    private static function deliveryForThread(LogisticsConversation $thread)
    {
        return Delivery::where('order_id', $thread->order_id)
            ->where(fn ($q) => $q->where('rider_id', $thread->rider_id)
                ->orWhere('sorting_center_handoff_rider_id', $thread->rider_id)
                ->orWhere('sorting_center_pickup_rider_id', $thread->rider_id))
            ->where(function ($q) use ($thread) {
                if ($thread->participant_type === 'seller') $q->where('seller_id', $thread->participant_id);
                else $q->whereHas('order', fn ($order) => $order->where('buyer_id', $thread->participant_id));
            });
    }

    /** Staff may reply only to an existing, correctly scoped delivery chat. */
    private static function canReplyToDelivery(User $actor, LogisticsConversation $thread): bool
    {
        if (! $actor->isLogisticsStaff() || $actor->isAdmin() || ! $thread->order_id || ! $thread->rider_id
            || ! in_array($thread->participant_type, ['buyer', 'seller'], true)
            || ! $thread->messages()->whereIn('sender_type', ['buyer', 'seller', 'rider'])->exists()) return false;
        return self::deliveryForThread($thread)->get()
            ->contains(fn ($delivery) => LogisticsAccess::canAccessDelivery($actor, $delivery));
    }

    public static function senderType(Actor $actor, LogisticsConversation $thread): string
    {
        if ($actor instanceof HubTransportDriver) return 'hub_driver';
        if ($thread->participant_type === 'rider' && (int) $thread->participant_id === (int) $actor->id) return 'rider';
        if (in_array($thread->participant_type, ['buyer', 'seller'], true)) {
            if ((int) $thread->participant_id === (int) $actor->id) return $thread->participant_type;
            return $actor->rider && (int) $actor->rider->id === (int) $thread->rider_id ? 'rider' : 'logistics';
        }
        return 'logistics';
    }

    public static function logisticsIds(Actor $actor): array
    {
        return self::logisticsQuery($actor)->get()->filter(fn ($thread) => self::canReadLogistics($actor, $thread))->modelKeys();
    }

    public static function logisticsQuery(Actor $actor)
    {
        $query = LogisticsConversation::query();
        if ($actor instanceof HubTransportDriver) {
            return $query->where('participant_type', 'hub_driver')->where('participant_id', $actor->id);
        }
        return $query->where(function ($q) use ($actor) {
            $q->where(fn ($direct) => $direct->where('participant_type', 'logistics')
                ->where(fn ($members) => $members->where('participant_id', $actor->id)->orWhere('logistics_user_id', $actor->id)))
                ->orWhere(fn ($customer) => $customer->whereIn('participant_type', ['buyer', 'seller'])
                    ->where(fn ($members) => $members->where('participant_id', $actor->id)->orWhere('rider_id', $actor->rider?->id ?? -1)))
                ->orWhere(fn ($support) => $support->where('participant_type', 'rider')->where('participant_id', $actor->id))
                ->orWhere(fn ($driver) => $driver->where('participant_type', 'hub_driver')->where('logistics_user_id', $actor->id));
            if ($actor->isLogisticsStaff()) {
                $riders = \App\Models\Rider::query();
                LogisticsAccess::scopeRiders($riders, $actor);
                $ids = (clone $riders)->pluck('id');
                $users = $riders->pluck('user_id')->filter();
                $q->orWhere(fn ($support) => $support->where('participant_type', 'rider')
                    ->where(fn ($members) => $members->whereIn('rider_id', $ids)->orWhereIn('participant_id', $users)));
                // Final authorization also verifies the order's actual party,
                // current rider assignment, and the staff's delivery scope.
                if (! $actor->isAdmin()) {
                    $orders = Delivery::select('order_id')->whereNotNull('order_id');
                    if (! LogisticsAccess::isGlobal($actor)) {
                        $centerId = LogisticsAccess::centerId($actor);
                        if ($centerId !== null) {
                            if ($centerId > 0) $orders->where('center_id', $centerId);
                            else $orders->whereRaw('1 = 0');
                        } else {
                            $companyId = LogisticsAccess::companyId($actor);
                            if ($companyId > 0) $orders->whereHas('logisticsCenter', fn ($hub) => $hub->where('logistics_company_id', $companyId));
                            else $orders->whereRaw('1 = 0');
                        }
                    }
                    $q->orWhere(fn ($delivery) => $delivery->whereIn('participant_type', ['buyer', 'seller'])
                        ->whereIn('order_id', $orders)->whereNotNull('rider_id')->whereHas('messages'));
                }
            }
        });
    }

    public static function unreadCount(Actor $actor): int
    {
        if (! self::active($actor)) return 0;
        $count = $actor instanceof User ? \App\Models\Message::where('is_read', false)
            ->where('sender_id', '!=', $actor->id)->where(fn ($q) => $q->where('receiver_id', $actor->id)
                ->orWhereHas('conversation', fn ($q) => $q->where('buyer_id', $actor->id)->orWhere('seller_id', $actor->id)))->count() : 0;
        // Older marketplace-only installations do not have logistics chat yet.
        if (! \Illuminate\Support\Facades\Schema::hasTable('logistics_conversations')) return $count;
        $incoming = \App\Models\Logistics\Message::whereIn('conversation_id', self::logisticsIds($actor))->where('is_read', false);
        $incoming->where(fn ($q) => $q->where('sender_id', '!=', $actor->id)
            ->orWhere('sender_type', $actor instanceof HubTransportDriver ? '!=' : '=', 'hub_driver'));
        return $count + $incoming->count();
    }
}
