<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerWaybillLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The fixtures below use DDL to keep the waybill scenario small.
        // Always bind that DDL to a per-test in-memory database, never the
        // persistent MySQL test schema.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->assertContains(
            'sqlite',
            \PDO::getAvailableDrivers(),
            'SQLite PDO is required for this isolated fixture; no schema operations were run.'
        );
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $sqlite = DB::connection('sqlite');
        $this->assertSame('sqlite', $sqlite->getDriverName());
        $this->assertSame(':memory:', $sqlite->getDatabaseName());
        $pdo = $sqlite->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $mainDatabase = collect($pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC))
            ->firstWhere('name', 'main');
        $this->assertSame('', $mainDatabase['file'] ?? null, 'The fixture database must be in memory.');
    }

    private function setUpDatabaseSchema(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->dropIfExists('order_items');
        $schema->dropIfExists('logistics_settings');
        $schema->dropIfExists('seller_profiles');
        $schema->dropIfExists('deliveries');
        $schema->dropIfExists('orders');
        $schema->dropIfExists('products');
        $schema->dropIfExists('sellers');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('seller');
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('phone')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('store_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->default('approved');
            $table->string('approval_status')->default('approved');
            $table->softDeletes();
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

        $schema->create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->text('business_info')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('ready_for_delivery');
            $table->json('shipping_address')->nullable();
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('tracking_number')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $schema->create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('seller_id');
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    private function createSeller(string $storeName = 'Test Shop'): array
    {
        $user = User::create([
            'name' => 'Seller Juan',
            'first_name' => 'Seller',
            'last_name' => 'Juan',
            'email' => 'seller_' . uniqid() . '@example.test',
            'password' => Hash::make('secret'),
            'role' => 'seller',
            'phone' => '+63 917 111 2222',
        ]);

        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => $storeName,
            'business_name' => $storeName,
            'status' => 'approved',
        ]);

        \Illuminate\Support\Facades\DB::table('seller_profiles')->insert([
            'seller_id' => $seller->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $seller];
    }

    public function test_waybill_css_enforces_one_quarter_a4_dimensions(): void
    {
        $cssPath = public_path('assets/seller/css/orders/waybill.css');
        $this->assertFileExists($cssPath);
        $css = file_get_contents($cssPath);

        // A4 page setup
        $this->assertStringContainsString('size: A4 portrait;', $css);
        $this->assertStringContainsString('margin: 8mm;', $css);
        $this->assertStringContainsString('height: 281mm !important', $css);

        // Dimensions: exactly 105mm width and 148.5mm height (1/4 of A4 sheet)
        $this->assertStringContainsString('width: 105mm', $css);
        $this->assertStringContainsString('max-width: 105mm', $css);
        $this->assertStringContainsString('height: 148.5mm', $css);
        $this->assertStringContainsString('max-height: 148.5mm', $css);
        $this->assertStringContainsString('box-sizing: border-box', $css);

        // Multi-page prevention
        $this->assertStringContainsString('page-break-inside: avoid', $css);
        $this->assertStringContainsString('break-inside: avoid', $css);
        $this->assertStringContainsString('page-break-after: avoid', $css);
        $this->assertStringContainsString('break-after: avoid', $css);
        $this->assertStringContainsString('overflow: hidden', $css);
        $this->assertStringContainsString('align-items: center !important', $css);
        $this->assertStringContainsString('justify-content: center !important', $css);

        // Screen-only elements hidden
        $this->assertStringContainsString('.no-print', $css);
        $this->assertStringContainsString('display: none !important', $css);
    }

    public function test_waybill_renders_all_critical_order_and_shipment_data(): void
    {
        $this->setUpDatabaseSchema();
        [$user, $seller] = $this->createSeller('INVOIZ Flagship Store');

        $order = Order::create([
            'total_amount' => 1250.00,
            'status' => 'ready_for_delivery',
            'shipping_address' => [
                'recipient_name' => 'Maria Clara',
                'address_line' => 'Unit 102 Crystal Tower',
                'barangay' => 'San Lorenzo',
                'city' => 'Makati City',
                'province' => 'Metro Manila',
                'postal_code' => '1223',
                'phone' => '+63 920 987 6543',
            ],
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-20261017-ZHB0',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $user->id,
            'product_name' => 'Premium Wireless Headset',
            'variant_label' => 'Matte Black',
            'quantity' => 1,
            'price' => 1250.00,
            'subtotal' => 1250.00,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'seller'])
            ->get("/seller/orders/{$order->id}/waybill");

        $response->assertOk();

        // Branding and headers
        $response->assertSee('SHIPPING WAYBILL', false);
        $response->assertSee('INVOIZ Flagship Store', false);
        $response->assertSee('#' . $order->id, false);

        // Recipient address
        $response->assertSee('Maria Clara', false);
        $response->assertSee('Unit 102 Crystal Tower', false);
        $response->assertSee('Makati City', false);
        $response->assertSee('+63 920 987 6543', false);

        // Tracking and barcode element
        $response->assertSee('TRK-20261017-ZHB0', false);
        $response->assertSee('id="seller-barcode"', false);
        $response->assertSee('id="seller-qr"', false);

        // Order item
        $response->assertSee('Premium Wireless Headset', false);
        $response->assertSee('Matte Black', false);

        // COD total
        $response->assertSee('1,250.00', false);

        // Check compact container class
        $response->assertSee('class="waybill"', false);
        $response->assertSee('id="print-area" class="waybill"', false);
        $response->assertSee('waybill.css', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="print-area"'));
    }

    public function test_waybill_caps_item_rows_to_prevent_vertical_overflow(): void
    {
        $this->setUpDatabaseSchema();
        [$user, $seller] = $this->createSeller('Wholesale Central');

        $order = Order::create([
            'total_amount' => 5000.00,
            'status' => 'ready_for_delivery',
            'shipping_address' => [
                'recipient_name' => 'Bulk Buyer',
                'address_line' => '100 Industrial Road',
                'city' => 'Taguig',
                'phone' => '+63 919 000 0000',
            ],
        ]);

        Delivery::create([
            'order_id' => $order->id,
            'tracking_number' => 'TRK-20261017-BULK',
            'status' => 'pending',
        ]);

        // Create 8 items to test overflow prevention
        for ($i = 1; $i <= 8; $i++) {
            OrderItem::create([
                'order_id' => $order->id,
                'seller_id' => $user->id,
                'product_name' => "Bulk Item #{$i}",
                'quantity' => $i,
                'price' => 100.00,
                'subtotal' => $i * 100.00,
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'seller'])
            ->get("/seller/orders/{$order->id}/waybill");

        $response->assertOk();
        // First 3 items shown
        $response->assertSee('Bulk Item #1', false);
        $response->assertSee('Bulk Item #2', false);
        $response->assertSee('Bulk Item #3', false);
        // Overflow summary message present
        $response->assertSee('more item(s) on order invoice', false);
    }

    public function test_seller_cannot_view_waybill_of_another_seller(): void
    {
        $this->setUpDatabaseSchema();
        [$userA, $sellerA] = $this->createSeller('Seller Alpha');
        [$userB, $sellerB] = $this->createSeller('Seller Beta');

        $order = Order::create([
            'total_amount' => 300.00,
            'status' => 'ready_for_delivery',
            'shipping_address' => ['recipient_name' => 'Target Customer'],
        ]);

        // Item belongs only to seller A
        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $userA->id,
            'product_name' => 'Alpha Special',
            'quantity' => 1,
            'price' => 300.00,
            'subtotal' => 300.00,
        ]);

        // Seller B should not be able to view Seller A's waybill (404)
        $response = $this->actingAs($userB)
            ->withSession(['active_role' => 'seller'])
            ->get("/seller/orders/{$order->id}/waybill");

        $response->assertNotFound();
    }
}
