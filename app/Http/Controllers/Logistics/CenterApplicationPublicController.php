<?php

namespace App\Http\Controllers\Logistics;

use App\Models\LogisticsCenterApplicationDocument;
use App\Models\Province;
use App\Models\RiderEmailVerification;
use App\Services\CenterApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public Logistics web flows: "Open a Logistics Center" application and
 * "Check a Logistics Center Application Status". Both share the exact same
 * validation and persistence logic as the mobile API via
 * CenterApplicationService, so the two surfaces stay consistent.
 */
class CenterApplicationPublicController extends Controller
{
    public function create(): View
    {
        $provinces = Province::orderBy('name')->get(['id', 'name']);
        $regions = collect(config('logistics.psgc_regions', []))
            ->only(Province::distinct()->pluck('region_code')->filter()->all())
            ->all();

        // Pre-render the cascading address options so that old() input from a
        // failed submission (or the initial GET) stays fully populated without
        // any client-side fetching. The page only fetches new options after the
        // applicant actually changes a selection.
        $municipalities = collect();
        $barangays = collect();

        if ($provinceName = old('province')) {
            $province = Province::where('name', $provinceName)->first();

            if ($province) {
                $municipalities = $province->municipalities()->orderBy('name')->get(['id', 'name']);

                if ($municipalityName = old('municipality')) {
                    $municipality = $municipalities->firstWhere('name', $municipalityName);

                    if ($municipality) {
                        $barangays = $municipality->barangays()->orderBy('name')->get(['id', 'name']);
                    }
                }
            }
        }

        return view('logistics.center-application-apply', [
            'documentLabels' => LogisticsCenterApplicationDocument::TYPES,
            'regions' => $regions,
            'provinces' => $provinces,
            'municipalities' => $this->addressOptions($municipalities, old('municipality')),
            'barangays' => $this->addressOptions($barangays, old('barangay')),
            // Restore the verified badge after a failed submission without
            // trusting the client: only a server-side consumed OTP counts,
            // and store() re-checks it before persisting anything.
            'verifiedEmail' => $this->verifiedOldEmail(),
        ]);
    }

    /**
     * Turn an Eloquent collection into option data for a cascading address
     * <select>. Values that are not in the local PSGC dataset (for example text
     * submitted before the address picker existed) are kept as a fallback option
     * so a failed submission round-trips exactly what the applicant entered.
     */
    private function addressOptions(Collection $items, ?string $selected): array
    {
        $options = $items
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
            ->values()
            ->all();

        if ($selected !== null && $selected !== '' && ! collect($options)->contains(fn ($o) => $o['name'] === $selected)) {
            $options[] = ['id' => null, 'name' => $selected];
        }

        return $options;
    }

    public function store(Request $request, CenterApplicationService $service): RedirectResponse
    {
        // Terms consent gate (web form only; the mobile API owns its own
        // consent screen): the submit button is disabled until checked, and
        // this rejects any direct POST without it.
        if (! $request->boolean('terms')) {
            return redirect()
                ->route('center-application.apply')
                ->withInput()
                ->withErrors(['terms' => 'You must agree to the Logistics Terms of Service to submit your application.']);
        }

        // Email OTP gate (mirrors the rider apply flow): only a server-side
        // consumed verification for the exact submitted address authorizes
        // the submission. Runs only for well-formed addresses so missing or
        // malformed emails still surface the standard validation errors.
        // The shared service and the mobile API are intentionally untouched.
        $email = strtolower(trim((string) $request->input('email', '')));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
            && ! RiderEmailVerification::isVerified($email)) {
            return redirect()
                ->route('center-application.apply')
                ->withInput()
                ->withErrors(['email' => 'Please verify your email address before submitting your application.']);
        }

        $application = $service->create($request, 'web');
        $service->notifyNewApplication($application);

        return redirect()
            ->route('center-application.apply')
            ->with('success', 'Your Logistics Center application has been submitted successfully. Use the "Check a Logistics Center Application Status" page with the email you registered to track its progress.');
    }

    /**
     * The previously submitted email, but only when it still holds a
     * server-side consumed OTP verification. Lets the wizard restore its
     * verified badge after a failed submission round-trip.
     */
    private function verifiedOldEmail(): string
    {
        $email = old('email');

        if (! is_string($email) || trim($email) === '') {
            return '';
        }

        $normalized = strtolower(trim($email));

        return RiderEmailVerification::isVerified($normalized) ? $normalized : '';
    }

    public function status(Request $request): View
    {
        return view('logistics.center-application-status', [
            'searched' => false,
        ]);
    }

    public function check(Request $request, CenterApplicationService $service): View
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        return view('logistics.center-application-status', [
            'searched' => true,
            'application' => $service->status($validated['email']),
            'email' => $validated['email'],
        ]);
    }
}



