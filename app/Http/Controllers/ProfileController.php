<?php

namespace App\Http\Controllers;

use App\Mail\OtpCodeMail;
use App\Models\Address;
use App\Models\Province;
use App\Models\Seller;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhilippinePhone;
use App\Support\NameNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    private const EMAIL_OTP_KEY = 'profile_email_change_otp';
    private const EMAIL_OTP_SENT_AT_KEY = 'profile_email_change_otp_sent_at';

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login')->with('error', 'Please login to view your profile.');
        }

        $sellerApp = Seller::where('user_id', $user->id)->first();
        Address::ensureForBuyer((int) $user->id);
        $addresses = $user->addresses()->orderByDesc('is_default')->orderByDesc('updated_at')->get();
        $pendingEmail = $request->session()->get(self::EMAIL_OTP_KEY)['email'] ?? null;

        $provinces = Province::orderBy('name')->get(['id', 'name', 'region_code', 'code']);
        $regionCodes = $provinces->pluck('region_code')->filter()->unique()->all();
        $configRegions = config('logistics.psgc_regions', []);
        $regions = [];
        foreach ($regionCodes as $code) {
            $regions[$code] = $configRegions[$code] ?? $code;
        }
        ksort($regions);

        return view('profile', compact('user', 'sellerApp', 'pendingEmail', 'addresses', 'regions', 'provinces'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['first_name', 'last_name']),
            ['first_name', 'last_name']
        ));

        if (is_string($request->input('phone'))) {
            $phone = trim($request->input('phone'));
            $request->merge([
                'phone' => $phone === '' ? null : PhilippinePhone::normalize($phone),
            ]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100', new PersonName()],
            'last_name' => ['required', 'string', 'max:100', new PersonName()],
            'phone' => [
                'nullable',
                'string',
                new PhilippinePhone(),
                function ($attribute, $value, $fail) use ($user) {
                    $canonical = PhilippinePhone::normalize((string) $value);
                    if (! preg_match('/^09\d{9}$/', (string) $canonical)) {
                        return;
                    }

                    $isUsed = User::query()
                        ->where('id', '!=', $user->id)
                        ->whereNotNull('phone')
                        ->cursor()
                        ->contains(fn (User $other) => PhilippinePhone::normalize((string) $other->phone) === $canonical);

                    if ($isUsed) {
                        $fail('This mobile number is already in use. Please use a different number.');
                    }
                },
            ],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'current_password' => [
                'nullable',
                'required_with:new_password',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value !== null && $value !== '' && ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
                        $fail('Your current password is incorrect.');
                    }
                },
            ],
            'new_password' => [
                'nullable',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'phone.regex' => 'Enter an 11-digit mobile number starting with 09, or use the equivalent +63 format.',
            'profile_picture.image' => 'The profile picture must be a photo.',
            'profile_picture.mimes' => 'Use a JPG, JPEG, PNG, or WEBP photo.',
            'profile_picture.max' => 'The profile picture must not exceed 5 MB.',
            'new_password.confirmed' => 'The new password confirmation does not match.',
        ]);

        $oldPhoto = $user->profile_picture;
        $newPhoto = null;
        if ($request->hasFile('profile_picture')) {
            $newPhoto = $request->file('profile_picture')->store('profile-pictures', 'public');
            if (! is_string($newPhoto) || $newPhoto === '') {
                return back()->withErrors(['profile_picture' => 'The profile picture could not be saved. Please try again.'])->withInput();
            }
        }

        try {
            if ($newPhoto) {
                $this->publishProfilePicture($newPhoto);
            }

            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $user->name = trim($data['first_name'] . ' ' . $data['last_name']);
            $user->phone = $data['phone'] ?? null;

            if ($newPhoto) {
                $user->profile_picture = $newPhoto;
            }
            if (! empty($data['new_password'])) {
                $user->password = $data['new_password'];
            }

            $user->save();
        } catch (Throwable $exception) {
            if ($newPhoto) {
                $this->deleteProfilePicture($newPhoto);
            }
            throw $exception;
        }

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto) {
            $this->deleteProfilePicture($oldPhoto);
        }

        $this->syncBuyerSession($request, $user);

        return redirect('/profile')->with('success', 'Profile updated successfully.');
    }

    public function requestEmailCode(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $email = $data['email'];

        if (strtolower($email) === strtolower((string) $user->email)) {
            return redirect('/profile')->with('error', 'That is already your current email address.');
        }

        $lastSentAt = (int) $request->session()->get(self::EMAIL_OTP_SENT_AT_KEY, 0);
        if ($lastSentAt > now()->subSeconds(60)->timestamp) {
            return redirect('/profile')->withInput(['email' => $email])->with('error', 'Please wait one minute before requesting another verification code.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Mail::to($email)->send(new OtpCodeMail($code, $user->first_name ?? ''));
        } catch (Throwable $exception) {
            \Log::warning('Profile email change OTP failed to send.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return redirect('/profile')->withInput(['email' => $email])->with('error', 'We could not send the verification code. Please try again.');
        }

        $request->session()->put(self::EMAIL_OTP_KEY, [
            'email' => $email,
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'attempts' => 0,
        ]);
        $request->session()->put(self::EMAIL_OTP_SENT_AT_KEY, now()->timestamp);

        return redirect('/profile')->withInput(['email' => $email])->with('success', 'A 6-digit verification code was sent to ' . $email . '. It expires in 10 minutes.');
    }

    public function confirmEmailChange(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'email_code' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get(self::EMAIL_OTP_KEY);
        if (! is_array($pending) || strtolower((string) ($pending['email'] ?? '')) !== $data['email']) {
            return back()->withErrors(['email_code' => 'Request a verification code for this email address first.'])->withInput();
        }

        if ((int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget([self::EMAIL_OTP_KEY, self::EMAIL_OTP_SENT_AT_KEY]);
            return back()->withErrors(['email_code' => 'That verification code has expired. Request a new one.'])->withInput();
        }

        if ((int) ($pending['attempts'] ?? 0) >= 5) {
            $request->session()->forget([self::EMAIL_OTP_KEY, self::EMAIL_OTP_SENT_AT_KEY]);
            return back()->withErrors(['email_code' => 'Too many incorrect codes. Request a new verification code.'])->withInput();
        }

        if (! Hash::check($data['email_code'], (string) ($pending['hash'] ?? ''))) {
            $pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
            if ($pending['attempts'] >= 5) {
                $request->session()->forget([self::EMAIL_OTP_KEY, self::EMAIL_OTP_SENT_AT_KEY]);
                return back()->withErrors(['email_code' => 'Too many incorrect codes. Request a new verification code.'])->withInput();
            }
            $request->session()->put(self::EMAIL_OTP_KEY, $pending);

            return back()->withErrors(['email_code' => 'That verification code does not match.'])->withInput();
        }

        $user->email = $data['email'];
        $user->email_verified_at = now();
        $user->save();
        $request->session()->forget([self::EMAIL_OTP_KEY, self::EMAIL_OTP_SENT_AT_KEY]);
        $this->syncBuyerSession($request, $user);

        return redirect('/profile')->with('success', 'Your email address has been verified and updated.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        Address::ensureForBuyer((int) $user->id);
        $input = $request->input('new_address', []);
        $input = is_array($input) ? $input : [];
        $validator = Validator::make($input, $this->addressRules());
        if ($validator->fails()) {
            return back()->withErrors($validator, 'address_new')->withInput();
        }

        $data = $validator->validated();
        $data['phone'] = PhilippinePhone::normalize($data['phone']);

        DB::transaction(function () use ($request, $user, $data) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $hasAddress = Address::query()->where('buyer_id', $user->id)->lockForUpdate()->exists();
            $makeDefault = $request->boolean('new_address.is_default') || ! $hasAddress;

            if ($makeDefault) {
                Address::query()->where('buyer_id', $user->id)->update(['is_default' => false]);
            }

            Address::create($data + [
                'buyer_id' => $user->id,
                'is_default' => $makeDefault,
            ]);
        });

        return redirect('/profile#saved-addresses')->with('success', 'Address saved.');
    }

    public function updateAddress(Request $request, int $addressId): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        $address = Address::query()->where('buyer_id', $user->id)->findOrFail($addressId);
        $key = 'addresses.' . $address->id;
        $input = $request->input($key, []);
        $input = is_array($input) ? $input : [];
        $validator = Validator::make($input, $this->addressRules());
        if ($validator->fails()) {
            return back()->withErrors($validator, 'address_edit_' . $address->id)->withInput();
        }

        $data = $validator->validated();
        $data['phone'] = PhilippinePhone::normalize($data['phone']);
        $makeDefault = $request->boolean($key . '.is_default');

        DB::transaction(function () use ($user, $address, $data, $makeDefault) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedAddress = Address::query()
                ->where('buyer_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($address->id);

            if ($makeDefault) {
                Address::query()->where('buyer_id', $user->id)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // Editing cannot unset the only default. Use “Set as default”
                // on another address before the current default can be removed.
                $data['is_default'] = (bool) $lockedAddress->is_default;
            }

            $lockedAddress->fill($data)->save();
        });

        return redirect('/profile#saved-addresses')->with('success', 'Address updated.');
    }

    public function setDefaultAddress(Request $request, int $addressId): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        DB::transaction(function () use ($user, $addressId) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $address = Address::query()
                ->where('buyer_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($addressId);

            Address::query()->where('buyer_id', $user->id)->update(['is_default' => false]);
            $address->is_default = true;
            $address->save();
        });

        return redirect('/profile#saved-addresses')->with('success', 'Default delivery address updated.');
    }

    public function deleteAddress(Request $request, int $addressId): RedirectResponse
    {
        $user = $this->currentUser($request);
        if (! $user) {
            return redirect('/login');
        }

        Address::ensureForBuyer((int) $user->id);
        $isDefault = DB::transaction(function () use ($user, $addressId) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $address = Address::query()
                ->where('buyer_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($addressId);

            if ($address->is_default) {
                return true;
            }

            $address->delete();

            return false;
        });

        if ($isDefault) {
            return redirect('/profile#saved-addresses')->with('error', 'Choose another address as the default before deleting this one.');
        }

        return redirect('/profile#saved-addresses')->with('success', 'Address deleted.');
    }

    private function addressRules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', new PhilippinePhone()],
            'address_line' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function publishProfilePicture(string $path): void
    {
        if (! str_starts_with($path, 'profile-pictures/') || str_contains($path, '..')) {
            throw new \RuntimeException('The profile picture path is invalid.');
        }

        $source = Storage::disk('public')->path($path);
        $destination = public_path('storage/' . $path);
        $publicStorageRoot = realpath(public_path('storage'));
        $diskRoot = realpath(Storage::disk('public')->path(''));

        if ($publicStorageRoot && $diskRoot && $publicStorageRoot === $diskRoot) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        if (! File::copy($source, $destination)) {
            throw new \RuntimeException('The profile picture could not be published.');
        }
    }

    private function deleteProfilePicture(string $path): void
    {
        Storage::disk('public')->delete($path);

        if (! str_starts_with($path, 'profile-pictures/') || str_contains($path, '..')) {
            return;
        }

        $publicStorageRoot = realpath(public_path('storage'));
        $diskRoot = realpath(Storage::disk('public')->path(''));
        if (! $publicStorageRoot || ! $diskRoot || $publicStorageRoot !== $diskRoot) {
            File::delete(public_path('storage/' . $path));
        }
    }

    private function currentUser(Request $request): ?User
    {
        $authenticatedUser = Auth::user();
        if ($authenticatedUser instanceof User) {
            $buyerSessionId = (int) $request->session()->get('buyer.id', 0);
            if ($buyerSessionId !== (int) $authenticatedUser->getKey()) {
                $this->syncBuyerSession($request, $authenticatedUser);
            }

            return $authenticatedUser;
        }

        $userId = $request->session()->get('buyer.id');

        return $userId ? User::find($userId) : null;
    }

    private function syncBuyerSession(Request $request, User $user): void
    {
        if ($request->session()->has('buyer')) {
            $request->session()->put('buyer', [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]);
        }
    }
}
