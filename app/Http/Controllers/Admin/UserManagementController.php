<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();
        return view('admin.manage-accounts', compact('users'));
    }

    public function toggleStatus(Request $request, User $user)
    {
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
