<x-app-layout>
    @php
        $statusClasses = match ($changeRequest->status) {
            'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'rejected' => 'bg-red-50 text-red-700 ring-red-200',
            default => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('document-change-requests.index') }}" class="lw-back" aria-label="Back to list">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Document Change Review</h1>
                <p class="text-sm text-gray-500 mt-1">Submitted {{ $changeRequest->created_at?->format('M d, Y g:i A') }}</p>
            </div>
        </div>
        <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-full ring-1 ring-inset {{ $statusClasses }}">
            {{ $changeRequest->statusLabel() }}
        </span>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            {{-- Rider account --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-bold text-gray-900 mb-4">Rider Account</h2>
                <div class="flex flex-col sm:flex-row sm:items-center gap-5">
                    <div class="h-14 w-14 rounded-full bg-teal flex items-center justify-center text-xl font-bold text-white flex-shrink-0">
                        {{ strtoupper(substr($rider?->name ?? '?', 0, 1)) }}
                    </div>
                    <div class="flex-1">
                        <p class="text-lg font-bold text-gray-900">{{ $rider?->name ?? 'Deleted rider' }}</p>
                        <p class="text-sm text-gray-500">{{ $rider?->email }} &middot; {{ $rider?->phone }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Hub</p>
                            <p class="font-medium text-gray-800">{{ $rider?->logisticsCenter?->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400">Account</p>
                            <p class="font-medium text-gray-800">{{ ucfirst($rider?->status ?? 'unknown') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Comparison --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Current official --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-gray-900">Current Official Document</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-teal-light text-teal-dark">Approved</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900">{{ $changeRequest->typeLabel() }}</p>

                    @if($currentDocument)
                        <p class="text-xs text-gray-500 mt-1 truncate">{{ $currentDocument->original_filename }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Uploaded {{ $currentDocument->created_at?->format('M d, Y') }} &middot; {{ $currentDocument->humanSize() }}</p>

                        @if($isLicense)
                            <div class="mt-4 rounded-xl bg-gray-50 border border-gray-100 p-3">
                                <p class="text-xs uppercase tracking-wide text-gray-400">License Expiry</p>
                                <p class="text-sm font-bold {{ $rider?->licenseExpired() ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $rider?->license_expiry?->format('M d, Y') ?? 'Not recorded' }}
                                </p>
                            </div>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-3">
                            @if($currentDocument->fileExists())
                                <a href="{{ route('rider-applications.documents.view', $currentDocument) }}" target="_blank"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-teal-dark hover:text-teal">View</a>
                                <a href="{{ route('rider-applications.documents.download', $currentDocument) }}"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900">Download</a>
                            @else
                                <span class="text-xs text-red-600">File missing on disk</span>
                            @endif
                        </div>
                    @else
                        <p class="mt-3 text-sm text-gray-500">No current document of this type is on file. Approval will add the proposed file as the official document.</p>
                    @endif
                </div>

                {{-- Proposed --}}
                <div class="bg-white rounded-2xl shadow-sm border border-amber-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-gray-900">Proposed Replacement</h2>
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">For review</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900">{{ $changeRequest->typeLabel() }}</p>
                    <p class="text-xs text-gray-500 mt-1 truncate">{{ $changeRequest->original_filename }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $changeRequest->humanSize() }} &middot; submitted {{ $changeRequest->created_at?->format('M d, Y g:i A') }}</p>

                    @if($isLicense)
                        <div class="mt-4 rounded-xl bg-amber-50/60 border border-amber-200 p-3">
                            <p class="text-xs uppercase tracking-wide text-amber-700">New License Expiry</p>
                            <p class="text-sm font-bold text-amber-800">{{ $changeRequest->proposed_expiry?->format('M d, Y') ?? 'Not provided' }}</p>
                        </div>
                    @endif

                    @if($changeRequest->reason)
                        <div class="mt-4">
                            <p class="text-xs uppercase tracking-wide text-gray-400">Rider's Reason</p>
                            <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $changeRequest->reason }}</p>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-3">
                        @if($changeRequest->fileExists())
                            <a href="{{ route('document-change-requests.documents.view', [$changeRequest, 'proposed']) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-teal-dark hover:text-teal">View</a>
                            <a href="{{ route('document-change-requests.documents.download', [$changeRequest, 'proposed']) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900">Download</a>
                        @else
                            <span class="text-xs text-red-600">File missing on disk</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Preserved previous file (approved requests) --}}
            @if($changeRequest->status === 'approved' && $previousDocument)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-1">Previous Official Document (History)</h2>
                    <p class="text-sm text-gray-500 mb-4">The file that was official before this approval. It is preserved and never deleted.</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $previousDocument->original_filename }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $previousDocument->humanSize() }}
                        @if($isLicense && $changeRequest->previous_expiry)
                            &middot; expired {{ $changeRequest->previous_expiry->format('M d, Y') }}
                        @endif
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if($previousDocument->fileExists())
                            <a href="{{ route('document-change-requests.documents.view', [$changeRequest, 'previous']) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-teal-dark hover:text-teal">View</a>
                            <a href="{{ route('document-change-requests.documents.download', [$changeRequest, 'previous']) }}"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900">Download</a>
                        @else
                            <span class="text-xs text-red-600">File missing on disk</span>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Pending decision --}}
            @if($changeRequest->status === 'pending')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Approve Replacement</h2>
                        <p class="text-sm text-gray-500 mb-5">
                            The proposed file becomes the official {{ strtolower($changeRequest->typeLabel()) }}.
                            @if($isLicense)
                                The rider's license expiry becomes <strong>{{ $changeRequest->proposed_expiry?->format('M d, Y') }}</strong>.
                            @endif
                            The previous file stays in history.
                        </p>
                        <form action="{{ route('document-change-requests.approve', $changeRequest) }}" method="POST" x-data
                              data-confirm-message="Approve this replacement? The current official document will be replaced and cannot be reverted from this screen.">
                            @csrf
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                    Approve &amp; Update Official Document
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6 self-start">
                        <h2 class="text-base font-bold text-gray-900 mb-1">Reject Request</h2>
                        <p class="text-sm text-gray-500 mb-5">The current official document and expiry stay unchanged. The rider sees your reason and can submit a corrected document.</p>
                        <form action="{{ route('document-change-requests.reject', $changeRequest) }}" method="POST" x-data
                              data-confirm-message="Reject this document change request?">
                            @csrf
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1.5">Reason *</label>
                            <textarea name="reason" id="reason" rows="3" required minlength="5" maxlength="1000"
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                            @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <div class="flex justify-end mt-4">
                                <button type="submit" class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                    Reject Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4">Review Decision</h2>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">Decision</dt>
                            <dd class="mt-1 font-semibold {{ $changeRequest->status === 'approved' ? 'text-emerald-700' : 'text-red-700' }}">{{ $changeRequest->statusLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">Reviewed By</dt>
                            <dd class="mt-1 font-semibold text-gray-800">{{ $changeRequest->reviewer?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">Reviewed At</dt>
                            <dd class="mt-1 text-gray-700">{{ $changeRequest->reviewed_at?->format('M d, Y g:i A') ?? '—' }}</dd>
                        </div>
                        @if($changeRequest->status === 'rejected')
                            <div class="sm:col-span-2">
                                <dt class="text-xs uppercase tracking-wide text-gray-400">Rejection Reason</dt>
                                <dd class="mt-1 text-gray-700 whitespace-pre-line">{{ $changeRequest->rejection_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            @if($isLicense)
                <div class="bg-white rounded-2xl shadow-sm border {{ $licenseStatus === 'expired' ? 'border-red-200' : 'border-amber-200' }} p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-3">License &amp; Eligibility</h2>
                    @if($licenseStatus === 'expired')
                        <p class="text-sm font-semibold text-red-700">Expired {{ $rider?->license_expiry?->format('M d, Y') }}</p>
                        <p class="text-sm text-gray-600 mt-2">The rider cannot receive new delivery assignments. A pending request does <strong>not</strong> restore eligibility.</p>
                    @elseif($licenseStatus === 'expiring')
                        <p class="text-sm font-semibold text-amber-700">Expiring {{ $rider?->license_expiry?->format('M d, Y') }}</p>
                        <p class="text-sm text-gray-600 mt-2">Warn the rider to renew before this date.</p>
                    @elseif($licenseStatus === 'missing')
                        <p class="text-sm font-semibold text-gray-700">No expiry recorded</p>
                    @else
                        <p class="text-sm font-semibold text-emerald-700">Valid until {{ $rider?->license_expiry?->format('M d, Y') }}</p>
                    @endif
                    @if($licenseBlockReason)
                        <p class="mt-3 text-xs text-gray-500"><span class="font-semibold">Current block:</span> {{ $licenseBlockReason }}</p>
                    @endif
                    @if($rider?->status === 'inactive')
                        <p class="mt-3 rounded-lg bg-gray-50 border border-gray-200 p-2.5 text-xs text-gray-600">This account is inactive. Approval updates the document but does <strong>not</strong> reactivate the rider; use Rider Activation when it is appropriate.</p>
                    @endif
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-bold text-gray-900 mb-3">Request Details</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Document Type</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $changeRequest->typeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Submitted</dt>
                        <dd class="mt-0.5 text-gray-700">{{ $changeRequest->created_at?->format('M d, Y g:i A') }}</dd>
                    </div>
                    @if($changeRequest->reason)
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-gray-400">Rider's Reason</dt>
                            <dd class="mt-0.5 text-gray-700 whitespace-pre-line">{{ $changeRequest->reason }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-gray-400">Reference</dt>
                        <dd class="mt-0.5 font-mono text-gray-700">DCR-{{ str_pad((string) $changeRequest->id, 5, '0', STR_PAD_LEFT) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-base font-bold text-gray-900 mb-3">Request History</h2>
                <p class="text-xs text-gray-500 mb-4">Other changes for this rider and document type.</p>
                @forelse($history as $entry)
                    <div class="py-3 border-b border-gray-100 last:border-0">
                        <div class="flex items-center justify-between gap-2">
                            <a href="{{ route('document-change-requests.show', $entry) }}" class="text-sm font-semibold text-teal-dark hover:text-teal">DCR-{{ str_pad((string) $entry->id, 5, '0', STR_PAD_LEFT) }}</a>
                            <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full
                                {{ $entry->status === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($entry->status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') }}">
                                {{ $entry->statusLabel() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $entry->created_at?->format('M d, Y g:i A') }}</p>
                        @if($entry->rejection_reason)
                            <p class="text-xs text-gray-600 mt-1">{{ $entry->rejection_reason }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No earlier requests.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
