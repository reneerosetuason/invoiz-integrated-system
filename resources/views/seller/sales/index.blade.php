@extends('layouts.seller')

@section('title', 'Store Sales')

@section('content')
<div x-data="salesPage()">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight">Store Sales</h2>
            <p class="mt-0.5 text-sm text-ink-light">Run a timed discount across a set of your products. Only one sale can run at a time.</p>
        </div>
        @if($activeSale)
            <span class="badge badge-active">Sale running: {{ $activeSale->name }}</span>
        @else
            <button type="button" @click="$refs.createModal.showModal()" class="btn btn-primary">
                <x-icon name="plus" class="h-4 w-4" /> Create Sale
            </button>
        @endif
    </div>

    @if($activeSale)
        <div class="card mt-4 border-l-4 border-l-primary p-4 text-sm">
            <b>{{ $activeSale->name }}</b> is currently running
            @if($activeSale->ends_at) until {{ $activeSale->ends_at->format('M j, Y g:ia') }}@endif.
            End it before starting a new sale.
        </div>
    @endif

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Window</th>
                        <th class="text-center">Products</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr>
                            <td class="font-semibold">{{ $sale->name }}</td>
                            <td class="font-semibold">
                                {{ $sale->discount_type === 'percent' ? rtrim(rtrim(number_format($sale->discount_value, 2), '0'), '.').'% off' : peso($sale->discount_value).' off' }}
                            </td>
                            <td class="text-xs">
                                {{ $sale->starts_at?->format('M j, Y g:ia') ?? 'Now' }} → {{ $sale->ends_at?->format('M j, Y g:ia') ?? 'No end' }}
                            </td>
                            <td class="text-center">{{ $sale->items->count() }}</td>
                            <td>
                                @if($sale->isRunning())
                                    <span class="badge badge-active">Running</span>
                                @else
                                    <span class="badge badge-{{ $sale->status }}">{{ ucfirst($sale->status) }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button @click="openEdit({{ $sale->id }})" class="btn btn-outline btn-xs" title="Edit">
                                        <x-icon name="edit" class="h-3.5 w-3.5" />
                                    </button>
                                    <form method="POST" action="{{ route('seller.sales.destroy', $sale->id) }}" onsubmit="return confirm('Delete this sale?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger btn-xs" title="Delete"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-sm text-ink-light">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $sales->links('admin.partials.pager') }}</div>

    {{-- Create modal --}}
    <dialog x-ref="createModal" class="rounded-2xl p-0 backdrop:bg-black/40 w-full max-w-xl">
        <form method="POST" action="{{ route('seller.sales.store') }}" class="p-6">
            @csrf
            <h3 class="mb-4 text-base font-bold">Create Sale</h3>
            @include('seller.sales._form', ['sale' => null])
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="$refs.createModal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Sale</button>
            </div>
        </form>
    </dialog>

    {{-- Edit modal --}}
    <dialog x-ref="editModal" class="rounded-2xl p-0 backdrop:bg-black/40 w-full max-w-xl">
        <form method="POST" :action="'{{ url('seller/sales') }}/' + form.id" class="p-6">
            @csrf
            <h3 class="mb-4 text-base font-bold">Edit Sale</h3>
            @include('seller.sales._form', ['sale' => null, 'edit' => true])
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="$refs.editModal.close()" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </dialog>
</div>
@endsection

@push('scripts')
<script>
function salesPage() {
    return {
        form: { id: null, name: '', discount_type: 'percent', discount_value: '', starts_at: '', ends_at: '', status: 'active', products: [] },
        saleMap: {{ \Illuminate\Support\Js::from($sales->getCollection()->mapWithKeys(fn($s) => [$s->id => [
            'id' => $s->id,
            'name' => $s->name,
            'discount_type' => $s->discount_type,
            'discount_value' => $s->discount_value,
            'starts_at' => $s->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $s->ends_at?->format('Y-m-d\TH:i'),
            'status' => $s->status,
            'products' => $s->items->pluck('product_id')->values(),
        ]])) }},
        openEdit(id) {
            const s = this.saleMap[id];
            if (!s) return;
            this.form = JSON.parse(JSON.stringify(s));
            this.$refs.editModal.showModal();
        }
    }
}
</script>
@endpush
