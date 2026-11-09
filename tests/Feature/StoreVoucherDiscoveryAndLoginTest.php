<?php

namespace Tests\Feature;

use App\Models\{Seller, User, Voucher, VoucherClaim};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, DB, Schema, URL};
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class StoreVoucherDiscoveryAndLoginTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('riders', function (Blueprint $table) { $table->id(); $table->unsignedBigInteger('user_id'); });
        Schema::create('seller_notifications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->boolean('is_read')->default(false); $table->timestamps();
        });
    }

    private function account(string $role='buyer', array $attributes=[]): User
    {
        return User::create($attributes+['name'=>'Voucher customer','first_name'=>'Voucher','last_name'=>'Customer',
            'email'=>uniqid('voucher-').'@example.test','password'=>'Password123!','role'=>$role,'is_admin'=>false,
            'status'=>'active','account_status'=>'active','approval_status'=>'approved','email_verified_at'=>now()]);
    }

    private function store(string $role='seller'): Seller
    {
        $user=$this->account($role);
        $store=Seller::create(['user_id'=>$user->id,'store_name'=>'Voucher store '.$user->id,'status'=>'active','approval_status'=>'approved']);
        DB::table('sellers')->where('id',$store->id)->update(['id'=>1000+$user->id]);
        return Seller::where('user_id',$user->id)->firstOrFail();
    }

    private function voucher(?Seller $store=null, array $attributes=[]): Voucher
    {
        return Voucher::create($attributes+['seller_id'=>$store?->id,'name'=>'Store discount '.Voucher::count(),'code'=>'CODE'.(Voucher::count()+1),
            'discount_type'=>'fixed','discount_value'=>25,'min_spend'=>100,'status'=>'active','used_count'=>0]);
    }

    public function test_buyer_discovers_store_vouchers_without_adding_cart_items(): void
    {
        $buyer=$this->account();$store=$this->store();$offer=$this->voucher($store);$platform=$this->voucher();
        $this->assertDatabaseCount('cart_items',0);
        $this->actingAs($buyer)->get('/vouchers')->assertOk()
            ->assertViewHas('vouchers',fn($rows)=>$rows->contains('id',$offer->id)&&$rows->contains('id',$platform->id))
            ->assertDontSee($offer->code)->assertSee('Claim voucher');
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_store_tab_shows_only_its_available_offers_and_claims_are_idempotent(): void
    {
        $buyer=$this->account();$store=$this->store();$other=$this->store();
        $offer=$this->voucher($store);$legacy=$this->voucher(null,['seller_id'=>$store->user_id,'name'=>'Safe legacy offer']);
        $this->voucher($other,['name'=>'Other store private offer']);$this->voucher(null,['name'=>'Platform offer elsewhere']);
        $this->voucher($store,['status'=>'inactive','name'=>'Inactive offer']);
        $this->voucher($store,['starts_at'=>now()->addDay(),'name'=>'Future offer']);
        $this->voucher($store,['ends_at'=>now()->subDay(),'name'=>'Expired offer']);
        $this->voucher($store,['usage_limit'=>1,'used_count'=>1,'name'=>'Exhausted offer']);
        $url='/store/'.$store->user_id.'?tab=vouchers';
        $response=$this->actingAs($buyer)->get($url)->assertOk()->assertSee('Vouchers (2)')->assertSee('Store vouchers')
            ->assertViewHas('storeVouchers',fn($rows)=>$rows->count()===2&&$rows->contains('id',$offer->id)&&$rows->contains('id',$legacy->id));
        foreach(['Other store private offer','Platform offer elsewhere','Inactive offer','Future offer','Expired offer','Exhausted offer'] as $text)$response->assertDontSee($text);
        $this->from($url)->post('/vouchers/'.$offer->id.'/claim')->assertRedirect($url)->assertSessionHas('success');
        $this->from($url)->post('/vouchers/'.$offer->id.'/claim')->assertRedirect($url)->assertSessionHas('success');
        $this->assertDatabaseCount('voucher_claims',1);
        $this->assertDatabaseHas('voucher_claims',['buyer_id'=>$buyer->id,'voucher_id'=>$offer->id]);
        $this->assertSame(0,(int)$offer->fresh()->used_count);
        $this->get($url)->assertOk()->assertSee('Claimed')->assertViewHas('claimedIds',fn($ids)=>in_array($offer->id,$ids,true));
        $offer->update(['status'=>'inactive']);
        $this->from($url)->post('/vouchers/'.$offer->id.'/claim')->assertRedirect($url)->assertSessionHas('error');
    }

    public function test_store_ids_do_not_leak_another_stores_vouchers_when_ids_overlap(): void
    {
        $buyer=$this->account();$store=$this->store();$other=$this->store();
        $otherUserId=$other->user_id;
        DB::table('sellers')->where('id',$other->id)->update(['id'=>$store->user_id]);
        $other=Seller::where('user_id',$otherUserId)->firstOrFail();
        $own=$this->voucher($store);$foreign=$this->voucher($other,['name'=>'Foreign collision offer']);
        $this->actingAs($buyer)->get('/store/'.$store->user_id.'?tab=vouchers')->assertOk()
            ->assertViewHas('storeVouchers',fn($rows)=>$rows->count()===1&&$rows->contains('id',$own->id))
            ->assertDontSee($foreign->name);
    }

    public function test_guests_can_find_store_offers_but_must_sign_in_to_claim(): void
    {
        $store=$this->store();$offer=$this->voucher($store);
        $this->get('/store/'.$store->user_id.'?tab=vouchers')->assertOk()->assertSee($offer->name)->assertSee('Log in to claim')
            ->assertDontSee('action="'.url('/vouchers/'.$offer->id.'/claim').'"',false);
        $this->post('/vouchers/'.$offer->id.'/claim')->assertRedirect('/login');
        $this->assertDatabaseCount('voucher_claims',0);
    }

    public function test_store_voucher_pagination_keeps_the_store_and_voucher_tab(): void
    {
        $buyer=$this->account();$store=$this->store();
        for($i=0;$i<13;$i++)$this->voucher($store);
        $this->actingAs($buyer)->get('/store/'.$store->user_id.'?tab=vouchers')->assertOk()->assertSee('voucher_page=2',false)
            ->assertViewHas('storeVouchers',fn($rows)=>$rows->count()===12&&$rows->total()===13);
        $this->get('/store/'.$store->user_id.'?tab=vouchers&voucher_page=2')->assertOk()
            ->assertViewHas('tab','vouchers')->assertViewHas('storeVouchers',fn($rows)=>$rows->count()===1);
    }

    public function test_primary_seller_login_skips_choice_and_sets_the_seller_session(): void
    {
        $store=$this->store();$user=$store->user;
        $this->withSession(['active_role'=>'buyer','seller_id'=>999])->post('/login',['email'=>$user->email,'password'=>'Password123!'])
            ->assertRedirect('/seller/dashboard')->assertSessionHas('active_role','seller')->assertSessionHas('seller_id',$store->id)->assertSessionMissing('buyer');
        $this->assertAuthenticatedAs($user);
        $this->get('/home')->assertRedirect('/seller/dashboard');
        session()->forget('active_role');
        $this->get('/home')->assertRedirect('/seller/dashboard')->assertSessionHas('active_role','seller');
        $this->post('/switch-role',['role'=>'buyer'])->assertRedirect('/shop')->assertSessionHas('active_role','buyer');
        $this->get('/home')->assertRedirect('/shop');
        $this->post('/switch-role',['role'=>'seller'])->assertRedirect('/seller/dashboard');
    }

    public function test_dedicated_seller_login_and_bookmarked_pages_skip_the_picker(): void
    {
        $store=$this->store();$user=$store->user;
        $this->post('/seller/login',['email'=>$user->email,'password'=>'Password123!'])->assertRedirect('/seller/dashboard')->assertSessionHas('active_role','seller');
        $this->get('/seller/login')->assertRedirect('/seller/dashboard');
        session()->forget('active_role');
        $this->get('/seller/products')->assertOk()->assertSessionHas('active_role','seller');
    }

    public function test_buyer_only_and_buyer_with_store_are_routed_separately(): void
    {
        $buyer=$this->account();
        $this->post('/login',['email'=>$buyer->email,'password'=>'Password123!'])->assertRedirect('/shop');
        $this->get('/choose')->assertRedirect('/shop');
        $this->post('/logout');
        $store=$this->store('buyer');$dual=$store->user;
        $this->post('/login',['email'=>$dual->email,'password'=>'Password123!'])->assertRedirect('/choose');
        $this->get('/choose')->assertOk()->assertSee('both buyer and seller');
        $this->post('/switch-role',['role'=>'seller'])->assertRedirect('/seller/dashboard');
    }

    public function test_signed_email_verification_sends_a_primary_seller_to_the_dashboard(): void
    {
        $store=$this->store();$user=$store->user;$user->update(['email_verified_at'=>null]);
        $url=URL::temporarySignedRoute('verification.verify',now()->addMinutes(10),['id'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($url)->assertRedirect('/seller/dashboard')->assertSessionHas('active_role','seller');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unapproved_or_inactive_accounts_do_not_gain_seller_access(): void
    {
        $store=$this->store();$user=$store->user;$store->update(['approval_status'=>'pending']);
        $this->post('/login',['email'=>$user->email,'password'=>'Password123!'])->assertRedirect('/shop')->assertSessionHas('error');
        $this->post('/logout');$user->update(['account_status'=>'suspended']);
        $this->post('/login',['email'=>$user->email,'password'=>'Password123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
