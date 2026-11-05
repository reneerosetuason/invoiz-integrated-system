@extends('admin.layout')
@section('content')
<div class="admin-deletion-detail max-w-3xl bg-white border rounded-lg p-6">
<a href="{{ route('admin.account-deletions.index') }}" class="btn btn-neutral">Back to deletion requests</a>
<h1 class="text-2xl font-bold my-4">Deletion request #{{ $deletion->id }}</h1>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<dl class="space-y-2">
    <dt>Buyer</dt><dd>{{ $deletion->user->name }} ({{ $deletion->user->email }})</dd>
    <dt>Reason</dt><dd>{{ $deletion->reason }}</dd><dt>Comments</dt><dd class="whitespace-pre-wrap">{{ $deletion->additional_feedback ?: 'None' }}</dd>
    <dt>Submitted</dt><dd>{{ $deletion->requested_at->toDayDateTimeString() }}</dd>
    <dt>Scheduled deletion</dt><dd>{{ $deletion->scheduled_archive_at->toDayDateTimeString() }}</dd>
    <dt>Status</dt><dd>{{ ucwords(str_replace('_',' ', $deletion->status)) }}</dd>
    <dt>Archived</dt><dd>{{ $deletion->archived_at?->toDayDateTimeString() ?? 'Not archived' }}</dd>
    <dt>Cancelled</dt><dd>{{ $deletion->cancelled_at?->toDayDateTimeString() ?? 'Not cancelled' }}</dd>
    <dt>Restored</dt><dd>{{ $deletion->restored_at?->toDayDateTimeString() ?? 'Not restored' }}@if($deletion->restored_by) by administrator #{{ $deletion->restored_by }}@endif</dd>
</dl>
<h2 class="font-bold mt-5">Transaction blockers</h2>
<ul>@forelse($blockers as $blocker)<li>{{ $blocker }}</li>@empty<li>No outstanding transactions detected.</li>@endforelse</ul>
@if($deletion->hold_reason)<p>{{ $deletion->hold_reason }}</p>@endif
<h2 class="font-bold mt-5">Audit history</h2>
<ul>@foreach($history as $event)<li>{{ $event->created_at->toDayDateTimeString() }}: {{ $event->meta['message'] }} {{ $event->meta['hold_reason'] ?? '' }} ({{ $event->user_id ? 'user #'.$event->user_id : 'system' }})</li>@endforeach</ul>
@if($deletion->status === 'completed' && ! $deletion->restored_at)
<form method="POST" action="{{ route('admin.account-deletions.restore', $deletion) }}" class="grid gap-3 mt-5" onsubmit="if(!confirm('Are you sure you want to restore this buyer account? The buyer will regain access to INVOIZ.'))return false;this.querySelector('button').disabled=true;">
    @csrf
    <label><input type="checkbox" name="identity_verified" value="1" required> Support has verified the buyer's identity and restoration request.</label>
    <label>Your administrator password <input name="current_password" type="password" autocomplete="current-password" required class="border rounded p-2"></label>
    <label><input type="checkbox" name="confirm_restore" value="1" required> Confirm restoration of this buyer account.</label>
    <button class="btn btn-primary" type="submit">Restore Account</button>
</form>
@endif
</div>
@endsection
