<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    public function status(Request $request)
    {
        return response()->json(['seller' => Seller::where('user_id', $request->user()->id)->first()]);
    }

    public function me(Request $request)
    {
        $seller = Seller::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($seller->isApproved(), 403, 'Your seller application is not approved yet.');

        return response()->json(['seller' => $seller]);
    }
}
