<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function conversationsForSeller(int $sellerId)
    {
        return Conversation::query()->where(function ($scope) use ($sellerId) {
            $scope->where('seller_id', $sellerId)
                ->orWhere(function ($legacy) use ($sellerId) {
                    $legacy->whereNull('seller_id')->whereHas('messages', function ($messages) use ($sellerId) {
                        $messages->where(function ($participant) use ($sellerId) {
                            $participant->where('sender_id', $sellerId)->orWhere('receiver_id', $sellerId);
                        });
                    });
                });
        });
    }

    public function index()
    {
        $sellerId = auth()->id();

        $conversations = $this->conversationsForSeller($sellerId)->with(['buyer', 'lastMessage.sender'])
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

        $conversation = $this->conversationsForSeller($sellerId)
            ->with(['buyer', 'messages.sender'])
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

        $conversation = $this->conversationsForSeller($sellerId)->findOrFail($id);

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