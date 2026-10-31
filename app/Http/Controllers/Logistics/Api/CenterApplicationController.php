<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Services\CenterApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promoter / operator "Open a Logistics Hub" application flow, the
 * center-application counterpart to the rider apply flow. Applications land
 * in logistics_center_applications for admin review on the Logistics Web.
 * Validation, normalization, document storage, and the admin notification
 * live in CenterApplicationService so the mobile API and the Logistics web
 * apply flow behave identically.
 */
class CenterApplicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $service = app(CenterApplicationService::class);

        $application = $service->create($request, 'mobile');
        $service->notifyNewApplication($application);

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'submitted_via' => $application->submitted_via,
            ],
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);
        \App\Support\ApplicationStatusAccess::requireOwner($request, $validated['email']);

        $app = app(CenterApplicationService::class)->status($validated['email']);

        if (! $app) {
            return response()->json(['application' => null, 'message' => 'No application found.'], 404);
        }

        return response()->json([
            'application' => [
                'id' => $app->id,
                'business_name' => $app->business_name,
                'owner_name' => $app->displayOwnerName(),
                'owner_first_name' => $app->owner_first_name,
                'owner_middle_name' => $app->owner_middle_name,
                'owner_last_name' => $app->owner_last_name,
                'sec_number' => $app->sec_number,
                'tin' => $app->tin,
                'postal_code' => $app->postal_code,
                'region' => $app->region,
                'region_name' => $app->displayRegionName(),
                'email' => $app->email,
                'status' => $app->status,
                'submitted_via' => $app->submitted_via,
                'created_at' => $app->created_at?->toISOString(),
                'reviewed_at' => $app->reviewed_at?->toISOString(),
                'provisioned_at' => $app->provisioned_at?->toISOString(),
                'notes' => $app->notes,
                'documents' => $app->supportingDocuments->map(fn ($d) => [
                    'type' => $d->document_type,
                    'name' => $d->original_filename,
                ])->values(),
            ],
        ]);
    }
}




