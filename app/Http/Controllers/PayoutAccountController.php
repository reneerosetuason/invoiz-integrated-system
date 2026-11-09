<?php

namespace App\Http\Controllers;

use App\Models\{EscrowRemittance, PayoutAccount, ReturnAllocation};
use App\Support\PayoutAccounts;
use Illuminate\Http\Request;

class PayoutAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = PayoutAccounts::forActor($request->user());
        abort_if($accounts->isEmpty(), 403);
        return response()->json(['accounts' => $accounts])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request)
    {
        $targets = PayoutAccounts::targets($request->user());
        abort_if($targets->isEmpty(), 403);
        $data = $request->validate([
            'target' => ['required', 'string'],
            'provider' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            'holder' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x1F\x7F]/'],
            'account' => ['required', 'string', 'min:4', 'max:100', 'regex:/[\p{L}\p{N}]/u', 'not_regex:/[\x00-\x1F\x7F]/'],
        ]);
        $target = $targets->firstWhere('key', $data['target']);
        abort_unless($target, 403);
        PayoutAccount::updateOrCreate(['owner_type' => $target['type'], 'owner_id' => $target['id']], [
            'details' => ['provider' => trim($data['provider']), 'holder' => trim($data['holder']), 'account' => trim($data['account'])],
        ]);
        return $request->expectsJson()
            ? response()->json(['message' => 'Payout details saved. Admin will see these on your remittances.', 'accounts' => PayoutAccounts::forActor($request->user())])->header('Cache-Control', 'private, no-store')
            : back()->with('payout_account_saved', 'Payout details saved. These apply to all your order and return payments.');
    }

    public function showForRemittance(Request $request, string $source, int $id)
    {
        abort_unless($request->user()?->isAdmin() && $request->user()->isActive(), 403);
        $row = match ($source) {
            'order' => EscrowRemittance::findOrFail($id),
            'return' => ReturnAllocation::findOrFail($id),
            default => abort(404),
        };
        return response()->json(['account' => PayoutAccounts::forRemittance($row)])->header('Cache-Control', 'private, no-store');
    }
}
