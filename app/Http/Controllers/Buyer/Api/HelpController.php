<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    /**
     * Return public support info and FAQs.
     */
    public function index(): JsonResponse
    {
        $support = config('support') ?? [];

        return response()->json([
            'email' => $support['email'] ?? 'invoizecommerce@gmail.com',
            'phone' => $support['phone'] ?? '',
            'hours' => $support['hours'] ?? 'Monday to Saturday, 9:00 AM – 6:00 PM (PHT)',
            'response_time' => $support['response_time'] ?? 'within 24 hours on business days',
            'faqs' => $support['faqs'] ?? [],
            'quick_replies' => $support['quick_replies'] ?? [],
        ]);
    }

    /**
     * Get complaints filed by the authenticated buyer.
     */
    public function myComplaints(Request $request): JsonResponse
    {
        $user = $request->user();
        $complaints = Complaint::with(['order.items', 'seller', 'orderItem', 'product'])
            ->where('buyer_id', $user->id)
            ->latest('created_at')
            ->get();

        return response()->json([
            'complaints' => $complaints,
        ]);
    }

    /**
     * File a new complaint from mobile.
     */
    public function storeComplaint(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'seller_id' => ['nullable', 'integer'],
        ]);

        $orderId = $validated['order_id'] ?? null;
        $order = null;
        if ($orderId) {
            $order = Order::with('items')->where('id', $orderId)->where('buyer_id', $user->id)->first();
            if (! $order) {
                return response()->json(['message' => 'That order does not belong to your account.'], 422);
            }
        }

        $orderItemId = $validated['order_item_id'] ?? null;
        $orderItem = null;
        if ($orderItemId && $order) {
            $orderItem = $order->items->firstWhere('id', (int) $orderItemId);
            if (! $orderItem) {
                $orderItemId = null;
            }
        }

        $productId = $orderItem?->product_id;

        // Resolve canonical Seller ID
        $sellerId = null;
        if (! empty($validated['seller_id'])) {
            $sCandidate = \App\Models\Seller::where('id', (int) $validated['seller_id'])->orWhere('user_id', (int) $validated['seller_id'])->first();
            if ($sCandidate) $sellerId = $sCandidate->id;
        }
        if (! $sellerId && $orderItem && $orderItem->seller_id) {
            $sCandidate = \App\Models\Seller::where('user_id', (int) $orderItem->seller_id)->orWhere('id', (int) $orderItem->seller_id)->first();
            if ($sCandidate) $sellerId = $sCandidate->id;
        }
        if (! $sellerId && $order) {
            if ($order->seller_id) {
                $sCandidate = \App\Models\Seller::where('id', (int) $order->seller_id)->orWhere('user_id', (int) $order->seller_id)->first();
                if ($sCandidate) $sellerId = $sCandidate->id;
            } elseif ($order->items->isNotEmpty()) {
                $firstSellerId = (int) $order->items->first()->seller_id;
                $sCandidate = \App\Models\Seller::where('user_id', $firstSellerId)->orWhere('id', $firstSellerId)->first();
                if ($sCandidate) $sellerId = $sCandidate->id;
            }
        }

        $seller = $sellerId ? \App\Models\Seller::find($sellerId) : null;
        $storeName = $seller?->store_name ?? ($order?->store_name_display ?? 'Invoiz Store');

        $subject = $validated['subject'] ?? null;
        if ($order) {
            $orderNum = $order->order_number ?? $order->id;
            if ($orderItem) {
                $subject = "Order #{$orderNum} ({$orderItem->product_name}) — Store: {$storeName}";
            } else {
                $subject = "Order #{$orderNum} — Store: {$storeName}";
            }
            $subject = \Illuminate\Support\Str::limit($subject, 150, '...');
        } elseif (empty($subject)) {
            $subject = 'General inquiry';
        }

        $complaint = Complaint::create([
            'buyer_id' => $user->id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'subject' => $subject,
            'description' => $validated['description'],
            'type' => $orderId ? 'order' : 'other',
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Complaint received. Our team will review it shortly.',
            'complaint' => $complaint->load(['seller', 'orderItem', 'product']),
        ], 201);
    }
}

