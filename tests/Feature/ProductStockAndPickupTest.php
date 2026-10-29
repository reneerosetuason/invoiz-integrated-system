<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, CartItem, Category, Order, OrderItem, Product, ProductVariant, Seller, User};
use App\Support\{DeliveryServiceability, Stock};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class ProductStockAndPickupTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $owner;
    private User $buyer;
    private Seller $seller;
    private Product $product;
    private ProductVariant $a;
    private ProductVariant $b;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('weight')->nullable();
            $table->string('dimensions')->nullable();
        });
        Schema::create('seller_notifications', function (Blueprint $table) {
            $table->id(); $table->integer('seller_id'); $table->integer('product_id')->nullable();
            $table->string('type'); $table->string('title'); $table->text('body');
            $table->boolean('is_read')->default(false); $table->timestamps();
        });
        (require database_path('migrations/2026_10_29_000003_track_product_stock_deductions.php'))->up();
        $this->owner = $this->account('stock-owner', 'seller');
        $this->buyer = $this->account('stock-buyer');
        $this->seller = Seller::create(['user_id' => $this->owner->id, 'store_name' => 'Stock Fixture Store', 'status' => 'active', 'approval_status' => 'approved',
            'vacation_mode' => false, 'address_line' => '1 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        $this->category = Category::create(['name' => 'Fixture category', 'slug' => 'fixture', 'status' => 'active']);
        $this->product = Product::create(['seller_id' => $this->owner->id, 'category_id' => $this->category->id, 'name' => 'Stock fixture product', 'price' => 100,
            'stock' => 10, 'cost_price' => 20, 'status' => 'active']);
        $this->a = ProductVariant::create(['product_id' => $this->product->id, 'variant_type' => 'Color', 'variant_value' => 'Blue', 'stock' => 6, 'status' => 'active']);
        $this->b = ProductVariant::create(['product_id' => $this->product->id, 'variant_type' => 'Color', 'variant_value' => 'Red', 'stock' => 4, 'status' => 'active']);
    }

    private function account(string $name, string $role = 'buyer'): User
    {
        return User::create(['name' => $name, 'first_name' => 'Fixture', 'last_name' => 'User', 'email' => $name.'@example.test', 'password' => 'Password123!',
            'role' => $role, 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function sellerSession(): self
    {
        Auth::forgetGuards();
        return $this->actingAs($this->owner)->withSession(['active_role' => 'seller']);
    }

    private function asBuyer(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->buyer);
    }

    private function data(array $extra = []): array
    {
        return $extra + ['name' => 'Managed fixture product', 'category_id' => $this->category->id, 'price' => 100, 'stock' => 10, 'status' => 'active'];
    }

    private function variations(array $stocks, bool $ids = false): array
    {
        return ['type' => ['Color', 'Color'], 'value' => ['Blue', 'Red'], 'adjustment' => [0, 0], 'stock' => $stocks]
            + ($ids ? ['id' => [$this->a->id, $this->b->id]] : []);
    }

    private function line(ProductVariant $variant, int $quantity): CartItem
    {
        return CartItem::create(['cart_id' => Cart::headerFor($this->buyer->id)->id, 'product_id' => $this->product->id,
            'variant_id' => $variant->id, 'quantity' => $quantity]);
    }

    private function address(): Address
    {
        return Address::create(['buyer_id' => $this->buyer->id, 'recipient_name' => 'Fixture Buyer', 'phone' => '09171234567',
            'address_line' => '2 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
    }

    public function test_seller_cannot_create_overallocated_or_invalid_variation_stock(): void
    {
        foreach ([[6, 5], [-1, 2], [1.5, 2]] as $stocks) {
            $this->sellerSession()->from('/seller/products/create')->post('/seller/products', $this->data(['variants' => $this->variations($stocks)]))
                ->assertRedirect('/seller/products/create')->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_exact_allocations_save_and_new_products_are_owned_by_the_seller(): void
    {
        $this->sellerSession()->post('/seller/products', $this->data(['variants' => $this->variations([6, 4])]))->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::where('id', '!=', $this->product->id)->firstOrFail();
        $this->assertEquals(10, $product->variants()->sum('stock'));
        $this->assertSame($this->owner->id, (int) $product->seller_id);
    }

    public function test_edits_preserve_variation_ids_and_reject_a_general_stock_below_allocations(): void
    {
        $line = $this->line($this->a, 1);
        $this->sellerSession()->post('/seller/products/'.$this->product->id, $this->data(['stock' => 9, 'variants' => $this->variations([6, 4], true)]))
            ->assertSessionHasErrors('variants');
        $this->sellerSession()->post('/seller/products/'.$this->product->id, $this->data(['stock' => 9]))->assertSessionHasErrors('stock');
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->sellerSession()->post('/seller/products/'.$this->product->id, $this->data(['stock' => 9, 'variants' => $this->variations([5, 4], true)]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(5, $this->a->fresh()->stock);
        $this->assertEquals(9, $this->product->fresh()->stock);
        $this->assertFalse($this->a->fresh()->trashed());
        $this->assertSame($this->a->id, (int) $line->fresh()->variant_id);
        $this->sellerSession()->post('/seller/products/'.$this->product->id, $this->data(['stock' => 9, 'variants_present' => 1]))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->product->variants()->count());
    }

    public function test_foreign_variation_ids_cannot_be_edited_through_another_product(): void
    {
        $foreign = Product::create(['seller_id' => $this->owner->id, 'name' => 'Other product', 'price' => 10, 'stock' => 10, 'status' => 'active']);
        $variant = ProductVariant::create(['product_id' => $foreign->id, 'stock' => 2, 'status' => 'active']);
        $rows = $this->variations([5, 4], true);
        $rows['id'][0] = $variant->id;
        $this->sellerSession()->post('/seller/products/'.$this->product->id, $this->data(['variants' => $rows]))->assertSessionHasErrors('variants');
        $this->assertEquals(2, $variant->fresh()->stock);
    }

    public function test_cart_and_checkout_cap_combined_variations_by_general_stock(): void
    {
        // Imported stock may already be inconsistent; it still cannot oversell.
        $this->b->update(['stock' => 6]);
        $this->asBuyer();
        $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'variant_id' => $this->a->id, 'quantity' => 6])->assertOk();
        $this->postJson('/api/cart/add', ['product_id' => $this->product->id, 'variant_id' => $this->b->id, 'quantity' => 5])->assertUnprocessable();
        $this->line($this->b, 5);
        $this->postJson('/api/orders/preview')->assertUnprocessable();
        $this->postJson('/api/orders/checkout', ['address_id' => $this->address()->id, 'payment_method' => 'cash_on_delivery'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertEquals(6, $this->a->fresh()->stock);
    }

    public function test_variant_orders_deduct_and_cancellation_restores_both_counts_once(): void
    {
        $this->line($this->a, 2);
        $this->asBuyer();
        $id = $this->postJson('/api/orders/checkout', ['address_id' => $this->address()->id, 'payment_method' => 'cash_on_delivery'])->assertCreated()->json('order.id');
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertEquals(4, $this->a->fresh()->stock);
        $this->assertEquals(4, $this->b->fresh()->stock);
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'product_stock_deducted' => 1]);
        $this->postJson('/api/orders/'.$id.'/cancel', ['reason' => 'changed_mind'])->assertOk();
        $this->postJson('/api/orders/'.$id.'/cancel', ['reason' => 'changed_mind']);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertEquals(6, $this->a->fresh()->stock);
    }

    public function test_stock_deduction_is_atomic_and_base_units_cannot_consume_allocated_stock(): void
    {
        $this->assertFalse(Stock::deduct($this->product->id, null, 1));
        $this->assertFalse(Stock::deduct($this->product->id, $this->a->id, 7));
        $this->assertFalse(Stock::deduct($this->product->id, 99999, 1));
        $this->product->update(['stock' => 0]);
        $this->assertFalse(Stock::deduct($this->product->id, $this->a->id, 1));
        $this->assertEquals(6, $this->a->fresh()->stock);
        $this->assertSame(0, Cart::availableFor($this->product->fresh(), $this->a));
    }

    public function test_historical_variant_orders_restore_only_the_count_they_originally_deducted(): void
    {
        $order = Order::create(['buyer_id' => $this->buyer->id, 'status' => 'pending', 'total_amount' => 100]);
        $id = DB::table('order_items')->insertGetId(['order_id' => $order->id, 'product_id' => $this->product->id,
            'product_variant_id' => $this->a->id, 'quantity' => 2, 'product_stock_deducted' => false]);
        Stock::restore(OrderItem::findOrFail($id), 1);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertEquals(7, $this->a->fresh()->stock);
    }

    public function test_pickup_address_is_required_and_saving_it_unblocks_checkout_for_the_same_cart(): void
    {
        $this->seller->update(['address_line' => null, 'barangay' => null, 'city' => null, 'province' => null]);
        $this->line($this->a, 1);
        $this->asBuyer();
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(1, 'cart.items');
        $this->postJson('/api/orders/preview')->assertUnprocessable()->assertJsonValidationErrors('cart_item_ids');
        $this->sellerSession()->from('/seller/account')->post('/seller/account/pickup-address', ['address_line' => '1 Test Street'])->assertSessionHasErrors(['barangay', 'city', 'province']);
        $this->sellerSession()->from('/seller/account')->post('/seller/account/pickup-address', [
            'address_line' => '1 Updated Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila', 'seller_id' => 999,
        ])->assertRedirect('/seller/account')->assertSessionHasNoErrors();
        $this->assertSame('1 Updated Test Street', $this->seller->fresh()->address_line);
        $this->assertNull(DeliveryServiceability::pickupAddressProblem($this->owner->id));
        $this->asBuyer();
        $this->postJson('/api/orders/preview')->assertOk()->assertJsonPath('quote.subtotal', 100);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_seller_forms_render_stock_limits_and_editable_pickup_address(): void
    {
        foreach (['account' => '/seller/account', 'product-create' => '/seller/products/create', 'product-edit' => '/seller/products/'.$this->product->id.'/edit'] as $name => $path) {
            $response = $this->sellerSession()->get($path)->assertOk();
            if ($name === 'account') $response->assertSee('Save Pickup Address')->assertSee(route('seller.account.pickup-address'), false);
            else $response->assertSee('Total variation stock exceeds general stock.')->assertSee('used to calculate profit');
            if ($directory = getenv('INVOIZ_STOCK_RENDER_DIR')) {
                if (! is_dir($directory)) mkdir($directory, 0777, true);
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public function test_buyer_website_variation_options_respect_general_stock(): void
    {
        $this->a->update(['stock' => 20]);
        Auth::forgetGuards();
        $response = $this->actingAs($this->buyer)->get('/product/'.$this->product->id)->assertOk();
        $response->assertSee('data-stock="10"', false)->assertDontSee('data-stock="20"', false);
        if ($directory = getenv('INVOIZ_STOCK_RENDER_DIR')) file_put_contents($directory.'/buyer-product.html', $response->getContent());
        $this->product->update(['stock' => 0]);
        $this->get('/product/'.$this->product->id)->assertOk()->assertSee('data-stock="0"', false)->assertSee('disabled title="Out of stock"', false);
    }
}
