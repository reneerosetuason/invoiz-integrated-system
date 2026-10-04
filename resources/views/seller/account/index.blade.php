@extends('layouts.seller')

@section('title', 'Account Management')

@section('content')
<div class="max-w-4xl">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Account Management</h2>
        <p class="mt-0.5 text-sm text-ink-light">Manage your seller profile, contact details, and password.</p>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Store summary --}}
        <div>
            <div class="card overflow-hidden">
                <div class="p-6" style="background:linear-gradient(135deg,#16697A,#0E4A57);">
                    <div class="flex items-center gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 text-xl font-extrabold text-white">
                            {{ strtoupper(substr(auth()->user()->seller->business_name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-lg font-extrabold text-white">{{ auth()->user()->seller->business_name }}</div>
                            <div class="text-xs text-white/70">{{ auth()->user()->seller->line_of_business }}</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="badge bg-white/20 !text-white"><span class="badge-dot" style="background:#4ADE80;"></span>Approved Seller</span>
                    </div>
                </div>
                <div class="space-y-3 p-6 text-sm">
                    <div class="flex justify-between"><span class="text-ink-light">Account ID</span><span class="font-semibold">#{{ auth()->id() }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-light">Products</span><span class="font-semibold">{{ auth()->user()->products()->count() }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-light">Member since</span><span class="font-semibold">{{ auth()->user()->created_at->format('M Y') }}</span></div>
                </div>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            {{-- Personal info --}}
            <div class="card p-6">
                <h3 class="mb-4 text-base font-bold">Personal Information</h3>
                <form method="POST" action="{{ route('seller.account.profile') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="field-label">First Name</label>
                        <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name) }}" class="input" required>
                    </div>
                    <div>
                        <label class="field-label">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name) }}" class="input" required>
                    </div>
                    <div>
                        <label class="field-label">Middle Initial</label>
                        <input type="text" name="middle_initial" value="{{ old('middle_initial', auth()->user()->middle_initial) }}" class="input" maxlength="10">
                    </div>
                    <div>
                        <label class="field-label">Sex</label>
                        <select name="sex" class="select">
                            @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" {{ old('sex', auth()->user()->sex) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="input" required>
                    </div>
                    <div>
                        <label class="field-label">Contact Number</label>
                        <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}" class="input" required>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>

            {{-- Store logo / shop picture --}}
            <div class="card p-6">
                <h3 class="mb-4 text-base font-bold">Shop Picture</h3>
                <div class="mb-4 flex items-center gap-4">
                    @if(auth()->user()->seller?->logo)
                        <img src="{{ asset('storage/'.auth()->user()->seller->logo) }}" alt="Shop logo" class="h-16 w-16 rounded-2xl object-cover ring-1 ring-gray-200">
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-light text-xl font-extrabold text-teal">
                            {{ strtoupper(substr(auth()->user()->seller->business_name ?? 'S', 0, 1)) }}
                        </div>
                    @endif
                    <p class="text-xs text-ink-light">Shown on your store page and across the marketplace instead of the letter avatar.</p>
                </div>
                <form method="POST" action="{{ route('seller.account.profile') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-4">
                    @csrf
                    <input type="hidden" name="first_name" value="{{ auth()->user()->first_name }}">
                    <input type="hidden" name="last_name" value="{{ auth()->user()->last_name }}">
                    <input type="hidden" name="phone" value="{{ auth()->user()->phone }}">
                    <input type="hidden" name="email" value="{{ auth()->user()->email }}">
                    <input type="hidden" name="sex" value="{{ auth()->user()->sex }}">
                    <div>
                        <label class="field-label">Upload shop picture (JPG/PNG, max 5MB)</label>
                        <input type="file" name="logo" accept="image/*" class="input">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Save Shop Picture</button>
                    </div>
                </form>
            </div>

            {{-- Password --}}
            <div class="card p-6">
                <h3 class="mb-4 text-base font-bold">Change Password</h3>
                <form method="POST" action="{{ route('seller.account.password') }}" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="field-label">Current Password</label>
                        <input type="password" name="current_password" class="input" required>
                    </div>
                    <div>
                        <label class="field-label">New Password</label>
                        <input type="password" name="new_password" class="input" required>
                    </div>
                    <div>
                        <label class="field-label">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" class="input" required>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection