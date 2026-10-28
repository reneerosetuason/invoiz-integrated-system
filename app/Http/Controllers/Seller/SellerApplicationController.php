<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Seller;
use App\Models\SellerProfile;
use App\Models\User;
use App\Rules\BusinessName;
use App\Rules\PersonName;
use App\Support\NameNormalizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SellerApplicationController extends Controller
{
    public function create(): View
    {
        $userId = session('buyer.id') ?? Auth::id();
        $user = $userId ? User::find($userId) : null;
        $existing = $user ? Seller::where('user_id', $user->id)->first() : null;
        $categories = Category::active()->orderBy('name')->get();
        $availableDocuments = [
            'id_image' => $this->hasStoredFile($existing?->id_image ?: $user?->id_image),
            'dti_certificate' => $this->hasStoredFile($existing?->dti_certificate),
            'bir_2303' => $this->hasStoredFile($existing?->bir_2303),
        ];

        return view('seller.apply', compact('existing', 'user', 'categories', 'availableDocuments'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'business_name' => NameNormalizer::clean((string) $request->input('business_name')),
            'tin' => trim((string) $request->input('tin')),
        ]);
        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['first_name', 'last_name', 'middle_initial']),
            ['first_name', 'last_name', 'middle_initial']
        ));

        $userId = $request->user()?->id ?? ($request->hasSession() ? $request->session()->get('buyer.id') : null);
        $currentUser = $userId ? User::find($userId) : null;
        $knownUser = $currentUser ?: User::where('email', $request->input('email'))->first();
        $existing = $knownUser ? Seller::where('user_id', $knownUser->id)->first() : null;

        $storedPaths = [
            'id_image' => $existing?->id_image ?: $knownUser?->id_image,
            'dti_certificate' => $existing?->dti_certificate,
            'bir_2303' => $existing?->bir_2303,
        ];
        $uniqueTin = Rule::unique('sellers', 'tin');
        if ($existing) {
            $uniqueTin->ignore($existing->id);
        }

        $rules = [
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            'middle_initial' => ['nullable', 'string', 'max:10', new PersonName],
            'email' => [
                'required', 'email', 'max:150',
                ...(! $knownUser ? [Rule::unique('users', 'email')] : []),
                function ($attribute, $value, $fail) use ($currentUser) {
                    if ($currentUser && strtolower((string) $currentUser->email) !== strtolower((string) $value)) {
                        $fail('The email address must match the account you are signed in with.');
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:30'],
            'business_name' => [
                'required', 'string', 'min:2', 'max:50',
                new BusinessName,
            ],
            'line_of_business' => ['required', 'string', 'max:100'],
            'seller_type' => ['required', Rule::in([
                'Sole Proprietorship', 'Partnership', 'Corporation', 'Cooperative', 'One Person Corporation (OPC)',
            ])],
            'tin' => [
                'required', 'string', 'regex:/^\d{12}$/', $uniqueTin,
                function ($attribute, $value, $fail) use ($existing) {
                    if (! preg_match('/^\d{12}$/', (string) $value)) {
                        return;
                    }

                    $digits = (string) $value;
                    $duplicate = Seller::withTrashed()
                        ->whereNotNull('tin')
                        ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                        ->get(['id', 'tin'])
                        ->contains(fn (Seller $seller) => preg_replace('/\D+/', '', (string) $seller->tin) === $digits);

                    if ($duplicate) {
                        $fail('This TIN is already registered to another seller.');
                    }
                },
            ],
            'region' => ['required', 'string', 'max:150'],
            'province' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:150'],
            'barangay' => ['required', 'string', 'max:150'],
            'address_line' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/^\d{4}$/'],
            'sex' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'birthday' => ['nullable', 'date', 'before:today'],
            'id_image' => [Rule::requiredIf(! $this->hasStoredFile($storedPaths['id_image'])), 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'dti_certificate' => [Rule::requiredIf(! $this->hasStoredFile($storedPaths['dti_certificate'])), 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'bir_2303' => [Rule::requiredIf(! $this->hasStoredFile($storedPaths['bir_2303'])), 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'business_permit' => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'terms' => ['accepted'],
        ];

        if ($knownUser && ! $currentUser) {
            $rules['password'] = [
                'required', 'string',
                function ($attribute, $value, $fail) use ($knownUser) {
                    if (! Hash::check((string) $value, $knownUser->password)) {
                        $fail('An account with this email already exists and the password entered is incorrect.');
                    }
                },
            ];
        } elseif (! $knownUser) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        $data = $request->validate($rules, [
            'business_name.min' => 'The store name must contain between 2 and 50 characters.',
            'business_name.max' => 'The store name cannot be longer than 50 characters.',
            'tin.regex' => 'Enter a 12-digit TIN with numbers only.',
            'postal_code.regex' => 'Enter a valid 4-digit Philippine postal code.',
            'id_image.required' => 'Upload a government-issued ID to apply.',
            'dti_certificate.required' => 'Upload a DTI certificate to apply.',
            'bir_2303.required' => 'Upload BIR Form 2303 to apply.',
            'id_image.max' => 'The government-issued ID must be 5 MB or smaller.',
            'dti_certificate.max' => 'The DTI certificate must be 5 MB or smaller.',
            'bir_2303.max' => 'BIR Form 2303 must be 5 MB or smaller.',
            'business_permit.max' => 'The business permit must be 5 MB or smaller.',
            'logo.max' => 'The store logo must be 5 MB or smaller.',
            'terms.accepted' => 'You must agree to the Terms of Service and Privacy Policy to continue.',
            'seller_type.in' => 'Please select a valid seller type.',
        ]);

        if ($existing && $existing->approval_status !== 'rejected') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You already have a seller application.'], 409);
            }

            return back()->withInput()->with('error', 'You already have a seller application.');
        }

        $age = ! empty($data['birthday'])
            ? Carbon::parse($data['birthday'])->age
            : null;
        $uploadMap = [
            'id_image' => 'seller-ids',
            'dti_certificate' => 'seller-documents',
            'bir_2303' => 'seller-documents',
            'business_permit' => 'seller-permits',
            'logo' => 'logos',
        ];
        $newFiles = [];

        try {
            foreach ($uploadMap as $field => $directory) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store($directory, $field === 'logo' ? 'public' : 'private');
                    if (! $path) {
                        throw new \RuntimeException("Could not store seller application file: {$field}");
                    }
                    $newFiles[$field] = $path;
                }
            }

            $user = DB::transaction(function () use ($data, $currentUser, $knownUser, $existing, $age, $newFiles) {
                $user = $currentUser ?: $knownUser;
                $userUpdates = [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'name' => trim($data['first_name'].' '.$data['last_name']),
                    'phone' => $data['phone'],
                    'region' => $data['region'],
                    'province' => $data['province'],
                    'city' => $data['city'],
                    'municipality' => $data['city'],
                    'barangay' => $data['barangay'],
                    'address_line' => $data['address_line'],
                    'postal_code' => $data['postal_code'],
                ];
                if (! empty($data['middle_initial'])) {
                    $userUpdates['middle_initial'] = $data['middle_initial'];
                }
                if (! empty($data['sex'])) {
                    $userUpdates['sex'] = $data['sex'];
                }
                if (! empty($data['birthday'])) {
                    $userUpdates['birthday'] = $data['birthday'];
                    $userUpdates['age'] = $age;
                }

                if ($user) {
                    $user->update($userUpdates);
                } else {
                    $userUpdates += [
                        'middle_initial' => $data['middle_initial'] ?? null,
                        'email' => $data['email'],
                        'sex' => $data['sex'] ?? 'other',
                        'birthday' => $data['birthday'] ?? null,
                        'age' => $age,
                        'password' => Hash::make($data['password']),
                        'role' => 'buyer',
                        'status' => 'active',
                        'account_status' => 'active',
                        'approval_status' => 'approved',
                        'email_verified_at' => null,
                    ];
                    $user = User::create($userUpdates);
                }

                $sellerAttributes = [
                    'store_name' => $data['business_name'],
                    'business_name' => $data['business_name'],
                    'line_of_business' => $data['line_of_business'],
                    'seller_type' => $data['seller_type'],
                    'tin' => $data['tin'],
                    'region' => $data['region'],
                    'province' => $data['province'],
                    'city' => $data['city'],
                    'barangay' => $data['barangay'],
                    'postal_code' => $data['postal_code'],
                    'address_line' => $data['address_line'],
                    'approval_status' => 'pending',
                    'status' => 'active',
                    'terms_accepted' => true,
                    'rejection_reason' => null,
                ];
                foreach ($newFiles as $field => $path) {
                    $sellerAttributes[$field] = $path;
                }
                if (isset($newFiles['id_image'])) {
                    $user->update(['id_image' => $newFiles['id_image']]);
                }

                if ($existing) {
                    $seller = $existing;
                    $seller->update($sellerAttributes);
                } else {
                    $seller = Seller::create($sellerAttributes + ['user_id' => $user->id]);
                }

                if (class_exists(SellerProfile::class)) {
                    try {
                        SellerProfile::updateOrCreate(
                            ['seller_id' => $seller->id],
                            [
                                'contact_email' => $user->email,
                                'contact_phone' => $data['phone'],
                                'address' => $seller->fullAddress(),
                            ]
                        );
                    } catch (Throwable $ignored) {
                        // The seller application remains valid when the optional profile table is absent.
                    }
                }

                return $user;
            });
        } catch (Throwable $exception) {
            foreach ($newFiles as $field => $path) {
                Storage::disk($field === 'logo' ? 'public' : 'private')->delete($path);
            }
            throw $exception;
        }

        foreach ($newFiles as $field => $path) {
            $oldPath = $storedPaths[$field] ?? $existing?->$field;
            if ($oldPath && $oldPath !== $path) {
                $this->deleteStoredFile($oldPath);
            }
        }

        if (! $user->email_verified_at) {
            app(\App\Http\Controllers\Buyer\Api\MobileAuthController::class)->sendCode($user);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Application submitted. Verify your email using the code we sent.', 'verification_required' => true], 201);
            }
            return redirect('/verify?email='.urlencode($user->email))->with('success', 'Application submitted. Please verify your email.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Application submitted. Please wait for the administrator decision.',
                'seller' => Seller::where('user_id', $user->id)->first(),
            ], 201);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('buyer', [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ]);

        return back()->with('success', "Application submitted! Please wait for the administrator's decision. A notification will be sent to {$user->email}.");
    }

    private function hasStoredFile(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $cleanPath = preg_replace('#^storage/#', '', $cleanPath);

        $candidates = [
            Storage::disk('private')->path($cleanPath),
            Storage::disk('public')->path($cleanPath),
            public_path('storage/'.$cleanPath),
            public_path($cleanPath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) <= 5 * 1024 * 1024) {
                return true;
            }
        }

        return false;
    }

    private function deleteStoredFile(string $path): void
    {
        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
        $cleanPath = preg_replace('#^storage/#', '', $cleanPath);
        Storage::disk('private')->delete($cleanPath);
        Storage::disk('public')->delete($cleanPath);

        $publicCopy = public_path('storage/'.$cleanPath);
        if (is_file($publicCopy) && ! is_link($publicCopy)) {
            @unlink($publicCopy);
        }
    }
}
