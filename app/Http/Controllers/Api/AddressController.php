<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = Address::where('buyer_id', $request->user()->id)->orderByDesc('is_default')->get();

        return response()->json(['addresses' => $addresses]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:30'],
            'address_line'   => ['required', 'string', 'max:255'],
            'barangay'       => ['required', 'string', 'max:100'],
            'city'           => ['required', 'string', 'max:100'],
            'province'       => ['required', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            Address::where('buyer_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address = Address::create([
            ...$validated,
            'buyer_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Address saved.', 'address' => $address], 201);
    }

    public function update(Request $request, $id)
    {
        $address = Address::where('buyer_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'recipient_name' => ['sometimes', 'string', 'max:100'],
            'phone'          => ['sometimes', 'string', 'max:30'],
            'address_line'   => ['sometimes', 'string', 'max:255'],
            'barangay'       => ['sometimes', 'string', 'max:100'],
            'city'           => ['sometimes', 'string', 'max:100'],
            'province'       => ['sometimes', 'string', 'max:100'],
            'postal_code'    => ['nullable', 'string', 'max:20'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['is_default']) && $validated['is_default']) {
            Address::where('buyer_id', $request->user()->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['message' => 'Address updated.', 'address' => $address]);
    }

    public function destroy(Request $request, $id)
    {
        $address = Address::where('buyer_id', $request->user()->id)->findOrFail($id);
        $address->delete();

        return response()->json(['message' => 'Address deleted.']);
    }
}