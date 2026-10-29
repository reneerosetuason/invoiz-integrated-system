<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, CartItem, Category, Product, ProductVariant, Seller, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Tests\TestCase;

class BuyerWebShoppingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Entirely isolated fixtures: never migrate or write the configured MySQL database.
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','session.driver'=>'array']);
        DB::purge('sqlite');
        Mail::fake();
        $tables = [
            'users'=>'name first_name last_name email password role status account_status approval_status is_admin email_verified_at remember_token profile_picture phone address_line barangay city municipality province region postal_code',
            'categories'=>'name slug active status parent_id deleted_at',
            'products'=>'seller_id category_id name slug description price compare_at_price stock status deleted_at views_count cart_additions rating image brand',
            'product_variants'=>'product_id stock price_adjustment status variant_type variant_value deleted_at',
            'sellers'=>'user_id store_name business_name status approval_status deleted_at vacation_mode address_line barangay city province',
            'seller_profiles'=>'seller_id business_info deleted_at',
            'logistics_settings'=>'user_id',
            'carts'=>'buyer_id',
            'cart_items'=>'cart_id product_id variant_id quantity',
            'favorites'=>'buyer_id product_id',
            'reviews'=>'buyer_id product_id order_id rating comment status seller_reply',
            'addresses'=>'buyer_id recipient_name phone address_line barangay city province region postal_code is_default',
            'orders'=>'seller_id buyer_id address_id total total_amount sub_total discount_total shipping_fee shipping_address status payment_status order_number deleted_at',
            'order_items'=>'order_id product_id product_variant_id seller_id product_name variant_label quantity price unit_price total_price',
            'order_status_histories'=>'order_id from_status to_status note notes',
            'deliveries'=>'order_id seller_id status payment_method amount_to_collect delivery_fee tracking_number archived_at',
            'payments'=>'order_id method amount status',
            'payment_transactions'=>'order_id transaction_ref gateway amount status seller_earning platform_commission rider_fee logistics_fee refunded_amount paid_at',
            'vouchers'=>'seller_id code name discount_type type value discount_value used_count status min_spend max_discount usage_limit starts_at ends_at',
            'voucher_claims'=>'voucher_id buyer_id order_id claimed_at used_at',
            'order_vouchers'=>'order_id voucher_id discount_amount',
            'conversations'=>'buyer_id seller_id subject',
            'messages'=>'conversation_id sender_id receiver_id body is_read',
            'complaints'=>'buyer_id subject status resolution deleted_at',
            'notification_reads'=>'user_id notification_key',
            'settings'=>'key value',
            'sales'=>'seller_id name type discount_type discount_value starts_at ends_at status deleted_at',
            'product_sales'=>'sale_id product_id sale_price deleted_at',
        ];
        foreach ($tables as $name=>$columns) {
            Schema::create($name, function(Blueprint $table) use($columns) {
                $table->id();
                foreach(explode(' ',$columns) as $column) {
                    if(str_ends_with($column,'_id') || in_array($column,['stock','quantity','is_admin','is_default','active','vacation_mode','used_count','usage_limit','is_read'])) $table->integer($column)->nullable();
                    elseif(preg_match('/amount|price|total|fee|earning|commission|discount_value|adjustment/', $column)) $table->decimal($column,12,2)->nullable();
                    else $table->text($column)->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::table('carts',fn(Blueprint $t)=>$t->unique('buyer_id'));
    }

    public function shoppingFixture(bool $legacySession=true): array
    {
        $buyer=User::create(['name'=>'Preview Buyer','first_name'=>'Preview','last_name'=>'Buyer','email'=>'preview-buyer@example.test','password'=>'Password123!','role'=>'buyer','status'=>'active','account_status'=>'active','email_verified_at'=>now()]);
        $sellerUser=User::create(['name'=>'Preview Seller','first_name'=>'Preview','last_name'=>'Seller','email'=>'preview-seller@example.test','password'=>'Password123!','role'=>'seller','status'=>'active','account_status'=>'active']);
        Seller::create(['user_id'=>$sellerUser->id,'store_name'=>'Preview Store','status'=>'active','approval_status'=>'approved','vacation_mode'=>false,'address_line'=>'1 Test Street','barangay'=>'Test Barangay','city'=>'Manila','province'=>'Metro Manila']);
        $category=Category::create(['name'=>'Appliances','active'=>true]);
        $product=Product::create(['seller_id'=>$sellerUser->id,'category_id'=>$category->id,'name'=>'Preview fan','price'=>100,'stock'=>10,'status'=>'active']);
        $this->actingAs($buyer);
        if($legacySession) $this->withSession(['buyer'=>['id'=>$buyer->id,'first_name'=>'Preview','last_name'=>'Buyer','email'=>'preview-buyer@example.test']]);
        return [$buyer,$product];
    }

    private function line(User $buyer, Product $product, int $quantity=1, ?int $variantId=null): CartItem
    {
        return CartItem::create(['cart_id'=>Cart::headerFor($buyer->id)->id,'product_id'=>$product->id,'variant_id'=>$variantId,'quantity'=>$quantity]);
    }

    public function test_add_to_cart_repeats_and_keeps_variations_separate(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$product->update(['stock'=>20]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'variant_type'=>'Color','variant_value'=>'White','stock'=>8,'price_adjustment'=>20,'status'=>'active']);
        $this->post('/cart/add/'.$product->id,['qty'=>2])->assertRedirect('/cart');
        $this->post('/cart/add/'.$product->id,['qty'=>3])->assertRedirect('/cart');
        $this->post('/cart/add/'.$product->id,['qty'=>2,'variant_id'=>$variant->id])->assertRedirect('/cart');
        $this->assertDatabaseCount('cart_items',2);
        $this->assertDatabaseHas('cart_items',['variant_id'=>null,'quantity'=>5]);
        $this->assertDatabaseHas('cart_items',['variant_id'=>$variant->id,'quantity'=>2]);
    }

    public function test_variation_cannot_be_added_when_general_stock_is_zero(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$product->update(['stock'=>0]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>4,'price_adjustment'=>20,'status'=>'active']);
        $this->post('/cart/add/'.$product->id,['qty'=>2,'variant_id'=>$variant->id])->assertRedirect('/cart')->assertSessionHas('error');
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_invalid_or_inactive_variation_does_not_silently_add_base_product(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $other=Product::create(['name'=>'Other','price'=>40,'stock'=>5,'status'=>'active']);
        $variant=ProductVariant::create(['product_id'=>$other->id,'stock'=>4,'status'=>'active']);
        $this->from('/product/'.$product->id)->post('/cart/add/'.$product->id,['variant_id'=>$variant->id])->assertSessionHasErrors('variant_id');
        $variant->update(['product_id'=>$product->id,'status'=>'inactive']);
        $this->post('/cart/add/'.$product->id,['variant_id'=>$variant->id])->assertSessionHasErrors('variant_id');
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_inactive_product_cannot_be_added(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$product->update(['status'=>'inactive']);
        $this->post('/cart/add/'.$product->id)->assertRedirect('/cart')->assertSessionHas('error');
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_quantity_increment_cannot_bypass_200_item_cart_limit(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$product->update(['stock'=>500]);
        $line=$this->line($buyer,$product,200);
        $this->from('/cart')->post('/cart/inc/'.$line->id)->assertSessionHas('error');
        $this->assertSame(200,$line->fresh()->quantity);
    }

    public function test_adding_only_remaining_stock_uses_actual_quantity_for_cart_limit(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $line=$this->line($buyer,$product,9);
        $other=Product::create(['name'=>'Other','price'=>1,'stock'=>500,'status'=>'active']);$this->line($buyer,$other,190);
        $this->post('/cart/add/'.$product->id,['qty'=>2])->assertSessionHas('success');
        $this->assertSame(10,$line->fresh()->quantity);
        $this->assertSame(200,(int)Cart::itemsFor($buyer->id)->sum('quantity'));
    }

    public function test_cart_adjustments_use_item_ids_without_product_id_fallback(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$product->id=900;$product->exists=false;$product->save();
        $line=$this->line($buyer,$product,2);
        $this->post('/cart/dec/900')->assertRedirect('/cart');
        $this->assertSame(2,$line->fresh()->quantity);
        $this->post('/cart/remove/900')->assertRedirect('/cart');
        $this->assertNotNull($line->fresh());
        $this->post('/cart/dec/'.$line->id)->assertRedirect('/cart');
        $this->assertSame(1,$line->fresh()->quantity);
        $this->post('/cart/remove/'.$line->id)->assertRedirect('/cart');
        $this->assertNull($line->fresh());
    }

    public function test_selected_checkout_never_matches_product_ids(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $product->id=100;$product->exists=false;$product->save();
        $first=$this->line($buyer,$product); // item #1, product #100
        $other=Product::find(1);$second=$this->line($buyer,$other); // item #2, product #1
        $this->get('/buy/selected?ids='.$first->id)->assertRedirect('/checkout?selected=1');
        $this->get('/checkout?selected=1')->assertOk()->assertViewHas('sellerGroups',fn($groups)=>collect($groups)->flatMap(fn($g)=>$g['lines'])->pluck('item.id')->all()===[$first->id]);
        $this->assertSame([$first->id],session('checkout_selected'));
    }

    public function test_buy_now_keeps_selected_quantity_and_variation_in_preview(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>8,'price_adjustment'=>20,'status'=>'active']);
        $response=$this->get('/buy/'.$product->id.'?qty=3&variant_id='.$variant->id)->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertViewHas('singleQty',3)->assertViewHas('singleVariantId',$variant->id)->assertViewHas('singleUnit',120.0);
    }

    public function test_buy_now_rejects_sold_out_variation(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>0,'status'=>'active']);
        $this->from('/product/'.$product->id)->get('/buy/'.$product->id.'?variant_id='.$variant->id)->assertSessionHas('error');
        $this->assertFalse(session()->has('checkout_single'));
    }

    public function test_cart_page_uses_authenticated_buyer_without_legacy_session(): void
    {
        [$buyer,$product]=$this->shoppingFixture(false);$this->line($buyer,$product,2);
        $this->get('/cart')->assertOk()->assertSee('Preview fan')->assertSee('item-check',false)->assertDontSee('Your cart is empty');
    }

    public function test_product_keeps_add_button_after_first_cart_addition(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$this->line($buyer,$product);
        $this->get('/product/'.$product->id)->assertOk()->assertSee('onclick="openOpt()"',false)->assertSee('View Cart');
    }

    public function test_cart_summary_uses_sale_and_variation_price(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>8,'price_adjustment'=>20,'status'=>'active']);$this->line($buyer,$product,2,$variant->id);
        $this->get('/shop')->assertOk()->assertViewHas('cartTotal',240.0);
    }

    public function test_buyer_can_toggle_wishlist_and_browse_search_results(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $this->from('/shop')->post('/wishlist/'.$product->id.'/toggle')->assertRedirect('/shop');
        $this->get('/wishlist')->assertOk()->assertSee('Preview fan');
        $this->post('/wishlist/'.$product->id.'/toggle')->assertRedirect();
        $this->assertDatabaseCount('favorites',0);
        $this->get('/shop?search=Preview&sort=price_asc')->assertOk()->assertSee('Preview fan');
    }

    private function deliveryAddress(User $buyer): Address
    {
        return Address::create(['buyer_id'=>$buyer->id,'recipient_name'=>'Preview Buyer','phone'=>'09171234567','address_line'=>'2 Test Street','barangay'=>'Test Barangay','city'=>'Manila','province'=>'Metro Manila','is_default'=>true]);
    }

    public function test_buy_now_places_the_previewed_quantity_and_variation(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>8,'price_adjustment'=>20,'status'=>'active']);
        $response=$this->get('/buy/'.$product->id.'?qty=3&variant_id='.$variant->id)->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk();
        $this->post('/checkout',['checkout_mode'=>'single','address_id'=>$address->id,'payment_method'=>'cod'])->assertRedirect('/orders')->assertSessionHas('success');
        $this->assertDatabaseHas('order_items',['product_id'=>$product->id,'product_variant_id'=>$variant->id,'quantity'=>3,'unit_price'=>120,'total_price'=>360]);
        $this->assertDatabaseHas('orders',['buyer_id'=>$buyer->id,'total_amount'=>410]);
        $this->assertSame(5,(int)$variant->fresh()->stock);
        $this->assertSame(7,$product->fresh()->stock);
        $this->assertDatabaseCount('payments',1);
        $this->assertDatabaseCount('payment_transactions',1);
    }

    public function test_selected_order_removes_only_selected_lines_and_keeps_other_items(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $product->id=100;$product->exists=false;$product->save();
        $first=$this->line($buyer,$product,2);
        $other=Product::find(1);$second=$this->line($buyer,$other,3);
        $this->get('/buy/selected?ids='.$first->id)->assertRedirect();
        $this->post('/checkout',['checkout_mode'=>'selected','address_id'=>$address->id,'payment_method'=>'cod'])->assertRedirect('/orders')->assertSessionHas('success');
        $this->assertDatabaseCount('order_items',1);
        $this->assertDatabaseHas('order_items',['product_id'=>100,'quantity'=>2]);
        $this->assertNull($first->fresh());
        $this->assertSame(3,$second->fresh()->quantity);
        $this->assertSame(10,$other->fresh()->stock);
    }

    public function test_deleted_variation_is_not_converted_to_base_stock_during_checkout(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>8,'status'=>'active']);
        $line=$this->line($buyer,$product,2,$variant->id);$variant->delete();
        $this->get('/buy/selected?ids='.$line->id)->assertRedirect();
        $this->from('/checkout')->post('/checkout',['checkout_mode'=>'selected','address_id'=>$address->id,'payment_method'=>'cod'])->assertSessionHas('error');
        $this->assertDatabaseCount('orders',0);
        $this->assertSame(10,$product->fresh()->stock);
        $this->assertNotNull($line->fresh());
    }

    public function test_voucher_claims_are_usable_and_marked_used_after_checkout(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $voucher=\App\Models\Voucher::create(['code'=>'PREVIEW10','discount_type'=>'fixed','discount_value'=>10,'min_spend'=>0,'status'=>'active','used_count'=>0]);
        $this->post('/vouchers/'.$voucher->id.'/claim')->assertSessionHas('success');
        $this->get('/vouchers')->assertOk()->assertViewHas('claimedIds',fn($ids)=>in_array($voucher->id,$ids,true));
        $claim=\App\Models\VoucherClaim::firstOrFail();
        $line=$this->line($buyer,$product,2);$this->get('/buy/selected?ids='.$line->id)->assertRedirect();
        $this->post('/checkout',['checkout_mode'=>'selected','address_id'=>$address->id,'payment_method'=>'cod','voucher_claim_ids'=>[$claim->id]])->assertRedirect('/orders')->assertSessionHas('success');
        $this->assertDatabaseHas('orders',['total_amount'=>240]);
        $this->assertNotNull($claim->fresh()->used_at);
        $this->assertSame(1,(int)$voucher->fresh()->used_count);
    }

    public function test_invalid_quantity_and_selection_inputs_return_validation_errors(): void
    {
        [$buyer,$product]=$this->shoppingFixture();
        $this->from('/product/'.$product->id)->post('/cart/add/'.$product->id,['qty'=>-1])->assertSessionHasErrors('qty');
        $this->from('/cart')->get('/buy/selected?ids[]=1')->assertSessionHasErrors('ids');
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_buy_now_submission_keeps_its_page_when_another_tab_changes_session(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $variant=ProductVariant::create(['product_id'=>$product->id,'stock'=>8,'price_adjustment'=>20,'status'=>'active']);
        $this->get('/buy/'.$product->id.'?qty=3&variant_id='.$variant->id)->assertRedirect();
        $other=Product::create(['seller_id'=>$product->seller_id,'name'=>'Second tab item','price'=>25,'stock'=>10,'status'=>'active']);
        $this->get('/buy/'.$other->id)->assertRedirect();
        $this->post('/checkout',['checkout_mode'=>'single','single'=>$product->id,'qty'=>3,'variant_id'=>$variant->id,'address_id'=>$address->id,'payment_method'=>'cod'])->assertRedirect('/orders')->assertSessionHas('success');
        $this->assertDatabaseHas('order_items',['product_id'=>$product->id,'product_variant_id'=>$variant->id,'quantity'=>3]);
        $this->assertDatabaseMissing('order_items',['product_id'=>$other->id]);
    }

    public function test_selected_submission_keeps_its_page_when_another_tab_changes_session(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$address=$this->deliveryAddress($buyer);
        $first=$this->line($buyer,$product,2);
        $other=Product::create(['seller_id'=>$product->seller_id,'name'=>'Second tab item','price'=>25,'stock'=>10,'status'=>'active']);$second=$this->line($buyer,$other,3);
        $this->get('/buy/selected?ids='.$first->id)->assertRedirect();
        $this->get('/checkout?selected=1')->assertOk()->assertSee('name="selected_item_ids[]"',false);
        $this->get('/buy/selected?ids='.$second->id)->assertRedirect();
        $this->post('/checkout',['checkout_mode'=>'selected','selected_item_ids'=>[$first->id],'address_id'=>$address->id,'payment_method'=>'cod'])->assertRedirect('/orders')->assertSessionHas('success');
        $this->assertDatabaseHas('order_items',['product_id'=>$product->id,'quantity'=>2]);
        $this->assertDatabaseMissing('order_items',['product_id'=>$other->id]);
        $this->assertNotNull($second->fresh());
    }

    public function test_foreign_cart_items_and_addresses_cannot_be_submitted(): void
    {
        [$buyer,$product]=$this->shoppingFixture();$own=$this->line($buyer,$product);
        $foreign=User::create(['name'=>'Other Buyer','email'=>'other@example.test','password'=>'Password123!','role'=>'buyer']);
        $foreignLine=$this->line($foreign,$product,2);$foreignAddress=$this->deliveryAddress($foreign);
        $this->post('/cart/inc/'.$foreignLine->id)->assertSessionHas('error');
        $this->post('/cart/remove/'.$foreignLine->id)->assertRedirect();
        $this->assertSame(2,$foreignLine->fresh()->quantity);
        $this->get('/buy/selected?ids='.$own->id)->assertRedirect();
        $this->post('/checkout',['checkout_mode'=>'selected','selected_item_ids'=>[$own->id],'address_id'=>$foreignAddress->id,'payment_method'=>'cod'])->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders',0);
    }
}
