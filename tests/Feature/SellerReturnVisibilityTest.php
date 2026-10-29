<?php

namespace Tests\Feature;

use App\Models\{Order, OrderItem, Product, ReturnRequest, Seller, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class SellerReturnVisibilityTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::table('return_requests', function (Blueprint $table) {
            $table->text('decision_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });
        Schema::create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    private function account(string $name, string $role = 'buyer'): User
    {
        return User::create([
            'name' => $name, 'email' => $name.'@example.test', 'password' => 'Password123!',
            'role' => $role, 'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
    }

    private function seller(string $name): User
    {
        $owner = $this->account($name, 'seller');
        $seller = Seller::create(['user_id' => $owner->id, 'store_name' => $name, 'status' => 'approved']);
        // A store record ID must not be confused with the seller's user ID.
        DB::table('sellers')->where('id', $seller->id)->update(['id' => 1000 + $owner->id]);

        return $owner;
    }

    private function purchase(User $buyer, User $seller, string $name): array
    {
        $product = Product::create(['seller_id' => $seller->id, 'name' => $name, 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'delivered', 'total_amount' => 250, 'shipping_fee' => 50]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $seller->id, 'product_name' => $name, 'quantity' => 2, 'price' => 100]);

        return [$order, $item];
    }

    private function sellerSession(User $seller): self
    {
        Auth::forgetGuards();

        return $this->actingAs($seller)->withSession(['active_role' => 'seller']);
    }

    public function test_mobile_return_appears_on_the_exact_sellers_web_page_and_can_be_reviewed(): void
    {
        $buyer = $this->account('return-buyer');
        $seller = $this->seller('return-owner');
        $other = $this->seller('other-owner');
        [$order, $item] = $this->purchase($buyer, $seller, 'Requested fixture item');
        Sanctum::actingAs($buyer);
        $created = $this->postJson('/api/orders/'.$order->id.'/returns', [
            'order_item_id' => $item->id, 'reason' => 'wrong_item', 'quantity' => 1, 'reason_details' => 'wrong',
        ])->assertCreated()->assertJsonPath('return.seller_id', $seller->id);
        $returnId = $created->json('return.id');
        $this->assertDatabaseHas('seller_notifications', ['seller_id' => $seller->id, 'type' => 'return_requested', 'order_id' => $order->id]);
        $this->postJson('/api/orders/'.$order->id.'/returns', [
            'order_item_id' => $item->id, 'reason' => 'wrong_item', 'quantity' => 1,
        ])->assertStatus(409)->assertJsonPath('return.id', $returnId);
        $this->assertDatabaseCount('return_requests', 1);
        $this->assertDatabaseCount('seller_notifications', 1);

        $this->sellerSession($seller)->get('/seller/returns')->assertOk()
            ->assertSee('Requested fixture item')->assertSee('Wrong Item')
            ->assertSee(route('seller.returns.update', $returnId), false)
            ->assertViewHas('returns', fn ($returns) => $returns->pluck('id')->all() === [$returnId])
            ->assertViewHas('counts', fn ($counts) => $counts['requested'] === 1);

        $this->sellerSession($other)->get('/seller/returns')->assertOk()
            ->assertDontSee('Requested fixture item')
            ->assertViewHas('returns', fn ($returns) => $returns->isEmpty());
        $this->sellerSession($other)->post('/seller/returns/'.$returnId, ['action' => 'approve'])->assertForbidden();

        $this->sellerSession($seller)->from('/seller/returns')
            ->post('/seller/returns/'.$returnId, ['action' => 'approve', 'note' => 'Please return the item.'])
            ->assertRedirect('/seller/returns')->assertSessionHasNoErrors();
        $this->assertSame('approved', ReturnRequest::findOrFail($returnId)->status);
        $this->sellerSession($seller)->getJson('/seller/returns')->assertOk()
            ->assertJsonPath('counts.approved', 1)->assertJsonPath('counts.requested', 0);
        Auth::forgetGuards();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/orders/'.$order->id)->assertOk()->assertJsonPath('order.returns.0.status', 'approved');
    }

    public function test_seller_refresh_feed_is_scoped_and_changes_when_a_buyer_saves_a_request(): void
    {
        $buyer = $this->account('return-buyer');
        $seller = $this->seller('return-owner');
        $other = $this->seller('other-owner');
        [$order, $item] = $this->purchase($buyer, $seller, 'Live return item');
        $initial = $this->sellerSession($seller)->getJson('/seller/returns')->assertOk()->assertJsonPath('counts.all', 0);
        $initialVersion = $initial->json('version');
        Auth::forgetGuards();
        Sanctum::actingAs($buyer);
        $created = $this->postJson('/api/orders/'.$order->id.'/returns', [
            'order_item_id' => $item->id, 'reason' => 'damaged', 'reason_details' => 'broken',
        ])->assertCreated();
        $feed = $this->sellerSession($seller)->getJson('/seller/returns')->assertOk()->assertJsonPath('counts.all', 1);
        $this->assertNotSame($initialVersion, $feed->json('version'));
        $this->assertStringContainsString('Live return item', $feed->json('html'));
        $this->assertStringContainsString('Return #'.$created->json('return.id'), $feed->json('html'));
        $otherFeed = $this->sellerSession($other)->getJson('/seller/returns?seller_id='.$seller->id)
            ->assertOk()->assertJsonPath('counts.all', 0);
        $this->assertStringNotContainsString('Live return item', $otherFeed->json('html'));
        $notificationId = DB::table('seller_notifications')->value('id');
        $this->sellerSession($other)->get('/seller/notifications/'.$notificationId.'/read')->assertNotFound();
        $this->sellerSession($seller)->get('/seller/notifications/'.$notificationId.'/read')->assertRedirect('/seller/returns');
        $this->assertDatabaseHas('seller_notifications', ['id' => $notificationId, 'is_read' => true]);
    }

    public function test_return_filter_shows_only_the_buyers_orders_with_cases_and_keeps_delivery_status(): void
    {
        $buyer = $this->account('return-buyer');
        $otherBuyer = $this->account('other-buyer');
        $seller = $this->seller('return-owner');
        [$order, $item] = $this->purchase($buyer, $seller, 'Returned item');
        $this->purchase($buyer, $seller, 'Delivered without return');
        [$foreign, $foreignItem] = $this->purchase($otherBuyer, $seller, 'Foreign return item');
        foreach ([[$order, $item, $buyer], [$foreign, $foreignItem, $otherBuyer]] as [$purchase, $line, $owner]) {
            ReturnRequest::create(['order_id' => $purchase->id, 'order_item_id' => $line->id, 'buyer_id' => $owner->id,
                'seller_id' => $seller->id, 'product_id' => $line->product_id, 'quantity' => 1, 'reason' => 'damaged',
                'status' => 'requested', 'refund_amount' => 100]);
        }
        Auth::forgetGuards();
        Sanctum::actingAs($buyer);
        foreach (['requested', 'approved', 'rejected', 'refunded', 'cancelled'] as $status) {
            ReturnRequest::where('order_id', $order->id)->update(['status' => $status]);
            $this->getJson('/api/orders?status=return')->assertOk()->assertJsonCount(1, 'orders')
                ->assertJsonPath('orders.0.id', $order->id)->assertJsonPath('orders.0.status', 'delivered')
                ->assertJsonPath('orders.0.returns.0.status', $status);
        }
        $this->getJson('/api/orders?status=all')->assertOk()->assertJsonCount(2, 'orders');
        $this->getJson('/api/orders?status=cancelled')->assertOk()->assertJsonCount(0, 'orders');
    }

    public function test_unsent_and_rejected_submissions_do_not_create_seller_requests(): void
    {
        $buyer = $this->account('return-buyer');
        $seller = $this->seller('return-owner');
        [$order, $item] = $this->purchase($buyer, $seller, 'Unsubmitted fixture item');
        $this->sellerSession($seller)->get('/seller/returns')->assertOk()
            ->assertSee('No return requests yet.')->assertDontSee('Unsubmitted fixture item');
        Auth::forgetGuards();
        Sanctum::actingAs($buyer);
        $this->postJson('/api/orders/'.$order->id.'/returns', ['order_item_id' => $item->id, 'reason' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason_details');
        $this->assertDatabaseCount('return_requests', 0);
    }
}
