{{-- Shared global styling for Logistics form controls (inputs, selects, textareas).
     Uses :where() (zero specificity) so Tailwind utility classes such as
     pl-10, pr-4, py-2, and focus:border-teal correctly override these defaults.
     Checkbox/radio/file/range/color/hidden inputs are excluded so the existing
     @tailwindcss/forms styling for them is left untouched. --}}
<style>
    :where(
        input:not([type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="hidden"]),
        select,
        textarea
    ) {
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background-color: #ffffff;
        color: #111827;
        font-size: 0.875rem;
        line-height: 1.4;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }

    :where(
        input:not([type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="hidden"]),
        select,
        textarea
    ):focus {
        border-color: #16697a;
        box-shadow: 0 0 0 3px rgba(22, 105, 122, 0.15);
        outline: none;
    }

    :where(
        input:not([type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="hidden"]),
        textarea
    )::placeholder {
        color: #9ca3af;
    }

    /* Selects: replace the native arrow with a teal chevron and reserve enough
       right-side padding so the selected text never runs under the arrow.
       `!important` is scoped to `select` only — it guarantees the arrow space
       regardless of any per-page padding utility (px-3, pr-4, etc.) that would
       otherwise override this shared default. It does not affect text inputs,
       search inputs, checkboxes, radios, file inputs, or textareas. The chevron
       is a background-image positioned on the right, so it never captures
       pointer events, and text stays clear of it. */
    :where(select) {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        padding-right: 2.75rem !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2316697A' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 1rem;
        cursor: pointer;
    }

    :where(textarea) {
        min-height: 80px;
        resize: vertical;
    }

    /* Password reveal: hide the browser-native reveal control on Chromium/Edge
       so it never appears alongside the application's custom visibility toggle
       (the button rendered by x-password-input). Scoped to password inputs only,
       so search/text/email/select/checkbox/radio/file controls are unaffected.
       The input already reserves right padding (pr-12) for the custom button, so
       typed text stays clear of the icon. */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
        display: none;
    }

    /* Disabled / read-only state */
    :where(
        input:not([type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="hidden"]),
        select,
        textarea
    ):disabled,
    :where(
        input:not([type="checkbox"], [type="radio"], [type="file"], [type="range"], [type="color"], [type="hidden"]),
        select,
        textarea
    )[readonly] {
        background-color: #f9fafb;
        color: #6b7280;
        cursor: not-allowed;
        opacity: 1;
    }
</style>
