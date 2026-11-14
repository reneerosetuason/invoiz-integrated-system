<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\HubTransportDriver;
use App\Models\Logistics\Conversation as LogisticsConversation;
use App\Models\Logistics\Message as LogisticsMessage;
use App\Models\Message;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use App\Support\MessagingAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class MessagingAccessTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        $tables = [
            'riders' => 'user_id center_id name email status approved_at deleted_at',
            'logistics_centers' => 'logistics_company_id name deleted_at',
            'logistics_companies' => 'name status deleted_at',
            'logistics_center_applications' => 'email status provisioned_at',
            'hub_transport_drivers' => 'user_id logistics_company_id name status deleted_at',
            'logistics_conversations' => 'order_id rider_id logistics_user_id direct_thread_key participant_type participant_id participant_name subject last_message_preview last_message_at unread_count',
            'logistics_messages' => 'conversation_id sender_type sender_id body is_read read_at edited_at deleted_at',
            'logistics_message_attachments' => 'message_id original_filename stored_path mime_type file_size disk',
            'return_settlements' => 'return_request_id',
            'return_allocations' => 'return_settlement_id rider_id recipient_user_id',
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) $table->text($column)->nullable();
                $table->timestamps();
            });
        }
        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['rider_id', 'sorting_center_handoff_rider_id', 'sorting_center_pickup_rider_id'] as $column) {
                if (! Schema::hasColumn('deliveries', $column)) $table->unsignedBigInteger($column)->nullable();
            }
        });
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('logistics_company_id')->nullable());
        Schema::table('messages', fn (Blueprint $table) => $table->json('product_card')->nullable());
    }

    private function user(string $role = 'buyer', array $attributes = []): User
    {
        return User::create($attributes + ['name' => ucfirst($role), 'email' => uniqid('chat-').'@example.test',
            'password' => 'Password123!', 'role' => $role, 'is_admin' => false,
            'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function store(): User
    {
        $user = $this->user('seller');
        Seller::create(['user_id' => $user->id, 'store_name' => 'Store', 'status' => 'approved']);
        return $user;
    }

    private function staff(int $company = 1): User
    {
        \Illuminate\Support\Facades\DB::table('logistics_companies')->insertOrIgnore(['id' => $company, 'name' => 'Company '.$company, 'status' => 'active']);
        \Illuminate\Support\Facades\DB::table('logistics_centers')->insertOrIgnore(['id' => $company, 'name' => 'Hub '.$company, 'logistics_company_id' => $company]);
        return $this->user('logistics_staff', ['logistics_company_id' => $company, 'center_id' => $company]);
    }

    private function rider(int $center = 1): User
    {
        $user = $this->user('rider');
        Rider::create(['user_id' => $user->id, 'name' => 'Rider', 'center_id' => $center, 'status' => 'active', 'approved_at' => now()]);
        return $user;
    }

    public function test_marketplace_contact_rules_and_support_reachability(): void
    {
        $buyer = $this->user(); $otherBuyer = $this->user(); $seller = $this->store();
        $admin = $this->user('admin', ['is_admin' => true]);
        $this->assertTrue(MessagingAccess::canContact($buyer, $seller));
        $this->assertFalse(MessagingAccess::canContact($seller, $buyer));
        $this->assertFalse(MessagingAccess::canContact($buyer, $otherBuyer));
        $this->assertFalse(MessagingAccess::canContact($buyer, $buyer));
        foreach ([$buyer, $seller, $this->staff(), $this->rider()] as $user) {
            $this->assertTrue(MessagingAccess::canContact($user, $admin));
            $this->assertTrue(MessagingAccess::canContact($admin, $user));
        }
        Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $this->assertFalse(MessagingAccess::canContact($seller, $buyer));
        Message::create(['conversation_id' => Conversation::first()->id, 'sender_id' => $buyer->id,
            'receiver_id' => $seller->id, 'body' => 'Store enquiry']);
        $this->assertTrue(MessagingAccess::canContact($seller, $buyer));
    }

    public function test_company_and_rider_assignment_limits(): void
    {
        $staff = $this->staff(); $otherStaff = $this->staff(2); $rider = $this->rider();
        $this->assertTrue(MessagingAccess::canContact($staff, $rider));
        $this->assertFalse(MessagingAccess::canContact($otherStaff, $rider));
        $this->assertFalse(MessagingAccess::canContact($staff, $otherStaff));
        $buyer = $this->user(); $seller = $this->store();
        $this->assertFalse(MessagingAccess::canContact($rider, $buyer));
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'pending']);
        Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->rider->id]);
        $this->assertTrue(MessagingAccess::canContact($rider, $buyer));
        $this->assertTrue(MessagingAccess::canContact($rider, $seller));
        $this->assertFalse(MessagingAccess::canContact($rider, $this->user()));
    }

    public function test_hub_driver_identity_is_separate_from_user_ids(): void
    {
        $staff = $this->staff(); $otherStaff = $this->staff(2);
        $driver = HubTransportDriver::create(['name' => 'Driver', 'status' => 'available', 'logistics_company_id' => 1]);
        $this->assertTrue(MessagingAccess::canContact($driver, $staff));
        $this->assertTrue(MessagingAccess::canContact($staff, $driver));
        $this->assertFalse(MessagingAccess::canContact($driver, $otherStaff));
        $this->assertFalse(MessagingAccess::canContact($driver, $this->user()));
        $thread = LogisticsConversation::create(['participant_type' => 'hub_driver', 'participant_id' => $driver->id, 'logistics_user_id' => $staff->id]);
        $this->assertTrue(MessagingAccess::canReadLogistics($driver, $thread));
        $this->assertFalse(MessagingAccess::canReadLogistics($this->user(), $thread));
    }

    public function test_suspended_accounts_cannot_send_or_be_contacted(): void
    {
        $buyer = $this->user(); $seller = $this->store();
        $seller->update(['account_status' => 'suspended']);
        $this->assertFalse(MessagingAccess::canContact($buyer, $seller));
        Sanctum::actingAs($buyer);
        $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$seller->id])->assertForbidden();
    }

    public function test_generic_inbox_round_trip_preserves_existing_marketplace_thread(): void
    {
        $buyer = $this->user(); $seller = $this->store();
        Sanctum::actingAs($buyer);
        $key = $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$seller->id])->assertCreated()->json('thread_key');
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Question'])->assertCreated();
        Sanctum::actingAs($seller);
        $this->getJson('/api/messaging/inbox')->assertOk()->assertJsonPath('unread_count', 1);
        $this->getJson('/api/messaging/threads/'.$key)->assertOk()->assertJsonPath('messages.0.body', 'Question');
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Answer'])->assertCreated();
        $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$buyer->id])->assertCreated()->assertJsonPath('thread_key', $key);
        $this->assertSame(1, Conversation::count());
        Sanctum::actingAs($buyer);
        $this->getJson('/api/conversations')->assertOk()->assertJsonCount(1, 'conversations');
        $this->getJson('/api/messaging/threads/'.$key)->assertJsonPath('messages.0.body', 'Answer');
    }

    public function test_foreign_threads_cannot_be_read_sent_edited_or_listed(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $stranger = $this->user();
        $thread = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $message = Message::create(['conversation_id' => $thread->id, 'sender_id' => $buyer->id, 'receiver_id' => $seller->id, 'body' => 'Private', 'is_read' => false]);
        Sanctum::actingAs($stranger);
        $key = 'marketplace:'.$thread->id;
        $this->getJson('/api/messaging/threads/'.$key)->assertForbidden();
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Intrude'])->assertForbidden();
        $this->patchJson('/api/messaging/threads/'.$key.'/messages/'.$message->id, ['body' => 'Intrude'])->assertForbidden();
        $this->getJson('/api/messaging/inbox')->assertOk()->assertJsonCount(0, 'conversations');
        $this->assertFalse($message->fresh()->is_read);
    }

    public function test_logistics_cannot_read_another_company_support_or_private_delivery_chat(): void
    {
        $rider = $this->rider(); $staff = $this->staff(); $otherStaff = $this->staff(2);
        $support = LogisticsConversation::create(['participant_type' => 'rider', 'participant_id' => $rider->id, 'rider_id' => $rider->rider->id]);
        $private = LogisticsConversation::create(['participant_type' => 'buyer', 'participant_id' => $this->user()->id, 'rider_id' => $rider->rider->id]);
        $this->assertTrue(MessagingAccess::canReadLogistics($staff, $support));
        $this->assertFalse(MessagingAccess::canReadLogistics($otherStaff, $support));
        $this->assertFalse(MessagingAccess::canReadLogistics($staff, $private));
        Sanctum::actingAs($otherStaff);
        $this->getJson('/api/messaging/threads/logistics:'.$support->id)->assertForbidden();
        $this->getJson('/api/messaging/inbox')->assertJsonCount(0, 'conversations');
    }

    public function test_hub_driver_and_staff_exchange_without_sender_id_collision(): void
    {
        $staff = $this->staff();
        $driver = HubTransportDriver::create(['id' => $staff->id, 'name' => 'Driver', 'status' => 'available', 'logistics_company_id' => 1]);
        Sanctum::actingAs($driver);
        $key = $this->postJson('/api/logistics/messaging/start', ['recipient' => 'user:'.$staff->id])->assertCreated()->json('thread_key');
        $this->postJson('/api/logistics/messaging/threads/'.$key, ['body' => 'At the hub'])->assertCreated()->assertJsonPath('data.mine', true);
        Sanctum::actingAs($staff);
        $this->getJson('/api/messaging/threads/'.$key)->assertJsonPath('messages.0.mine', false);
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Proceed'])->assertCreated();
        Sanctum::actingAs($driver);
        $this->getJson('/api/logistics/messaging/threads/'.$key)->assertJsonPath('messages.0.mine', false);
        $this->assertSame(2, LogisticsMessage::count());
        $staff->update(['account_status' => 'suspended']);
        $this->postJson('/api/logistics/messaging/threads/'.$key, ['body' => 'Inactive contact'])->assertForbidden();
        $row = collect($this->getJson('/api/logistics/messaging/inbox')->json('conversations'))->firstWhere('thread_key', $key);
        $this->assertFalse($row['can_send']);
    }

    public function test_buyer_legacy_send_does_not_bypass_recipient_rules(): void
    {
        $buyer = $this->user(); $stranger = $this->user();
        $this->actingAs($buyer)->postJson('/buyer/chat/send', ['receiver_id' => $stranger->id, 'body' => 'Blocked'])->assertForbidden();
        $this->assertSame(0, Message::count());
    }

    public function test_guest_and_malformed_identity_are_rejected(): void
    {
        $this->getJson('/api/messaging/inbox')->assertUnauthorized();
        Sanctum::actingAs($this->user());
        $this->postJson('/api/messaging/start', ['recipient' => '1'])->assertUnprocessable();
        $this->getJson('/api/messaging/threads/nonsense')->assertNotFound();
    }

    public function test_support_without_recipient_reaches_real_platform_admin(): void
    {
        $buyer = $this->user();
        $this->user('admin'); // A company manager is not platform support.
        $admin = $this->user('admin', ['is_admin' => true]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/conversations', ['subject' => 'Help', 'body' => 'Please help'])
            ->assertCreated()->assertJsonPath('conversation.seller_id', $admin->id);
        $this->assertSame($admin->id, (int) Message::first()->receiver_id);
    }

    public function test_customer_and_assigned_rider_can_reply_to_same_delivery_thread(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $rider = $this->rider();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'pending']);
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->rider->id]);
        $thread = LogisticsConversation::create(['participant_type' => 'buyer', 'participant_id' => $buyer->id, 'rider_id' => $rider->rider->id, 'order_id' => $order->id]);
        $key = 'logistics:'.$thread->id;
        Sanctum::actingAs($rider);
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Arriving'])->assertCreated();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/messaging/threads/'.$key)->assertJsonPath('messages.0.body', 'Arriving');
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Thank you'])->assertCreated();
        $delivery->update(['rider_id' => null]);
        $this->getJson('/api/messaging/threads/'.$key)->assertOk();
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'After reassignment'])->assertForbidden();
    }

    public function test_attachment_requires_participation_and_edit_cannot_target_another_message(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $staff = $this->staff();
        $driver = HubTransportDriver::create(['name' => 'Driver', 'status' => 'available', 'logistics_company_id' => 1]);
        Sanctum::actingAs($driver);
        $key = $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$staff->id])->json('thread_key');
        $uploaded = \Illuminate\Http\UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1cAAAAASUVORK5CYII='));
        $payload = $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Proof', 'attachment' => $uploaded])
            ->assertCreated()->json('data');
        $id = $payload['attachments'][0]['id'];
        $this->get('/api/messaging/attachments/'.$id)->assertOk();
        Sanctum::actingAs($this->user());
        $this->get('/api/messaging/attachments/'.$id)->assertForbidden();
        Sanctum::actingAs($staff);
        $this->patchJson('/api/messaging/threads/'.$key.'/messages/'.$payload['id'], ['body' => 'Not mine'])->assertForbidden();
    }

    public function test_reading_support_as_rider_preserves_staff_unread_state(): void
    {
        $rider = $this->rider(); $staff = $this->staff();
        $thread = LogisticsConversation::create(['participant_type' => 'rider', 'participant_id' => $rider->id,
            'rider_id' => $rider->rider->id, 'unread_count' => 1]);
        LogisticsMessage::create(['conversation_id' => $thread->id, 'sender_type' => 'rider', 'sender_id' => $rider->id, 'body' => 'Help', 'is_read' => false]);
        Sanctum::actingAs($rider);
        $this->getJson('/api/messaging/threads/logistics:'.$thread->id)->assertOk();
        $this->assertSame(1, $thread->fresh()->unread_count);
        Sanctum::actingAs($staff);
        $this->getJson('/api/messaging/threads/logistics:'.$thread->id)->assertOk();
        $this->assertSame(0, $thread->fresh()->unread_count);
    }

    public function test_company_manager_and_global_logistics_contacts_respect_their_scopes(): void
    {
        $manager = $this->user('admin', ['logistics_company_id' => 1]);
        $global = $this->user('logistics_admin', ['email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL]);
        $staff = $this->staff(); $otherCompany = $this->staff(2);
        $this->assertTrue(MessagingAccess::canContact($manager, $staff));
        $this->assertFalse(MessagingAccess::canContact($manager, $otherCompany));
        $this->assertTrue(MessagingAccess::canContact($global, $otherCompany));
        $this->assertFalse(MessagingAccess::canContact($manager, $this->user()));
        $this->assertFalse(MessagingAccess::canContact($this->store(), $this->store()));
    }

    public function test_historical_return_allocation_alone_does_not_grant_chat_access(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $rider = $this->rider();
        $return = \App\Models\ReturnRequest::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'approved']);
        $settlement = \App\Models\ReturnSettlement::create(['return_request_id' => $return->id]);
        $this->assertFalse(MessagingAccess::canContact($rider, $buyer));
        $allocation = \App\Models\ReturnAllocation::create(['return_settlement_id' => $settlement->id,
            'rider_id' => $rider->rider->id, 'recipient_user_id' => $rider->id]);
        $this->assertFalse(MessagingAccess::canContact($rider, $buyer));
        $this->assertFalse(MessagingAccess::canContact($seller, $rider));
        $allocation->update(['recipient_user_id' => null]);
        $this->assertFalse(MessagingAccess::canContact($rider, $buyer));
        $this->assertSame('approved', $return->fresh()->status);
    }

    public function test_every_role_can_compose_to_allowed_contacts_and_blocked_pairs_cannot_start(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $admin = $this->user('admin', ['is_admin' => true]);
        $rider = $this->rider(); $staff = $this->staff();
        $driver = HubTransportDriver::create(['name' => 'Courier', 'status' => 'available', 'logistics_company_id' => 1]);
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'pending']);
        Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->rider->id, 'center_id' => 1]);
        $enquiry = Conversation::create(['buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        Message::create(['conversation_id' => $enquiry->id, 'sender_id' => $buyer->id, 'receiver_id' => $seller->id, 'body' => 'An actual store enquiry']);
        $roles = compact('buyer', 'seller', 'admin', 'rider', 'staff', 'driver');
        $allowed = [
            'buyer' => ['seller', 'admin', 'rider'], 'seller' => ['buyer', 'admin', 'rider', 'staff'],
            'admin' => ['buyer', 'seller', 'rider', 'staff', 'driver'], 'rider' => ['buyer', 'seller', 'admin', 'staff'],
            'staff' => ['admin', 'rider', 'driver', 'seller'], 'driver' => ['admin', 'staff'],
        ];
        foreach ($roles as $from => $actor) {
            foreach ($roles as $to => $other) {
                Sanctum::actingAs($actor);
                $recipient = ($other instanceof HubTransportDriver ? 'driver:' : 'user:').$other->id;
                $response = $this->postJson('/api/messaging/start', ['recipient' => $recipient]);
                if (! in_array($to, $allowed[$from], true)) {
                    $response->assertForbidden();
                    continue;
                }
                $key = $response->assertCreated()->json('thread_key');
                $row = collect($this->getJson('/api/messaging/inbox')->assertOk()->json('conversations'))->firstWhere('thread_key', $key);
                $this->assertNotNull($row, "$from to $to thread absent from inbox");
                $this->assertTrue($row['can_send'], "$from to $to composer disabled");
                $this->postJson('/api/messaging/threads/'.$key, ['body' => "$from to $to compose check"])
                    ->assertCreated()->assertJsonPath('data.mine', true);
            }
        }
    }

    public function test_staff_cannot_join_customer_rider_threads_and_seller_partnerships_are_separate(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $rider = $this->rider(); $staff = $this->staff();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'ready_for_delivery']);
        Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->rider->id, 'center_id' => 1]);
        foreach (['buyer' => $buyer, 'seller' => $seller] as $type => $customer) {
            $thread = LogisticsConversation::create(['participant_type' => $type, 'participant_id' => $customer->id,
                'participant_name' => $customer->name, 'rider_id' => $rider->rider->id, 'order_id' => $order->id]);
            LogisticsMessage::create(['conversation_id' => $thread->id, 'sender_type' => $type,
                'sender_id' => $customer->id, 'body' => 'Private delivery question', 'is_read' => false]);
            Sanctum::actingAs($staff);
            $this->getJson('/api/messaging/threads/logistics:'.$thread->id)->assertForbidden();
            $this->postJson('/api/messaging/threads/logistics:'.$thread->id, ['body' => 'Cannot join'])->assertForbidden();
            $this->assertNotContains('logistics:'.$thread->id, collect($this->getJson('/api/messaging/inbox')->json('conversations'))->pluck('thread_key'));
        }
        Sanctum::actingAs($buyer);
        $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$staff->id])->assertForbidden();
        Sanctum::actingAs($seller);
        $key = $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$staff->id])->assertCreated()->json('thread_key');
        $this->assertStringStartsWith('marketplace:', $key);
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Partnership enquiry'])->assertCreated();
        Sanctum::actingAs($buyer);
        $this->getJson('/api/messaging/threads/'.$key)->assertForbidden();
        Sanctum::actingAs($rider);
        $this->getJson('/api/messaging/threads/'.$key)->assertForbidden();
    }

    public function test_parcel_chat_follows_handoff_and_keeps_customer_and_handler_histories_private(): void
    {
        Schema::table('deliveries', function (Blueprint $t) {
            foreach (['center_id', 'picked_up_at', 'received_at', 'sorting_center_handoff_at', 'sorting_center_pickup_at', 'parcel_status'] as $field) {
                if (! Schema::hasColumn('deliveries', $field)) $t->text($field)->nullable();
            }
        });
        $buyer = $this->user(); $seller = $this->store(); $pickup = $this->rider(); $lastMile = $this->rider();
        $staff = $this->staff(); $otherHub = $this->staff(2);
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'ready_for_delivery']);
        $parcel = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $pickup->rider->id,
            'center_id' => 1, 'parcel_status' => 'pending_arrival', 'status' => 'assigned', 'tracking_number' => 'PRIVATE-PARCEL']);
        Sanctum::actingAs($buyer);
        $contacts = $this->getJson('/api/messaging/contacts')->assertOk()->json('contacts');
        $this->assertContains('parcel:'.$parcel->id, array_column($contacts, 'key'));
        $this->assertNotContains('user:'.$pickup->id, array_column($contacts, 'key'));
        $buyerThread = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->postJson('/api/messaging/threads/'.$buyerThread, ['body' => 'Buyer private pickup chat'])->assertCreated();
        Sanctum::actingAs($seller);
        $sellerThread = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->assertNotSame($buyerThread, $sellerThread);
        $this->getJson('/api/messaging/threads/'.$buyerThread)->assertForbidden();
        $this->postJson('/api/messaging/threads/'.$sellerThread, ['body' => 'Seller private pickup chat'])->assertCreated();
        Sanctum::actingAs($staff);
        $this->getJson('/api/messaging/threads/'.$buyerThread)->assertForbidden();
        $this->getJson('/api/messaging/threads/'.$sellerThread)->assertForbidden();
        Sanctum::actingAs($lastMile);
        $this->postJson('/api/messaging/start', ['recipient' => 'user:'.$buyer->id])->assertForbidden();

        // Preserve the old pickup rider reference: it must not grant contact.
        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update([
            'received_at' => now(), 'picked_up_at' => now(), 'sorting_center_handoff_at' => now(),
            'sorting_center_handoff_rider_id' => $pickup->rider->id, 'parcel_status' => 'received', 'status' => 'picked_up',
        ]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/messaging/threads/'.$buyerThread, ['body' => 'Old rider cannot receive this'])->assertForbidden();
        $this->getJson('/api/messaging/threads/'.$buyerThread)->assertOk()->assertJsonPath('can_send', false);
        $hubThread = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->assertNotSame($buyerThread, $hubThread);
        $this->postJson('/api/messaging/threads/'.$hubThread, ['body' => 'Hub only chat'])->assertCreated();
        $this->assertSame(1, LogisticsConversation::unreadForLogisticsUser($staff->id));
        $this->assertSame(0, LogisticsConversation::unreadForLogisticsUser($otherHub->id));
        Sanctum::actingAs($pickup);
        $this->getJson('/api/messaging/threads/'.$hubThread)->assertForbidden();
        $this->postJson('/api/messaging/threads/'.$buyerThread, ['body' => 'Former rider'])->assertForbidden();
        Sanctum::actingAs($staff);
        $this->getJson('/api/messaging/threads/'.$hubThread)->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Hub only chat');
        $this->postJson('/api/messaging/threads/'.$hubThread, ['body' => 'Current hub reply'])->assertCreated()->assertJsonPath('data.sender_type', 'logistics');
        Sanctum::actingAs($otherHub);
        $this->getJson('/api/messaging/threads/'.$hubThread)->assertForbidden();

        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update(['rider_id' => $lastMile->rider->id, 'status' => 'assigned']);
        $this->assertSame('h', \App\Support\ParcelChat::holder($parcel->fresh())['kind']);
        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update(['sorting_center_pickup_at' => now(), 'status' => 'out_for_delivery']);
        Sanctum::actingAs($buyer);
        $lastThread = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->assertNotSame($hubThread, $lastThread);
        $this->postJson('/api/messaging/threads/'.$hubThread, ['body' => 'Old hub'])->assertForbidden();
        Sanctum::actingAs($lastMile);
        $this->getJson('/api/messaging/threads/'.$lastThread)->assertJsonCount(0, 'messages');
        $this->getJson('/api/messaging/threads/'.$buyerThread)->assertForbidden();
        $this->getJson('/api/messaging/threads/'.$hubThread)->assertForbidden();
        $this->postJson('/api/messaging/threads/'.$lastThread, ['body' => 'Delivering now'])->assertCreated();
        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update(['status' => 'delivered']);
        $this->postJson('/api/messaging/threads/'.$lastThread, ['body' => 'Delivery is over'])->assertForbidden();
    }

    public function test_multi_seller_shipments_and_mixed_legacy_history_are_not_merged(): void
    {
        $buyer = $this->user(); $sellerA = $this->store(); $sellerB = $this->store(); $rider = $this->rider(); $staff = $this->staff();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'ready_for_delivery']);
        $a = Delivery::create(['order_id' => $order->id, 'seller_id' => $sellerA->id, 'rider_id' => $rider->rider->id]);
        $b = Delivery::create(['order_id' => $order->id, 'seller_id' => $sellerB->id, 'rider_id' => $rider->rider->id]);
        $legacy = LogisticsConversation::create(['order_id' => $order->id, 'rider_id' => $rider->rider->id, 'participant_type' => 'buyer', 'participant_id' => $buyer->id]);
        LogisticsMessage::create(['conversation_id' => $legacy->id, 'sender_type' => 'logistics', 'sender_id' => $staff->id, 'body' => 'Old shared support history']);
        Sanctum::actingAs($buyer);
        $first = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$a->id])->assertCreated()->json('thread_key');
        $second = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$b->id])->assertCreated()->json('thread_key');
        $this->assertNotSame($first, $second);
        $this->assertNotSame('logistics:'.$legacy->id, $first);
        $this->getJson('/api/messaging/threads/'.$first)->assertJsonCount(0, 'messages');
        $this->postJson('/api/messaging/threads/logistics:'.$legacy->id, ['body' => 'Cannot resume shared chat'])->assertForbidden();
        $this->getJson('/api/messaging/threads/logistics:'.$legacy->id)->assertJsonPath('messages.0.body', 'Old shared support history');
        Sanctum::actingAs($sellerA);
        $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$b->id])->assertForbidden();
        $this->getJson('/api/messaging/threads/'.$second)->assertForbidden();
        $this->assertSame(1, LogisticsMessage::where('conversation_id', $legacy->id)->count());
    }

    public function test_return_chat_uses_current_workflow_custody_instead_of_old_allocations(): void
    {
        Schema::create('return_workflows', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('return_request_id'); $t->string('stage');
            $t->unsignedBigInteger('rider_id')->nullable(); $t->unsignedBigInteger('center_id')->nullable(); $t->timestamps();
        });
        $buyer = $this->user(); $seller = $this->store(); $rider = $this->rider(); $staff = $this->staff();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'delivered']);
        $parcel = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id]);
        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update(['status' => 'delivered']);
        $return = \App\Models\ReturnRequest::create(['order_id' => $order->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'approved']);
        $flow = \App\Models\ReturnWorkflow::create(['return_request_id' => $return->id, 'stage' => 'pickup_assigned', 'rider_id' => $rider->rider->id, 'center_id' => 1]);
        $this->assertTrue(MessagingAccess::canContact($rider, $buyer));
        Sanctum::actingAs($buyer);
        $key = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Return collection'])->assertCreated();
        $flow->update(['stage' => 'at_hub', 'rider_id' => null]);
        $this->assertFalse(MessagingAccess::canContact($rider, $buyer));
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Former return rider'])->assertForbidden();
        $hubKey = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        Sanctum::actingAs($staff);
        $this->postJson('/api/messaging/threads/'.$hubKey, ['body' => 'Return received at hub'])->assertCreated();
        $this->getJson('/api/messaging/threads/'.$key)->assertForbidden();
        $this->assertSame('at_hub', $flow->fresh()->stage);
        $this->assertSame('approved', $return->fresh()->status);
    }

    public function test_transport_driver_is_the_contact_until_destination_hub_accepts_the_parcel(): void
    {
        Schema::create('hub_transfers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('driver_id'); $t->string('status'); $t->timestamp('dispatched_at')->nullable(); $t->timestamps();
        });
        Schema::create('hub_transfer_parcels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('hub_transfer_id'); $t->unsignedBigInteger('delivery_id');
            $t->string('loading_status'); $t->string('receiving_status'); $t->timestamps();
        });
        Schema::table('deliveries', function (Blueprint $t) {
            if (! Schema::hasColumn('deliveries', 'center_id')) $t->unsignedBigInteger('center_id')->nullable();
            if (! Schema::hasColumn('deliveries', 'received_at')) $t->timestamp('received_at')->nullable();
        });
        $buyer = $this->user(); $seller = $this->store(); $origin = $this->staff(); $destination = $this->staff(2);
        $driver = HubTransportDriver::create(['name' => 'Current truck driver', 'status' => 'in_transit', 'logistics_company_id' => 1]);
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'shipped']);
        $parcel = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'center_id' => 1, 'received_at' => now()]);
        $trip = \App\Models\HubTransfer::create(['driver_id' => $driver->id, 'status' => 'arrived', 'dispatched_at' => now()->subHour()]);
        $entry = \App\Models\HubTransferParcel::create(['hub_transfer_id' => $trip->id, 'delivery_id' => $parcel->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);
        Sanctum::actingAs($buyer);
        $key = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Truck arrival question'])->assertCreated();
        foreach ([$origin, $destination, $seller] as $outsider) {
            Sanctum::actingAs($outsider);
            $this->getJson('/api/messaging/threads/'.$key)->assertForbidden();
        }
        Sanctum::actingAs($driver);
        $this->getJson('/api/messaging/threads/'.$key)->assertOk();
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Waiting for hub receiving'])->assertCreated()->assertJsonPath('data.sender_type', 'hub_driver');
        $entry->update(['receiving_status' => 'received']);
        \Illuminate\Support\Facades\DB::table('deliveries')->where('id', $parcel->id)->update(['center_id' => 2]);
        $this->postJson('/api/messaging/threads/'.$key, ['body' => 'Former truck driver'])->assertForbidden();
        Sanctum::actingAs($buyer);
        $hubKey = $this->postJson('/api/messaging/start', ['recipient' => 'parcel:'.$parcel->id])->assertCreated()->json('thread_key');
        $this->assertNotSame($key, $hubKey);
        Sanctum::actingAs($destination);
        $this->postJson('/api/messaging/threads/'.$hubKey, ['body' => 'Destination hub reply'])->assertCreated();
    }

    public function test_wrong_customer_in_same_order_does_not_grant_delivery_thread_access(): void
    {
        $buyer = $this->user(); $seller = $this->store(); $stranger = $this->store(); $rider = $this->rider(); $staff = $this->staff();
        $order = \App\Models\Order::create(['buyer_id' => $buyer->id, 'status' => 'pending']);
        Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'rider_id' => $rider->rider->id, 'center_id' => 1]);
        $thread = LogisticsConversation::create(['participant_type' => 'seller', 'participant_id' => $stranger->id,
            'rider_id' => $rider->rider->id, 'order_id' => $order->id]);
        LogisticsMessage::create(['conversation_id' => $thread->id, 'sender_type' => 'seller', 'sender_id' => $stranger->id, 'body' => 'Foreign seller']);
        $this->assertFalse(MessagingAccess::canReadLogistics($staff, $thread));
        $this->assertFalse(MessagingAccess::canSendLogistics($rider, $thread));
    }
}
