<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationDecision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UserManagementController extends Controller
{
    public function reviewBuyer(Request $request, User $user)
    {
        $data = $request->validate(['action' => 'required|in:approve,reject', 'reason' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->role === 'buyer' && $locked->seller === null, 422, 'This account is not a buyer registration.');
            abort_unless($locked->approval_status === 'pending', 409, 'This registration has already been reviewed.');
            abort_if($data['action'] === 'approve' && ! $locked->email_verified_at, 422, 'The buyer must verify their email first.');
            $locked->update(['approval_status' => $data['action'] === 'approve' ? 'approved' : 'rejected']);
        });
        $emailSent = true;
        try {
            Mail::to($user->email)->send(new RegistrationDecision($data['action'] === 'approve', $data['reason'] ?? 'Registration declined by the administrator.'));
        } catch (\Throwable $e) {
            report($e);
            $emailSent = false;
        }
        $message = 'Buyer registration '.($data['action'] === 'approve' ? 'approved.' : 'rejected.');
        if (! $emailSent) {
            $message .= ' The decision email could not be sent.';
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'approval_status' => $user->fresh()->approval_status])
            : back()->with('status', $message);
    }

    public function index(Request $request)
    {
        $query = User::query()->withExists('rider');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);

        return view('admin.manage-accounts', compact('users'));
    }

    public function toggleStatus(Request $request, User $user)
    {
        abort_if(
            $user->role === 'rider' || $user->rider()->exists(),
            403,
            'Rider accounts are managed by logistics personnel.'
        );

        $request->validate([
            'action' => 'required|in:activate,suspend,deactivate',
        ]);

        $action = $request->action;
        $statusMap = [
            'activate' => 'active',
            'suspend' => 'suspended',
            'deactivate' => 'deactivated',
        ];

        $user->account_status = $statusMap[$action];
        // Keep legacy shared-schema `status` in sync so buyer/seller apps enforce it too.
        $user->status = match ($action) {
            'activate' => 'active',
            'suspend' => 'suspended',
            'deactivate' => 'inactive',
        };
        $user->save();

        return redirect()->back()->with('status', "User {$action}d successfully.");
    }
}
