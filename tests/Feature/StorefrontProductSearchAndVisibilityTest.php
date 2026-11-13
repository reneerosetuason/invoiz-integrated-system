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

class StorefrontProductSearchAndVisibilityTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $sellerUser;

    private Seller $seller;

    private Category $category;

    private Product $regularProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();

        if (! Schema::hasColumn('products', 'dimensions')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('dimensions')->nullable();
            });
        }

        $this->sellerUser = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@example.test',
            'password' => 'Password123!',
            'role' => 'seller',
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->seller = Seller::create([
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Hewang Official Store',
            'status' => 'approved',
            'approval_status' => 'approved',
            'address_line' => '123 Market St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
        ]);

        $this->category = Category::create([
            'name' => 'Gadgets',
            'status' => 'active',
            'active' => 1,
        ]);

        // Regular product not on sale
        $this->regularProduct = Product::create([
            'seller_id' => $this->sellerUser->id,
            'category_id' => $this->category->id,
            'name' => 'hewang wireless earbuds',
            'description' => 'High quality hewang sound earbuds',
            'price' => 1299.00,
            'compare_at_price' => null,
            'stock' => 50,
            'status' => 'active',
        ]);
    }

    public function test_guest_can_search_and_find_non_sale_product(): void
    {
        $response = $this->get('/shop?search=hewang');
        $response->assertOk();
        $response->assertSee('hewang wireless earbuds');
        $response->assertDontSee('No products found');
    }

    public function test_buyer_can_search_and_find_non_sale_product(): void
    {
        $buyer = User::create([
            'name' => 'Jane Buyer',
            'email' => 'jane@example.test',
            'password' => 'Password123!',
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($buyer)->withSession(['active_role' => 'buyer'])
            ->get('/shop?search=hewang');

        $response->assertOk();
        $response->assertSee('hewang wireless earbuds');
    }

    public function test_all_active_products_appear_on_shop_catalog_without_requiring_sale(): void
    {
        $response = $this->get('/shop');
        $response->assertOk();
        $response->assertSee('hewang wireless earbuds');
    }

    public function test_guest_and_buyer_can_view_product_detail_page(): void
    {
        $guestResponse = $this->get('/product/'.$this->regularProduct->id);
        $guestResponse->assertOk();
        $guestResponse->assertSee('hewang wireless earbuds');

        $buyer = User::create([
            'name' => 'John Buyer',
            'email' => 'john@example.test',
            'password' => 'Password123!',
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $buyerResponse = $this->actingAs($buyer)->withSession(['active_role' => 'buyer'])
            ->get('/product/'.$this->regularProduct->id);
        $buyerResponse->assertOk();
        $buyerResponse->assertSee('hewang wireless earbuds');
    }

    public function test_product_with_multiple_images_renders_swipeable_gallery_and_thumbnails(): void
    {
        $multiImageProduct = Product::create([
            'seller_id' => $this->sellerUser->id,
            'category_id' => $this->category->id,
            'name' => 'Multi Photo Smartphone',
            'price' => 9999.00,
            'stock' => 10,
            'status' => 'active',
            'image' => 'products/cover.jpg',
        ]);

        \App\Models\ProductImage::create([
            'product_id' => $multiImageProduct->id,
            'path' => 'products/angle1.jpg',
            'is_main' => 1,
            'sort_order' => 0,
        ]);
        \App\Models\ProductImage::create([
            'product_id' => $multiImageProduct->id,
            'path' => 'products/angle2.jpg',
            'is_main' => 0,
            'sort_order' => 1,
        ]);
        \App\Models\ProductImage::create([
            'product_id' => $multiImageProduct->id,
            'path' => 'products/angle3.jpg',
            'is_main' => 0,
            'sort_order' => 2,
        ]);

        $response = $this->get('/product/' . $multiImageProduct->id);
        $response->assertOk();
        $response->assertSee('id="galleryViewport"', false);
        $response->assertSee('id="galleryPrevBtn"', false);
        $response->assertSee('id="galleryNextBtn"', false);
        $response->assertSee('id="galleryCounter"', false);
        $response->assertSee('class="gallery-thumbs"', false);
        $response->assertSee('products/angle1.jpg');
        $response->assertSee('products/angle2.jpg');
        $response->assertSee('products/angle3.jpg');
    }

    public function test_searching_by_store_name_shows_store_card_and_store_products(): void
    {
        // $this->seller has store_name = 'Hewang Official Store'
        // $this->regularProduct belongs to $this->seller
        $response = $this->get('/shop?search=Hewang+Official+Store');
        $response->assertOk();
        $response->assertSee('Matching Stores');
        $response->assertSee('Hewang Official Store');
        $response->assertSee('Visit Store');
        $response->assertSee('hewang wireless earbuds');
    }
}
