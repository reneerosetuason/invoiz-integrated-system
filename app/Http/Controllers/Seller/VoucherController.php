<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Seller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherController extends Controller
{
    public function index()
    {
        $sellerIds = $this->sellerRecord()->recordIds();
        $scope = Voucher::withCount(['claims', 'orderVouchers'])->whereIn('seller_id', $sellerIds);
        $vouchers = (clone $scope)->latest()->paginate(10);
        $voucherMap = (clone $scope)->latest()->get();

        return view('seller.vouchers.index', compact('vouchers', 'voucherMap'));
    }

    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code'           => ['required', 'string', 'regex:/^[A-Z0-9]{4,12}$/', 'unique:vouchers,code'],
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'discount_type'  => ['required', 'in:fixed,percent,free_shipping'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'valid_from'     => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit'    => ['nullable', 'integer', 'min:1'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        Voucher::create([
            'seller_id'      => $this->sellerRecord()->id,
            'code'           => strtoupper($validated['code']),
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'min_spend'      => $validated['min_spend'] ?? 0,
            'max_discount'   => $validated['max_discount'] ?? null,
            'valid_from'     => $validated['valid_from'] ?? null,
            'valid_until'    => $validated['valid_until'] ?? null,
            'usage_limit'    => $validated['usage_limit'] ?? null,
            'status'         => $validated['status'],
        ]);

        return back()->with('success', 'Voucher created successfully.');
    }

    public function update(Request $request, $id)
    {
        $sellerIds = $this->sellerRecord()->recordIds();
        $voucher = Voucher::whereIn('seller_id', $sellerIds)->findOrFail($id);

        // Once a buyer has claimed the voucher it is locked: only the
        // active/inactive toggle may change.
        if ($voucher->used_count > 0 || $voucher->claims()->exists() || $voucher->orderVouchers()->exists()) {
            $request->validate(['status' => ['required', 'in:active,inactive']]);
            $voucher->update(['status' => $request->input('status')]);

            return back()->with('success', 'This voucher has been claimed or used, so only its active status was updated.');
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'discount_type'  => ['required', 'in:fixed,percent,free_shipping'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'valid_from'     => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit'    => ['nullable', 'integer', 'min:1'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        $voucher->update([
            'name'           => $validated['name'],
            'description'    => $validated['description'] ?? null,
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'min_spend'      => $validated['min_spend'] ?? 0,
            'max_discount'   => $validated['max_discount'] ?? null,
            'valid_from'     => $validated['valid_from'] ?? null,
            'valid_until'    => $validated['valid_until'] ?? null,
            'usage_limit'    => $validated['usage_limit'] ?? null,
            'status'         => $validated['status'],
        ]);

        return back()->with('success', 'Voucher updated successfully.');
    }

    public function destroy($id)
    {
        $voucher = Voucher::whereIn('seller_id', $this->sellerRecord()->recordIds())->findOrFail($id);

        if ($voucher->used_count > 0 || $voucher->claims()->exists() || $voucher->orderVouchers()->exists()) {
            return back()->with('error', 'This voucher has already been claimed and cannot be deleted.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }

    public function updateStatus(Request $request, $id)
    {
        $sellerIds = $this->sellerRecord()->recordIds();
        DB::transaction(function () use ($request, $id, $sellerIds) {
            // Checkout locks the same row before applying the voucher, so a
            // status change cannot race its final availability check.
            $voucher = Voucher::whereIn('seller_id', $sellerIds)->lockForUpdate()->findOrFail($id);
            $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);
            $voucher->update(['status' => $validated['status']]);
        });

        return back()->with('success', $request->input('status') === 'active' ? 'Voucher activated.' : 'Voucher deactivated.');
    }

    private function sellerRecord(): Seller
    {
        return Seller::where('user_id', auth()->id())->firstOrFail();
    }
}
