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
<style>
    .admin-settings{max-width:1040px;margin:0 auto;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;color:#19383E}
    .admin-settings .page-title{margin:0!important;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:28px;font-weight:560;letter-spacing:-.02em;line-height:1.15}
    .admin-settings>.settings-intro{margin:7px 0 18px!important;color:#5C7073!important;font-size:14px;line-height:1.5}
    .admin-settings .settings-nav{display:flex;gap:8px;margin:18px 0 27px;padding:2px 0 5px;overflow-x:auto;scrollbar-width:thin;scroll-snap-type:x proximity}
    .admin-settings .settings-nav-link{display:inline-flex;min-height:38px;flex:0 0 auto;align-items:center;gap:7px;padding:7px 13px;border:1px solid rgba(15,76,92,.14);border-radius:999px;background:#fff;color:#31565D;font-size:12.5px;font-weight:600;text-decoration:none;scroll-snap-align:start;transition:background-color .15s ease,border-color .15s ease,color .15s ease}
    .admin-settings .settings-nav-link:hover,.admin-settings .settings-nav-link:focus-visible{border-color:#86AEB1;background:#EAF3F2;color:#0E4A57}
    .admin-settings h2[id^="section-"]{display:flex;align-items:center;gap:9px;margin:28px 0 11px!important;scroll-margin-top:92px;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:21px!important;font-weight:560;line-height:1.2}
    .admin-settings #section-general{margin-top:0!important}
    .admin-settings form[style]{padding:20px 22px!important;border:1px solid rgba(15,76,92,.13)!important;border-radius:14px!important;background:#fff!important;color:#19383E}
    .admin-settings input:not([type="checkbox"]):not([type="file"]),.admin-settings select,.admin-settings textarea{width:100%!important;min-height:42px!important;padding:9px 12px!important;border:1px solid rgba(15,76,92,.18)!important;border-radius:9px!important;background:#fff!important;color:#19383E!important;font-family:inherit!important;font-size:13.5px!important;box-shadow:none}
    .admin-settings textarea{min-height:110px!important;resize:vertical}
    .admin-settings input:focus,.admin-settings select:focus,.admin-settings textarea:focus{border-color:#5A9298!important;outline:0;box-shadow:0 0 0 3px rgba(22,105,122,.1)!important}
    .admin-settings label{color:#36555B}
    .admin-settings input[type="checkbox"]{width:17px!important;height:17px!important;flex:0 0 17px;accent-color:#0E4A57}
    .admin-settings .settings-checks{display:grid;gap:9px!important;margin:16px 0 0!important}
    .admin-settings .settings-checks label{display:flex;align-items:center;gap:10px;padding:5px 0;color:#36555B;font-size:13px}
    .admin-settings .settings-checks--single{display:block!important}
    .admin-settings .settings-check-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:9px!important;margin-bottom:16px!important}
    .admin-settings .settings-check-option{min-height:44px;padding:10px 13px!important;border:1px solid rgba(15,76,92,.13)!important;border-radius:10px!important;background:#F7F9F8!important;color:#36555B}
    .admin-settings .settings-save{margin-top:18px}
    .admin-settings .settings-save button,.admin-settings .settings-content-form button{min-height:40px;padding:9px 18px!important;border:0!important;border-radius:9px!important;background:#0E4A57!important;color:#fff!important;font-family:inherit;font-size:13px;font-weight:650;cursor:pointer;transition:background-color .15s ease,transform .15s ease}
    .admin-settings .settings-save button:hover,.admin-settings .settings-content-form button:hover{background:#16697A!important;transform:translateY(-1px)}
    .admin-settings .settings-logo-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:16px}
    .admin-settings .settings-logo-preview{display:flex;width:64px;height:64px;flex:0 0 64px;align-items:center;justify-content:center;overflow:hidden;border:1px solid rgba(15,76,92,.18);border-radius:14px;background:#F7F9F8}
    .admin-settings .settings-logo-preview img{width:100%;height:100%;object-fit:contain}
    .admin-settings .settings-logo-control{min-width:220px;flex:1}
    .admin-settings .settings-logo-control label{display:block;margin-bottom:6px;color:#36555B;font-size:13px;font-weight:600}
    .admin-settings input[type="file"]{max-width:100%;padding:7px!important;border:1px solid rgba(15,76,92,.16);border-radius:9px;background:#F7F9F8;color:#496267;font-family:inherit;font-size:12px}
    .admin-settings .settings-content-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .admin-settings .settings-content-form{padding:18px!important;border:1px solid rgba(15,76,92,.13)!important;border-radius:14px!important;background:#fff!important}
    .admin-settings .settings-content-form label{display:block;margin:0 0 5px;color:#36555B;font-size:13px;font-weight:600}
    .admin-settings .settings-content-form>div{margin-bottom:12px}
    .admin-settings .settings-content-form textarea{width:100%!important}
    .admin-settings .settings-subhead{margin:20px 0 10px;color:#0E4A57;font-family:'Fraunces',Georgia,serif;font-size:18px;font-weight:560}
    .admin-settings .settings-content-record{position:relative;margin:0 0 9px;padding:13px 15px;border:1px solid rgba(15,76,92,.12);border-radius:11px;background:#fff}
    .admin-settings .settings-content-record strong{color:#19383E;font-size:13.5px}
    .admin-settings .settings-content-record p{margin:4px 0;color:#5C7073;font-size:13px;line-height:1.5}
    .admin-settings .settings-content-meta{color:#687B7E;font-size:12px}
    .admin-settings .settings-active-indicator{display:inline-block;width:8px;height:8px;margin-right:7px;border-radius:50%;background:#899698;vertical-align:1px}
    .admin-settings .settings-active-indicator.is-active{background:#4B8B69}
    .admin-settings .settings-section-empty{color:#687B7E;font-size:13px}
    .admin-settings :focus-visible{outline:2px solid #0E4A57;outline-offset:2px}
    @media(max-width:700px){
        .admin-settings .page-title{font-size:25px}
        .admin-settings .settings-nav{margin:15px 0 23px}
        .admin-settings form[style]{padding:17px 15px!important}
        .admin-settings .settings-content-grid{grid-template-columns:minmax(0,1fr);gap:12px}
    }
    @media(prefers-reduced-motion:reduce){.admin-settings *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
</style>
<main class="card admin-settings">
    <h1 class="page-title">System Settings</h1>
    <p class="settings-intro">Main configuration center for the Invoiz platform.</p>

    @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
    <div class="alert alert-warn">{{ $errors->first() }}</div>
    @endif

    <nav class="settings-nav" aria-label="Settings sections">
        @foreach ($sections as $id => [$icon, $label])
        <a href="#section-{{ $id }}" class="settings-nav-link"><x-ui-icon :name="$icon" :size="14" />{{ $label }}</a>
        @endforeach
    </nav>

    @php
    $inputStyle = 'width:100%;min-height:42px;padding:9px 12px;border:1px solid rgba(15,76,92,.18);border-radius:9px;box-sizing:border-box;background:#fff;color:#19383E;';
    $labelStyle = 'display:block;margin-bottom:5px;font-weight:600;font-size:13px;color:#36555B;';
    $cardStyle = 'background:#fff;padding:20px 22px;border-radius:14px;border:1px solid rgba(15,76,92,.13);';
    $saveBtn = 'min-height:40px;padding:9px 18px;background:#0E4A57;color:white;border:none;border-radius:9px;cursor:pointer;font-weight:650;';
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
            <div class="settings-logo-row">
                <div class="settings-logo-preview" data-photo-preview-target-key="admin-store-logo-preview">
                @if ($settings->get('store_logo'))
                <img src="{{ url('storage/'.$settings->get('store_logo')) }}" alt="logo" style="width:100%;height:100%;object-fit:cover;" />
                @else
                <img src="{{ asset('images/logo.png') }}" alt="logo" style="width:100%;height:100%;object-fit:cover;" />
                @endif
            </div>
            <div class="settings-logo-control">
                <label for="settings-store-logo">Store Logo (PNG/JPG/SVG, max 2MB)</label>
                <input id="settings-store-logo" type="file" name="store_logo" accept="image/*"
                       data-photo-editor data-photo-aspect="1" data-photo-max-bytes="2097152" data-photo-output-format="png"
                       data-photo-preview-key="admin-store-logo-preview" />
            </div>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save General Settings</button></div>
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
        <div class="settings-checks">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_approve_sellers" value="1" {{ $checked('auto_approve_sellers') }} style="width:16px;height:16px;" />
                Auto-approve new seller registrations (skip manual review)
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="product_approval" value="1" {{ $checked('product_approval', '1') }} style="width:16px;height:16px;" />
                Require admin approval before new products go live
            </label>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Marketplace Settings</button></div>
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
        <div class="settings-checks">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="allow_cancellations" value="1" {{ $checked('allow_cancellations', '1') }} style="width:16px;height:16px;" />
                Allow buyers to cancel orders
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="allow_returns" value="1" {{ $checked('allow_returns', '1') }} style="width:16px;height:16px;" />
                Allow product returns
            </label>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Order Settings</button></div>
    </form>

    {{-- ============================ PAYMENTS ============================ --}}
    <h2 id="section-payments" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="card" :size="18" /> Payments</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="payments" />
        <label style="{{ $labelStyle }}">Enabled Payment Methods</label>
        <div class="settings-check-options">
            @foreach (['pm_gcash' => 'GCash', 'pm_maya' => 'Maya', 'pm_card' => 'Credit / Debit Card', 'pm_cod' => 'Cash on Delivery'] as $key => $label)
            <label class="settings-check-option" style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
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
        <div class="settings-checks settings-checks--single">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_refund" value="1" {{ $checked('auto_refund') }} style="width:16px;height:16px;" />
                Automatically refund approved disputes
            </label>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Payment Settings</button></div>
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
        <div class="settings-checks settings-checks--single">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="auto_assign_rider" value="1" {{ $checked('auto_assign_rider', '1') }} style="width:16px;height:16px;" />
                Auto-assign available riders to paid orders
            </label>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Delivery Settings</button></div>
    </form>

    {{-- ============================ NOTIFICATIONS ============================ --}}
    <h2 id="section-notifications" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="bell" :size="18" /> Notifications</h2>
    <form method="POST" action="{{ url('/admin/settings/update') }}" style="{{ $cardStyle }}">
        @csrf
        <input type="hidden" name="section" value="notifications" />
        <div class="settings-checks">
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
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Notification Settings</button></div>
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
        <div class="settings-checks">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="password_require_symbols" value="1" {{ $checked('password_require_symbols', '1') }} style="width:16px;height:16px;" />
                Require symbols & numbers in passwords
            </label>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
                <input type="checkbox" name="two_factor" value="1" {{ $checked('two_factor') }} style="width:16px;height:16px;" />
                Require Two-Factor Authentication (2FA) for admins
            </label>
        </div>
        <div class="settings-save"><button type="submit" style="{{ $saveBtn }}">Save Security Settings</button></div>
    </form>

    {{-- ============================ ANNOUNCEMENTS & POLICIES ============================ --}}
    <h2 id="section-content" style="font-size:18px;margin:28px 0 12px;display:flex;align-items:center;gap:8px;"><x-ui-icon name="mega" :size="18" /> Announcements & Policies</h2>
    <div class="settings-content-grid">
        <div>
            <form method="POST" action="{{ url('/admin/settings/announcement') }}" class="settings-content-form">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Announcement Title</label>
                    <input type="text" name="title" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;" />
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Body</label>
                    <textarea name="body" rows="4" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;"></textarea>
                </div>
                <button type="submit">Post Announcement</button>
            </form>

            <h3 class="settings-subhead">Recent Announcements</h3>
            @forelse($announcements as $ann)
            <div class="settings-content-record">
                <span class="settings-active-indicator {{ $ann->is_active ? 'is-active' : 'is-inactive' }}" title="{{ $ann->is_active ? 'Active' : 'Inactive' }}" aria-label="{{ $ann->is_active ? 'Active' : 'Inactive' }}"></span>
                <strong>{{ $ann->title }}</strong>
                <p style="color:#6E6E73;margin:4px 0 0;font-size:13px;">{{ Str::limit($ann->body, 100) }}</p>
                <span class="settings-content-meta">{{ $ann->created_at->diffForHumans() }} by {{ $ann->creator->name ?? 'Admin' }}</span>
            </div>
            @empty
            <p class="settings-section-empty">No announcements yet.</p>
            @endforelse
        </div>

        <div>
            <form method="POST" action="{{ url('/admin/settings/policy') }}" class="settings-content-form">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Policy Title</label>
                    <input type="text" name="title" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;" />
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block;margin-bottom:4px;font-weight:600;">Content</label>
                    <textarea name="content" rows="5" required style="width:100%;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;"></textarea>
                </div>
                <button type="submit">Create Policy</button>
            </form>

            @forelse($policies as $policy)
            <div class="settings-content-record">
                <span class="settings-active-indicator {{ $policy->is_active ? 'is-active' : 'is-inactive' }}" title="{{ $policy->is_active ? 'Active' : 'Inactive' }}" aria-label="{{ $policy->is_active ? 'Active' : 'Inactive' }}"></span>
                <strong>{{ $policy->title }}</strong>
                <span style="font-size:12px;color:#6E6E73;margin-left:8px;">v{{ $policy->version }}</span>
                <p style="color:#6E6E73;margin:4px 0 0;font-size:13px;">{{ Str::limit($policy->content, 100) }}</p>
            </div>
            @empty
            <p class="settings-section-empty">No policies created yet.</p>
            @endforelse
        </div>
    </div>
</main>
@push('scripts')
    @include('partials.seller-photo-editor')
@endpush
@endsection
