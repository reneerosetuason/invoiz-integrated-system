<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5 px-4 py-8">
        <h1 class="text-2xl font-bold">Failed delivery resolutions</h1>
        <p>After three unsuccessful buyer delivery attempts, the seller chooses a funded return or unclaimed storage. Completed services remain payable.</p>
        @forelse($cases as $case)
            <a class="block rounded-xl border bg-white p-4" href="{{ route('failed-deliveries.show', $case->delivery_id) }}">
                <strong>{{ $case->delivery->tracking_number }}</strong>
                <span class="ml-3">{{ ucwords(str_replace('_', ' ', $case->stage)) }}</span>
            </a>
        @empty
            <p>No failed-delivery resolutions are assigned to you.</p>
        @endforelse
        {{ $cases->links() }}
    </div>
</x-app-layout>
