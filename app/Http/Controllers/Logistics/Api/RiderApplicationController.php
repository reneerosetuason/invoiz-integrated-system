<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderApplication;
use App\Models\RiderApplicationDocument;
use App\Models\RiderEmailVerification;
use App\Models\Notification;
use App\Models\VehicleType;
use App\Rules\PersonName;
use App\Rules\PhilippinePhone;
use App\Support\NameNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RiderApplicationController extends Controller
{
    public function vehicleTypes(): JsonResponse
    {
        return response()->json([
            'vehicle_types' => VehicleType::where('is_active', true)->orderBy('sort_order')->get(['name', 'label', 'capacity_kg'])->filter(fn ($t) => $t->operationalCategory() === 'two_wheel')->map(fn ($t) => [
                'name' => $t->name,
                'label' => $t->label,
                'capacity_kg' => (float) $t->capacity_kg,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['vehicle_type' => strtolower(trim((string) $request->input('vehicle_type')))]);
        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['name', 'middle_initial']),
            ['name', 'middle_initial']
        ));

        // Normalize the phone before validating so the `PhilippinePhone` format
        // check and the `unique:rider_applications,phone` rule both run against
        // the canonical 09XXXXXXXXX value. Without this, an equivalent variant
        // such as +639171234567 would pass the unique check against an existing
        // 09171234567 and create a duplicate row (mirrors CenterApplicationService).
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => PhilippinePhone::normalize($request->input('phone'))]);
        }

        $activeApplication = fn ($query) => $query->whereIn('status', ['pending', 'approved']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName()],
            'middle_initial' => ['nullable', 'string', 'max:5', new PersonName()],
            'sex' => 'required|in:male,female,other',
            'birthday' => 'required|date|before:today',
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:riders,email',
                Rule::unique('rider_applications', 'email')->where($activeApplication),
            ],
            'phone' => [
                'required',
                new PhilippinePhone,
                'unique:riders,phone',
                Rule::unique('rider_applications', 'phone')->where($activeApplication),
            ],
            'accepted_terms' => 'required|accepted',
            'address' => 'required|string|max:500',
            'house_number' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'vehicle_type' => ['required', Rule::in(VehicleType::where('is_active', true)->get()->filter(fn ($t) => $t->operationalCategory() === 'two_wheel')->pluck('name')->all())],
            'license_plate' => [
                'required',
                'string',
                'max:20',
                Rule::unique('riders', 'license_plate'),
                Rule::unique('rider_applications', 'license_plate')->where($activeApplication),
            ],
            'license_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('riders', 'license_number'),
                Rule::unique('rider_applications', 'license_number')->where($activeApplication),
            ],
            'license_expiry' => 'required|date|after_or_equal:today',
            'vehicle_registration' => 'required|string|max:100',
            'documents.valid_id' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.drivers_license' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.vehicle_registration' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.proof_of_address' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.deed_of_sale' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.sales_invoice' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.owner_valid_id' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.authorization_letter' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.encumbrance_certificate' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.other' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'rider_type' => 'nullable|in:full_time,part_time',
            'vehicle_ownership' => 'nullable|in:own,borrowed,second_hand,financing',
        ]);

        // Server-side gate: the submitted address must have completed OTP
        // verification. Checked against our own records ??? the client cannot
        // bypass this with a flag.
        if (! RiderEmailVerification::isVerified($data['email'])) {
            return response()->json([
                'message' => 'Please verify your email address before submitting your rider application.',
                'errors' => [
                    'email' => ['Please verify your email address before submitting your rider application.'],
                ],
            ], 422);
        }

        $data['phone'] = PhilippinePhone::normalize($data['phone']);
        $data['status'] = 'pending';
        $data['documents'] = [];
        $data['submitted_via'] = 'mobile';
        $data['rider_type'] = $data['rider_type'] ?? 'full_time';
        $data['vehicle_ownership'] = $data['vehicle_ownership'] ?? null;

        $data['age'] = (int) \Carbon\Carbon::parse($data['birthday'])->age;

        if ($data['age'] < 18) {
            return response()->json([
                'message' => 'You must be at least 18 years old to apply as a rider.',
                'errors' => [
                    'birthday' => ['You must be at least 18 years old to apply as a rider.'],
                ],
            ], 422);
        }

        unset($data['accepted_terms']);

        $application = DB::transaction(function () use ($data, $request) {
            $app = RiderApplication::create($data);
            foreach (array_keys(RiderApplicationDocument::TYPES) as $type) {
                $file = $request->file("documents.$type");
                if (!$file || !$file->isValid()) continue;
                $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
                $storedPath = $file->storeAs("rider-documents/{$app->id}", $filename);
                RiderApplicationDocument::create([
                    'rider_application_id' => $app->id,
                    'document_type' => $type,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
            return $app;
        });

        Notification::create([
            'type' => 'new_rider_application',
            'title' => 'New Rider Application (Mobile)',
            'message' => "A new rider application from {$application->name} via mobile is waiting for review.",
            'icon' => '🛵',
            'priority' => 'high',
            'audience' => 'admin',
            'link' => route('rider-applications.show', $application),
        ]);

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

        $app = RiderApplication::where('email', $validated['email'])->latest('id')->first();

        if (!$app) {
            return response()->json(['application' => null, 'message' => 'No application found.'], 404);
        }

        $app->load(['supportingDocuments', 'logs']);

        return response()->json([
            'application' => [
                'id' => $app->id,
                'name' => $app->name,
                'email' => $app->email,
                'phone' => $app->phone,
                'status' => $app->status,
                'submitted_via' => $app->submitted_via,
                'rider_type' => $app->rider_type,
                'vehicle_ownership' => $app->vehicle_ownership,
                'license_number' => $app->license_number,
                'license_expiry' => $app->license_expiry?->format('Y-m-d'),
                'vehicle_registration' => $app->vehicle_registration,
                'created_at' => $app->created_at->toISOString(),
                'reviewed_at' => $app->reviewed_at?->toISOString(),
                'notes' => $app->notes,
                'documents' => $app->supportingDocuments->map(fn ($d) => [
                    'type' => $d->document_type,
                    'name' => $d->original_filename,
                ])->values(),
                'logs' => $app->logs->map(fn ($l) => [
                    'from' => $l->previous_status,
                    'to' => $l->new_status,
                    'reason' => $l->reason,
                    'at' => $l->created_at->toISOString(),
                ])->values(),
            ],
        ]);
    }
}





