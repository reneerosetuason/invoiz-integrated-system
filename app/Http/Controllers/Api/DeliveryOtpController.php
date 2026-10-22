<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\DeliveryOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryOtpController extends Controller
{
    public function show(
        Request $request,
        Order $order,
        Delivery $delivery,
        DeliveryOtpService $otpService,
    ): JsonResponse {
        abort_unless((int) $order->buyer_id === (int) $request->user()->id, 404);
        abort_unless((int) $delivery->order_id === (int) $order->id, 404);

        if (! in_array($delivery->status, DeliveryOtpService::ELIGIBLE_STATUSES, true)) {
            return response()->json([
                'message' => 'The delivery OTP is available once the parcel is out for delivery.',
            ], 409);
        }

        $payload = $otpService->forBuyer($delivery);

        return response()->json([
            'message' => 'Delivery OTP ready.',
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'otp' => $payload['otp'],
            'expires_at' => $payload['expires_at'],
            'attempts_remaining' => $payload['attempts_remaining'],
        ]);
    }
}
