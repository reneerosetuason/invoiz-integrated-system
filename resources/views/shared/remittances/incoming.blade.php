@extends($layout)
@section('title','Remittances')
@section('content')
<div class="mx-auto max-w-5xl space-y-4">
    <h1 class="text-2xl font-bold">Remittances</h1>
    <p>Order and return-delivery payments. Confirm receipt after the released amount reaches you.</p>
    @include('shared.remittances.payout-account')
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <p>Awaiting confirmation: <b>{{ peso($totals['remitted']) }}</b> · Confirmed earnings: <b>{{ peso($totals['accepted']) }}</b></p>
    @foreach($rows as $payout)
    <article class="min-w-0 rounded-xl border border-gray-200 bg-white p-4">
        <h2 class="break-words font-bold">{{ $payout->source==='return'?'Return #'.$payout->return_id:$payout->order?->order_number }} · {{ $payout->roleLabel() }}</h2>
        <p>{{ peso($payout->amount) }} · {{ ucwords(str_replace('_',' ',$payout->status)) }}</p>
        @if($payout->bank_reference)<p class="break-all text-sm">Release / transfer reference: {{ $payout->bank_reference }}</p>@endif
        @if($payout->status==='remitted')
        <form method="post" action="{{ route('remittances.incoming.accept',['source'=>$payout->source,'id'=>$payout->id]) }}" class="mt-3 space-y-3">@csrf
            <label class="flex items-start gap-2"><input type="checkbox" name="confirm_received" value="1" required><span>I confirm I received this payment.</span></label>
            <button class="rounded-lg border border-teal px-4 py-2 font-semibold" type="submit">Confirm received</button>
        </form>
        @endif
    </article>
    @endforeach
    @if($rows->isEmpty())<p>No remittances assigned to you yet.</p>@endif
    {{ $rows->links() }}
</div>
@endsection
