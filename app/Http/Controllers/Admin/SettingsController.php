<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PlatformPolicy;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    private array $sections = [
        'general' => [
            'text' => ['store_name', 'store_email', 'store_phone', 'store_address', 'currency', 'currency_symbol', 'timezone', 'language'],
            'bool' => [],
        ],
        'marketplace' => [
            'text' => ['seller_registration', 'default_commission', 'payout_minimum', 'payout_schedule'],
            'bool' => ['auto_approve_sellers', 'product_approval'],
        ],
        'orders' => [
            'text' => ['order_auto_cancel_hours', 'return_window_days'],
            'bool' => ['allow_cancellations', 'allow_returns'],
        ],
        'payments' => [
            'text' => ['gateway_mode', 'refund_window_days'],
            'bool' => ['pm_gcash', 'pm_maya', 'pm_card', 'pm_cod', 'auto_refund'],
        ],
        'delivery' => [
            'text' => ['base_delivery_fee', 'free_shipping_threshold', 'zone_manila', 'zone_luzon', 'zone_visayas', 'zone_mindanao'],
            'bool' => ['auto_assign_rider'],
        ],
        'notifications' => [
            'text' => ['sms_gateway'],
            'bool' => ['notif_email', 'notif_sms', 'notif_push'],
        ],
        'security' => [
            'text' => ['session_timeout', 'password_min_length', 'max_login_attempts'],
            'bool' => ['password_require_symbols', 'two_factor'],
        ],
    ];

    public function index()
    {
        $announcements = Announcement::with('creator')->orderByDesc('created_at')->get();
        $policies = PlatformPolicy::orderByDesc('updated_at')->get();
        $settings = Schema::hasTable('settings') ? Setting::pluck('value', 'key') : collect();

        return view('admin.settings', compact('announcements', 'policies', 'settings'));
    }

    public function update(Request $request)
    {
        $section = $request->input('section');

        if (! isset($this->sections[$section])) {
            return redirect()->back()->withErrors(['Unknown settings section.']);
        }

        foreach ($this->sections[$section]['text'] as $field) {
            if ($request->has($field)) {
                Setting::set($field, (string) $request->input($field));
            }
        }

        foreach ($this->sections[$section]['bool'] as $field) {
            Setting::set($field, $request->boolean($field) ? '1' : '0');
        }

        if ($section === 'general' && $request->hasFile('store_logo')) {
            $request->validate([
                'store_logo' => 'image|mimes:png,jpg,jpeg,webp,svg|max:2048',
            ]);
            $path = $request->file('store_logo')->store('settings', 'public');
            Setting::set('store_logo', $path);
        }

        return redirect()->back()->with('status', ucfirst($section) . ' settings saved successfully.');
    }

    public function save(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        Announcement::create([
            'title' => $request->title,
            'body' => $request->body,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('status', 'Announcement posted successfully.');
    }

    public function updatePolicy(Request $request, PlatformPolicy $policy)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $policy->update([
            'title' => $request->title,
            'content' => $request->content,
            'version' => $policy->version + 1,
        ]);

        return redirect()->back()->with('status', 'Policy updated successfully.');
    }

    public function createPolicy(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $base = Str::slug($request->title) ?: 'policy';
        $slug = $base;
        $i = 2;
        while (PlatformPolicy::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        PlatformPolicy::create([
            'title' => $request->title,
            'content' => $request->content,
            'slug' => $slug,
            'is_active' => true,
            'version' => 1,
        ]);

        return redirect()->back()->with('status', 'Policy created successfully.');
    }
}
