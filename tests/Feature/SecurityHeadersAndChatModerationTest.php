<?php

namespace Tests\Feature;

use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\Seller;
use App\Models\User;
use App\Rules\CleanMessage;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class SecurityHeadersAndChatModerationTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
    }

    public function test_security_headers_are_applied_and_php_version_is_hidden(): void
    {
        $response = $this->get('/help/faq');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertTrue(
            $response->headers->has('Content-Security-Policy-Report-Only'),
            'Response must contain Content-Security-Policy-Report-Only header'
        );
        $this->assertFalse(
            $response->headers->has('X-Powered-By'),
            'X-Powered-By header must be stripped from response'
        );
    }

    public function test_clean_message_rule_permits_ordinary_conversations(): void
    {
        $validator = Validator::make(
            ['body' => 'Hello! Is this product still available in blue? Thank you!'],
            ['body' => ['required', 'string', new CleanMessage]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_clean_message_rule_rejects_profanity(): void
    {
        $validator = Validator::make(
            ['body' => 'This is total shit!'],
            ['body' => ['required', 'string', new CleanMessage]]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_chat_block_prevents_messaging_between_blocked_users(): void
    {
        $buyer = User::create([
            'name' => 'Buyer One', 'first_name' => 'Buyer', 'last_name' => 'One',
            'email' => 'buyer1@test.local', 'password' => 'Secret123!', 'role' => 'buyer',
            'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);

        $sellerUser = User::create([
            'name' => 'Seller One', 'first_name' => 'Seller', 'last_name' => 'One',
            'email' => 'seller1@test.local', 'password' => 'Secret123!', 'role' => 'seller',
            'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);

        $seller = Seller::create([
            'user_id' => $sellerUser->id, 'store_name' => 'Test Store',
            'status' => 'active', 'approval_status' => 'approved',
        ]);

        // Seller blocks buyer
        ChatBlock::create([
            'blocker_id' => $sellerUser->id,
            'blocked_id' => $buyer->id,
        ]);

        $this->assertTrue(ChatBlock::blocksBetween($buyer, $sellerUser));
        $this->assertTrue(ChatBlock::blocksBetween($sellerUser, $buyer));

        // Buyer attempts to create new conversation with blocked seller
        Sanctum::actingAs($buyer);
        $response = $this->postJson('/api/conversations', [
            'seller_id' => $sellerUser->id,
            'subject' => 'Inquiry',
            'body' => 'Hello seller',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['seller_id']);

        // Test unblocking via block API endpoint
        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $sellerUser->id,
            'subject' => 'Order discussion',
        ]);

        // Seller is currently blocked, so replying should abort with 403
        $replyResponse = $this->postJson("/api/conversations/{$conversation->id}/reply", [
            'body' => 'Can you reply?',
        ]);
        $replyResponse->assertStatus(403);

        // Buyer unblocks seller
        $unblockResponse = $this->postJson("/api/conversations/{$conversation->id}/block", [
            'blocked' => false,
        ]);
        $unblockResponse->assertOk();

        // Also remove the reverse block from setUp so both sides are unblocked
        ChatBlock::where('blocker_id', $sellerUser->id)->delete();

        // Now replying succeeds
        $allowedReply = $this->postJson("/api/conversations/{$conversation->id}/reply", [
            'body' => 'Now it works!',
        ]);
        $allowedReply->assertStatus(201);
    }
}
