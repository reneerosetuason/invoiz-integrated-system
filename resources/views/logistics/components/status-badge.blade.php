@props(['status', 'label' => null])

@php
    $map = [
        // delivery statuses
        'waiting_for_rider'    => ['bg-amber-50 text-amber-700 ring-amber-200', 'Waiting for Rider'],
        'assigned'             => ['bg-blue-50 text-blue-700 ring-blue-200', 'Assigned'],
        'accepted'             => ['bg-cyan-50 text-cyan-700 ring-cyan-200', 'Accepted'],
        'going_to_pickup'      => ['bg-sky-50 text-sky-700 ring-sky-200', 'Going to Pickup'],
        'arrived_at_shop'      => ['bg-sky-50 text-sky-700 ring-sky-200', 'Arrived at Shop'],
        'picked_up'            => ['bg-indigo-50 text-indigo-700 ring-indigo-200', 'Picked Up'],
        'out_for_delivery'     => ['bg-purple-50 text-purple-700 ring-purple-200', 'Out for Delivery'],
        'arrived_at_customer'  => ['bg-violet-50 text-violet-700 ring-violet-200', 'Arrived at Customer'],
        'delivered'            => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Delivered'],
        'delivery_failed'      => ['bg-red-50 text-red-700 ring-red-200', 'Delivery Failed'],
        'cancelled'            => ['bg-gray-100 text-gray-600 ring-gray-200', 'Cancelled'],
        // rider statuses
        'available'            => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Available'],
        'delivering'           => ['bg-blue-50 text-blue-700 ring-blue-200', 'Delivering'],
        'inactive'             => ['bg-gray-100 text-gray-600 ring-gray-200', 'Inactive'],
        // application statuses
        'pending'              => ['bg-amber-50 text-amber-700 ring-amber-200', 'Pending'],
        'approved'             => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Approved'],
        'rejected'             => ['bg-red-50 text-red-700 ring-red-200', 'Rejected'],
    ];

    [$classes, $defaultLabel] = $map[$status] ?? ['bg-gray-100 text-gray-600 ring-gray-200', ucwords(str_replace('_', ' ', $status))];
    $text = $label ?? $defaultLabel;
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $text }}
</span>