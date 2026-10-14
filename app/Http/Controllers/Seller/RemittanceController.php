<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Remittance;
use App\Support\Remittances;
use Illuminate\Http\Request;

class RemittanceController extends Controller
{
    public function index()
    {
        $keys = Remittances::sellerKeys(auth()->id());

        $pendingQuery = Remittance::with(['order', 'delivery', 'rider'])
            ->whereIn('seller_id', $keys)
            ->whereIn('status', ['pending', 'sent'])
            ->latest();

        $pending = (clone $pendingQuery)->paginate(10, ['*'], 'pending_page');

        $history = Remittance::with(['order', 'delivery', 'rider'])
            ->whereIn('seller_id', $keys)
            ->where('status', 'confirmed')
            ->latest()
            ->paginate(10, ['*'], 'history_page');

        $pendingTotal = round((clone $pendingQuery)->sum('amount'), 2);

        return view('seller.remittances.index', compact('pending', 'history', 'pendingTotal'));
    }

    public function confirm(Request $request, $id)
    {
        $keys = Remittances::sellerKeys(auth()->id());
        $remittance = Remittance::whereIn('seller_id', $keys)->findOrFail($id);

        $request->validate([
            'reference' => ['nullable', 'string', 'max:150'],
        ]);

        $settled = Remittances::confirm(
            $remittance,
            $keys,
            $request->input('reference')
        );

        return back()->with(
            'success',
            $settled
                ? 'Cash receipt confirmed — order #' . $remittance->order_id . ' is now settled and paid.'
                : 'Cash receipt confirmed. The order settles once every delivery is confirmed.'
        );
    }
}
