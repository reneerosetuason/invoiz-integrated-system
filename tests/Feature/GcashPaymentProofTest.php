<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class GcashPaymentProofTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $buyer;

    private User $seller;

    private Address $address;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        (require database_path('migrations/2026_11_04_120000_add_proof_path_to_payments_table.php'))->up();
        Storage::fake('private');
        Storage::fake('public');
        (require database_path('migrations/2026_11_04_140000_add_gcash_escrow_workflow.php'))->up();
        DB::table('gcash_escrow_settings')->insert(['account_name' => 'Platform', 'account_details' => 'Test receiving account', 'created_at' => now(), 'updated_at' => now()]);
        $this->buyer = $this->account('buyer');
        $this->seller = $this->account('seller');
        $this->product = $this->storeProduct($this->seller);
        $this->address = Address::create([
            'buyer_id' => $this->buyer->id, 'recipient_name' => 'Test Buyer', 'phone' => '09171234567',
            'address_line' => '2 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila',
            'province' => 'Metro Manila', 'is_default' => true,
        ]);
        $this->actingAs($this->buyer);
    }

    private function account(string $role): User
    {
        return User::create([
            'name' => 'Test '.$role, 'email' => uniqid($role).'@example.test', 'password' => 'Password123!',
            'role' => $role, 'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function storeProduct(User $seller): Product
    {
        Storage::disk('public')->put('seller-gcash-qr/'.$seller->id.'.png', $this->imageBytes());
        Seller::create([
            'gcash_qr_path' => 'seller-gcash-qr/'.$seller->id.'.png', 'gcash_account_name' => $seller->name,
            'user_id' => $seller->id, 'store_name' => 'Test Store', 'status' => 'active',
            'approval_status' => 'approved', 'vacation_mode' => false, 'address_line' => '1 Test Street',
            'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila',
        ]);

        return Product::create(['seller_id' => $seller->id, 'name' => 'Test product', 'price' => 100, 'stock' => 10, 'status' => 'active']);
    }

    private function checkout(array $overrides = []): array
    {
        return $overrides + [
            'address_id' => $this->address->id, 'payment_method' => 'gcash', 'checkout_mode' => 'single',
            'single' => $this->product->id, 'qty' => 1,
        ];
    }

    private function imageBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=');
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='
        ));
    }

    public function test_gcash_requires_a_real_image_with_a_size_limit(): void
    {
        $this->post('/checkout', $this->checkout())->assertSessionHasErrors('gcash_proof');
        $this->post('/checkout', $this->checkout(['gcash_proof' => UploadedFile::fake()->create('receipt.jpg', 10, 'text/plain')]))
            ->assertSessionHasErrors('gcash_proof');
        $this->post('/checkout', $this->checkout(['gcash_proof' => $this->receipt()->size(5121)]))
            ->assertSessionHasErrors('gcash_proof');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_single_checkout_stores_a_private_receipt_and_keeps_payment_pending(): void
    {
        $this->post('/checkout', $this->checkout(['gcash_proof' => $this->receipt()]))
            ->assertRedirect('/orders')->assertSessionHasNoErrors()->assertSessionHas('success', fn ($message) => str_contains($message, 'GCash'));
        $order = Order::firstOrFail();
        $payment = $order->payment;
        $this->assertSame('gcash', $payment->method);
        $this->assertSame('pending', $payment->status);
        $this->assertNotSame('paid', $order->payment_status);
        Storage::disk('private')->assertExists($payment->proof_path);
        $this->assertStringStartsWith('payment-proofs/', $payment->proof_path);
        $this->assertArrayNotHasKey('proof_path', $payment->toArray());
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'gcash', 'status' => 'pending']);
        $this->assertDatabaseHas('deliveries', ['order_id' => $order->id, 'payment_method' => 'gcash', 'amount_to_collect' => 0]);
    }

    public function test_receipt_is_visible_only_to_its_buyer_and_seller(): void
    {
        $file = $this->receipt();
        $bytes = file_get_contents($file->getRealPath());
        $this->post('/checkout', $this->checkout(['gcash_proof' => $file]))->assertRedirect('/orders');
        $order = Order::firstOrFail();
        $this->get('/orders/'.$order->id.'/payment-proof')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($bytes);
        $this->actingAs($this->seller)->withSession(['active_role' => 'seller'])->get('/seller/orders/'.$order->id.'/payment-proof')->assertOk()->assertStreamedContent($bytes);
        $otherSeller = $this->account('seller');
        $this->storeProduct($otherSeller);
        $this->actingAs($otherSeller)->get('/seller/orders/'.$order->id.'/payment-proof')->assertNotFound();
        $this->actingAs($this->account('buyer'))->get('/orders/'.$order->id.'/payment-proof')->assertNotFound();
        $this->get('/storage/private/'.$order->payment->proof_path)->assertNotFound();
        auth()->guard('web')->logout();
        $this->get('/orders/'.$order->id.'/payment-proof')->assertUnauthorized();
    }

    public function test_failed_checkout_removes_uploaded_receipt_and_rolls_back(): void
    {
        $this->product->update(['stock' => 0]);
        $this->post('/checkout', $this->checkout(['gcash_proof' => $this->receipt()]))
            ->assertSessionHas('error');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_multi_store_checkout_attaches_receipt_to_each_order(): void
    {
        $otherSeller = $this->account('seller');
        $otherProduct = $this->storeProduct($otherSeller);
        $cart = Cart::headerFor($this->buyer->id);
        foreach ([$this->product, $otherProduct] as $product) {
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        }
        $this->post('/checkout', $this->checkout(['checkout_mode' => 'all', 'gcash_proofs' => [$this->seller->id => $this->receipt(), $otherSeller->id => $this->receipt()]]))
            ->assertRedirect('/orders')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(2, Payment::where('method', 'gcash')->whereNotNull('proof_path')->count());
        $this->assertCount(2, Storage::disk('private')->allFiles());
        $this->assertSame(9, $this->product->fresh()->stock);
        $this->assertSame(9, $otherProduct->fresh()->stock);
    }

    public function test_cod_does_not_accept_a_gcash_receipt(): void
    {
        $this->post('/checkout', $this->checkout(['payment_method' => 'cod', 'gcash_proof' => $this->receipt()]))
            ->assertSessionHasErrors('gcash_proof');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_api_checkout_accepts_receipt_without_exposing_storage_path(): void
    {
        DB::table('gcash_escrow_settings')->delete();
        $cart = Cart::headerFor($this->buyer->id);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        Sanctum::actingAs($this->buyer);
        $response = $this->post('/api/orders/checkout', [
            'address_id' => $this->address->id, 'payment_method' => 'gcash', 'gcash_proof' => $this->receipt(),
        ], ['Accept' => 'application/json']);
        $response->assertCreated()->assertJsonPath('order.payment.method', 'gcash')->assertJsonMissingPath('order.payment.proof_path');
        $this->getJson('/api/orders/'.$response->json('order.id').'/payment-proof')->assertOk();
    }

    public function test_checkout_renders_gcash_upload_and_validation_errors(): void
    {
        $this->get('/checkout?single='.$this->product->id)->assertOk()
            ->assertSee('multipart/form-data', false)->assertSee('GCash payment receipt')->assertSee('checkout-payment.js');
    }

    public function test_web_quote_shows_store_account_and_exact_amount_before_payment(): void
    {
        $this->postJson('/checkout/gcash-quote', ['checkout_mode' => 'single', 'single' => $this->product->id, 'qty' => 2])
            ->assertOk()->assertJsonPath('accounts.0.seller_id', $this->seller->id)
            ->assertJsonPath('accounts.0.amount', 250)->assertJsonPath('accounts.0.available', true)
            ->assertJsonPath('accounts.0.account_name', $this->seller->name)
            ->assertJsonPath('accounts.0.qr_url', route('buyer.gcash.qr', $this->seller->id));
    }

    public function test_missing_store_qr_blocks_gcash_checkout_without_creating_orders(): void
    {
        $this->seller->seller->update(['gcash_qr_path' => null]);
        $this->post('/checkout', $this->checkout(['gcash_proof' => $this->receipt()]))->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_multi_store_gcash_rejects_one_combined_receipt(): void
    {
        $product = $this->storeProduct($this->account('seller'));
        $cart = Cart::headerFor($this->buyer->id);
        foreach ([$product, $this->product] as $item) {
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $item->id, 'quantity' => 1]);
        }
        $this->post('/checkout', $this->checkout(['checkout_mode' => 'all', 'gcash_proof' => $this->receipt()]))->assertSessionHasErrors('gcash_proof');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_buyer_can_upload_receipt_without_platform_setup_and_payment_stays_pending(): void
    {
        DB::table('gcash_escrow_settings')->delete();
        $url = route('buyer.gcash.qr', $this->seller->id);
        $this->postJson('/checkout/gcash-quote', ['checkout_mode' => 'single', 'single' => $this->product->id])
            ->assertOk()->assertJsonPath('accounts.0.qr_url', $url)
            ->assertJsonPath('accounts.0.available', true)->assertJsonPath('accounts.0.unavailable_reason', null);
        $this->get('/checkout?single='.$this->product->id)->assertOk()->assertSee('src="'.$url.'"', false)
            ->assertSee('GCash payment receipt')->assertDontSee('Please do not send a payment yet.');
        $this->post('/checkout', $this->checkout(['gcash_proofs' => [$this->seller->id => $this->receipt()]]))
            ->assertRedirect('/orders')->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment->status);
        Storage::disk('private')->assertExists($order->payment->proof_path);
        $this->assertDatabaseCount('gcash_escrow_deposits', 0);
        $this->actingAs($this->seller)->withSession(['active_role' => 'seller'])
            ->post(route('seller.orders.status', $order), ['action' => 'accept'])->assertSessionHasErrors('gcash');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_receiving_qr_is_served_directly_without_a_public_storage_link(): void
    {
        $path = $this->seller->seller->gcash_qr_path;
        Storage::disk('public')->put($path, $this->imageBytes());
        $this->get(route('buyer.gcash.qr', $this->seller->id))->assertOk()
            ->assertHeader('content-type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('public')->delete($path);
        $this->get(route('buyer.gcash.qr', $this->seller->id))->assertNotFound();
    }

    public function test_receiving_qr_route_rejects_non_images_and_unapproved_stores(): void
    {
        $store = $this->seller->seller;
        Storage::disk('public')->put($store->gcash_qr_path, '<html>not an image</html>');
        $this->get(route('buyer.gcash.qr', $this->seller->id))->assertNotFound();
        Storage::disk('public')->put($store->gcash_qr_path, $this->imageBytes());
        $store->update(['status' => 'pending', 'approval_status' => 'pending']);
        $this->get(route('buyer.gcash.qr', $this->seller->id))->assertNotFound();
    }
}
