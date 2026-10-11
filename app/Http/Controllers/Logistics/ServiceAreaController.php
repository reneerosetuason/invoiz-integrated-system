<?php

namespace App\Http\Controllers\Logistics;

use App\Models\ServiceArea;
use App\Models\LogisticsCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('logistics.service-areas.create', [
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logistics_center_id' => 'required|exists:logistics_centers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        ServiceArea::create($validated);

        return redirect()->route('service-areas.index')->with('success', 'Service area created successfully.');
    }

    public function show(ServiceArea $serviceArea): View
    {
        $serviceArea->load('logisticsCenter')->loadCount(['riders', 'deliveries']);
        return view('logistics.service-areas.show', ['area' => $serviceArea]);
    }

    public function edit(ServiceArea $serviceArea): View
    {
        return view('logistics.service-areas.edit', [
            'area' => $serviceArea,
            'centers' => LogisticsCenter::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ServiceArea $serviceArea): RedirectResponse
    {
        $validated = $request->validate([
            'logistics_center_id' => 'required|exists:logistics_centers,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $serviceArea->update($validated);

        return redirect()->route('service-areas.show', $serviceArea)->with('success', 'Service area updated successfully.');
    }

    public function toggle(ServiceArea $serviceArea): RedirectResponse
    {
        $serviceArea->update(['is_active' => !$serviceArea->is_active]);
        $label = $serviceArea->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Service area {$label}.");
    }

    public function destroy(ServiceArea $serviceArea): RedirectResponse
    {
        $serviceArea->delete();
        return redirect()->route('service-areas.index')->with('success', 'Service area deleted.');
    }
}




