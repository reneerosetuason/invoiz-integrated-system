<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Message;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;
use App\Models\PaymentDispute;
use App\Models\CommissionRate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function seedData(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('is_admin', true)->firstOrFail();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->seedData();
        $admin = $this->admin();

        foreach ([
            '/admin/dashboard',
            '/admin/sellers',
            '/admin/sellers?status=suspended',
            '/admin/buyers',
            '/admin/riders',
            '/admin/products',
            '/admin/categories',
            '/admin/orders',
            '/admin/payments',
            '/admin/payments?status=successful',
            '/admin/commission',
            '/admin/complaints',
            '/admin/manage-accounts',
            '/admin/settings',
            '/admin/chat',
            '/admin/reports?type=sales&period=daily',
            '/admin/reports?type=sales&period=weekly',
            '/admin/reports?type=sales&period=monthly',
            '/admin/reports?type=sales&period=yearly',
            '/admin/reports?type=customers',
            '/admin/reports?type=sellers',
            '/admin/reports?type=products',
            '/admin/reports?type=financial',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }
    }

    public function test_exports_download(): void
    {
        $this->seedData();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/reports/export?type=sales&format=csv')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($admin)
            ->get('/admin/reports/export?type=financial&format=excel')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.ms-excel');

        $this->actingAs($admin)
            ->get('/admin/reports/print?type=products')
            ->assertStatus(200)
            ->assertSee('Print');
    }

    public function test_payout_can_be_marked_paid(): void
    {
        $this->seedData();
        $payout = SellerPayout::where('status', 'pending')->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/admin/payments/payouts/{$payout->id}/mark-paid")
            ->assertRedirect();

        $this->assertEquals('paid', $payout->fresh()->status);
        $this->assertNotNull($payout->fresh()->paid_at);
    }

    public function test_withdrawal_approval_creates_payout(): void
    {
        $this->seedData();
        $w = WithdrawalRequest::where('status', 'pending')->firstOrFail();
        $before = SellerPayout::count();

        $this->actingAs($this->admin())
            ->post("/admin/payments/withdrawals/{$w->id}/approve")
            ->assertRedirect();

        $this->assertEquals('approved', $w->fresh()->status);
        $this->assertEquals($before + 1, SellerPayout::count());
    }

    public function test_dispute_refund_updates_order_and_transaction(): void
    {
        $this->seedData();
        $dispute = PaymentDispute::where('status', 'open')->firstOrFail();
        $order = $dispute->order;
        $order->payment_status = 'paid';
        $order->save();

        $this->actingAs($this->admin())
            ->post("/admin/payments/disputes/{$dispute->id}/resolve", ['resolution' => 'refund_buyer'])
            ->assertRedirect();

        $this->assertEquals('resolved', $dispute->fresh()->status);
        $this->assertEquals('refunded', $order->fresh()->payment_status);
    }

    public function test_commission_rate_can_be_updated(): void
    {
        $this->seedData();
        $rate = CommissionRate::whereNotNull('category_id')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/admin/commission/rates', ['rate_id' => $rate->id, 'rate' => 9.5])
            ->assertRedirect();

        $this->assertEquals(9.5, $rate->fresh()->rate);
    }

    public function test_category_archive_and_restore(): void
    {
        $this->seedData();
        $cat = Category::whereNull('deleted_at')->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/admin/categories/{$cat->id}/archive")
            ->assertRedirect();
        $this->assertNotNull($cat->fresh()->deleted_at);

        $this->actingAs($this->admin())
            ->post("/admin/categories/{$cat->id}/restore")
            ->assertRedirect();
        $this->assertNull($cat->fresh()->deleted_at);
    }

    public function test_seller_and_buyer_chat_persists_to_database(): void
    {
        $this->seedData();
        $sellerUser = User::where('role', 'seller')->firstOrFail();
        $buyer = User::where('role', 'buyer')->firstOrFail();
        $before = Message::count();

        $this->actingAs($sellerUser)
            ->post('/seller/chat/send', ['receiver_id' => $buyer->id, 'body' => 'Hello buyer!'])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post('/buyer/chat/send', ['receiver_id' => $sellerUser->id, 'body' => 'Hello seller!'])
            ->assertRedirect();

        $this->assertEquals($before + 2, Message::count());
        $this->assertDatabaseHas('messages', ['sender_id' => $sellerUser->id, 'body' => 'Hello buyer!']);
        $this->assertDatabaseHas('messages', ['sender_id' => $buyer->id, 'body' => 'Hello seller!']);

        $this->actingAs($buyer)
            ->get('/buyer/chat?with=' . $sellerUser->id)
            ->assertStatus(200)
            ->assertSee('Hello buyer!');

        $this->actingAs($this->admin())
            ->get('/admin/chat?user_id=' . $sellerUser->id)
            ->assertStatus(200);
    }

    public function test_order_detail_renders_with_items(): void
    {
        $this->seedData();
        $order = Order::firstOrFail();

        $this->actingAs($this->admin())
            ->get("/admin/orders/{$order->id}")
            ->assertStatus(200)
            ->assertSee($order->order_number);
    }

    public function test_seeded_counts_are_correct(): void
    {
        $this->seedData();
        $this->assertEquals(8, Seller::count());
        $this->assertGreaterThanOrEqual(50, Product::count());
        $this->assertGreaterThanOrEqual(21, Category::count());
        $this->assertGreaterThanOrEqual(15, Order::count());
    }

    public function test_suspended_seller_is_blocked_from_seller_center(): void
    {
        $this->seedData();
        $sellerUser = User::where('role', 'seller')->firstOrFail();
        $sellerUser->account_status = 'suspended';
        $sellerUser->save();

        $this->actingAs($sellerUser)
            ->get('/seller/dashboard')
            ->assertRedirect('/seller/login');
    }

    public function test_seller_suspend_and_reinstate(): void
    {
        $this->seedData();
        $seller = Seller::where('status', 'approved')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/suspend")
            ->assertRedirect();
        $this->assertEquals('suspended', $seller->fresh()->status);
        $this->assertEquals('suspended', $seller->user->fresh()->account_status);

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/reinstate")
            ->assertRedirect();
        $this->assertEquals('approved', $seller->fresh()->status);
        $this->assertEquals('active', $seller->user->fresh()->account_status);
    }

    public function test_rejected_seller_can_be_approved_again(): void
    {
        $this->seedData();
        $seller = Seller::where('status', 'approved')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/reject")
            ->assertRedirect();
        $this->assertEquals('rejected', $seller->fresh()->status);

        $this->actingAs($admin)
            ->post("/admin/sellers/{$seller->id}/approve")
            ->assertRedirect();
        $this->assertEquals('approved', $seller->fresh()->status);
    }

    public function test_settings_page_renders_all_sections(): void
    {
        $this->seedData();
        $response = $this->actingAs($this->admin())->get('/admin/settings');

        $response->assertStatus(200)
            ->assertSee('System Settings')
            ->assertSee('General')
            ->assertSee('Marketplace')
            ->assertSee('Delivery')
            ->assertSee('Security')
            ->assertSee('Two-Factor');
    }

    public function test_settings_can_be_saved_per_section(): void
    {
        $this->seedData();

        $this->actingAs($this->admin())
            ->post('/admin/settings/update', [
                'section' => 'marketplace',
                'seller_registration' => 'closed',
                'default_commission' => '12.5',
                'payout_minimum' => '1000',
                'payout_schedule' => 'monthly',
                'auto_approve_sellers' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('settings', ['key' => 'seller_registration', 'value' => 'closed']);
        $this->assertDatabaseHas('settings', ['key' => 'default_commission', 'value' => '12.5']);
        $this->assertDatabaseHas('settings', ['key' => 'auto_approve_sellers', 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => 'product_approval', 'value' => '0']);

        $this->assertEquals('closed', \App\Models\Setting::get('seller_registration'));
        $this->assertEquals('1', \App\Models\Setting::get('auto_approve_sellers'));
        $this->assertEquals('0', \App\Models\Setting::get('product_approval'));
    }
}
