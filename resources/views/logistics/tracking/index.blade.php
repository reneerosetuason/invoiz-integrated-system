<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold text-gray-900 leading-tight">Tracking</h2>
        <p class="mt-1 text-sm text-gray-500">Search for any delivery by its tracking number</p>
    </x-slot>

    <div class="flex items-center justify-center py-8">
        <div class="w-full max-w-2xl">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <div class="flex items-center justify-center mb-6">
                    <div class="h-14 w-14 rounded-2xl bg-teal-light flex items-center justify-center">
                        <svg class="h-7 w-7 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                @if (session('error'))
                    <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                        <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                @if (isset($error))
                    <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                        <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $error }}</span>
                    </div>
                @endif

                <form method="GET" action="{{ route('tracking.search') }}">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                            <input
                                type="text"
                                name="tracking_number"
                                placeholder="Enter tracking number..."
                                value="{{ request('tracking_number') }}"
                                class="w-full border-gray-300 focus:border-teal focus:ring-teal rounded-xl shadow-sm text-base py-3.5 pl-12 pr-4"
                                required
                            />
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center bg-teal hover:bg-teal-dark text-white font-semibold px-8 py-3.5 rounded-xl transition-colors duration-200"
                        >
                            <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Search
                        </button>
                    </div>
                </form>

                <p class="mt-4 text-center text-sm text-gray-400">e.g. TRK-20260101-XXXX</p>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-medium">
                    TRK-20260101-XXXX
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-medium">
                    TRK-YYYYMMDD-XXXX
                </span>
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-teal-light text-teal-dark text-xs font-medium">
                    Check package receipt
                </span>
            </div>
        </div>
    </div>
</x-app-layout>
