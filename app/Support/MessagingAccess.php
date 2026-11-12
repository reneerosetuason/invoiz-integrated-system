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
            if ($staff->isLogisticsStaff() && $riderUser->rider
                && LogisticsAccess::canAccessCenter($staff, $riderUser->rider->center_id)) {
                return true;
            }
        }
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
        return Conversation::where(fn ($q) => $q->where('buyer_id', $a->id)->where('seller_id', $b->id))
            ->orWhere(fn ($q) => $q->where('buyer_id', $b->id)->where('seller_id', $a->id))->exists();
    }

    private static function deliveryContact(User $riderUser, User $customer): bool
    {
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
            return $actor->rider && (int) $thread->rider_id === (int) $actor->rider->id;
        }
        return false;
    }

    public static function canSendLogistics(Actor $actor, LogisticsConversation $thread): bool
    {
        if (! self::canReadLogistics($actor, $thread)) {
            return false;
        }
        if (in_array($thread->participant_type, ['buyer', 'seller'], true)) {
            $rider = \App\Models\Rider::find($thread->rider_id);
            $customer = User::find($thread->participant_id);
            return $rider?->user && $customer && self::canContact($rider->user, $customer)
                && Delivery::where('order_id', $thread->order_id)->where('rider_id', $rider->id)->exists();
        }
        if ($thread->participant_type === 'logistics') {
            $otherId = (int) $thread->participant_id === (int) $actor->id ? $thread->logistics_user_id : $thread->participant_id;
            $other = User::find($otherId);
            return $other && self::canContact($actor, $other);
        }
        return true;
    }

    public static function senderType(Actor $actor, LogisticsConversation $thread): string
    {
        if ($actor instanceof HubTransportDriver) return 'hub_driver';
        if ($thread->participant_type === 'rider' && (int) $thread->participant_id === (int) $actor->id) return 'rider';
        if (in_array($thread->participant_type, ['buyer', 'seller'], true)) {
            return (int) $thread->participant_id === (int) $actor->id ? $thread->participant_type : 'rider';
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
