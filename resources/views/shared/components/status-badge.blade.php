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
        'lost'                 => ['bg-red-100 text-red-800 ring-red-300', 'Lost'],
        'damaged'              => ['bg-orange-50 text-orange-700 ring-orange-200', 'Damaged'],
        'returned_to_seller'   => ['bg-slate-100 text-slate-700 ring-slate-200', 'Returned to Seller'],
        'cancelled'            => ['bg-gray-100 text-gray-600 ring-gray-200', 'Cancelled'],
        // rider statuses
        'available'            => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Available'],
        'delivering'           => ['bg-blue-50 text-blue-700 ring-blue-200', 'Delivering'],
        'on_delivery'          => ['bg-blue-50 text-blue-700 ring-blue-200', 'On Delivery'],
        'offline'              => ['bg-gray-100 text-gray-600 ring-gray-200', 'Offline'],
        'active'               => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Active'],
        'suspended'            => ['bg-red-50 text-red-700 ring-red-200', 'Suspended'],
        'inactive'             => ['bg-gray-100 text-gray-600 ring-gray-200', 'Inactive'],
        // parcel / sorting / finance statuses
        'pending_arrival'      => ['bg-amber-50 text-amber-700 ring-amber-200', 'Pending Arrival'],
        'received'             => ['bg-sky-50 text-sky-700 ring-sky-200', 'Received'],
        'received_at_center'   => ['bg-sky-50 text-sky-700 ring-sky-200', 'Received at Center'],
        'scanned'              => ['bg-cyan-50 text-cyan-700 ring-cyan-200', 'Scanned'],
        'sorted'               => ['bg-indigo-50 text-indigo-700 ring-indigo-200', 'Sorted'],
        'dispatched'           => ['bg-purple-50 text-purple-700 ring-purple-200', 'Dispatched'],
        'confirmed'            => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Confirmed'],
        'disputed'             => ['bg-red-50 text-red-700 ring-red-200', 'Disputed'],
        'completed'            => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Completed'],
        // application statuses
        'pending'              => ['bg-amber-50 text-amber-700 ring-amber-200', 'Pending'],
        'approved'             => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'Approved'],
        'rejected'             => ['bg-red-50 text-red-700 ring-red-200', 'Rejected'],
    ];

    [$classes, $defaultLabel] = $map[$status] ?? ['bg-gray-100 text-gray-600 ring-gray-200', ucwords(str_replace('_', ' ', (string) $status))];
    $text = $label ?? $defaultLabel;
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ring-1 ring-inset {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $text }}
</span>
