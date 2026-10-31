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
        $complaints = Complaint::where('buyer_id', $user->id)
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
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
        ]);

        $orderId = $validated['order_id'] ?? null;
        if ($orderId && ! Order::where('id', $orderId)->where('buyer_id', $user->id)->exists()) {
            return response()->json(['message' => 'That order does not belong to your account.'], 422);
        }

        $complaint = Complaint::create([
            'buyer_id' => $user->id,
            'order_id' => $orderId,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'type' => $orderId ? 'order' : 'other',
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Complaint received. Our team will review it shortly.',
            'complaint' => $complaint,
        ], 201);
    }
}

