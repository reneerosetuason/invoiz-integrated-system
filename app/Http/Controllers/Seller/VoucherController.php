<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::where(function ($q) {
            $q->whereNull('seller_id')->orWhere('seller_id', auth()->id());
        })->latest()->get();

        return view('seller.vouchers.index', compact('vouchers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'unique:vouchers,code'],
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'discount_type'  => ['required', 'in:fixed,percent'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_discount'   => ['nullable', 'numeric', 'min:0'],
            'valid_from'     => ['nullable', 'date'],
            'valid_until'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit'    => ['nullable', 'integer', 'min:1'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        Voucher::create([
            'seller_id'      => auth()->id(),
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
        $voucher = Voucher::where(function ($q) {
            $q->whereNull('seller_id')->orWhere('seller_id', auth()->id());
        })->findOrFail($id);

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'discount_type'  => ['required', 'in:fixed,percent'],
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
        $voucher = Voucher::where('seller_id', auth()->id())->findOrFail($id);

        if ($voucher->used_count > 0) {
            return back()->with('error', 'This voucher has already been used and cannot be deleted.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }
}