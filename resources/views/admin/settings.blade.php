@extends('admin.layout')

@section('content')
@php
$s = fn ($key, $default = '') => e($settings->get($key, $default));
$checked = fn ($key, $default = '0') => ($settings->get($key, $default) === '1' || $settings->get($key, $default) === true) ? 'checked' : '';
$sections = [
    'general' => ['building', 'General'],
    'marketplace' => ['bag', 'Marketplace'],
    'orders' => ['box', 'Orders'],
    'payments' => ['card', 'Payments'],
    'delivery' => ['truck', 'Delivery'],
    'notifications' => ['bell', 'Notifications'],
    'security' => ['lock', 'Security'],
    'content' => ['mega', 'Announcements & Policies'],
];
@endphp
<div class="card">
    <h1 class="page-title">System Settings</h1>
    <p style="color:#6E6E73;margin-top:-8px;">Main configuration center for the Invoiz platform.</p>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
    <div class="alert alert-warn">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;">
        @foreach ($sections as $id => [$icon, $label])
        <a href="#section-{{ $id }}" style="padding:7px 15px;border-radius:999px;background:#F0EEE9;color:#374151;font-size:12.5px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><x-ui-icon :name="$icon" :size="14" />{{ $label }}</a>
        @endforeach
    </div>

    @php
    $inputStyle = 'width:100%;padding:9px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;background:#fff;';
    $labelStyle = 'display:block;margin-bottom:4px;font-weight:600;font-size:13px;';
    $cardStyle = 'background:#F7F6F2;padding:22px;border-radius:14px;border:1px solid #E8E6E0;';
    $saveBtn = 'padding:10px 26px;background:#16697A;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;';
    @endphp

    {{-- ============================ GENERAL ============================ --}}
    <h2 id="section-general" style="font-size:18px;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="building" :size="18" /> General</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" enctype="multipart/form-data" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="general" />
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Store Name</label>
                <input type="text" name="store_name" value="{{ $s('store_name', 'Invoiz') }}" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Contact Email</label>
                <input type="email" name="store_email" value="{{ $s('store_email') }}" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Contact Phone</label>
                <input type="text" name="store_phone" value="{{ $s('store_phone') }}" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Store Address</label>
                <input type="text" name="store_address" value="{{ $s('store_address') }}" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Currency</label>
                <select name="currency" style="{{ $inputStyle }}">
                    @foreach (['PHP' => 'PHP — Philippine Peso', 'USD' => 'USD — US Dollar', 'EUR' => 'EUR — Euro'] as $k => $label)
                    <option value="{{ $k }}" {{ $settings->get('currency') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Currency Symbol</label>
                <input type="text" name="currency_symbol" value="{{ $s('currency_symbol', '₱') }}" maxlength="3" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Timezone</label>
                <select name="timezone" style="{{ $inputStyle }}">
                    @foreach (['Asia/Manila', 'UTC', 'Asia/Singapore', 'America/New_York'] as $tz)
                    <option value="{{ $tz }}" {{ $settings->get('timezone') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Language</label>
                <select name="language" style="{{ $inputStyle }}">
                    @foreach (['English', 'Filipino', 'Spanish'] as $lang)
                    <option value="{{ $lang }}" {{ $settings->get('language') === $lang ? 'selected' : '' }}>{{ $lang }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="margin-top:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="width:64px;height:64px;border-radius:50%;border:2px solid #E8E6E0;overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                @if ($settings->get('store_logo'))
                <img src="{{ url('storage/'.$settings->get('store_logo')) }}" alt="logo" style="width:100%;height:100%;object-fit:cover;" />
                @else
                <img src="{{ asset('images/logo.png') }}" alt="logo" style="width:100%;height:100%;object-fit:cover;" />
                @endif
            </div>
            <div>
                <label style="{{ $labelStyle }}">Store Logo (PNG/JPG/SVG, max 2MB)</label>
                <input type="file" name="store_logo" accept="image/*" style="padding:6px;" />
            </div>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save General Settings</button></div>
    </form>

    {{-- ============================ MARKETPLACE ============================ --}}
    <h2 id="section-marketplace" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="bag" :size="18" /> Marketplace</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="marketplace" />
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Seller Registration</label>
                <select name="seller_registration" style="{{ $inputStyle }}">
                    <option value="open" {{ $settings->get('seller_registration') === 'open' ? 'selected' : '' }}>Open — anyone can apply</option>
                    <option value="closed" {{ $settings->get('seller_registration') === 'closed' ? 'selected' : '' }}>Closed — no new applications</option>
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Default Commission (%)</label>
                <input type="number" name="default_commission" value="{{ $s('default_commission', '10') }}" min="0" max="100" step="0.01" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Payout Minimum (₱)</label>
                <input type="number" name="payout_minimum" value="{{ $s('payout_minimum', '500') }}" min="0" step="0.01" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Payout Schedule</label>
                <select name="payout_schedule" style="{{ $inputStyle }}">
                    @foreach (['weekly' => 'Weekly', 'monthly' => 'Monthly', 'manual' => 'Manual'] as $k => $label)
                    <option value="{{ $k }}" {{ $settings->get('payout_schedule') === $k ? 'selected' : '' }}>{{ ucfirst($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="margin-top:16px;display:grid;gap:10px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_approve_sellers" value="1" {{ $checked('auto_approve_sellers') }} style="width:16px;height:16px;" />
                Auto-approve new seller registrations (skip manual review)
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="product_approval" value="1" {{ $checked('product_approval', '1') }} style="width:16px;height:16px;" />
                Require admin approval before new products go live
            </label>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Marketplace Settings</button></div>
    </form>

    {{-- ============================ ORDERS ============================ --}}
    <h2 id="section-orders" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="box" :size="18" /> Orders</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="orders" />
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Auto-Cancel Unpaid Orders After (hours)</label>
                <input type="number" name="order_auto_cancel_hours" value="{{ $s('order_auto_cancel_hours', '48') }}" min="1" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Return Window (days)</label>
                <input type="number" name="return_window_days" value="{{ $s('return_window_days', '7') }}" min="0" style="{{ $inputStyle }}" />
            </div>
        </div>
        <div style="margin-top:16px;display:grid;gap:10px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="allow_cancellations" value="1" {{ $checked('allow_cancellations', '1') }} style="width:16px;height:16px;" />
                Allow buyers to cancel orders
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="allow_returns" value="1" {{ $checked('allow_returns', '1') }} style="width:16px;height:16px;" />
                Allow product returns
            </label>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Order Settings</button></div>
    </form>

    {{-- ============================ PAYMENTS ============================ --}}
    <h2 id="section-payments" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="card" :size="18" /> Payments</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="payments" />
        <label style="{{ $labelStyle }}">Enabled Payment Methods</label>
        <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:16px;">
            @foreach (['pm_gcash' => 'GCash', 'pm_maya' => 'Maya', 'pm_card' => 'Credit / Debit Card', 'pm_cod' => 'Cash on Delivery'] as $key => $label)
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;background:#fff;border:1px solid #E8E6E0;border-radius:10px;padding:10px 14px;">
                <input type="checkbox" name="{{ $key }}" value="1" {{ $checked($key, '1') }} style="width:16px;height:16px;" />
                {{ $label }}
            </label>
            @endforeach
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Payment Gateway Mode</label>
                <select name="gateway_mode" style="{{ $inputStyle }}">
                    <option value="sandbox" {{ $settings->get('gateway_mode') === 'sandbox' ? 'selected' : '' }}>Sandbox (test mode)</option>
                    <option value="live" {{ $settings->get('gateway_mode') === 'live' ? 'selected' : '' }}>Live (production)</option>
                </select>
            </div>
            <div>
                <label style="{{ $labelStyle }}">Refund Window (days)</label>
                <input type="number" name="refund_window_days" value="{{ $s('refund_window_days', '14') }}" min="0" style="{{ $inputStyle }}" />
            </div>
        </div>
        <div style="margin-top:16px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_refund" value="1" {{ $checked('auto_refund') }} style="width:16px;height:16px;" />
                Automatically refund approved disputes
            </label>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Payment Settings</button></div>
    </form>

    {{-- ============================ DELIVERY ============================ --}}
    <h2 id="section-delivery" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="truck" :size="18" /> Delivery</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="delivery" />
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Base Delivery Fee (₱)</label>
                <input type="number" name="base_delivery_fee" value="{{ $s('base_delivery_fee', '45') }}" min="0" step="0.01" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Free Shipping Threshold (₱)</label>
                <input type="number" name="free_shipping_threshold" value="{{ $s('free_shipping_threshold', '1000') }}" min="0" step="0.01" style="{{ $inputStyle }}" />
            </div>
        </div>
        <label style="{{ $labelStyle }}margin-top:16px;">Delivery Zones (₱ fee per zone)</label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
            @foreach (['zone_manila' => 'Metro Manila', 'zone_luzon' => 'Luzon', 'zone_visayas' => 'Visayas', 'zone_mindanao' => 'Mindanao'] as $key => $label)
            <div>
                <label style="display:block;margin-bottom:4px;font-size:12px;font-weight:600;color:#6E6E73;">{{ $label }}</label>
                <input type="number" name="{{ $key }}" value="{{ $s($key) }}" min="0" step="0.01" style="{{ $inputStyle }}" />
            </div>
            @endforeach
        </div>
        <div style="margin-top:16px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_assign_rider" value="1" {{ $checked('auto_assign_rider', '1') }} style="width:16px;height:16px;" />
                Auto-assign available riders to paid orders
            </label>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Delivery Settings</button></div>
    </form>

    {{-- ============================ NOTIFICATIONS ============================ --}}
    <h2 id="section-notifications" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="bell" :size="18" /> Notifications</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="notifications" />
        <div style="display:grid;gap:10px;max-width:520px;margin-bottom:16px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="notif_email" value="1" {{ $checked('notif_email', '1') }} style="width:16px;height:16px;" />
                <x-ui-icon name="mail" :size="15" /> Email Notifications (order updates, approvals)
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="notif_sms" value="1" {{ $checked('notif_sms') }} style="width:16px;height:16px;" />
                <x-ui-icon name="phone" :size="15" /> SMS Notifications
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="notif_push" value="1" {{ $checked('notif_push') }} style="width:16px;height:16px;" />
                <x-ui-icon name="bell" :size="15" /> Push Notifications
            </label>
        </div>
        <div style="max-width:320px;">
            <label style="{{ $labelStyle }}">SMS Gateway</label>
            <select name="sms_gateway" style="{{ $inputStyle }}">
                @foreach (['none' => 'None', 'twilio' => 'Twilio', 'semaphore' => 'Semaphore (PH)', 'infobip' => 'Infobip'] as $k => $label)
                <option value="{{ $k }}" {{ $settings->get('sms_gateway') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Notification Settings</button></div>
    </form>

    {{-- ============================ SECURITY ============================ --}}
    <h2 id="section-security" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="lock" :size="18" /> Security</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="security" />
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
            <div>
                <label style="{{ $labelStyle }}">Admin Session Timeout (minutes)</label>
                <input type="number" name="session_timeout" value="{{ $s('session_timeout', '60') }}" min="5" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Minimum Password Length</label>
                <input type="number" name="password_min_length" value="{{ $s('password_min_length', '8') }}" min="6" max="64" style="{{ $inputStyle }}" />
            </div>
            <div>
                <label style="{{ $labelStyle }}">Max Login Attempts</label>
                <input type="number" name="max_login_attempts" value="{{ $s('max_login_attempts', '5') }}" min="1" style="{{ $inputStyle }}" />
            </div>
        </div>
        <div style="margin-top:16px;display:grid;gap:10px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="password_require_symbols" value="1" {{ $checked('password_require_symbols', '1') }} style="width:16px;height:16px;" />
                Require symbols & numbers in passwords
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="two_factor" value="1" {{ $checked('two_factor') }} style="width:16px;height:16px;" />
                Require Two-Factor Authentication (2FA) for admins
            </label>
        </div>
        <div style="margin-top:18px;"><button type="submit" style="{{ $saveBtn }}">Save Security Settings</button></div>
    </form>

    {{-- ============================ ANNOUNCEMENTS & POLICIES ============================ --}}
    <h2 id="section-content" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="mega" :size="18" /> Announcements & Policies</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <form method="POST" action="{{ url('/admin/settings/announcement') }}" style="background:#F0EEE9;padding:20px;border-radius:12px;">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Announcement Title</label>
                    <input type="text" name="title" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;" />
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Body</label>
                    <textarea name="body" rows="4" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;"></textarea>
                </div>
                <button type="submit" style="padding:10px 24px;background:#16697A;color:white;border:none;border-radius:8px;cursor:pointer;">Post Announcement</button>
            </form>

            <h3 style="font-size:15px;margin:20px 0 12px;">Recent Announcements</h3>
            @forelse($announcements as $ann)
            <div style="background:#F0EEE9;padding:14px;border-radius:10px;margin-bottom:10px;border-left:3px solid {{ $ann->is_active ? '#2E8B57' : '#6E6E73' }};">
                <strong>{{ $ann->title }}</strong>
                <p style="color:#6E6E73;margin:4px 0 0;font-size:13px;">{{ Str::limit($ann->body, 100) }}</p>
                <span style="font-size:12px;color:#6E6E73;">{{ $ann->created_at->diffForHumans() }} by {{ $ann->creator->name ?? 'Admin' }}</span>
            </div>
            @empty
            <p style="color:#6E6E73;">No announcements yet.</p>
            @endforelse
        </div>

        <div>
            <form method="POST" action="{{ url('/admin/settings/policy') }}" style="background:#F0EEE9;padding:20px;border-radius:12px;margin-bottom:20px;">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Policy Title</label>
                    <input type="text" name="title" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;" />
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Content</label>
                    <textarea name="content" rows="5" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;"></textarea>
                </div>
                <button type="submit" style="padding:10px 24px;background:#8b5cf6;color:white;border:none;border-radius:8px;cursor:pointer;">Create Policy</button>
            </form>

            @forelse($policies as $policy)
            <div style="background:#F0EEE9;padding:14px;border-radius:10px;margin-bottom:10px;border-left:3px solid {{ $policy->is_active ? '#8b5cf6' : '#6E6E73' }};">
                <strong>{{ $policy->title }}</strong>
                <span style="font-size:12px;color:#6E6E73;margin-left:8px;">v{{ $policy->version }}</span>
                <p style="color:#6E6E73;margin:4px 0 0;font-size:13px;">{{ Str::limit($policy->content, 100) }}</p>
            </div>
            @empty
            <p style="color:#6E6E73;">No policies created yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
