<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Returns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $keys = $this->sellerKeys();

        $returns = ReturnRequest::with(['order', 'product', 'buyer', 'item'])
            ->whereIn('seller_id', $keys)
            ->latest()
            ->paginate(10);

        $counts = [
            'all' => ReturnRequest::whereIn('seller_id', $keys)->count(),
            'requested' => ReturnRequest::whereIn('seller_id', $keys)->where('status', 'requested')->count(),
            'approved' => ReturnRequest::whereIn('seller_id', $keys)->whereIn('status', ['approved', 'item_received'])->count(),
            'refunded'  => ReturnRequest::whereIn('seller_id', $keys)->where('status', 'refunded')->count(),
        ];

        $version = hash('sha256', $returns->getCollection()->toJson().json_encode($counts));
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('seller.returns._records', compact('returns'))->render(),
                'counts' => $counts,
                'version' => $version,
            ])->header('Cache-Control', 'private, no-store');
        }
        $windowDays = max(1, (int) Setting::get('return_window_days', 7));

        return view('seller.returns.index', compact('returns', 'counts', 'version', 'windowDays'));
    }

    public function update(Request $request, ReturnRequest $returnRequest)
    {
        $keys = $this->sellerKeys();
        abort_unless(in_array((int) $returnRequest->seller_id, $keys, true), 403);

        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,receive'],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        return DB::transaction(function () use ($returnRequest, $data, $keys) {
            Order::query()->whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail();
            $returnRequest = ReturnRequest::query()->lockForUpdate()->findOrFail($returnRequest->id);
            abort_unless(in_array((int) $returnRequest->seller_id, $keys, true), 403);

            if ($data['action'] === 'approve') {
                if ($returnRequest->status !== 'requested') {
                    return back()->with('error', 'Only newly requested returns can be approved.');
                }
                $returnRequest->update([
                    'status'        => 'approved',
                    'decision_note' => $data['note'] ?? null,
                ]);

                return back()->with('success', 'Return approved. Ask the buyer to ship the item back.');
            }

            if ($data['action'] === 'reject') {
                if (! in_array($returnRequest->status, ['requested', 'approved'], true)) {
                    return back()->with('error', 'This return can no longer be rejected.');
                }
                $returnRequest->update([
                    'status'        => 'rejected',
                    'decision_note' => $data['note'] ?? null,
                    'resolved_at'   => now(),
                ]);

                return back()->with('success', 'Return rejected.');
            }

            // receive: item came back → refund the buyer and restore stock.
            if ($returnRequest->status !== 'approved') {
                return back()->with('error', 'Approve the return before marking the item received.');
            }

            Returns::complete($returnRequest);

            return back()->with('success', 'Item received — refund recorded and stock restored. Arrange the refund payment with the buyer.');
        }, 3);
    }

    private function sellerKeys(): array
    {
        return [(int) auth()->id()];
    }
}
