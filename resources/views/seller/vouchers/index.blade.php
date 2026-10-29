@extends('seller.layouts.app')

@section('title', 'Vouchers & Discounts')

@section('content')
<div x-data="editVoucher()">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight">Vouchers &amp; Discounts</h2>
            <p class="mt-0.5 text-sm text-ink-light">Create and manage vouchers for your store.</p>
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
                                @if($voucher->discount_type === 'free_shipping')
                                    Free shipping
                                    <div class="text-[11px] font-normal text-ink-light">covers up to {{ peso($voucher->discount_value > 0 ? $voucher->discount_value : \App\Models\Order::SHIPPING_FEE_PER_SELLER) }} · you absorb it</div>
                                @else
                                    {{ $voucher->discount_type === 'percent' ? $voucher->discount_value.'%' : peso($voucher->discount_value) }}
                                @endif
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
                            <td><span class="badge badge-{{ $voucher->status_bool ? 'active' : 'inactive' }}">{{ $voucher->status_bool ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <form method="POST" action="{{ route('seller.vouchers.status', $voucher->id) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $voucher->status_bool ? 'inactive' : 'active' }}">
                                        <button type="submit" class="btn btn-outline btn-xs min-h-[44px]" aria-label="{{ $voucher->status_bool ? 'Deactivate' : 'Activate' }} {{ $voucher->name }}">
                                            {{ $voucher->status_bool ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <button type="button" @click="openEdit({{ $voucher->id }})" class="btn btn-outline btn-xs" title="Edit" aria-label="Edit {{ $voucher->name }}">
                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                    </button>
                                    <form method="POST" action="{{ route('seller.vouchers.destroy', $voucher->id) }}"
                                          onsubmit="return confirm('Delete this voucher?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-xs" title="Delete">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
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
            @include('admin.partials.pager', ['paginator' => $vouchers])
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
                <p x-show="editing.has_claims" x-cloak class="text-xs text-ink-light">Buyers have claimed or used this voucher. You can change its status; its offer details are locked.</p>
                <div>
                    <label class="field-label">Code</label>
                    <input type="text" class="input bg-white font-bold" :value="editing.code" disabled>
                </div>
                <div>
                    <label class="field-label">Name</label>
                    <input type="text" name="name" class="input" x-model="editing.name" :disabled="editing.has_claims" required>
                </div>
                <div>
                    <label class="field-label">Description</label>
                    <textarea name="description" rows="2" class="textarea" x-model="editing.description" :disabled="editing.has_claims"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Type</label>
                        <select name="discount_type" class="select" x-model="editing.discount_type" :disabled="editing.has_claims">
                            <option value="fixed">Fixed (₱)</option>
                            <option value="percent">Percent (%)</option>
                            <option value="free_shipping">Free shipping</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Value</label>
                        <input type="number" name="discount_value" step="0.01" min="0" class="input" x-model="editing.discount_value" :disabled="editing.has_claims" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Min Spend</label>
                        <input type="number" name="min_spend" step="0.01" min="0" class="input" x-model="editing.min_spend" :disabled="editing.has_claims">
                    </div>
                    <div>
                        <label class="field-label">Max Discount (percent)</label>
                        <input type="number" name="max_discount" step="0.01" min="0" class="input" x-model="editing.max_discount" :disabled="editing.has_claims">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Valid From</label>
                        <input type="date" name="valid_from" class="input" x-model="editing.valid_from" :disabled="editing.has_claims">
                    </div>
                    <div>
                        <label class="field-label">Valid Until</label>
                        <input type="date" name="valid_until" class="input" x-model="editing.valid_until" :disabled="editing.has_claims">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Usage Limit</label>
                        <input type="number" name="usage_limit" min="1" class="input" x-model="editing.usage_limit" :disabled="editing.has_claims">
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
    $voucherJson = $voucherMap->keyBy('id')->map(fn ($v) => [
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
        'status' => $v->status_bool ? 'active' : 'inactive',
        'has_claims' => $v->claims_count > 0 || $v->order_vouchers_count > 0 || $v->used_count > 0,
        'update_url' => route('seller.vouchers.update', $v->id),
    ])->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
@endphp
<script src="{{ asset('assets/seller/js/vouchers/index.js') }}?v={{ filemtime(public_path('assets/seller/js/vouchers/index.js')) }}" data-config="{{ json_encode([(json_decode($voucherJson, true))], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endpush
