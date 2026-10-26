@php $edit = $edit ?? false; @endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="field-label">Sale name</label>
        <input type="text" name="name" maxlength="120" class="input" required placeholder="e.g. Weekend Blowout"
               @if($edit) :value="form.name" @else value="{{ old('name') }}" @endif>
    </div>
    <div>
        <label class="field-label">Discount type</label>
        <select name="discount_type" class="select" @if($edit) x-model="form.discount_type" @endif>
            <option value="percent" @if(!$edit && old('discount_type', 'percent') === 'percent') selected @endif>Percentage (%)</option>
            <option value="fixed" @if(!$edit && old('discount_type') === 'fixed') selected @endif>Fixed amount (₱)</option>
        </select>
    </div>
    <div>
        <label class="field-label">Discount value</label>
        <input type="number" step="0.01" min="0.01" name="discount_value" class="input" required placeholder="e.g. 15"
               @if($edit) :value="form.discount_value" @else value="{{ old('discount_value') }}" @endif>
    </div>
    <div>
        <label class="field-label">Starts at <span class="normal-case text-ink-light">(blank = now)</span></label>
        <input type="datetime-local" name="starts_at" class="input"
               @if($edit) :value="form.starts_at" @else value="{{ old('starts_at') }}" @endif>
    </div>
    <div>
        <label class="field-label">Ends at <span class="normal-case text-ink-light">(blank = no end)</span></label>
        <input type="datetime-local" name="ends_at" class="input"
               @if($edit) :value="form.ends_at" @else value="{{ old('ends_at') }}" @endif>
    </div>
    <div class="md:col-span-2">
        <label class="field-label">Status</label>
        <select name="status" class="select" @if($edit) x-model="form.status" @endif>
            <option value="active" @if(!$edit && old('status', 'active') === 'active') selected @endif>Active</option>
            <option value="inactive" @if(!$edit && old('status') === 'inactive') selected @endif>Inactive</option>
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="field-label">Products in this sale</label>
        <div class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-borderline p-2">
            @foreach($products as $p)
                <label class="flex items-center gap-2 rounded-lg px-1 py-1 text-sm hover:bg-basebg">
                    <input type="checkbox" name="products[]" value="{{ $p->id }}"
                           @if($edit) :checked="form.products.includes({{ $p->id }})" @else @checked(in_array($p->id, (array) old('products', []))) @endif>
                    <span class="truncate">{{ $p->name }}</span>
                    <span class="ml-auto text-ink-light">{{ peso($p->price) }}</span>
                </label>
            @endforeach
            @if($products->isEmpty())
                <p class="py-3 text-center text-xs text-ink-light">No products available.</p>
            @endif
        </div>
    </div>
</div>
