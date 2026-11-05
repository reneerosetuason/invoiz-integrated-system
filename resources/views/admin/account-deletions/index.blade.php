@extends('admin.layout')
@section('content')
<h1 class="text-2xl font-bold mb-4">Account Deletion Requests</h1>
<form method="GET" class="flex flex-wrap gap-3 mb-4">
    <input name="search" value="{{ request('search') }}" placeholder="Buyer name, email or request ID" aria-label="Search deletion requests" class="border rounded p-2">
    <select name="status" aria-label="Request status" class="border rounded p-2">
        <option value="">All requests</option>
        @foreach(['pending','on_hold','cancelled','completed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_',' ', $status)) }}</option>@endforeach
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
</form>
<div class="overflow-x-auto bg-white rounded-lg border">
<table class="w-full text-sm text-left">
    <thead><tr>@foreach(['ID','Buyer','Email','Reason','Request date','Scheduled deletion','Status','Actions'] as $label)<th class="p-3">{{ $label }}</th>@endforeach</tr></thead>
    <tbody>@forelse($requests as $record)<tr class="border-t">
        <td class="p-3">{{ $record->id }}</td><td class="p-3">{{ $record->user->name }}</td><td class="p-3">{{ $record->user->email }}</td>
        <td class="p-3">{{ $record->reason }}</td><td class="p-3">{{ $record->requested_at->format('M d, Y') }}</td><td class="p-3">{{ $record->scheduled_archive_at->format('M d, Y') }}</td>
        <td class="p-3">{{ ucwords(str_replace('_',' ', $record->status)) }}@if($record->restored_at) (Restored)@endif</td>
        <td class="p-3"><a href="{{ route('admin.account-deletions.show', $record) }}" class="underline">View details / Restore</a></td>
    </tr>@empty<tr><td colspan="8" class="p-4">No account deletion requests.</td></tr>@endforelse</tbody>
</table></div>
{{ $requests->links() }}
@endsection
