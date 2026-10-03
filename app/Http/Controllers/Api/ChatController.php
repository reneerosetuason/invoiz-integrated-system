<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::with(['lastMessage.sender', 'seller.seller'])
            ->where('buyer_id', $request->user()->id)
            ->withCount(['messages as unread_count' => function ($q) use ($request) {
                $q->where('is_read', false)->where('sender_id', '!=', $request->user()->id);
            }])
            ->latest('updated_at')
            ->get();

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'   => ['required', 'string', 'max:150'],
            'body'      => ['required', 'string', 'max:2000'],
            'seller_id' => ['nullable', 'integer'],
        ]);

        $buyerId = $request->user()->id;

        // Chats initiated from a product/store link to that seller. Reuse an
        // existing thread between this buyer and the seller when possible.
        $conversation = Conversation::where('buyer_id', $buyerId)
            ->where('seller_id', $validated['seller_id'] ?? null)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'buyer_id'  => $buyerId,
                'seller_id' => $validated['seller_id'] ?? null,
                'subject'   => $validated['subject'],
            ]);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $buyerId,
            'body'            => $validated['body'],
        ]);

        $conversation->touch();

        return response()->json([
            'message'      => 'Message sent.',
            'conversation' => $conversation->load('seller.seller'),
        ], 201);
    }

    public function messages(Request $request, $id)
    {
        $conversation = Conversation::where('buyer_id', $request->user()->id)->findOrFail($id);

        // Mark the buyer's received messages as read.
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()->with('sender')->orderBy('created_at')->get();

        return response()->json(['messages' => $messages]);
    }

    public function reply(Request $request, $id)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = Conversation::where('buyer_id', $request->user()->id)->findOrFail($id);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $request->user()->id,
            'body'            => $validated['body'],
        ]);

        $conversation->touch();

        return response()->json(['message' => $message->load('sender')], 201);
    }
}