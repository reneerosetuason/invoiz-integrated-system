<?php

namespace App\Http\Controllers\Logistics;

use App\Models\User;
use App\Models\LogisticsCenter;
use App\Rules\PhilippinePhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $centerId = (int) $request->query('center_id', 0);

        $query = User::where('role', 'staff')->with('logisticsCenter');

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('phone', 'like', $like);
            });
        }

        if ($centerId > 0) {
            $query->where('center_id', $centerId);
        }

        $staff = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('logistics.staff.index', [
            'staff' => $staff,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('logistics.staff.create', [
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:10',
            'sex' => 'required|in:male,female,other',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['required', new PhilippinePhone],
            'birthday' => 'required|date|before:today',
            'center_id' => 'required|exists:logistics_centers,id',
        ]);

        $validated['name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        $validated['role'] = 'staff';
        $validated['status'] = 'active';
        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;
        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = now();

        User::create($validated);

        return redirect()->route('staff.index')->with('success', 'Staff account created successfully.');
    }

    public function show(User $staff): View
    {
        $staff->load('logisticsCenter');
        return view('logistics.staff.show', ['staff' => $staff]);
    }

    public function edit(User $staff): View
    {
        return view('logistics.staff.edit', [
            'staff' => $staff,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:10',
            'sex' => 'required|in:male,female,other',
            'email' => 'required|email|max:150|unique:users,email,' . $staff->id,
            'phone' => ['required', new PhilippinePhone],
            'birthday' => 'required|date|before:today',
            'center_id' => 'required|exists:logistics_centers,id',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;

        $staff->update($validated);

        return redirect()->route('staff.show', $staff)->with('success', 'Staff account updated successfully.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $staff->update(['status' => 'inactive']);
        return redirect()->route('staff.index')->with('success', 'Staff account deactivated.');
    }

    public function activate(User $staff): RedirectResponse
    {
        $staff->update(['status' => 'active']);
        return redirect()->route('staff.show', $staff)->with('success', 'Staff account activated.');
    }
}




