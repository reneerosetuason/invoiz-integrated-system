<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Rules\PhilippinePhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        Address::ensureForBuyer((int) $request->user()->id);
        $addresses = Address::where('buyer_id', $request->user()->id)->orderByDesc('is_default')->get();

        return response()->json(['addresses' => $addresses]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', new PhilippinePhone()],
            'address_line'   => ['required', 'string', 'max:255'],
            'barangay'       => ['required', 'string', 'max:100'],
            'city'           => ['required', 'string', 'max:100'],
            'province'       => ['required', 'string', 'max:100'],
            'region'         => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        unset($validated['is_default']);
        $address = DB::transaction(function () use ($request, $validated) {
            $buyerId = (int) $request->user()->id;
            \App\Models\User::query()->whereKey($buyerId)->lockForUpdate()->firstOrFail();
            $hasAddress = Address::query()->where('buyer_id', $buyerId)->lockForUpdate()->exists();
            $makeDefault = $request->boolean('is_default') || ! $hasAddress;

            if ($makeDefault) {
                Address::where('buyer_id', $buyerId)->update(['is_default' => false]);
            }

            return Address::create($validated + [
                'buyer_id' => $buyerId,
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json(['message' => 'Address saved.', 'address' => $address], 201);
    }

    public function update(Request $request, $id)
    {
        Address::ensureForBuyer((int) $request->user()->id);
        $address = Address::where('buyer_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'recipient_name' => ['sometimes', 'string', 'max:100'],
            'phone'          => ['sometimes', 'string', new PhilippinePhone()],
            'address_line'   => ['sometimes', 'string', 'max:255'],
            'barangay'       => ['sometimes', 'string', 'max:100'],
            'city'           => ['sometimes', 'string', 'max:100'],
            'province'       => ['sometimes', 'string', 'max:100'],
            'region'         => ['nullable', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['phone'])) {
            $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        }

        DB::transaction(function () use ($request, $address, &$validated) {
            $buyerId = (int) $request->user()->id;
            \App\Models\User::query()->whereKey($buyerId)->lockForUpdate()->firstOrFail();
            $lockedAddress = Address::query()->where('buyer_id', $buyerId)->lockForUpdate()->findOrFail($address->id);

            if (! empty($validated['is_default'])) {
                Address::where('buyer_id', $buyerId)->update(['is_default' => false]);
                $validated['is_default'] = true;
            } else {
                // Never allow an update to leave a buyer with no default.
                unset($validated['is_default']);
            }

            $lockedAddress->update($validated);
        });

        $address->refresh();

        return response()->json(['message' => 'Address updated.', 'address' => $address]);
    }

    public function destroy(Request $request, $id)
    {
        Address::ensureForBuyer((int) $request->user()->id);
        $deleted = DB::transaction(function () use ($request, $id) {
            $buyerId = (int) $request->user()->id;
            \App\Models\User::query()->whereKey($buyerId)->lockForUpdate()->firstOrFail();
            $address = Address::query()->where('buyer_id', $buyerId)->lockForUpdate()->findOrFail($id);

            if ($address->is_default) {
                return false;
            }

            $address->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Choose another address as the default before deleting this one.',
            ], 409);
        }

        return response()->json(['message' => 'Address deleted.']);
    }
}
