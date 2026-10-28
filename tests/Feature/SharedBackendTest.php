<?php

namespace Tests\Feature;

use App\Http\Controllers\Logistics\CentralEntryController;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedBackendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        // Only isolated memory fixtures: never migrate the shared application database.
        $tables = [
            'users' => 'name first_name last_name email password role status account_status is_admin email_verified_at remember_token center_id',
            'sellers' => 'user_id store_name business_name status approval_status deleted_at',
            'riders' => 'user_id name status approved_at',
            'products' => 'seller_id name price stock status deleted_at',
            'orders' => 'buyer_id seller_id status payment_status total total_amount order_number deleted_at',
            'order_items' => 'order_id product_id seller_id product_name quantity price unit_price total_price',
            'deliveries' => 'order_id seller_id rider_id status tracking_number picked_up_at archived_at',
            'remittances' => 'order_id delivery_id seller_id status amount',
            'payments' => 'order_id method amount status',
            'personal_access_tokens' => 'tokenable_id tokenable_type name token abilities expires_at',
            'logistics_settings' => 'user_id timezone',
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
            });
        }
    }

    private function account(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Account',
            'email' => $role.'@example.test',
            'password' => 'Password123!',
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_backend_check_verifies_connections_without_writing_data(): void
    {
        $this->account('buyer');
        DB::connection()->beforeExecuting(function ($query) {
            $this->assertMatchesRegularExpression('/^\s*(select|pragma)\b/i', $query, 'Backend check must be read-only.');
        });

        $this->artisan('invoiz:check-backend')
            ->expectsOutputToContain('application models, API tokens and accounts share one database connection')
            ->assertSuccessful();
        $this->assertSame(1, User::count());
    }

    public function test_backend_check_rejects_a_separate_session_database(): void
    {
        config([
            'database.connections.separate_backend' => config('database.connections.sqlite'),
            'session.driver' => 'database',
            'session.connection' => 'separate_backend',
        ]);

        $this->artisan('invoiz:check-backend')
            ->expectsOutputToContain('Sessions uses a separate database connection.')
            ->assertFailed();
    }

    public function test_backend_check_rejects_a_separate_account_provider(): void
    {
        config(['auth.providers.users.model' => Seller::class]);

        $this->artisan('invoiz:check-backend')
            ->expectsOutputToContain('Authentication must use the shared App\\Models\\User model.')
            ->assertFailed();
    }

    public function test_backend_check_detects_missing_role_tables(): void
    {
        Schema::drop('riders');

        $this->artisan('invoiz:check-backend')
            ->expectsOutputToContain('Shared database is missing the riders table.')
            ->assertFailed();
    }

    public function test_every_role_authenticates_against_the_same_users_table(): void
    {
        foreach (['buyer', 'seller', 'admin', 'logistics_admin', 'logistics_staff', 'staff', 'rider'] as $role) {
            $user = $this->account($role);
            $this->assertTrue(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'Password123!']));
            $this->assertSame($user->id, Auth::guard('web')->getProvider()->retrieveById($user->id)->id);
        }

        $this->assertDatabaseCount('users', 7);
    }

    public function test_buyer_order_seller_items_and_rider_delivery_reference_one_record(): void
    {
        $buyer = $this->account('buyer');
        $seller = $this->account('seller');
        $riderUser = $this->account('rider');
        $rider = Rider::create(['user_id' => $riderUser->id, 'name' => 'Shared Rider', 'status' => 'available', 'approved_at' => now()]);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Shared product', 'price' => 100, 'stock' => 5]);
        $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'pending', 'total_amount' => 100]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $seller->id, 'quantity' => 1, 'price' => 100]);
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->id, 'status' => 'assigned']);

        $buyerOrder = Order::where('buyer_id', $buyer->id)->findOrFail($order->id);
        $sellerOrder = Order::whereHas('items', fn ($q) => $q->where('seller_id', $seller->id))->findOrFail($order->id);
        $logisticsOrder = Delivery::findOrFail($delivery->id)->order;
        $this->assertSame($buyerOrder->id, $sellerOrder->id);
        $this->assertSame($buyerOrder->id, $logisticsOrder->id);
        $this->assertSame($buyer->id, $logisticsOrder->buyer->id);
        $this->assertSame($riderUser->id, $delivery->rider->user->id);

        $sellerOrder->update(['status' => 'confirmed']);
        $this->assertSame('confirmed', $buyerOrder->fresh()->status);
        $this->assertSame('confirmed', $logisticsOrder->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_marketplace_entry_stays_local_and_keeps_the_existing_login(): void
    {
        // Stale deployment settings must not send either role to another system.
        config([
            'invoiz.platforms.buyer_url' => 'https://buyer.example.invalid',
            'invoiz.platforms.seller_url' => 'https://seller.example.invalid',
        ]);

        foreach (['buyer' => '/shop', 'seller' => '/home'] as $role => $destination) {
            $user = $this->account($role);
            $this->actingAs($user)->get('/logistics')->assertRedirect($destination);
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_shared_backend_preserves_logistics_permissions_and_rider_destination(): void
    {
        $buyer = $this->account('buyer');
        $this->actingAs($buyer)->get('/logistics/dashboard')->assertRedirect('/logistics/login');
        $this->assertGuest();

        $staff = $this->account('logistics_staff');
        $this->assertSame('/logistics/dashboard', CentralEntryController::destinationFor($staff));

        $riderUser = $this->account('rider');
        Rider::create(['user_id' => $riderUser->id, 'status' => 'available', 'approved_at' => now()]);
        $this->assertSame('/logistics/rider/entry', CentralEntryController::destinationFor($riderUser));
    }
}
