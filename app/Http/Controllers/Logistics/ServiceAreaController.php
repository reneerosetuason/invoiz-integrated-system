<?php

namespace App\Http\Controllers\Logistics;

use App\Models\ServiceArea;
use App\Models\LogisticsCenter;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceAreaController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
        $search = trim((string) $request->query('search', ''));
        $centerId = (int) $request->query('center_id', 0);
        $status = trim((string) $request->query('status', ''));

        $query = ServiceArea::with('logisticsCenter')->withCount(['riders', 'deliveries']);
        $companyId = ($request->user()->isAdmin() || $request->user()->isLogisticsAdmin()) ? null : (int) ($request->user()->logistics_company_id ?? 0);
        if ($companyId !== null) $query->whereHas('logisticsCenter', fn ($q) => $q->where('logistics_company_id', $companyId));

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        if ($centerId > 0) {
            $query->where('logistics_center_id', $centerId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $areas = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('logistics.service-areas.index', [
            'areas' => $areas,
            'centers' => LogisticsCenter::where('is_active', true)->when($companyId !== null, fn ($q) => $q->where('logistics_company_id', $companyId))->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        abort_unless(request()->user()->isAdmin() || request()->user()->isLogisticsAdmin(), 403);
        return view('logistics.service-areas.create', [
            'centers' => LogisticsCenter::where('is_active', true)->when(! request()->user()->isAdmin() && ! request()->user()->isLogisticsAdmin(), fn ($q) => $q->where('logistics_company_id', request()->user()->logistics_company_id ?: 0))->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLogisticsAdmin(), 403);
        $validated = $request->validate([
            'logistics_center_id' => 'required|exists:logistics_centers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);
        $this->assertCompanyCenter((int) $validated['logistics_center_id'], $request);

        $serviceArea = ServiceArea::create($validated);

        $audit->record(
            Auth::user(),
            'service_area.created',
            ServiceArea::class,
            $serviceArea->id,
            [],
            $this->auditValues($serviceArea),
        );

        return redirect()->route('service-areas.index')->with('success', 'Service area created successfully.');
    }

    public function show(ServiceArea $serviceArea): View
    {
        $this->assertCompanyArea($serviceArea, request());
        $serviceArea->load('logisticsCenter')->loadCount(['riders', 'deliveries']);
        return view('logistics.service-areas.show', ['area' => $serviceArea]);
    }

    public function edit(ServiceArea $serviceArea): View
    {
        $this->assertCompanyArea($serviceArea, request());
        return view('logistics.service-areas.edit', [
            'area' => $serviceArea,
            'centers' => LogisticsCenter::where('is_active', true)->when(! request()->user()->isAdmin() && ! request()->user()->isLogisticsAdmin(), fn ($q) => $q->where('logistics_company_id', request()->user()->logistics_company_id ?: 0))->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ServiceArea $serviceArea, AuditLogger $audit): RedirectResponse
    {
        $this->assertCompanyArea($serviceArea, $request);
        $validated = $request->validate([
            'logistics_center_id' => 'required|exists:logistics_centers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);
        $this->assertCompanyCenter((int) $validated['logistics_center_id'], $request);

        $before = $this->auditValues($serviceArea);
        $serviceArea->update($validated);
        $after = $this->auditValues($serviceArea->fresh());

        if ($before !== $after) {
            $audit->record(
                Auth::user(),
                'service_area.updated',
                ServiceArea::class,
                $serviceArea->id,
                $before,
                $after,
            );
        }

        return redirect()->route('service-areas.show', $serviceArea)->with('success', 'Service area updated successfully.');
    }

    public function toggle(ServiceArea $serviceArea, AuditLogger $audit): RedirectResponse
    {
        $this->assertCompanyArea($serviceArea, request());
        $before = $this->auditValues($serviceArea);
        $serviceArea->update(['is_active' => !$serviceArea->is_active]);
        $label = $serviceArea->is_active ? 'activated' : 'deactivated';

        $audit->record(
            Auth::user(),
            'service_area.' . $label,
            ServiceArea::class,
            $serviceArea->id,
            $before,
            $this->auditValues($serviceArea->fresh()),
        );

        return redirect()->back()->with('success', "Service area {$label}.");
    }

    public function destroy(ServiceArea $serviceArea, AuditLogger $audit): RedirectResponse
    {
        $this->assertCompanyArea($serviceArea, request());
        $values = $this->auditValues($serviceArea);

        try {
            $serviceArea->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'Cannot delete this service area while deliveries or riders are linked to it. Deactivate it instead.');
        }

        $audit->record(
            Auth::user(),
            'service_area.deleted',
            ServiceArea::class,
            $serviceArea->id,
            $values,
            [],
        );

        return redirect()->route('service-areas.index')->with('success', 'Service area deleted.');
    }

    private function auditValues(ServiceArea $serviceArea): array
    {
        return $serviceArea->only(['logistics_center_id', 'name', 'description', 'is_active']);
    }

    private function assertCompanyCenter(int $centerId, Request $request): void
    {
        $center = LogisticsCenter::findOrFail($centerId);
        if (! $request->user()->isAdmin() && ! $request->user()->isLogisticsAdmin()) abort_unless((int) $center->logistics_company_id === (int) $request->user()->logistics_company_id, 403);
    }

    private function assertCompanyArea(ServiceArea $area, Request $request): void
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isLogisticsAdmin()) abort_unless((int) $area->logisticsCenter?->logistics_company_id === (int) $request->user()->logistics_company_id, 404);
    }
}




