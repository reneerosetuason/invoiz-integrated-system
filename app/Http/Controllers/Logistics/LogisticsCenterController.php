<?php

namespace App\Http\Controllers\Logistics;

use App\Models\LogisticsCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogisticsCenterController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = LogisticsCenter::withCount(['serviceAreas', 'riders', 'staff', 'deliveries']);
        $companyId = $this->companyScope($request);
        if ($companyId !== null) $query->where('logistics_company_id', $companyId);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('city', 'like', $like)
                  ->orWhere('province', 'like', $like)
                  ->orWhere('address', 'like', $like);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $centers = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('logistics.centers.index', ['centers' => $centers]);
    }

    public function create(): View
    {
        $this->authorizeManager(request());
        return view('logistics.centers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:logistics_centers,name',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        if (! $request->user()->isAdmin() && ! $request->user()->isLogisticsAdmin()) {
            $validated['logistics_company_id'] = $request->user()->logistics_company_id;
            abort_unless($validated['logistics_company_id'], 403, 'Your account is not assigned to a logistics company.');
            $validated['ownership_verified_at'] = now();
            $validated['ownership_verified_by'] = $request->user()->id;
        } else {
            $validated['logistics_company_id'] = $request->user()->logistics_company_id ?: (\App\Models\LogisticsCompany::value('id') ?? 1);
            $validated['ownership_verified_at'] = now();
            $validated['ownership_verified_by'] = $request->user()->id;
        }
        LogisticsCenter::create($validated);

        return redirect()->route('centers.index')->with('success', 'Logistics center created successfully.');
    }

    public function show(LogisticsCenter $center): View
    {
        $this->authorizeCenter(request(), $center);
        $center->loadCount(['serviceAreas', 'riders', 'staff', 'deliveries']);
        return view('logistics.centers.show', ['center' => $center]);
    }

    public function edit(LogisticsCenter $center): View
    {
        $this->authorizeCenter(request(), $center);
        return view('logistics.centers.edit', ['center' => $center]);
    }

    public function update(Request $request, LogisticsCenter $center): RedirectResponse
    {
        $this->authorizeCenter($request, $center);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:logistics_centers,name,' . $center->id,
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        $center->update($validated);

        return redirect()->route('centers.show', $center)->with('success', 'Logistics center updated successfully.');
    }

    public function toggle(LogisticsCenter $center): RedirectResponse
    {
        $this->authorizeCenter(request(), $center);
        $center->update(['is_active' => !$center->is_active]);
        $label = $center->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Logistics center {$label}.");
    }

    public function destroy(LogisticsCenter $center): RedirectResponse
    {
        $this->authorizeCenter(request(), $center);
        $center->delete();
        return redirect()->route('centers.index')->with('success', 'Logistics center deleted.');
    }

    private function companyScope(Request $request): ?int
    {
        return ($request->user()->isAdmin() || $request->user()->isLogisticsAdmin()) ? null : (int) ($request->user()->logistics_company_id ?? 0);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLogisticsAdmin(), 403);
    }

    private function authorizeCenter(Request $request, LogisticsCenter $center): void
    {
        $this->authorizeManager($request);
        if (! $request->user()->isAdmin() && ! $request->user()->isLogisticsAdmin()) {
            abort_unless($request->user()->logistics_company_id && (int) $center->logistics_company_id === (int) $request->user()->logistics_company_id, 404);
        }
    }
}




