<?php

namespace Tests\Feature;

use App\Models\{Product, Seller, User};
use App\Support\LandingFeatures;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class LandingProductFeaturesTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $admin;
    private User $seller;
    private Product $regular;
    private Product $discounted;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        $this->admin = $this->account('admin', true);
        $this->seller = $this->account('seller');
        Seller::create(['user_id' => $this->seller->id, 'store_name' => 'Featured store', 'status' => 'approved', 'approval_status' => 'approved']);
        $this->regular = Product::create(['seller_id' => $this->seller->id, 'name' => 'Regular fixture', 'price' => 1000, 'status' => 'active', 'stock' => 10]);
        $this->discounted = Product::create(['seller_id' => $this->seller->id, 'name' => 'Sale fixture', 'price' => 750, 'compare_at_price' => 1000, 'status' => 'active', 'stock' => 10]);
    }

    private function account(string $role, bool $admin = false): User
    {
        return User::create(['name' => $role, 'email' => uniqid().'@example.test', 'password' => 'Password123!',
            'role' => $role, 'is_admin' => $admin, 'status' => 'active', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_deals_only_include_discounts_and_never_automatically_feature_them(): void
    {
        $this->get('/')->assertOk()->assertViewHas('deals', fn ($items) => array_column($items, 'id') === [$this->discounted->id])
            ->assertViewHas('featured', [])->assertDontSee('id="landing-sort"', false)->assertDontSee('Sort deals');
        $this->discounted->update(['compare_at_price' => null]);
        $this->get('/')->assertOk()->assertViewHas('deals', [])->assertSee('No deals right now');
    }

    public function test_admin_can_select_multiple_products_and_remove_a_pick(): void
    {
        $this->actingAs($this->admin);
        foreach ([$this->regular, $this->discounted] as $p) {
            $this->post('/admin/products/'.$p->id.'/feature', ['featured' => 1])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->get('/admin/products')->assertOk()->assertSee('Remove from featured');
        $response = $this->get('/')->assertOk();
        $response->assertViewHas('featured', fn ($items) => count($items) === 2)->assertViewHas('deals', fn ($items) => count($items) === 1);
        $this->post('/admin/products/'.$this->regular->id.'/feature', ['featured' => 0])->assertRedirect();
        $this->assertSame([$this->discounted->id], LandingFeatures::ids());
        $this->get('/')->assertViewHas('featured', fn ($items) => array_column($items, 'id') === [$this->discounted->id]);
    }

    public function test_deals_include_every_discounted_item_and_exclude_equal_or_higher_prices(): void
    {
        $ids = [$this->discounted->id];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = Product::create(['seller_id' => $this->seller->id, 'name' => 'Sale item '.$i,
                'price' => 50, 'compare_at_price' => 100, 'status' => 'active', 'stock' => 10])->id;
        }
        foreach ([100, 120] as $price) {
            Product::create(['seller_id' => $this->seller->id, 'name' => 'Not discounted '.$price,
                'price' => $price, 'compare_at_price' => 100, 'status' => 'active']);
        }
        $response = $this->get('/')->assertOk();
        $deals = $response->viewData('deals');
        $this->assertEqualsCanonicalizing($ids, array_column($deals, 'id'));
        foreach ($deals as $deal) $this->assertLessThan($deal['compare_at'], $deal['price']);
        $response->assertSee('data-deals-carousel', false)->assertSee('Pause movement');
    }

    public function test_tiny_price_differences_that_round_to_zero_percent_do_not_appear_in_deals(): void
    {
        $headphones = Product::create(['seller_id' => $this->seller->id, 'name' => 'Noise-Cancelling Headphones Pro',
            'price' => 3748.97, 'compare_at_price' => 3749, 'status' => 'active', 'stock' => 10]);
        $this->get('/')->assertOk()->assertViewHas('deals', fn ($items) => array_column($items, 'id') === [$this->discounted->id])
            ->assertDontSee($headphones->name);
        $this->assertSame(3748.97, (float) $headphones->fresh()->price);
    }

    public function test_sellers_buyers_guests_and_unflagged_admins_cannot_feature_products(): void
    {
        $url = '/admin/products/'.$this->regular->id.'/feature';
        $this->postJson($url, ['featured' => 1])->assertUnauthorized();
        foreach ([$this->seller, $this->account('buyer'), $this->account('admin')] as $user) {
            $this->actingAs($user)->postJson($url, ['featured' => 1])->assertForbidden();
        }
        $this->assertSame([], LandingFeatures::ids());
    }

    public function test_hidden_or_deleted_products_and_unapproved_sellers_are_excluded(): void
    {
        $this->actingAs($this->admin)->post('/admin/products/'.$this->discounted->id.'/feature', ['featured' => 1])->assertRedirect();
        $this->discounted->update(['status' => 'suspended']);
        $this->get('/')->assertViewHas('featured', [])->assertViewHas('deals', []);
        $this->post('/admin/products/'.$this->discounted->id.'/feature', ['featured' => 1])->assertSessionHasErrors('featured');
        $this->discounted->update(['status' => 'active']);
        $this->seller->seller->update(['status' => 'inactive', 'approval_status' => 'pending']);
        $this->get('/')->assertViewHas('featured', [])->assertViewHas('deals', []);
        $this->seller->seller->update(['status' => 'approved', 'approval_status' => 'approved']);
        $this->discounted->delete();
        $this->get('/')->assertViewHas('featured', [])->assertViewHas('deals', []);
    }
}
