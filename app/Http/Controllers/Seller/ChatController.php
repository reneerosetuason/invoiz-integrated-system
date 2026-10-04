<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        $sellerId = auth()->id();

        $conversations = Conversation::with(['buyer', 'lastMessage.sender'])
            ->where(function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId)
                    ->orWhereHas('buyer', function ($buyer) use ($sellerId) {
                        $buyer->whereHas('orders.items', function ($items) use ($sellerId) {
                            $items->where('seller_id', $sellerId);
                        });
                    });
            })
            ->withCount(['messages as unread_count' => function ($q) use ($sellerId) {
                $q->where('is_read', false)->where('sender_id', '!=', $sellerId);
            }])
            ->latest('updated_at')
            ->get();

        return view('seller.chat.index', compact('conversations'));
    }

    public function show($id)
    {
        $sellerId = auth()->id();

        $conversation = Conversation::with(['buyer', 'messages.sender'])
            ->where(function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId)
                    ->orWhereHas('buyer', function ($buyer) use ($sellerId) {
                        $buyer->whereHas('orders.items', function ($items) use ($sellerId) {
                            $items->where('seller_id', $sellerId);
                        });
                    });
            })
            ->findOrFail($id);

        // Mark the buyer's messages as read by the seller.
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $sellerId)
            ->update(['is_read' => true]);

        // Auto-link the conversation to this store when the seller replies.
        if (! $conversation->seller_id) {
            $conversation->update(['seller_id' => $sellerId]);
        }

        $messages = $conversation->messages()->with('sender')->orderBy('created_at')->get();

        return view('seller.chat.show', compact('conversation', 'messages'));
    }

    public function reply(Request $request, $id)
    {
        $sellerId = auth()->id();

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = Conversation::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
                ->orWhereHas('buyer', function ($buyer) use ($sellerId) {
                    $buyer->whereHas('orders.items', function ($items) use ($sellerId) {
                        $items->where('seller_id', $sellerId);
                    });
                });
        })->findOrFail($id);

        if (! $conversation->seller_id) {
            $conversation->update(['seller_id' => $sellerId]);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $sellerId,
            'receiver_id'     => $conversation->buyer_id,
            'body'            => $validated['body'],
            'is_read'         => false,
        ]);

        $conversation->touch();

        return back()->with('success', 'Message sent.');
    }
}