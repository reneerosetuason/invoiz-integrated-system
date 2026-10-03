@extends('layouts.seller')

@section('title', 'Vouchers & Discounts')

@section('content')
<div x-data="editVoucher()">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight">Vouchers &amp; Discounts</h2>
            <p class="mt-0.5 text-sm text-ink-light">Create discount codes buyers can apply at checkout.</p>
        </div>
        <button type="button" @click="$refs.createModal.showModal()" class="btn btn-primary">
            <x-icon name="plus" class="h-4 w-4" /> Create Voucher
        </button>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Min Spend</th>
                        <th>Validity</th>
                        <th class="text-center">Usage</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                        <tr>
                            <td><span class="badge !bg-[#16697A] !text-white">{{ $voucher->code }}</span></td>
                            <td>
                                <div class="font-semibold">{{ $voucher->name }}</div>
                                @if($voucher->description)<div class="max-w-[260px] truncate text-[11px] text-ink-light">{{ $voucher->description }}</div>@endif
                            </td>
                            <td class="font-semibold">
                                {{ $voucher->discount_type === 'percent' ? $voucher->discount_value.'%' : peso($voucher->discount_value) }}
                                @if($voucher->discount_type === 'percent' && $voucher->max_discount)
                                    <div class="text-[11px] font-normal text-ink-light">up to {{ peso($voucher->max_discount) }}</div>
                                @endif
                            </td>
                            <td>{{ $voucher->min_spend > 0 ? peso($voucher->min_spend) : 'None' }}</td>
                            <td class="text-xs">
                                @if($voucher->valid_from || $voucher->valid_until)
                                    {{ $voucher->valid_from?->format('M j') ?? '—' }} → {{ $voucher->valid_until?->format('M j, Y') ?? '—' }}
                                @else
                                    <span class="text-ink-light">No expiry</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="font-bold">{{ $voucher->used_count }}</span>
                                @if($voucher->usage_limit)<span class="text-ink-light">/ {{ $voucher->usage_limit }}</span>@endif
                            </td>
                            <td><span class="badge badge-{{ $voucher->status }}">{{ ucfirst($voucher->status) }}</span></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button @click="openEdit({{ $voucher->id }})" class="btn btn-outline btn-xs" title="Edit">
                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                    </button>
                                    @if($voucher->seller_id)
                                        <form method="POST" action="{{ route('seller.vouchers.destroy', $voucher->id) }}"
                                              onsubmit="return confirm('Delete this voucher?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-xs" title="Delete">
                                                <x-icon name="trash" class="h-3.5 w-3.5" />
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <x-icon name="ticket" class="mx-auto h-10 w-10 text-ink-light" />
                                <p class="mt-3 font-semibold">No vouchers yet</p>
                                <p class="text-sm text-ink-light">Create a voucher to offer discounts on your store.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create modal --}}
    <dialog x-ref="createModal" class="m-auto w-full max-w-md rounded-2xl border border-borderline bg-white p-0 shadow-2xl backdrop:bg-black/40">
        <form method="POST" action="{{ route('seller.vouchers.store') }}" class="p-6">
            @csrf
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold">Create Voucher</h3>
                <button type="button" @click="$refs.createModal.close()" class="rounded-xl p-1.5 text-ink-light hover:bg-soft"><x-icon name="x" class="h-5 w-5" /></button>
            </div>
            @include('seller.vouchers._form', ['voucher' => null])
            <button type="submit" class="btn btn-primary mt-4 w-full">Create Voucher</button>
        </form>
    </dialog>

    {{-- Edit modal --}}
    <dialog x-ref="editModal" class="m-auto w-full max-w-md rounded-2xl border border-borderline bg-white p-0 shadow-2xl backdrop:bg-black/40">
        <form method="POST" :action="editAction" class="p-6">
            @csrf
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold">Edit Voucher</h3>
                <button type="button" @click="$refs.editModal.close()" class="rounded-xl p-1.5 text-ink-light hover:bg-soft"><x-icon name="x" class="h-5 w-5" /></button>
            </div>
            <div class="space-y-4" x-show="editing">
                <div>
                    <label class="field-label">Code</label>
                    <input type="text" class="input bg-white font-bold" :value="editing.code" disabled>
                </div>
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" name="name" class="input" x-model="editing.name" required>
                </div>
                <div>
                    <label class="field-label">Description</label>
                    <textarea name="description" rows="2" class="textarea" x-model="editing.description"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Type</label>
                        <select name="discount_type" class="select" x-model="editing.discount_type">
                            <option value="fixed">Fixed (₱)</option>
                            <option value="percent">Percent (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Value</label>
                        <input type="number" name="discount_value" step="0.01" min="0" class="input" x-model="editing.discount_value" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Min Spend</label>
                        <input type="number" name="min_spend" step="0.01" min="0" class="input" x-model="editing.min_spend">
                    </div>
                    <div>
                        <label class="field-label">Max Discount (percent)</label>
                        <input type="number" name="max_discount" step="0.01" min="0" class="input" x-model="editing.max_discount">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Valid From</label>
                        <input type="date" name="valid_from" class="input" x-model="editing.valid_from">
                    </div>
                    <div>
                        <label class="field-label">Valid Until</label>
                        <input type="date" name="valid_until" class="input" x-model="editing.valid_until">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Usage Limit</label>
                        <input type="number" name="usage_limit" min="1" class="input" x-model="editing.usage_limit">
                    </div>
                    <div>
                        <label class="field-label">Status</label>
                        <select name="status" class="select" x-model="editing.status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4 w-full">Save Changes</button>
        </form>
    </dialog>
</div>
@endsection

@push('scripts')
@php
    $voucherJson = $vouchers->keyBy('id')->map(fn ($v) => [
        'code' => $v->code,
        'name' => $v->name,
        'description' => $v->description,
        'discount_type' => $v->discount_type,
        'discount_value' => $v->discount_value,
        'min_spend' => $v->min_spend,
        'max_discount' => $v->max_discount,
        'valid_from' => $v->valid_from?->toDateString(),
        'valid_until' => $v->valid_until?->toDateString(),
        'usage_limit' => $v->usage_limit,
        'status' => $v->status,
    ])->toJson();
@endphp
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('editVoucher', () => ({
        editing: null,
        editAction: '',
        openEdit(id) {
            const v = {!! $voucherJson !!};
            this.editing = v[id];
            this.editAction = '{{ url('vouchers') }}/' + id;
            this.$refs.editModal.showModal();
        }
    }));
});
</script>
@endpush