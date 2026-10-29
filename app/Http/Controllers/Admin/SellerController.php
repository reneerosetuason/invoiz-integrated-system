<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\NotificationsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Throwable;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $query = Seller::with(['user', 'products']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter mapped to shared invoizdb schema:
        // pending/rejected -> approval_status, suspended -> status=inactive,
        // approved -> approval_status=approved (+ legacy status=approved).
        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'suspended', 'rejected'])) {
            match ($request->status) {
                'pending' => $query->where('approval_status', 'pending'),
                'rejected' => $query->where('approval_status', 'rejected'),
                'suspended' => $query->where('status', 'inactive'),
                'approved' => $query->approved(),
            };
        }

        $sellers = $query->withCount('products')->orderBy('created_at', 'desc')->paginate(10);
        $stats = [
            'all' => Seller::count(),
            'pending' => Seller::where('approval_status', 'pending')->count(),
            'approved' => Seller::approved()->count(),
            'suspended' => Seller::where('status', 'inactive')->count(),
            'rejected' => Seller::where('approval_status', 'rejected')->count(),
        ];
        if ($request->wantsJson()) {
            return $sellers;
        }
        return view('admin.sellers', compact('sellers', 'stats'));
    }

    public function approve(Request $request, Seller $seller)
    {
        $seller->loadMissing('user');
        DB::transaction(function () use ($seller) {
            $seller->approval_status = 'approved';
            $seller->rejection_reason = null;
            $seller->status = 'active';
            $seller->save();

            if ($seller->user) {
                $user = $seller->user;

                if (! $user->isAdmin() && ! $user->isLogisticsStaff() && ! $user->hasApprovedRiderProfile()) {
                    $user->role = 'seller';
                }

                $user->approval_status = 'approved';
                $user->save();
            }
        });

        $recipient = $seller->user?->email;
        $mailed = $this->sendDecisionEmail($seller, 'approved', $recipient);
        $this->saveDecisionLog(
            $seller,
            'registration_approved',
            'Your Seller Registration Has Been Approved',
            "Congratulations! Your seller account for \"{$seller->display_name}\" has been approved.",
            $recipient,
            $mailed,
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller approved', 'email_sent' => $mailed, 'seller' => $seller]);
        }
        $message = "Seller \"{$seller->display_name}\" has been approved.";
        $message .= $mailed ? " Approval email sent to {$recipient}." : ' The approval was saved, but the email could not be sent.';
        return redirect()->back()->with('status', $message);
    }

    public function reject(Request $request, Seller $seller)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'reason.required' => 'Enter a reason before rejecting this application.',
            'reason.min' => 'The rejection reason must be at least 5 characters.',
        ]);
        $reason = trim($data['reason']);
        $seller->loadMissing('user');

        DB::transaction(function () use ($seller, $reason) {
            $seller->approval_status = 'rejected';
            $seller->rejection_reason = $reason;
            $seller->status = 'inactive';
            $seller->save();

            if ($seller->user && $seller->user->role === 'seller') {
                $seller->user->approval_status = 'approved';
                $seller->user->save();
            }
        });

        $recipient = $seller->user?->email;
        $mailed = $this->sendDecisionEmail($seller, 'rejected', $recipient, $reason);
        $this->saveDecisionLog(
            $seller,
            'registration_rejected',
            'Your Seller Registration Update',
            "Your seller application for \"{$seller->display_name}\" was not approved.\n\nReason: {$reason}",
            $recipient,
            $mailed,
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller rejected', 'email_sent' => $mailed, 'seller' => $seller]);
        }
        $message = "Seller \"{$seller->display_name}\" has been rejected.";
        $message .= $mailed ? " Rejection email sent to {$recipient}." : ' The rejection was saved, but the email could not be sent.';
        return redirect()->back()->with('status', $message);
    }

    private function sendDecisionEmail(Seller $seller, string $decision, ?string $recipient, string $reason = ''): bool
    {
        if (! $recipient) {
            \Log::warning("Seller {$decision} email skipped: no applicant email is recorded", ['seller_id' => $seller->id]);
            return false;
        }

        try {
            Mail::to($recipient)->send(new \App\Mail\SellerDecisionMail(
                $decision,
                $seller->user?->displayName() ?? '',
                $seller->display_name,
                $reason,
            ));
            return true;
        } catch (Throwable $exception) {
            \Log::warning("Seller {$decision} email failed: " . $exception->getMessage(), ['seller_id' => $seller->id]);
            return false;
        }
    }

    private function saveDecisionLog(
        Seller $seller,
        string $type,
        string $subject,
        string $body,
        ?string $email,
        bool $sent,
    ): void {
        try {
            NotificationsLog::create([
                'user_id' => $seller->user_id,
                'type' => $type,
                'subject' => $subject,
                'body' => $body,
                'email' => $email,
                'sent' => $sent,
            ]);
        } catch (Throwable $exception) {
            \Log::warning('Seller decision notification could not be saved: ' . $exception->getMessage(), ['seller_id' => $seller->id]);
        }
    }

    public function suspend(Request $request, Seller $seller)
    {
        // Shared schema has no 'suspended' for sellers.status -> use inactive.
        $seller->status = 'inactive';
        $seller->save();

        // A store restriction must not suspend an independently managed rider
        // login attached to the same shared identity.
        if ($seller->user && $seller->user->role !== 'rider' && ! $seller->user->rider()->exists()) {
            $seller->user->account_status = 'suspended';
            $seller->user->status = 'suspended';
            $seller->user->save();
        }

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'account_suspended',
            'subject' => 'Your Seller Account Has Been Suspended',
            'body' => "Dear {$seller->user->name},\n\nYour seller account \"{$seller->store_name}\" has been suspended due to a compliance violation. Please contact support for assistance.",
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller suspended', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been suspended.");
    }

    public function reinstate(Request $request, Seller $seller)
    {
        $seller->approval_status = 'approved';
        $seller->status = 'active';
        $seller->save();

        if ($seller->user && $seller->user->role !== 'rider' && ! $seller->user->rider()->exists()) {
            $seller->user->account_status = 'active';
            $seller->user->status = 'active';
            $seller->user->save();
        }

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'account_reinstated',
            'subject' => 'Your Seller Account Has Been Reinstated',
            'body' => "Dear {$seller->user->name},\n\nGood news! Your seller account \"{$seller->store_name}\" has been reinstated and is now active again.",
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller reinstated', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been reinstated and is active again.");
    }

    public function show(Request $request, Seller $seller)
    {
        $seller->load('user', 'profile', 'products');
        if ($request->wantsJson()) {
            return $seller;
        }
        return view('admin.seller-detail', compact('seller'));
    }

    public function viewDocument(Request $request, Seller $seller, string $type)
    {
        $field = match (strtolower($type)) {
            'id', 'id_image', 'govt_id' => $seller->id_image ?: ($seller->user?->id_image),
            'dti', 'dti_certificate' => $seller->dti_certificate ?: $seller->business_permit,
            'bir', 'bir_2303', 'bir_form_2303' => $seller->bir_2303,
            'permit', 'business_permit' => $seller->business_permit ?: $seller->dti_certificate,
            'logo' => $seller->logo,
            default => null,
        };

        if (! $field) {
            abort(404, 'No document file recorded for this requirement.');
        }

        $found = $type === 'logo'
            ? \App\Support\AssetFiles::publicFile($field)
            : \App\Support\AssetFiles::document($field);
        abort_unless($found, 404, 'Document file not found.');

        $mime = mime_content_type($found) ?: 'application/octet-stream';
        $filename = basename($found);

        return response()->file($found, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }
}

