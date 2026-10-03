@php
    $v = $voucher;
@endphp
<div class="space-y-4">
    <div>
        <label class="field-label">Code</label>
        <input type="text" name="code" class="input uppercase" placeholder="e.g. SAVE10"
               value="{{ old('code', $v?->code) }}" {{ $v ? 'disabled' : 'required' }}>
        @if($v)<p class="mt-1 text-[11px] text-ink-light">Codes cannot be changed after creation.</p>@endif
    </div>
    <div>
        <label class="field-label">Name</label>
        <input type="text" name="name" class="input" placeholder="e.g. 10% Off Your Order"
               value="{{ old('name', $v?->name) }}" required>
    </div>
    <div>
        <label class="field-label">Description</label>
        <textarea name="description" rows="2" class="textarea" placeholder="Optional note">{{ old('description', $v?->description) }}</textarea>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="field-label">Discount Type</label>
            <select name="discount_type" class="select">
                <option value="fixed" {{ old('discount_type', $v?->discount_type) === 'fixed' ? 'selected' : '' }}>Fixed (₱)</option>
                <option value="percent" {{ old('discount_type', $v?->discount_type) === 'percent' ? 'selected' : '' }}>Percent (%)</option>
            </select>
        </div>
        <div>
            <label class="field-label">Discount Value</label>
            <input type="number" name="discount_value" step="0.01" min="0" class="input"
                   value="{{ old('discount_value', $v?->discount_value) }}" required>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="field-label">Min Spend (₱)</label>
            <input type="number" name="min_spend" step="0.01" min="0" class="input"
                   value="{{ old('min_spend', $v?->min_spend ?? 0) }}">
        </div>
        <div>
            <label class="field-label">Max Discount (₱) <span class="normal-case">for %</span></label>
            <input type="number" name="max_discount" step="0.01" min="0" class="input"
                   value="{{ old('max_discount', $v?->max_discount) }}">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="field-label">Valid From</label>
            <input type="date" name="valid_from" class="input" value="{{ old('valid_from', $v?->valid_from?->toDateString()) }}">
        </div>
        <div>
            <label class="field-label">Valid Until</label>
            <input type="date" name="valid_until" class="input" value="{{ old('valid_until', $v?->valid_until?->toDateString()) }}">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="field-label">Usage Limit</label>
            <input type="number" name="usage_limit" min="1" class="input" placeholder="Unlimited"
                   value="{{ old('usage_limit', $v?->usage_limit) }}">
        </div>
        <div>
            <label class="field-label">Status</label>
            <select name="status" class="select">
                <option value="active" {{ old('status', $v?->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status', $v?->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
    </div>
</div>