<?php

namespace App\Services;

use App\Models\LogisticsCenterApplication;
use App\Models\LogisticsCenterApplicationDocument;
use App\Models\Notification;
use App\Rules\PhilippinePhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
     */
    public static function validationRules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:logistics_center_applications,email',
            'phone' => ['required', new PhilippinePhone, 'unique:logistics_center_applications,phone'],
            'house_number' => 'nullable|string|max:50',
            'street' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'municipality' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
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
            'email.unique' => 'This email address already has an application on file. Check your application status or use a different email address.',
            'phone.unique' => 'This contact number is already on file for another application. Check your application status or use a different number.',
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

        $data['phone'] = PhilippinePhone::normalize($data['phone']);
        $data['status'] = 'pending';
        $data['submitted_via'] = $submittedVia;
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
        $locationStepTwo = ['house_number', 'street', 'barangay', 'municipality', 'province'];
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
            'icon' => '🏬',
            'priority' => 'high',
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