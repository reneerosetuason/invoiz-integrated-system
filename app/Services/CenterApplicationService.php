<?php

namespace App\Services;

use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCenterApplicationDocument;
use App\Models\Notification;
use App\Models\RiderEmailVerification;
use App\Rules\BusinessName;
use App\Rules\PersonName;
use App\Rules\PhilippinePhone;
use App\Support\NameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * Shared "Open a Logistics Center" application logic used by both the mobile
 * API (Api\CenterApplicationController) and the Logistics web flows
 * (CenterApplicationPublicController). Keeps validation, normalization, the
 * document store, and the admin notification in one place so the API and web
 * behave identically.
 */
class CenterApplicationService
{
    /**
     * Validation rules shared by the API and the web application form.
     *
     * Uniqueness scopes ignore rejected rows so a rejected applicant can
     * re-apply with a corrected submission; pending/approved rows still block.
     */
    public static function validationRules(): array
    {
        $notRejected = fn (string $column) => Rule::unique('logistics_center_applications', $column)
            ->where(fn ($q) => $q->where('status', '!=', 'rejected'));

        return [
            'business_name' => [
                'required', 'string', 'min:2', 'max:255',
                new BusinessName(),
                $notRejected('business_name'),
            ],
            'owner_name' => ['nullable', 'string', 'max:255', new PersonName()],
            'owner_first_name' => ['required_without:owner_name', 'nullable', 'string', 'min:2', 'max:50', new PersonName()],
            'owner_middle_name' => ['nullable', 'string', 'max:50', new PersonName()],
            'owner_last_name' => ['required_without:owner_name', 'nullable', 'string', 'min:2', 'max:50', new PersonName()],
            'sec_number' => [
                'required', 'string', 'regex:/^[A-Za-z]{2}\d{9}$/',
                $notRejected('sec_number'),
            ],
            'tin' => [
                'required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
                $notRejected('tin'),
            ],
            'postal_code' => 'required|string|regex:/^\d{4}$/',
            'region' => ['required', 'string', 'regex:/^\d{9}$/'],
            'province' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                // Strict only against our local PSGC dataset (names outside
                // it, e.g. from the live national API, pass through: the
                // cascade already constrained the choice client-side).
                $province = \App\Models\Province::where('name', trim($value))->first();
                if (! $province) {
                    return;
                }
                $region = trim((string) request()->input('region', ''));
                if ($region !== '' && (string) $province->region_code !== $region) {
                    $fail('The selected province does not belong to the chosen region.');
                }
            }],
            'terms' => 'required|accepted',
            'email' => [
                'required', 'email', 'max:255',
                $notRejected('email'),
                function ($attribute, $value, $fail) {
                    $email = strtolower(trim((string) $value));
                    if (\App\Models\User::where('email', $email)->whereIn('role', ['buyer', 'seller'])->exists()
                        || \App\Models\Seller::where('email', $email)->exists()) {
                        $fail('This email is already registered as a Buyer or Seller on INVOIZ and cannot be used for a Logistics Center application.');
                        return;
                    }

                    if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)
                        && ! RiderEmailVerification::isVerified($value)) {
                        $fail('Please verify your email address before submitting your application.');
                    }
                },
            ],
            'phone' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Single smart rule so the displayed message is exact:
                    // digit strings of the wrong length name the 11-digit
                    // requirement; anything else gets the invalid message.
                    // (+63 numbers are already normalized to 09… above.)
                    $v = trim((string) $value);
                    if (! preg_match('/^09\d{9}$/', $v)) {
                        if (preg_match('/^\d+$/', $v)) {
                            $fail('The contact number must be exactly 11 digits (e.g., 09171234567).');
                        } else {
                            $fail('Please enter a valid Philippine mobile number.');
                        }
                    }
                },
                $notRejected('phone'),
            ],
            'house_number' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'documents.valid_id' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.business_registration' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.barangay_clearance' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.mayors_permit' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.lease_contract' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
            'documents.other' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:5120',
        ];
    }

    /**
     * Custom messages for the shared application rules (kept in one place so
     * the API and web form surface the exact same copy).
     */
    public static function validationMessages(): array
    {
        return [
            'terms.required' => 'You must agree to the Logistics Terms of Service to submit your application.',
            'terms.accepted' => 'You must agree to the Logistics Terms of Service to submit your application.',
            'business_name.unique' => 'This company name is already registered. Please check your application status or use a different company name.',
            'email.unique' => 'This email address already has an application on file. Check your application status or use a different email address.',
            'phone.unique' => 'This contact number is already on file for another application. Check your application status or use a different number.',
            'phone.regex' => 'The contact number must be exactly 11 digits (e.g., 09171234567).',
            'sec_number.regex' => 'The SEC registration number must be 2 letters followed by 9 digits (e.g., AB123456789).',
            'sec_number.unique' => 'This SEC registration number is already in use. Please check your application status.',
            'tin.regex' => 'The TIN must be in the format 000-000-000-000 (12 digits).',
            'tin.unique' => 'This TIN is already registered. Please check your application status.',
            'postal_code.regex' => 'The postal code must be exactly 4 digits.',
            'region.regex' => 'Please choose a valid region from the list.',
        ];
    }

    /**
     * Validate, normalize, and persist a new pending application together with
     * its uploaded supporting documents (single transaction). Throws the usual
     * ValidationException: API requests keep receiving JSON 422 responses while
     * web requests get the standard redirect-back-with-errors treatment.
     */
    public function create(Request $request, string $submittedVia): LogisticsCenterApplication
    {
        // Normalize the phone before validating so the `PhilippinePhone` format
        // check and the `unique:logistics_center_applications,phone` rule both
        // run against the canonical 09XXXXXXXXX representation. Without this,
        // an equivalent variant such as +639171234567 would pass the unique
        // check against an existing 09171234567 and create a duplicate row.
        $input = $request->all();
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = PhilippinePhone::normalize($input['phone']);
        }
        // Trim + lowercase the email before validating so padded or cased
        // duplicates match the stored canonical address instead of failing
        // or slipping through as a second row.
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower(trim($input['email']));
        }
        $input = NameNormalizer::cleanKeys($input, [
            'business_name',
            'owner_name',
            'owner_first_name',
            'owner_middle_name',
            'owner_last_name',
        ]);

        $validator = Validator::make(
            $input,
            self::validationRules(),
            self::validationMessages()
        );

        if ($validator->fails()) {
            $this->rememberFailedWizardSteps($request, $validator);
            $validator->validate();
        }

        $data = $validator->validated();
        unset($data['terms']);

        $data['phone'] = PhilippinePhone::normalize($data['phone']);
        $data['status'] = 'pending';
        $data['submitted_via'] = $submittedVia;
        // Split owner names win when present (web wizard); otherwise keep
        // the legacy single field (older rows, mobile API).
        if (trim((string) ($data['owner_name'] ?? '')) === '') {
            $data['owner_name'] = trim(implode(' ', array_filter([
                trim((string) ($data['owner_first_name'] ?? '')),
                trim((string) ($data['owner_middle_name'] ?? '')),
                trim((string) ($data['owner_last_name'] ?? '')),
            ])));
        }
        $data['address'] = trim((string) ($data['address'] ?? '')) ?: trim(collect([
            $data['house_number'] ?? '',
            $data['street'] ?? '',
            $data['barangay'] ?? '',
            $data['municipality'] ?? '',
            $data['province'] ?? '',
        ])->implode(', '), ' ,');

        $recordedSteps = $this->consumeRecordedWizardErrorSteps($data['email'] ?? null);
        if ($recordedSteps !== []) {
            $data['wizard_error_steps'] = $recordedSteps;
        }

        return DB::transaction(function () use ($data, $request) {
            $application = LogisticsCenterApplication::create($data);

            foreach (array_keys(LogisticsCenterApplicationDocument::TYPES) as $type) {
                $file = $request->file("documents.$type");
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
                $storedPath = $file->storeAs("center-documents/{$application->id}", $filename);

                LogisticsCenterApplicationDocument::create([
                    'logistics_center_application_id' => $application->id,
                    'document_type' => $type,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }

            return $application;
        });
    }

    /**
     * Map failing field keys to the 1-based wizard step that owns them, so the
     * admin maintenance view can show exactly which wizard step(s) a submitted
     * application failed during validation.
     *
     * @param array<int, string> $fieldKeys Field keys from a validator.
     * @return array<int, int> Sorted 1..4 step numbers, or [] when all clear.
     */
    public static function stepsForErrors(array $fieldKeys): array
    {
        $locationStepTwo = ['house_number', 'street', 'barangay', 'municipality', 'province', 'region', 'postal_code'];
        $steps = [];

        foreach ($fieldKeys as $key) {
            $key = (string) $key;
            $step = str_starts_with($key, 'documents') ? 3
                : (in_array($key, $locationStepTwo, true) ? 2 : 1);
            $steps[$step] = true;
        }

        $steps = array_map('intval', array_keys($steps));
        sort($steps);

        return $steps;
    }

    /**
     * Stage the failed wizard steps under a per-email session key so the next
     * successful submission for the SAME email carries them onto the created
     * application record (a failed attempt creates no row).
     */
    private function rememberFailedWizardSteps(Request $request, ValidatorContract $validator): void
    {
        $email = mb_strtolower(trim((string) $request->input('email')));

        if ($email === '') {
            return;
        }

        $steps = self::stepsForErrors($validator->errors()->keys());

        if ($steps !== []) {
            session()->put('wizard_error_steps.' . sha1($email), $steps);
        }
    }

    /**
     * Pull any staged wizard steps for the given email off the session and
     * return them normalized; forgets the key so it is never re-applied.
     *
     * @return array<int, int>
     */
    private function consumeRecordedWizardErrorSteps(?string $email): array
    {
        if (! $email) {
            return [];
        }

        $key = 'wizard_error_steps.' . sha1(mb_strtolower(trim($email)));
        $steps = session()->pull($key);

        return LogisticsCenterApplication::normalizeWizardErrorSteps($steps);
    }

    /**
     * Raise the admin-review notification for a fresh application.
     */
    public function notifyNewApplication(LogisticsCenterApplication $application): void
    {
        Notification::create([
            'type' => 'new_center_application',
            'title' => 'New Center Application',
            'message' => "A new Logistics Center application from {$application->business_name} is waiting for review.",
            'icon' => 'building',
            'priority' => 'high',
            'audience' => 'admin',
            'link' => route('center-applications.show', $application),
        ]);
    }

    /**
     * Most recent application for the given email (same lookup as the API).
     */
    public function status(string $email): ?LogisticsCenterApplication
    {
        return LogisticsCenterApplication::where('email', $email)
            ->latest('id')
            ->first();
    }
}