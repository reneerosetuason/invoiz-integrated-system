<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Support\ChatBridge;
use App\Support\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function unreadCount(Request $request)
    {
        $count = Message::whereHas('conversation', fn ($q) => $q->where('buyer_id', $request->user()->id))
            ->where('sender_id', '!=', $request->user()->id)->where('is_read', false)->count();

        return response()->json(['unread_count' => $count]);
    }

    public function index(Request $request)
    {
        ChatBridge::linkExistingForBuyer((int) $request->user()->id);
        $conversations = Conversation::with([
            'lastMessage.sender:id,first_name,last_name,name',
            'seller:id,first_name,last_name,name',
            'seller.seller:id,user_id,business_name,store_name',
        ])
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
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'seller_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer', 'required_with:variant_id'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['seller_id'])) {
            $seller = User::find($validated['seller_id']);
            if (! $seller || (int) $seller->id === (int) $request->user()->id || ! $seller->isActive() || (! $seller->canSell() && ! $seller->isAdmin())) {
                throw ValidationException::withMessages(['seller_id' => 'Choose an active store or support contact.']);
            }
        }
        $buyerId = $request->user()->id;

        $productCard = null;
        if (! empty($validated['product_id'])) {
            // Resolve the card from the catalog, never from client-supplied
            // names, prices or image URLs. Keep a snapshot in the message.
            $product = Product::with(['variants', 'images'])->storefront()
                ->where('seller_id', $validated['seller_id'] ?? 0)
                ->find($validated['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['product_id' => 'Choose an available product from this store.']);
            }
            $variant = null;
            if (! empty($validated['variant_id'])) {
                $variant = $product->variants->firstWhere('id', $validated['variant_id']);
                if (! $variant || in_array($variant->status, ['inactive', 'unavailable', 'deleted'], true)) {
                    throw ValidationException::withMessages(['variant_id' => 'Choose an available option for this product.']);
                }
            }
            $image = $product->image ?: $product->images
                ->sortBy(fn ($image) => [! $image->is_main, (int) $image->sort_order, $image->id])
                ->first()?->resolved_path;
            $productCard = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $image,
                'price' => round(Sales::priceFor($product) + (float) ($variant?->price_adjustment ?? 0), 2),
                'currency' => 'PHP',
                'variant_id' => $variant?->id,
                'option' => $variant ? $variant->variant_type.': '.$variant->variant_value : null,
            ];
        }

        $conversation = DB::transaction(function () use ($buyerId, $validated, $productCard) {
            // Chats initiated from a product/store link to that seller. Reuse an
            // existing thread between this buyer and the seller when possible.
            $conversation = Conversation::where('buyer_id', $buyerId)
                ->where('seller_id', $validated['seller_id'] ?? null)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'buyer_id' => $buyerId,
                    'seller_id' => $validated['seller_id'] ?? null,
                    'subject' => $validated['subject'],
                ]);
            }

            $attributes = [
                'conversation_id' => $conversation->id,
                'sender_id' => $buyerId,
                'receiver_id' => $validated['seller_id'] ?? $conversation->seller_id,
                'body' => $validated['body'],
            ];
            if ($productCard !== null) {
                $attributes['product_card'] = $productCard;
            }
            Message::create($attributes);

            $conversation->touch();

            return $conversation;
        });

        return response()->json([
            'message' => 'Message sent.',
            'conversation' => $conversation->load(['seller:id,first_name,last_name,name', 'seller.seller:id,user_id,business_name,store_name']),
        ], 201);
    }

    public function messages(Request $request, $id)
    {
        $conversation = Conversation::where('buyer_id', $request->user()->id)->findOrFail($id);

        // Mark the buyer's received messages as read.
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['is_read' => true]);

        $messages = $conversation->messages()->with('sender:id,first_name,last_name,name')->orderBy('created_at')->orderBy('id')->get();

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
            'sender_id' => $request->user()->id,
            'receiver_id' => $conversation->seller_id,
            'body' => $validated['body'],
        ]);

        $conversation->touch();

        return response()->json(['message' => $message->load('sender:id,first_name,last_name,name')], 201);
    }
}
