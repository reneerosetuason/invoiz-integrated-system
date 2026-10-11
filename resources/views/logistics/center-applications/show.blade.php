<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('center-applications.index') }}"
               aria-label="Back to center applications"
               class="p-2 bg-white border border-gray-200 rounded-xl text-gray-500 hover:text-teal-dark hover:border-teal transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Center Application Review</h1>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if($application->status === 'pending')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">Pending</span>
            @elseif($application->status === 'approved')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Approved</span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">Rejected</span>
            @endif
            @if($application->submitted_via === 'mobile')
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200">Via Mobile</span>
            @endif
            @if($application->provisioned_at)
                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full bg-teal-light text-teal-dark">Center Provisioned</span>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="h-16 w-16 rounded-full bg-teal flex items-center justify-center text-2xl font-bold text-white flex-shrink-0">
                {{ strtoupper(substr($application->business_name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $application->business_name }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $application->email }} &middot; {{ $application->phone }}</p>
                <span class="mt-3 inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-teal-light text-teal-dark">Logistics Center Applicant</span>
            </div>
        </div>
    </div>

    {{-- Application details --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Application Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Business Name</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->business_name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Owner Name</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->owner_name }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->email }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Phone</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->phone }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Address</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->composedAddress() }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Submitted</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->created_at?->format('M d, Y h:i A') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Reviewed By</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->approver?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Reviewed At</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->reviewed_at?->format('M d, Y h:i A') ?? '—' }}</p>
            </div>
        </div>

        @if($application->notes && $application->status !== 'pending')
            <div class="mt-6 bg-gray-50 rounded-xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Review Note</p>
                <p class="text-sm font-medium text-gray-900">{{ $application->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Documents --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <h3 class="text-base font-bold text-gray-900 mb-5">Supporting Documents</h3>
        @if($application->supportingDocuments->isEmpty())
            <p class="text-sm text-gray-500">No documents uploaded.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($application->supportingDocuments as $document)
                    <div class="border border-gray-100 rounded-xl p-4 flex items-start gap-3 bg-gray-50/50">
                        <div class="h-10 w-10 rounded-lg bg-teal-light flex items-center justify-center flex-shrink-0">
                            @if($document->isImage())
                                <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            @else
                                <svg class="h-5 w-5 text-teal-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $document->typeLabel() }}</p>
                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ $document->original_filename }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $document->humanSize() }}</p>
                            @if($document->fileExists())
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <a href="{{ route('center-applications.documents.view', $document) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-semibold text-teal-dark hover:text-teal">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        View
                                    </a>
                                    <a href="{{ route('center-applications.documents.download', $document) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Download
                                    </a>
                                </div>
                            @else
                                <p class="text-xs text-red-500 mt-1">File missing on disk</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Review actions (pending only) --}}
    @if($application->status === 'pending')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Approve & provision --}}
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-100 p-6">
                <h3 class="text-base font-bold text-gray-900 mb-1">Approve &amp; Provision Center</h3>
                <p class="text-sm text-gray-500 mb-5">Creates the Logistics Center (set active) and a staff login for the owner. Leave the password blank to automatically generate a secure temporary password. After approval, the login credentials will be sent to the center's registered email address.</p>
                <form action="{{ route('center-applications.approve', $application) }}" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Initial Password (optional — auto-generated if blank)</label>
                                <x-password-input name="password" id="password" class="w-full" autocomplete="new-password" :error="$errors->has('password')" />
                                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                                <x-password-input name="password_confirmation" id="password_confirmation" class="w-full" autocomplete="new-password" />
                            </div>
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1.5">Approval Note (optional)</label>
                            <textarea name="notes" id="notes" rows="2"
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                Approve &amp; Create Center Account
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Reject --}}
            <div class="bg-white rounded-2xl shadow-sm border border-red-100 p-6 self-start">
                <h3 class="text-base font-bold text-gray-900 mb-1">Reject Application</h3>
                <p class="text-sm text-gray-500 mb-5">No center is created; the applicant is notified they were rejected.</p>
                <form action="{{ route('center-applications.reject', $application) }}" method="POST" x-data x-on:submit.prevent="if (confirm('Reject this application?')) $el.submit()">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1.5">Reason *</label>
                            <textarea name="reason" id="reason" rows="3" required
                                      class="w-full rounded-xl border-gray-300 focus:border-teal focus:ring-teal text-sm @error('reason') border-red-500 @enderror"></textarea>
                            @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                                Reject Application
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Resend login credentials (approved & provisioned centers only) --}}
    @if($application->status === 'approved' && $application->provisioned_at)
        <div class="bg-white rounded-2xl shadow-sm border border-teal-100 p-6">
            <h3 class="text-base font-bold text-gray-900 mb-1">Resend Login Credentials</h3>
            <p class="text-sm text-gray-500 mb-5">This will generate a new temporary password and send new login credentials to the center's registered email address (<span class="font-medium text-gray-700">{{ $application->email }}</span>). The previous temporary password will stop working.</p>
            <form action="{{ route('center-applications.resend-credentials', $application) }}" method="POST"
                  x-data x-on:submit.prevent="if (confirm('Resend login credentials? This will generate a new temporary password and send new login credentials to the registered center email address.')) $el.submit()">
                @csrf
                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 bg-teal hover:bg-teal-dark text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Resend Login Credentials
                    </button>
                </div>
            </form>
        </div>
    @endif
</x-app-layout>