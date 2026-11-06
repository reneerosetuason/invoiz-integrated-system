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
    public function vehicleTypes(Request $request): JsonResponse
    {
        $appType = strtolower(trim((string) ($request->query('application_type') ?? $request->query('type'))));
        $category = in_array($appType, ['hub_transport_driver', 'four_wheel', 'truck', 'courier'], true)
            ? 'four_wheel'
            : 'two_wheel';

        return response()->json([
            'vehicle_types' => VehicleType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['name', 'label', 'capacity_kg'])
                ->filter(fn ($t) => $t->operationalCategory() === $category)
                ->map(fn ($t) => [
                    'name' => $t->name,
                    'label' => $t->label,
                    'capacity_kg' => (float) $t->capacity_kg,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $appType = strtolower(trim((string) $request->input('application_type', 'delivery_rider')));
        if (! in_array($appType, ['delivery_rider', 'hub_transport_driver'], true)) {
            $appType = 'delivery_rider';
        }
        $request->merge([
            'application_type' => $appType,
            'vehicle_type' => strtolower(trim((string) $request->input('vehicle_type'))),
        ]);
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

        if (is_string($request->input('email'))) {
            $request->merge(['email' => \App\Support\AccountEmailGuard::normalize($request->input('email'))]);
        }

        $activeApplication = fn ($query) => $query->whereIn('status', ['pending', 'approved']);
        $isHubDriver = $appType === 'hub_transport_driver';
        $isDriverOwnedHub = $isHubDriver && in_array($request->input('vehicle_ownership'), ['driver_owned', 'own'], true);
        $allowedVehicleCategory = $isHubDriver ? 'four_wheel' : 'two_wheel';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName()],
            'middle_initial' => ['nullable', 'string', 'max:5', new PersonName()],
            'sex' => 'required|in:male,female,other',
            'birthday' => 'required|date|before:today',
            'application_type' => 'nullable|in:delivery_rider,hub_transport_driver',
            'email' => [
                'required',
                'email',
                'max:255',
                $isHubDriver ? 'unique:hub_transport_drivers,email' : 'unique:riders,email',
                Rule::unique('rider_applications', 'email')->where($activeApplication),
                new \App\Rules\SeparateAccountEmail('logistics'),
                function ($attribute, $value, $fail) {
                    $email = strtolower(trim((string) $value));
                    if (\App\Models\User::where('email', $email)->where(function ($q) {
                        $q->whereIn('role', ['buyer', 'seller'])->orWhereHas('seller');
                    })->exists()) {
                        $fail('This email is already registered as a Buyer or Seller on INVOIZ and cannot be used for a Rider account.');
                        return;
                    }

                    if (\App\Models\User::whereRaw('LOWER(email) = ?', [$email])->where(function ($q) {
                        $q->whereIn('role', \App\Support\AccountEmailGuard::LOGISTICS_ROLES)->orWhere('is_admin', true);
                    })->exists()) {
                        $fail('This email is already registered to a Logistics account and cannot be reused.');
                    }
                },
                function ($attribute, $value, $fail) use ($isHubDriver) {
                    $email = strtolower(trim((string) $value));
                    $conflict = $isHubDriver
                        ? \App\Models\Rider::whereRaw('LOWER(email) = ?', [$email])->exists()
                        : \App\Models\HubTransportDriver::whereRaw('LOWER(email) = ?', [$email])->exists();

                    if ($conflict) {
                        $fail($isHubDriver
                            ? 'This email is already registered to a Delivery Rider account and cannot be reused.'
                            : 'This email is already registered to a Hub Transport Driver account and cannot be reused.');
                    }
                },
            ],
            'phone' => [
                'required',
                new PhilippinePhone,
                $isHubDriver ? 'nullable' : 'unique:riders,phone',
                Rule::unique('rider_applications', 'phone')->where($activeApplication),
            ],
            'accepted_terms' => 'required|accepted',
            'address' => 'required|string|max:500',
            'house_number' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'region_code' => 'nullable|string|max:20',
            'province' => 'nullable|string|max:255',
            'province_code' => 'nullable|string|max:20',
            'municipality' => 'nullable|string|max:255',
            'municipality_code' => 'nullable|string|max:20',
            'barangay' => 'nullable|string|max:255',
            'barangay_code' => 'nullable|string|max:20',
            'vehicle_type' => $isHubDriver
                ? ($isDriverOwnedHub ? 'required|string|max:50' : 'nullable|string|max:50')
                : ['required', Rule::in(VehicleType::where('is_active', true)->get()->filter(fn ($t) => $t->operationalCategory() === 'two_wheel')->pluck('name')->all())],
            'vehicle_name' => 'nullable|string|max:255',
            'vehicle_model' => 'nullable|string|max:255',
            'parcel_capacity' => 'nullable|integer|min:1',
            'capacity_kg' => 'nullable|numeric|min:0.01',
            'license_plate' => [
                ($isHubDriver && ! $isDriverOwnedHub) ? 'nullable' : 'required',
                'string',
                'max:40',
                $isHubDriver ? 'nullable' : Rule::unique('riders', 'license_plate'),
                Rule::unique('rider_applications', 'license_plate')->where($activeApplication),
            ],
            'license_number' => [
                'required',
                'string',
                'max:100',
                $isHubDriver ? Rule::unique('hub_transport_drivers', 'license_number') : Rule::unique('riders', 'license_number'),
                Rule::unique('rider_applications', 'license_number')->where($activeApplication),
            ],
            'license_expiry' => 'required|date|after_or_equal:today',
            'vehicle_registration' => [($isHubDriver && ! $isDriverOwnedHub) ? 'nullable' : 'required', 'string', 'max:100'],
            'documents.valid_id' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.drivers_license' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.vehicle_registration' => [($isHubDriver && ! $isDriverOwnedHub) ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:5120'],
            'documents.proof_of_address' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.deed_of_sale' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.sales_invoice' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.owner_valid_id' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.authorization_letter' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.encumbrance_certificate' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.other' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'rider_type' => 'nullable|in:full_time,part_time',
            'vehicle_ownership' => $isHubDriver ? 'nullable|in:company,driver_owned,own' : 'nullable|in:own,borrowed,second_hand,financing',
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
        $data['application_type'] = $appType;
        $data['rider_type'] = $data['rider_type'] ?? 'full_time';

        if ($isHubDriver) {
            if ($isDriverOwnedHub) {
                $data['vehicle_ownership'] = 'driver_owned';
            } else {
                $data['vehicle_ownership'] = 'company';
                $data['vehicle_type'] = null;
                $data['license_plate'] = null;
                $data['vehicle_registration'] = null;
                $data['vehicle_name'] = null;
                $data['vehicle_model'] = null;
                $data['parcel_capacity'] = null;
                $data['capacity_kg'] = null;
            }
        } else {
            $data['vehicle_ownership'] = $data['vehicle_ownership'] ?? null;
        }

        $data['age'] = (int) \Carbon\Carbon::parse($data['birthday'])->age;

        if ($data['age'] < 18) {
            return response()->json([
                'message' => 'You must be at least 18 years old to apply.',
                'errors' => [
                    'birthday' => ['You must be at least 18 years old to apply.'],
                ],
            ], 422);
        }

        $geoError = $this->validateGeographicHierarchy($data);
        if ($geoError) {
            return $geoError;
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

        $isHub = $application->isHubTransportDriver();
        Notification::create([
            'type' => $isHub ? 'new_hub_driver_application' : 'new_rider_application',
            'title' => $isHub ? 'New Hub Transport Driver Application (Mobile)' : 'New Rider Application (Mobile)',
            'message' => $isHub
                ? "A new hub transport driver application from {$application->name} via mobile is waiting for review."
                : "A new rider application from {$application->name} via mobile is waiting for review.",
            'icon' => 'truck',
            'priority' => 'high',
            'audience' => 'admin',
            'link' => route('rider-applications.show', $application),
        ]);

        $token = \App\Support\ApplicationStatusAccess::grant($request, $application->email);

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'application_type' => $application->application_type,
                'submitted_via' => $application->submitted_via,
                'reference_number' => 'RID-'.$application->created_at->year.'-'.str_pad($application->id, 4, '0', STR_PAD_LEFT),
            ],
            'application_access_token' => $token,
            'application_access_expires_in' => 900,
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'reference_number' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:50',
        ]);
        \App\Support\ApplicationStatusAccess::requireOwner($request, $validated['email']);

        $query = RiderApplication::where('email', $validated['email']);

        $ref = $request->input('reference_number') ?? $request->input('reference');
        if (is_string($ref) && preg_match('/(?:RID-\d{4}-)?0*(\d+)$/i', trim($ref), $m)) {
            $query->where('id', (int) $m[1]);
        }

        $app = $query->latest('id')->first();

        if (!$app) {
            return response()->json(['application' => null, 'message' => 'No application found.'], 404);
        }

        $app->load(['supportingDocuments', 'logs']);

        return response()->json([
            'application' => [
                'id' => $app->id,
                'reference_number' => 'RID-'.$app->created_at->year.'-'.str_pad($app->id, 4, '0', STR_PAD_LEFT),
                'name' => $app->name,
                'email' => $app->email,
                'phone' => $app->phone,
                'status' => $app->status,
                'submitted_via' => $app->submitted_via,
                'application_type' => $app->application_type ?? 'delivery_rider',
                'application_type_label' => $app->applicationTypeLabel(),
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

    private function validateGeographicHierarchy(array &$data): ?JsonResponse
    {
        $regionCode = $data['region_code'] ?? null;
        $provinceCode = $data['province_code'] ?? null;
        $municipalityCode = $data['municipality_code'] ?? null;
        $barangayCode = $data['barangay_code'] ?? null;

        if ($regionCode) {
            $region = \App\Models\Region::where('code', $regionCode)->first();
            if (! $region) {
                return response()->json([
                    'message' => 'The selected region is invalid.',
                    'errors' => ['region_code' => ['The selected region is invalid.']],
                ], 422);
            }
            if (empty($data['region'])) {
                $data['region'] = $region->name;
            }

            $isNcr = ($regionCode === '130000000');

            if (! $isNcr && $provinceCode) {
                $province = \App\Models\Province::where('code', $provinceCode)->first();
                if (! $province) {
                    return response()->json([
                        'message' => 'The selected province is invalid.',
                        'errors' => ['province_code' => ['The selected province is invalid.']],
                    ], 422);
                }
                if ($province->region_code !== $regionCode) {
                    return response()->json([
                        'message' => 'The selected province does not belong to the selected region.',
                        'errors' => ['province_code' => ['The selected province does not belong to the selected region.']],
                    ], 422);
                }
                if (empty($data['province'])) {
                    $data['province'] = $province->name;
                }
            }

            if ($municipalityCode) {
                $municipality = \App\Models\Municipality::where('code', $municipalityCode)->first();
                if (! $municipality) {
                    return response()->json([
                        'message' => 'The selected city / municipality is invalid.',
                        'errors' => ['municipality_code' => ['The selected city / municipality is invalid.']],
                    ], 422);
                }
                if ($isNcr) {
                    if ($municipality->region_code !== '130000000') {
                        return response()->json([
                            'message' => 'The selected city does not belong to the selected region.',
                            'errors' => ['municipality_code' => ['The selected city does not belong to the selected region.']],
                        ], 422);
                    }
                } elseif ($provinceCode && $municipality->province_code !== $provinceCode) {
                    return response()->json([
                        'message' => 'The selected city / municipality does not belong to the selected province.',
                        'errors' => ['municipality_code' => ['The selected city / municipality does not belong to the selected province.']],
                    ], 422);
                }
                if (empty($data['municipality'])) {
                    $data['municipality'] = $municipality->name;
                }
            }

            if ($barangayCode) {
                $barangay = \App\Models\Barangay::where('code', $barangayCode)->first();
                if (! $barangay) {
                    return response()->json([
                        'message' => 'The selected barangay is invalid.',
                        'errors' => ['barangay_code' => ['The selected barangay is invalid.']],
                    ], 422);
                }
                if ($municipalityCode && $barangay->municipality_code !== $municipalityCode) {
                    return response()->json([
                        'message' => 'The selected barangay does not belong to the selected city / municipality.',
                        'errors' => ['barangay_code' => ['The selected barangay does not belong to the selected city / municipality.']],
                    ], 422);
                }
                if (empty($data['barangay'])) {
                    $data['barangay'] = $barangay->name;
                }
            }
        }

        return null;
    }
}
