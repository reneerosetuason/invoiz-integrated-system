<?php

namespace App\Http\Controllers\Logistics;

use App\Models\User;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Rules\PersonName;
use App\Rules\PhilippinePhone;
use App\Support\NameNormalizer;
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

        $query = User::whereIn('role', ['staff', 'logistics_staff'])->with('logisticsCenter');
        $companyId = $this->companyScope();
        if ($companyId !== null) $query->where('logistics_company_id', $companyId);

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
            'centers' => LogisticsCenter::where('is_active', true)->when($companyId !== null, fn ($q) => $q->where('logistics_company_id', $companyId))->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('logistics.staff.create', [
            'centers' => LogisticsCenter::where('is_active', true)->when($this->companyScope() !== null, fn ($q) => $q->where('logistics_company_id', $this->companyScope()))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeNameInput($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', new PersonName()],
            'last_name' => ['required', 'string', 'max:100', new PersonName()],
            'middle_initial' => ['nullable', 'string', 'max:10', new PersonName()],
            'sex' => 'required|in:male,female,other',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'terms' => 'accepted',
            'phone' => ['required', new PhilippinePhone],
            'birthday' => 'required|date|before:today',
            'center_id' => ['required', 'exists:logistics_centers,id', function ($attribute, $value, $fail): void { $this->validateCenterCompany((int) $value, $fail); }],
        ]);

        $validated['name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        $validated['role'] = 'staff';
        $centerCompanyId = LogisticsCenter::whereKey($validated['center_id'])->value('logistics_company_id');
        $validated['logistics_company_id'] = $centerCompanyId ?: ($request->user()?->logistics_company_id ?: (\App\Models\LogisticsCompany::value('id') ?? 1));
        $validated['status'] = 'active';
        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;
        $validated['password'] = Hash::make($validated['password']);
        $validated['email_verified_at'] = now();

        User::create($validated);

        return redirect()->route('staff.index')->with('success', 'Staff account created successfully.');
    }

    public function show(User $staff): View
    {
        $this->ensureStaffAccount($staff);
        $staff->load('logisticsCenter');
        return view('logistics.staff.show', ['staff' => $staff]);
    }

    public function edit(User $staff): View
    {
        $this->ensureStaffAccount($staff, manage: true);

        return view('logistics.staff.edit', [
            'staff' => $staff,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaffAccount($staff, manage: true);

        $this->normalizeNameInput($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100', new PersonName()],
            'last_name' => ['required', 'string', 'max:100', new PersonName()],
            'middle_initial' => ['nullable', 'string', 'max:10', new PersonName()],
            'sex' => 'required|in:male,female,other',
            'email' => 'required|email|max:150|unique:users,email,' . $staff->id,
            'phone' => ['required', new PhilippinePhone],
            'birthday' => 'required|date|before:today',
            'center_id' => ['required', 'exists:logistics_centers,id', function ($attribute, $value, $fail): void { $this->validateCenterCompany((int) $value, $fail); }],
            'status' => 'required|in:active,inactive',
        ]);

        $validated['name'] = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        $validated['phone'] = PhilippinePhone::normalize($validated['phone']);
        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;
        $centerCompanyId = LogisticsCenter::whereKey($validated['center_id'])->value('logistics_company_id');
        $validated['logistics_company_id'] = $centerCompanyId ?: ($staff->logistics_company_id ?: ($request->user()?->logistics_company_id ?: (\App\Models\LogisticsCompany::value('id') ?? 1)));

        $staff->update($validated);

        return redirect()->route('staff.show', $staff)->with('success', 'Staff account updated successfully.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->ensureStaffAccount($staff, manage: true);
        $staff->update(['status' => 'inactive']);
        return redirect()->route('staff.index')->with('success', 'Staff account deactivated.');
    }

    public function activate(User $staff): RedirectResponse
    {
        $this->ensureStaffAccount($staff, manage: true);
        $staff->update(['status' => 'active']);
        return redirect()->route('staff.show', $staff)->with('success', 'Staff account activated.');
    }

    /**
     * Staff management is scoped to logistics staff accounts. Route-model
     * binding accepts any user id, so guard every mutating action against a
     * buyer/seller/admin being edited through the staff resource.
     */
    private function normalizeNameInput(Request $request): void
    {
        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['first_name', 'last_name', 'middle_initial']),
            ['first_name', 'last_name', 'middle_initial']
        ));
    }

    private function ensureStaffAccount(User $staff, bool $manage = false): void
    {
        abort_if($staff->isDesignatedLogisticsAdminAccount(), 404);
        abort_unless(in_array($staff->role, ['staff', 'logistics_staff'], true), 404);
        $companyId = $this->companyScope();
        if ($companyId !== null) abort_unless((int) $staff->logistics_company_id === (int) $companyId, 404);

        if ($manage && $staff->rider()->exists()) {
            abort_unless(request()->user()?->canManageRiders(), 403,
                'Accounts with rider profiles are managed by logistics personnel.');
        }
    }

    private function companyScope(): ?int
    {
        $user = request()->user();
        if ($user?->isAdmin()) return null;
        return $user?->logistics_company_id ? (int) $user->logistics_company_id : null;
    }

    private function validateCenterCompany(int $centerId, callable $fail): void
    {
        $companyId = $this->companyScope();
        $center = LogisticsCenter::find($centerId);
        if (! $center) {
            $fail('The selected logistics hub does not exist.');
            return;
        }
        if ($companyId !== null && $center->logistics_company_id && (int) $center->logistics_company_id !== $companyId) {
            $fail('The logistics hub must belong to your logistics organization.');
        }
    }
}



