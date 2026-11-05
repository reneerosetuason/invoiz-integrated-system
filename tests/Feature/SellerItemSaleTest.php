<?php

namespace Tests\Feature;

use App\Models\{User, Seller, Product, Category, Cart, CartItem, Address, OrderItem, Sale, ProductSale};
use App\Support\Sales;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class SellerItemSaleTest extends TestCase
{
    use BuyerMobileDatabase;
    private User $owner;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp(); $this->createMobileDatabase();
        \Illuminate\Support\Facades\Schema::create('seller_notifications', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('seller_id'); $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('product_id')->nullable(); $t->string('type'); $t->string('title'); $t->text('body');
            $t->boolean('is_read')->default(false); $t->timestamps();
        });
        $this->owner = $this->account('seller');
        Seller::create(['user_id' => $this->owner->id, 'store_name' => 'Sale shop', 'status' => 'approved', 'approval_status' => 'approved',
            'address_line' => '2 Seller Street', 'barangay' => 'Seller Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        $this->category = Category::create(['name' => 'Sale fixtures', 'status' => 'active', 'active' => 1]);
        $this->actingAs($this->owner)->withSession(['active_role' => 'seller']);
    }

    private function account(string $role): User
    {
        return User::create(['name' => 'Sale '.$role, 'email' => uniqid().'@example.test', 'password' => 'Password123!',
            'role' => $role, 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'Discounted fixture bag', 'category_id' => $this->category->id, 'price' => 1000,
            'discounted_price' => 750, 'item_sale_enabled' => 1, 'stock' => 10, 'status' => 'active'], $overrides);
    }

    private function product(): Product
    {
        $this->post('/seller/products', $this->data())->assertRedirect()->assertSessionHasNoErrors();
        return Product::firstOrFail();
    }

    public function test_seller_can_create_edit_and_end_an_individual_sale(): void
    {
        $p = $this->product();
        $this->assertSame(750.0, (float)$p->price); $this->assertSame(1000.0, (float)$p->compare_at_price);
        $this->get('/seller/products/'.$p->id.'/edit')->assertOk()->assertSee('Put this item on sale')->assertSee('name="discounted_price"', false);
        $this->post('/seller/products/'.$p->id, $this->data(['discounted_price' => 600]))->assertSessionHasNoErrors();
        $this->assertSame(600.0, (float)$p->fresh()->price);
        $this->post('/seller/products/'.$p->id, $this->data(['item_sale_enabled' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(1000.0, (float)$p->fresh()->price); $this->assertNull($p->fresh()->compare_at_price);
    }

    public function test_discount_must_be_lower_than_regular_price_and_products_are_owner_scoped(): void
    {
        foreach ([1000, 1100, 0, -10, 'invalid'] as $amount) {
            $this->postJson('/seller/products', $this->data(['discounted_price' => $amount]))->assertUnprocessable()->assertJsonValidationErrors('discounted_price');
        }
        $p = $this->product(); $other = $this->account('seller');
        Seller::create(['user_id' => $other->id, 'store_name' => 'Other shop', 'status' => 'approved', 'approval_status' => 'approved']);
        $this->actingAs($other)->post('/seller/products/'.$p->id, $this->data(['discounted_price' => 100]))->assertNotFound();
        $this->assertSame(750.0, (float)$p->fresh()->price);
    }

    public function test_sale_badges_listing_cart_and_checkout_use_the_sellers_price(): void
    {
        $p = $this->product(); $buyer = $this->account('buyer'); Auth::forgetGuards();
        $this->actingAs($buyer)->withSession(['active_role' => 'buyer']);
        $this->get('/sale')->assertOk()->assertSee($p->name)->assertSee('25%');
        $this->get('/product/'.$p->id)->assertOk()->assertSee($p->name)->assertSee('25%');
        $this->get('/')->assertOk()->assertSee($p->name)->assertSee('25%')->assertDontSee('id="deal-countdown"', false);
        $this->getJson('/api/products/'.$p->id)->assertOk()->assertJsonPath('product.effective_price', 750)->assertJsonPath('product.discount_percent', 25);
        $cart = Cart::headerFor($buyer->id); CartItem::create(['cart_id' => $cart->id, 'product_id' => $p->id, 'quantity' => 2]);
        $this->assertSame(750.0, Cart::unitFor($p, null));
        $address = Address::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Sale buyer', 'phone' => '09171234567',
            'address_line' => '1 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        Sanctum::actingAs($buyer);
        $response = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery']);
        $response->assertCreated()->assertJsonPath('order.total_amount', 1550);
        $this->assertSame(750.0, (float)OrderItem::firstOrFail()->unit_price);
        $this->actingAs($this->owner)->withSession(['active_role' => 'seller'])->post('/seller/products/'.$p->id, $this->data(['item_sale_enabled' => 0]))->assertSessionHasNoErrors();
        $this->assertSame(750.0, (float)OrderItem::firstOrFail()->unit_price);
    }

    public function test_store_campaigns_are_discoverable_and_choose_the_better_non_stacked_price(): void
    {
        $p = $this->product();
        $sale = Sale::create(['seller_id' => $this->owner->seller->id, 'name' => 'Store campaign', 'type' => 'store', 'status' => 'active', 'discount_type' => 'percent', 'discount_value' => 20]);
        ProductSale::create(['sale_id' => $sale->id, 'product_id' => $p->id]);
        $this->assertSame(750.0, Sales::priceFor($p));
        $sale->update(['discount_value' => 40]); $this->assertSame(600.0, Sales::priceFor($p));
        $p->update(['price' => 1000, 'compare_at_price' => null]);
        $this->get('/sale')->assertOk()->assertSee($p->name)->assertSee('40%');
        $this->get('/')->assertOk()->assertSee($p->name);
        $sale->update(['ends_at' => now()->subMinute()]);
        $this->assertSame(1000.0, Sales::priceFor($p));
        $this->get('/sale')->assertOk()->assertDontSee($p->name);
    }

    public function test_zero_percent_discounts_are_rejected_and_excluded_from_web_and_mobile_sales(): void
    {
        $this->postJson('/seller/products', $this->data(['price' => 3749, 'discounted_price' => 3748.97]))
            ->assertUnprocessable()->assertJsonValidationErrors('discounted_price');
        $valid = $this->product();
        $this->postJson('/seller/products/'.$valid->id, $this->data(['price' => 3749, 'discounted_price' => 3748.97]))
            ->assertUnprocessable()->assertJsonValidationErrors('discounted_price');
        $tiny = Product::create(['seller_id' => $this->owner->id, 'category_id' => $this->category->id,
            'name' => 'Zero percent headphones', 'price' => 3748.97, 'compare_at_price' => 3749, 'stock' => 10, 'status' => 'active']);
        $campaignOnly = Product::create(['seller_id' => $this->owner->id, 'category_id' => $this->category->id,
            'name' => 'Tiny campaign headphones', 'price' => 3749, 'stock' => 10, 'status' => 'active']);
        $sale = Sale::create(['seller_id' => $this->owner->seller->id, 'name' => 'Tiny campaign', 'type' => 'store',
            'status' => 'active', 'discount_type' => 'fixed', 'discount_value' => 0.03]);
        ProductSale::create(['sale_id' => $sale->id, 'product_id' => $campaignOnly->id]);
        $buyer = $this->account('buyer'); Auth::forgetGuards();
        $this->actingAs($buyer)->withSession(['active_role' => 'buyer']);
        $this->get('/sale')->assertOk()->assertSee($valid->name)->assertDontSee($tiny->name)->assertDontSee($campaignOnly->name);
        foreach ([$tiny, $campaignOnly] as $p) {
            $this->getJson('/api/products/'.$p->id)->assertOk()->assertJsonPath('product.on_sale', false)
                ->assertJsonPath('product.discount_percent', 0)->assertJsonPath('product.compare_at_price', null)->assertJsonPath('product.sale', null);
            $this->get('/product/'.$p->id)->assertOk()->assertDontSee('class="poff"', false);
        }
        $this->getJson('/api/products?on_sale=1&per_page=1')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $valid->id);
        $this->getJson('/api/products?sort=discount')->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(3748.97, (float)$tiny->fresh()->price);
    }
}
