@extends('buyer.layouts.website')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/buyer/css/profile.css') }}">
<div class="buyer-profile" style="max-width:680px;margin:20px auto;padding:20px;background:#fff;border:1px solid var(--border);border-radius:12px;line-height:1.6">
    <a href="{{ url('/profile') }}">Back to account settings</a>
    <h1 style="font-size:24px;margin:16px 0">Delete Account</h1>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <p>Deletion is not immediate. There is a mandatory 30-day grace period. You can still log in, view your account and orders, and cancel before the scheduled date.</p>
    <p>After 30 days, your account will be deactivated and archived once unresolved orders, deliveries, refunds, disputes and payments are settled. Only an administrator can restore it after that.</p>
    <p>{{ $retention_notice }}</p>
    @if($deletion_request && in_array($deletion_request['status'], ['pending', 'on_hold']))
        <h2 style="margin-top:20px">Account Deletion Scheduled</h2>
        <dl>
            <dt>Request date</dt><dd>{{ \Carbon\Carbon::parse($deletion_request['requested_at'])->timezone(config('app.timezone'))->format('M d, Y H:i T') }}</dd>
            <dt>Scheduled deletion date</dt><dd>{{ \Carbon\Carbon::parse($deletion_request['scheduled_archive_at'])->timezone(config('app.timezone'))->format('M d, Y H:i T') }}</dd>
            <dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $deletion_request['status'])) }}</dd>
            <dt>Remaining</dt><dd>{{ $deletion_request['remaining_days'] }} days</dd>
        </dl>
        <p>Your account is scheduled for deletion. You can cancel this request anytime before the scheduled deletion date. After deletion is completed, only an administrator can restore your account.</p>
        @if($deletion_request['hold_reason'])<p role="status">{{ $deletion_request['hold_reason'] }} We will automatically recheck these transactions.</p>@endif
        @if($deletion_request['can_cancel'])
            <form method="POST" action="{{ route('buyer.profile.cancel-deletion') }}" onsubmit="if(!confirm('Are you sure you want to cancel your account deletion request? Your account will remain active.'))return false;this.querySelector('button').disabled=true;">
                @csrf<input type="hidden" name="confirm_cancellation" value="1">
                <button type="submit" class="profile-button profile-button-primary">Cancel Account Deletion</button>
            </form>
        @endif
    @else
        @if($deletion_request)<p>Previous request: {{ ucfirst($deletion_request['status']) }}.</p>@endif
        <form method="POST" action="{{ route('buyer.profile.delete-request') }}" id="account-deletion-form" style="display:grid;gap:14px;margin-top:20px">
            @csrf
            <label for="reason">Reason for deletion</label>
            <select name="reason" id="reason" required class="profile-input">
                <option value="">Select a reason</option>
                @foreach($reasons as $reason)<option @selected(old('reason') === $reason)>{{ $reason }}</option>@endforeach
            </select>
            <label for="additional_feedback">Comments <span id="deletion-comments-required">(required for Other)</span></label>
            <textarea name="additional_feedback" id="additional_feedback" maxlength="2000" rows="3" class="profile-input">{{ old('additional_feedback') }}</textarea>
            <label for="current_password">Re-enter your current password</label>
            <input type="password" name="current_password" id="current_password" required autocomplete="current-password" class="profile-input">
            <a href="{{ route('auth.password.request') }}">Forgot your password? Recover it before requesting deletion.</a>
            <label><input type="checkbox" name="confirm_deletion" value="1" required> I understand that my account will be scheduled for deletion after 30 days and that only an administrator can restore it once deletion is finalized.</label>
            <button type="submit" class="profile-button profile-button-primary">Confirm Account Deletion</button>
            <p id="deletion-verification-error" role="alert"></p>
        </form>
        <script>
            (() => { const reason = document.getElementById('reason'); const comments = document.getElementById('additional_feedback');
                const sync = () => { comments.required = reason.value === 'Other (please specify).'; };
                reason.addEventListener('change', sync); sync();
                const form = document.getElementById('account-deletion-form');
                form.addEventListener('submit', async event => {
                    event.preventDefault(); const button = form.querySelector('button'); if(button.disabled) return;
                    button.disabled = true; const error = document.getElementById('deletion-verification-error'); error.textContent = '';
                    try {
                        const response = await fetch(@json(route('buyer.account-deletion.verify')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value }, body: new FormData(form) });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.errors?.current_password?.[0] || result.message || 'Verification failed.');
                        if(confirm('Are you sure you want to delete your INVOIZ account? Your account will be scheduled for deletion in 30 days.')) { form.submit(); return; }
                    } catch(failure) { error.textContent = failure.message; }
                    button.disabled = false;
                });
            })();
        </script>
    @endif
</div>
@endsection
