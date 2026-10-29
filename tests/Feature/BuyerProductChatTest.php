<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class BuyerProductChatTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_29_000001_add_product_card_to_messages.php'))->up();
    }

    private function user(string $email, string $role = 'buyer'): User
    {
        return User::create([
            'name' => 'Fixture User', 'first_name' => 'Fixture', 'last_name' => 'User',
            'email' => $email, 'password' => 'Password123!', 'role' => $role,
            'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function product(string $email = 'store@example.test'): Product
    {
        $owner = $this->user($email, 'seller');
        Seller::create([
            'user_id' => $owner->id, 'store_name' => 'Fixture Store',
            'status' => 'active', 'approval_status' => 'approved',
            'logo' => 'logos/store.png',
        ]);

        return Product::create([
            'seller_id' => $owner->id, 'name' => 'Linen everyday bag',
            'price' => 100, 'stock' => 10, 'status' => 'active',
            'image' => 'products/bag.png',
        ]);
    }

    private function send(Product $product, array $overrides = [])
    {
        return $this->postJson('/api/conversations', array_merge([
            'seller_id' => $product->seller_id, 'product_id' => $product->id,
            'subject' => 'Product inquiry', 'body' => 'Is this still available?',
        ], $overrides));
    }

    public function test_product_card_reaches_the_seller_and_survives_catalog_changes(): void
    {
        $product = $this->product();
        $buyer = $this->user('buyer@example.test');
        Sanctum::actingAs($buyer);
        $conversation = $this->send($product, ['product_card' => ['price' => 1, 'name' => 'Forged']])
            ->assertCreated()->json('conversation.id');
        $message = Message::firstOrFail();
        $this->assertSame((int) $product->seller_id, (int) $message->receiver_id);
        $this->assertSame($product->name, $message->product_card['name']);
        $this->assertSame(100.0, (float) $message->product_card['price']);
        $product->update(['name' => 'Changed later', 'price' => 500]);
        $this->getJson('/api/conversations/'.$conversation.'/messages')->assertOk()
            ->assertJsonPath('messages.0.product_card.name', 'Linen everyday bag')
            ->assertJsonPath('messages.0.product_card.image', 'products/bag.png');

        $owner = User::findOrFail($product->seller_id);
        $this->actingAs($owner)->withSession(['active_role' => 'seller'])
            ->get('/seller/chat/'.$conversation)->assertOk()
            ->assertSee('data-product-card="'.$product->id.'"', false)
            ->assertSee('Linen everyday bag')->assertSee('₱100.00')
            ->assertSee('/storage/products/bag.png');
    }

    public function test_card_price_uses_running_sale_and_selected_variant(): void
    {
        $product = $this->product();
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'variant_type' => 'Size',
            'variant_value' => 'Large', 'price_adjustment' => 25,
            'stock' => 5, 'status' => 'active',
        ]);
        $sale = Sale::create([
            'seller_id' => Seller::where('user_id', $product->seller_id)->value('id'),
            'name' => 'Fixture sale', 'discount_type' => 'percent',
            'discount_value' => 20, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
        ]);
        ProductSale::create(['sale_id' => $sale->id, 'product_id' => $product->id]);
        Sanctum::actingAs($this->user('buyer@example.test'));
        $this->send($product, ['variant_id' => $variant->id])->assertCreated();
        $card = Message::firstOrFail()->product_card;
        $this->assertSame(105.0, (float) $card['price']);
        $this->assertSame('Size: Large', $card['option']);
    }

    public function test_foreign_store_product_and_foreign_option_are_rejected_without_messages(): void
    {
        $product = $this->product();
        $foreign = $this->product('other-store@example.test');
        $variant = ProductVariant::create([
            'product_id' => $foreign->id, 'variant_type' => 'Size',
            'variant_value' => 'Small', 'stock' => 5, 'status' => 'active',
        ]);
        Sanctum::actingAs($this->user('buyer@example.test'));
        $this->send($product, ['product_id' => $foreign->id])->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
        $this->send($product, ['variant_id' => $variant->id])->assertUnprocessable()
            ->assertJsonValidationErrors('variant_id');
        $product->update(['status' => 'inactive']);
        $this->send($product)->assertUnprocessable();
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_same_seller_thread_is_reused_and_plain_replies_still_work(): void
    {
        $product = $this->product();
        Sanctum::actingAs($this->user('buyer@example.test'));
        $first = $this->send($product)->assertCreated()->json('conversation.id');
        $this->send($product)->assertCreated()->assertJsonPath('conversation.id', $first);
        $this->postJson('/api/conversations/'.$first.'/reply', ['body' => 'Thank you'])
            ->assertCreated()->assertJsonPath('message.body', 'Thank you');
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 3);
        $this->assertNull(Message::latest('id')->firstOrFail()->product_card);
    }

    public function test_gallery_photo_is_used_when_product_has_no_cover(): void
    {
        $product = $this->product();
        $product->update(['image' => null]);
        $product->images()->create(['path' => 'products/gallery.png', 'is_main' => true]);
        Sanctum::actingAs($this->user('buyer@example.test'));
        $this->send($product)->assertCreated();
        $this->assertSame('products/gallery.png', Message::firstOrFail()->product_card['image']);
    }

    public function test_both_buyer_store_payloads_include_the_public_logo(): void
    {
        $product = $this->product();
        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('product.shop.logo', 'logos/store.png');
        $this->getJson('/api/stores/'.$product->seller_id)->assertOk()
            ->assertJsonPath('store.logo', 'logos/store.png');
    }
}
