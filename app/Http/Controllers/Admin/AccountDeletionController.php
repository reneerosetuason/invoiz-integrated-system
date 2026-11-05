<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\AuditLog;
use App\Services\BuyerAccountDeletionService;
use Illuminate\Http\Request;

class AccountDeletionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => 'nullable|in:pending,on_hold,cancelled,completed', 'search' => 'nullable|string|max:200']);
        $query = AccountDeletionRequest::with('user:id,name,email');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['search'])) {
            $term = $data['search'];
            $query->where(fn ($q) => $q->where('id', ctype_digit($term) ? (int) $term : 0)
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')));
        }
        $requests = $query->latest('id')->paginate(20)->withQueryString();

        return $request->is('api/*') ? response()->json($requests) : view('admin.account-deletions.index', compact('requests'));
    }

    public function show(Request $request, AccountDeletionRequest $deletion, BuyerAccountDeletionService $service)
    {
        $deletion->load('user');
        $blockers = $service->blockers($deletion->user);
        $history = AuditLog::where('entity', 'account_deletion_requests')->where('entity_id', $deletion->id)->oldest('id')->get();

        return $request->is('api/*') ? response()->json(['deletion_request' => $deletion, 'blockers' => $blockers, 'history' => $history])
            : view('admin.account-deletions.show', compact('deletion', 'blockers', 'history'));
    }

    public function restore(Request $request, AccountDeletionRequest $deletion, BuyerAccountDeletionService $service)
    {
        $data = $request->validate(['current_password' => 'required|string|max:255', 'confirm_restore' => 'required|accepted', 'identity_verified' => 'required|accepted']);
        $service->restore($deletion, $request->user(), $data['current_password']);

        return $request->is('api/*') ? response()->json(['message' => 'Buyer account restored.'])
            : back()->with('success', 'Buyer account restored. Original transaction history is preserved.');
    }
}
