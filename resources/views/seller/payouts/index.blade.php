@extends('seller.layouts.app')

@section('title', 'Payouts')

@section('content')
<div>
    <h2 class="text-xl font-extrabold tracking-tight">Payouts</h2>
    <p class="mt-0.5 text-sm text-ink-light">Request a withdrawal of your settled, available earnings. The minimum per request is {{ peso($minimum) }} and only one request can be pending at a time.</p>
</div>

@if(session('success'))
    <div class="card mt-4 border-successc bg-[#E8F5EE] px-4 py-3 text-sm font-semibold text-successc">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="card mt-4 border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid gap-4 sm:grid-cols-3 mt-4">
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Available balance</div>
        <div class="mt-1 text-2xl font-extrabold text-primary">{{ peso($available) }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Minimum per request</div>
        <div class="mt-1 text-2xl font-extrabold">{{ peso($minimum) }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Status</div>
        <div class="mt-1 text-sm font-bold {{ $hasPending ? 'text-amber-700' : 'text-successc' }}">
            {{ $hasPending ? 'Request pending review' : 'Ready to request' }}
        </div>
    </div>
</div>

<div class="card mt-4 px-4 py-4">
    <h3 class="text-sm font-bold">New payout request</h3>
    <form method="POST" action="{{ route('seller.payouts.request') }}" class="mt-3 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="block text-xs font-bold text-ink-light">Amount (₱)</label>
            <input type="number" name="amount" min="{{ $minimum }}" max="{{ $available }}" step="0.01"
                   value="{{ old('amount') }}" class="input mt-1 !w-40" required
                   {{ $hasPending || $available < $minimum ? 'disabled' : '' }}>
        </div>
        <div>
            <label class="block text-xs font-bold text-ink-light">Method</label>
            <select name="method" class="input mt-1 !w-48" {{ $hasPending || $available < $minimum ? 'disabled' : '' }}>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="GCash">GCash</option>
                <option value="PayMaya">PayMaya</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" {{ $hasPending || $available < $minimum ? 'disabled' : '' }}>Request payout</button>
    </form>
    @if($available < $minimum)
        <p class="mt-2 text-xs text-ink-light">You need at least {{ peso($minimum) }} in available earnings to request a payout.</p>
    @endif
</div>

<div class="card mt-4 overflow-hidden">
    <div class="px-4 pt-4 pb-2.5">
        <h3 class="text-sm font-bold">Request history</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-right">Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Processed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td>{{ $r->created_at?->format('M j, Y') }}</td>
                        <td class="text-right font-bold">{{ peso($r->amount) }}</td>
                        <td>{{ $r->method }}</td>
                        <td>
                            @if($r->status === 'approved')
                                <span class="badge bg-[#E8F5EE] !text-successc">Approved</span>
                            @elseif($r->status === 'rejected')
                                <span class="badge bg-red-50 !text-red-700">Rejected</span>
                            @else
                                <span class="badge badge-pending">Pending</span>
                            @endif
                        </td>
                        <td class="text-ink-light">{{ $r->processed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-light">No payout requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('admin.partials.pager', ['paginator' => $requests])
    </div>
</div>
@endsection
