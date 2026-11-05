<?php

namespace Tests\Feature;

use App\Models\{Category, Product, User};
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class AdminProductCatalogTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        // Search supports SKU in the real product table.
        \Illuminate\Support\Facades\Schema::table('products', fn ($t) => $t->string('sku')->nullable());
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'catalog@example.test', 'password' => 'Password123!',
            'is_admin' => true, 'status' => 'active', 'account_status' => 'active']));
    }

    public function test_each_category_link_filters_correctly_even_with_ampersands_and_missing_slugs(): void
    {
        foreach (['Beauty & Health', 'Home & Living', 'Sports & Outdoors', 'Electronics'] as $i => $name) {
            $cat = Category::create(['name' => $name, 'slug' => $i === 3 ? null : strtolower(str_replace(' ', '-', $name)), 'active' => true]);
            $p = Product::create(['name' => $name.' fixture', 'category_id' => $cat->id, 'price' => 20, 'status' => 'inactive']);
            $all = $this->get('/admin/products?page=2')->assertOk();
            $all->assertSee(route('admin.products', ['category' => $cat->id]), false);
            $all->assertViewHas('categories', fn ($categories) => $categories->find($cat->id)->products_count === 1);
            $this->get(route('admin.products', ['category' => $cat->id]))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->id === $p->id);
            if ($cat->slug) {
                $this->get(route('admin.products', ['category' => $cat->slug]))->assertOk()
                    ->assertViewHas('products', fn ($products) => $products->total() === 1);
            }
        }
    }

    public function test_search_and_category_survive_pagination_and_all_products_are_reachable(): void
    {
        $cat = Category::create(['name' => 'Sports & Outdoors', 'slug' => 'sports-&-outdoors']);
        $ids = [];
        for ($i = 0; $i < 13; $i++) {
            $ids[] = Product::create(['name' => 'Ball & bat '.$i, 'category_id' => $cat->id, 'price' => 20, 'status' => 'active'])->id;
        }
        Product::create(['name' => 'Unrelated', 'price' => 20]);
        $url = route('admin.products', ['category' => $cat->id, 'search' => 'Ball & bat']);
        $first = $this->get($url)->assertOk();
        $page = $first->viewData('products');
        $this->assertSame(13, $page->total());
        $next = $this->get($page->nextPageUrl())->assertOk();
        $second = $next->viewData('products');
        $this->assertSame(13, $second->total());
        $this->assertCount(3, $second);
        $seen = array_merge($page->pluck('id')->all(), $second->pluck('id')->all());
        sort($seen); sort($ids);
        $this->assertSame($ids, $seen);
        $next->assertSee('name="category" value="'.$cat->id.'"', false);
    }

    public function test_admin_shows_saved_cover_and_both_gallery_image_columns(): void
    {
        $relative = 'products/admin-preview-'.uniqid().'.png';
        $file = storage_path('app/public/'.$relative);
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP9sAAAAASUVORK5CYII='));
        try {
            $cover = Product::create(['name' => 'Saved cover', 'price' => 20, 'image' => $relative]);
            foreach (['path', 'image_path'] as $column) {
                $p = Product::create(['name' => 'Gallery '.$column, 'price' => 20]);
                \App\Models\ProductImage::create(['product_id' => $p->id, $column => $relative]);
            }
            $response = $this->get('/admin/products')->assertOk();
            $this->assertSame(3, substr_count($response->getContent(), 'src="'.$cover->primary_image_url.'"'));
            $response->assertSee('aria-label="Preview Saved cover"', false);
        } finally {
            unlink($file);
        }
    }
}
