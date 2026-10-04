<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/**
 * Bridges admin/seller-center direct messages (sender_id/receiver_id)
 * with the buyer app's conversation threads (conversation_id).
 *
 * The buyer mobile app only reads messages through `conversations`
 * (buyer_id scope), so a DM without `conversation_id` is invisible
 * there even though it saves fine. Every created message that
 * involves a buyer is attached to that buyer thread here.
 */
class ChatBridge
{
    public static function link(Message $message): void
    {
        if ($message->conversation_id) {
            Conversation::where('id', $message->conversation_id)->update([
                'updated_at' => now(),
            ]);

            return;
        }

        $sender = $message->sender_id ? User::find($message->sender_id) : null;
        $receiver = $message->receiver_id ? User::find($message->receiver_id) : null;

        $buyer = null;
        $other = null;
        if ($receiver && $receiver->role === 'buyer') {
            $buyer = $receiver;
            $other = $sender;
        } elseif ($sender && $sender->role === 'buyer') {
            $buyer = $sender;
            $other = $receiver;
        }

        // No buyer involved: admin <-> seller support threads are carried
        // by a conversation too (admin recorded as the contact party),
        // so the seller's conversation inbox shows them.
        if (! $buyer) {
            $admin = null;
            $sellerUser = null;
            foreach ([$sender, $receiver] as $party) {
                if (! $party) {
                    continue;
                }
                if ($party->role === 'admin' && ! $admin) {
                    $admin = $party;
                } elseif (\App\Models\Seller::where('user_id', $party->id)->exists() && ! $sellerUser) {
                    $sellerUser = $party;
                }
            }
            if (! $admin || ! $sellerUser) {
                return;
            }
            $conversation = Conversation::firstOrCreate(
                ['buyer_id' => $admin->id, 'seller_id' => $sellerUser->id],
                ['subject' => 'Support']
            );
            $message->conversation_id = $conversation->id;
            $conversation->touch();
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['buyer_id' => $buyer->id, 'seller_id' => $other?->id],
            ['subject' => 'Support']
        );

        $message->conversation_id = $conversation->id;
        $conversation->touch();
    }
}
