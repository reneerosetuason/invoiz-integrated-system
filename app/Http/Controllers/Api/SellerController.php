<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SellerController extends Controller
{
    // Submit a seller application for the currently logged-in buyer.
    // The account already holds the personal info (name, address, etc.),
    // so this only adds the business side to that same identity.
    public function apply(Request $request)
    {
        $user = $request->user();

        $existing = Seller::where('user_id', $user->id)->first();
        if ($existing) {
            return response()->json([
                'message' => $existing->isApproved()
                    ? 'Your account is already an approved seller.'
                    : 'You already have a pending seller application.',
            ], 422);
        }

        $validated = $request->validate([
            'business_name'    => ['required', 'string', 'max:150'],
            'line_of_business' => ['required', 'string', 'max:100'],
            'id_image'         => ['nullable', 'image', 'max:5120'],
            'business_permit'  => ['nullable', 'image', 'max:5120'],
        ]);

        $idImagePath = null;
        if ($request->hasFile('id_image')) {
            $idImagePath = $request->file('id_image')->store('seller-ids', 'public');
        }

        $permitPath = null;
        if ($request->hasFile('business_permit')) {
            $permitPath = $request->file('business_permit')->store('seller-permits', 'public');
        }

        $seller = Seller::create([
            'user_id'         => $user->id,
            'business_name'   => $validated['business_name'],
            'line_of_business'=> $validated['line_of_business'],
            'id_image'        => $idImagePath,
            'business_permit' => $permitPath,
            'approval_status' => 'pending',
            'status'          => 'active',
        ]);

        return response()->json([
            'message' => 'Seller application submitted. Please wait for the administrator\'s approval.',
            'seller'  => $seller->fresh(),
        ], 201);
    }

    // Current user's seller application (or null if never applied).
    public function status(Request $request)
    {
        $seller = Seller::where('user_id', $request->user()->id)->first();

        return response()->json([
            'seller' => $seller,
        ]);
    }

    // Seller profile for an approved seller.
    public function me(Request $request)
    {
        $seller = Seller::where('user_id', $request->user()->id)->first();

        if (! $seller) {
            return response()->json(['message' => 'No seller application found.'], 404);
        }

        if (! $seller->isApproved()) {
            return response()->json([
                'message' => 'Your seller application is not approved yet.',
            ], 403);
        }

        return response()->json([
            'seller' => $seller,
        ]);
    }
}