<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\BuyerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function buyer(Request $request, int $order): StreamedResponse
    {
        $buyerId = $request->user()?->id ?? (BuyerSession::current()['id'] ?? null);
        abort_unless($buyerId, 401);

        return $this->receipt(Order::where('buyer_id', $buyerId)->findOrFail($order));
    }

    public function seller(Request $request, int $order): StreamedResponse
    {
        return $this->receipt(Order::whereHas('items', fn ($query) => $query
            ->where('seller_id', $request->user()->id))->findOrFail($order));
    }

    private function receipt(Order $order): StreamedResponse
    {
        $payment = $order->payment;
        $path = $payment?->proof_path;
        abort_unless($payment?->method === 'gcash' && is_string($path)
            && str_starts_with($path, 'payment-proofs/')
            && Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, 'gcash-receipt-'.$order->id.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
