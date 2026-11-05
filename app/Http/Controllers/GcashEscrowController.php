<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\EscrowRemittance;
use App\Models\GcashEscrowDeposit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\VoucherClaim;
use App\Services\GcashEscrowService;
use App\Support\BuyerSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GcashEscrowController extends Controller
{
    private const IMAGE = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public function quote(Request $request)
    {
        $buyerId = $request->user()?->id ?? BuyerSession::current()['id'] ?? null;
        abort_unless($buyerId, 401);
        $data = $request->validate([
            'checkout_mode' => ['required', 'in:single,seller,all,selected'], 'single' => ['nullable', 'integer'],
            'variant_id' => ['nullable', 'integer'], 'qty' => ['nullable', 'integer', 'min:1', 'max:200'],
            'seller_id' => ['nullable', 'integer'], 'selected_item_ids' => ['nullable', 'array', 'max:200'],
            'selected_item_ids.*' => ['integer'], 'voucher_claim_ids' => ['nullable', 'array', 'max:3'], 'voucher_claim_ids.*' => ['integer'],
        ]);
        if ($data['checkout_mode'] === 'single') {
            $product = Product::where('status', 'active')->findOrFail($data['single'] ?? 0);
            $variant = Cart::purchaseVariant($product, $data['variant_id'] ?? null);
            $subtotals = [$product->seller_id => Cart::unitFor($product, $variant) * ($data['qty'] ?? 1)];
        } else {
            $lines = $data['checkout_mode'] === 'selected'
                ? Cart::selectedLinesFor($buyerId, $data['selected_item_ids'] ?? [])
                : Cart::linesFor($buyerId);
            if ($data['checkout_mode'] === 'seller') {
                $lines = $lines->filter(fn ($line) => (int) $line['product']->seller_id === (int) ($data['seller_id'] ?? 0));
            }
            $subtotals = $lines->groupBy(fn ($line) => $line['product']->seller_id)->map(fn ($group) => $group->sum('line'))->all();
        }
        $ids = array_values(array_unique($data['voucher_claim_ids'] ?? []));
        $claims = VoucherClaim::with('voucher')->where('buyer_id', $buyerId)->whereNull('used_at')->whereIn('id', $ids)->get();
        if ($claims->count() !== count($ids)) {
            throw ValidationException::withMessages(['voucher_claim_ids' => 'A selected voucher is unavailable.']);
        }
        $vouchers = $claims->pluck('voucher')->filter();

        return response()->json(GcashEscrowService::quoteForStores($subtotals, $vouchers));
    }

    public function qr(int $seller)
    {
        $store = Seller::approved()->where('user_id', $seller)->firstOrFail();
        $path = $store->gcash_qr_path;
        abort_unless($path && str_starts_with($path, 'seller-gcash-qr/') && Storage::disk('public')->exists($path), 404);
        $mime = Storage::disk('public')->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk('public')->response($path, null, ['Content-Type' => $mime, 'Cache-Control' => 'no-cache, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function sellerQr(Request $request)
    {
        $data = $request->validate(['gcash_account_name' => ['required', 'string', 'max:150'], 'gcash_qr' => self::IMAGE]);
        $seller = $request->user()->seller()->firstOrFail();
        $path = $request->file('gcash_qr')->store('seller-gcash-qr', 'public');
        try {
            $seller->update(['gcash_account_name' => $data['gcash_account_name'], 'gcash_qr_path' => $path]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return back()->with('success', 'GCash receiving QR updated. Buyers will see this account at checkout.');
    }

    public function verify(Request $request, Order $order)
    {
        $request->validate(['confirm_received' => ['required', 'accepted']]);
        GcashEscrowService::verifyReceipt($order, $request->user());

        return back()->with('success', 'Buyer receipt verified. Transfer the entire order payment to escrow and submit the transfer receipt.');
    }

    public function acceptSellerPayout(Request $request, EscrowRemittance $remittance)
    {
        abort_unless($remittance->recipient_role === EscrowRemittance::ROLE_SELLER
            && (int) $remittance->recipient_user_id === (int) $request->user()->id, 404);
        $request->validate(['confirm_received' => ['required', 'accepted']]);
        $accepted = $remittance->accept($request->user());

        return back()->with('success', $accepted ? 'Escrow payout receipt confirmed.' : 'Payout was already processed or has not been released.');
    }

    public function submit(Request $request, Order $order)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9-]+$/'], 'escrow_proof' => self::IMAGE]);
        $path = $request->file('escrow_proof')->store('escrow-deposit-proofs', 'private');
        try {
            DB::transaction(function () use ($request, $order, $data, $path) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                GcashEscrowService::assertSeller($order, $request->user());
                $deposit = GcashEscrowDeposit::where('order_id', $order->id)->lockForUpdate()->first();
                if (! $deposit || ! in_array($deposit->status, ['receipt_verified', 'rejected'], true)) {
                    throw ValidationException::withMessages(['gcash' => 'Verify the buyer receipt first. A submitted or confirmed deposit cannot be replaced.']);
                }
                if (! DB::table('gcash_escrow_settings')->exists()) {
                    throw ValidationException::withMessages(['gcash' => 'Admin must configure the escrow receiving account first.']);
                }
                if ((int) round((float) $data['amount'] * 100) !== (int) round((float) $order->total_amount * 100)) {
                    throw ValidationException::withMessages(['amount' => 'Transfer the whole buyer payment including shipping, with no cuts deducted.']);
                }
                $reference = strtoupper(trim($data['reference']));
                if (GcashEscrowDeposit::where('reference', $reference)->where('id', '!=', $deposit->id)->exists()) {
                    throw ValidationException::withMessages(['reference' => 'This transfer reference is already used by another order.']);
                }
                // Audit retains prior evidence when a rejected submission is replaced.
                GcashEscrowService::audit($request->user(), 'gcash.escrow_resubmitting', $deposit);
                $deposit->update(['amount' => $order->total_amount, 'reference' => $reference, 'proof_path' => $path,
                    'submitted_at' => now(), 'status' => 'submitted', 'confirmed_at' => null, 'confirmed_by' => null]);
                GcashEscrowService::audit($request->user(), 'gcash.escrow_submitted', $deposit);
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Escrow deposit submitted. Order acceptance unlocks after admin checks the actual incoming funds.');
    }

    public function index()
    {
        return view('admin.gcash-escrow', [
            'deposits' => GcashEscrowDeposit::with(['order', 'seller'])->latest()->paginate(20),
            'settings' => DB::table('gcash_escrow_settings')->first(),
        ]);
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['account_name' => ['required', 'string', 'max:150'],
            'account_details' => ['required', 'string', 'max:500'], 'qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $old = DB::table('gcash_escrow_settings')->first();
        $path = $request->hasFile('qr') ? $request->file('qr')->store('escrow-receiving-qr', 'public') : $old?->qr_path;
        DB::table('gcash_escrow_settings')->updateOrInsert(['id' => 1], [
            'account_name' => $data['account_name'], 'account_details' => $data['account_details'],
            'qr_path' => $path, 'created_at' => $old?->created_at ?? now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Escrow receiving instructions saved. Confirm deposits against this account before approving them.');
    }

    public function review(Request $request, GcashEscrowDeposit $deposit)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['required', 'string', 'max:1000'],
            'verified_in_account' => ['exclude_unless:decision,approve', 'required', 'accepted']]);
        GcashEscrowService::confirm($deposit, $request->user(), $data['decision'] === 'approve', $data['note']);

        return back()->with('status', $data['decision'] === 'approve' ? 'Full escrow deposit confirmed. Seller can accept the order.' : 'Deposit rejected. Seller can correct and resubmit it.');
    }

    public function proof(GcashEscrowDeposit $deposit)
    {
        abort_unless($deposit->proof_path && str_starts_with($deposit->proof_path, 'escrow-deposit-proofs/')
            && Storage::disk('private')->exists($deposit->proof_path), 404);

        return Storage::disk('private')->response($deposit->proof_path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
