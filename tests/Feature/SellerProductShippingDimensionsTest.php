<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class SellerProductShippingDimensionsTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $owner;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();

        if (! Schema::hasColumn('products', 'dimensions')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('dimensions')->nullable();
            });
        }

        if (! Schema::hasTable('seller_notifications')) {
            Schema::create('seller_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('type')->nullable();
                $table->string('title')->nullable();
                $table->text('message')->nullable();
                $table->text('body')->nullable();
                $table->string('link')->nullable();
                $table->boolean('is_read')->default(false);
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        $this->owner = User::create([
            'name' => 'Seller Test',
            'email' => 'seller-shipping@example.test',
            'password' => 'Password123!',
            'role' => 'seller',
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);

        Seller::create([
            'user_id' => $this->owner->id,
            'store_name' => 'Shipping Test Store',
            'status' => 'approved',
            'approval_status' => 'approved',
            'address_line' => '123 Market St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'status' => 'active',
            'active' => 1,
        ]);

        $this->actingAs($this->owner)->withSession(['active_role' => 'seller']);
    }

    public function test_seller_can_create_product_with_weight_in_kg_and_dimensions_in_cm(): void
    {
        $response = $this->post('/seller/products', [
            'name' => 'Portable Bluetooth Speaker',
            'category_id' => $this->category->id,
            'price' => 1499.00,
            'stock' => 25,
            'weight' => 2,
            'length' => 40,
            'width' => 30,
            'height' => 20,
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $product = Product::where('name', 'Portable Bluetooth Speaker')->firstOrFail();
        $this->assertSame('2 kg', $product->weight);
        $this->assertSame('40 × 30 × 20 cm', $product->dimensions);
        $this->assertSame('2', $product->weight_value);
        $this->assertSame('40', $product->length);
        $this->assertSame('30', $product->width);
        $this->assertSame('20', $product->height);
    }

    public function test_product_edit_page_populates_weight_and_dimension_fields(): void
    {
        $product = Product::create([
            'seller_id' => $this->owner->id,
            'name' => 'Standing Desk Lamp',
            'category_id' => $this->category->id,
            'price' => 899.00,
            'stock' => 10,
            'weight' => '1.5 kg',
            'dimensions' => '45 × 15 × 12 cm',
            'status' => 'active',
        ]);

        $response = $this->get('/seller/products/'.$product->id.'/edit');
        $response->assertOk();
        $response->assertSee('Actual weight (kg)');
        $response->assertSee('Length (cm)');
        $response->assertSee('Width (cm)');
        $response->assertSee('Height (cm)');
        $response->assertSee('value="1.5"', false);
        $response->assertSee('value="45"', false);
        $response->assertSee('value="15"', false);
        $response->assertSee('value="12"', false);
    }

    public function test_seller_can_update_weight_and_dimensions(): void
    {
        $product = Product::create([
            'seller_id' => $this->owner->id,
            'name' => 'Kitchen Blender',
            'category_id' => $this->category->id,
            'price' => 2500.00,
            'stock' => 5,
            'weight' => '2 kg',
            'dimensions' => '40 × 30 × 20 cm',
            'status' => 'active',
        ]);

        $response = $this->post('/seller/products/'.$product->id, [
            'name' => 'Kitchen Blender Pro',
            'category_id' => $this->category->id,
            'price' => 2500.00,
            'stock' => 5,
            'weight' => 3.5,
            'length' => 50,
            'width' => 25,
            'height' => 18,
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $product->refresh();

        $this->assertSame('Kitchen Blender Pro', $product->name);
        $this->assertSame('3.5 kg', $product->weight);
        $this->assertSame('50 × 25 × 18 cm', $product->dimensions);
        $this->assertSame('3.5', $product->weight_value);
        $this->assertSame('50', $product->length);
        $this->assertSame('25', $product->width);
        $this->assertSame('18', $product->height);
    }
}
