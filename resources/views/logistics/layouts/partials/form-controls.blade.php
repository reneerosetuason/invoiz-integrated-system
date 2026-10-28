{{-- Shared global styling for Logistics form controls (inputs, selects, textareas).
     Uses :where() (zero specificity) so Tailwind utility classes such as
     pl-10, pr-4, py-2, and focus:border-teal correctly override these defaults.
     Checkbox/radio/file/range/color/hidden inputs are excluded so the existing
     @tailwindcss/forms styling for them is left untouched. --}}
<link rel="stylesheet" href="{{ asset('assets/logistics/css/layouts/partials/form-controls.css') }}">
