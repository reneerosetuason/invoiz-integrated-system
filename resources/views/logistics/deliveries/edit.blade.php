<x-app-layout>
    @include('logistics.partials.delivery-theme')
    <div class="logistics-deliveries delivery-page-edit">
    <div class="delivery-head">
        <a href="{{ route('deliveries.index') }}"
           class="delivery-back" aria-label="Back to deliveries">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Delivery</h1>
            <p class="text-sm text-gray-500 mt-1">Update the details for this delivery order</p>
        </div>
    </div>

    <div class="delivery-panel delivery-entry-form">
        <form action="{{ route('deliveries.update', $delivery) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="h-2 w-1 rounded-full bg-teal"></div>
                        <h2 class="text-base font-semibold text-gray-900">Sender Information</h2>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="sender_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="sender_name" id="sender_name" value="{{ old('sender_name', $delivery->sender_name) }}" required
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            @error('sender_name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" name="sender_phone" id="sender_phone" value="{{ old('sender_phone', $delivery->sender_phone) }}" required
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            @error('sender_phone')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sender_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="sender_address" id="sender_address" rows="3" required
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">{{ old('sender_address', $delivery->sender_address) }}</textarea>
                            @error('sender_address')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="h-2 w-1 rounded-full bg-teal"></div>
                        <h2 class="text-base font-semibold text-gray-900">Recipient Information</h2>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="recipient_name" id="recipient_name" value="{{ old('recipient_name', $delivery->recipient_name) }}" required
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            @error('recipient_name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" name="recipient_phone" id="recipient_phone" value="{{ old('recipient_phone', $delivery->recipient_phone) }}" required
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            @error('recipient_phone')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="recipient_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea name="recipient_address" id="recipient_address" rows="3" required
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">{{ old('recipient_address', $delivery->recipient_address) }}</textarea>
                            @error('recipient_address')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h2 class="text-base font-semibold text-gray-900">Package Details</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="package_type" class="block text-sm font-medium text-gray-700 mb-1">Package Type</label>
                        <select name="package_type" id="package_type"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            <option value="">— Select type —</option>
                            @foreach(['Document', 'Parcel', 'Fragile', 'Electronics', 'Groceries', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(old('package_type', $delivery->package_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('package_type')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="package_description" class="block text-sm font-medium text-gray-700 mb-1">Package Description</label>
                        <input type="text" name="package_description" id="package_description" value="{{ old('package_description', $delivery->package_description) }}" maxlength="500"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                        @error('package_description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                        <input type="number" name="weight" id="weight" value="{{ old('weight', $delivery->weight) }}" step="0.01" min="0.01"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                        @error('weight')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">{{ old('notes', $delivery->notes) }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-2 w-1 rounded-full bg-teal"></div>
                    <h2 class="text-base font-semibold text-gray-900">Delivery Information</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="estimated_delivery_at" class="block text-sm font-medium text-gray-700 mb-1">Preferred Delivery Date</label>
                        <input type="datetime-local" name="estimated_delivery_at" id="estimated_delivery_at" value="{{ old('estimated_delivery_at', $delivery->estimated_delivery_at ? $delivery->estimated_delivery_at->format('Y-m-d\TH:i') : '') }}"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                        @error('estimated_delivery_at')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Delivery Priority</label>
                        <select name="priority" id="priority"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-teal focus:ring-teal text-sm">
                            @foreach(['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $delivery->priority ?? 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('priority')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="delivery-form-footer mt-8 border-t border-gray-100 pt-6">
                <p>Fields marked <span class="text-red-500">*</span> are required.</p>
                <div>
                <a href="{{ route('deliveries.index') }}"
                   class="inline-flex items-center justify-center bg-white border border-gray-200 hover:border-gray-300 text-gray-700 font-semibold px-6 py-2.5 rounded-xl transition">
                    Cancel
                </a>
                <button type="submit" class="bg-teal hover:bg-teal-dark text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">
                    Save changes
                </button>
                </div>
            </div>
        </form>
    </div>
    </div>
</x-app-layout>
