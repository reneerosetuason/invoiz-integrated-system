<?php

namespace Tests\Feature;

use App\Models\Logistics\Conversation;
use App\Models\Logistics\Message;
use App\Models\MessageAttachment;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Logistics messaging: attachments must hang off logistics_messages (not the
 * marketplace messages table) and both the rider API and rider web attachment
 * routes must authorize through the Logistics message relation.
 */
class LogisticsMessagingTest extends TestCase
{
    use DatabaseTransactions;

    private function riderUser(string $email = 'msg-rider@test.local'): User
    {
        $user = User::create([
            'name' => 'Messaging Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
        ]);

        Rider::create([
            'user_id' => $user->id,
            'name' => 'Messaging Rider',
            'email' => $email,
            'phone' => '+639171234567',
            'vehicle_type' => 'motorcycle',
            'approved_at' => now(),
        ]);

        return $user->fresh();
    }

    private function riderConversation(User $user): Conversation
    {
        return Conversation::create([
            'participant_type' => 'rider',
            'participant_id' => $user->id,
            'participant_name' => $user->name,
            'subject' => 'Rider Support',
            'rider_id' => Rider::where('user_id', $user->id)->value('id'),
            'last_message_at' => now(),
        ]);
    }

    private function logisticsMessage(Conversation $conversation, User $user, string $body = 'Message'): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'rider',
            'sender_id' => $user->id,
            'body' => $body,
        ]);
    }

    public function test_attachment_message_relation_resolves_logistics_message(): void
    {
        $user = $this->riderUser();
        $conversation = $this->riderConversation($user);
        $message = $this->logisticsMessage($conversation, $user);

        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'original_filename' => 'license.pdf',
            'stored_path' => 'message-attachments/'.$conversation->id.'/license.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12,
            'disk' => 'public',
        ]);

        $resolved = $attachment->fresh()->message;

        $this->assertInstanceOf(Message::class, $resolved);
        $this->assertTrue($resolved->is($message));
        $this->assertSame($conversation->id, $resolved->conversation_id);
    }

    public function test_rider_api_conversation_send_with_attachment_uses_logistics_tables(): void
    {
        Storage::fake('public');

        $user = $this->riderUser();
        Sanctum::actingAs($user);

        $index = $this->getJson('/api/logistics/rider/conversations')->assertOk();
        $riderConversation = collect($index->json('conversations'))
            ->firstWhere('type', 'rider');
        $conversationId = $riderConversation['id'] ?? null;

        $this->assertNotNull($conversationId, 'Rider support conversation was not provisioned.');

        $response = $this->post(
            "/api/logistics/rider/conversations/{$conversationId}/messages",
            [
                'body' => 'Hello logistics',
                'attachment' => UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf'),
            ],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $messageId = $response->json('data.id');

        $this->assertDatabaseHas('logistics_messages', [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'sender_type' => 'rider',
            'body' => 'Hello logistics',
        ]);
        $this->assertDatabaseHas('logistics_message_attachments', [
            'message_id' => $messageId,
            'original_filename' => 'proof.pdf',
        ]);
        $this->assertDatabaseMissing('messages', ['body' => 'Hello logistics']);

        $stored = MessageAttachment::where('message_id', $messageId)->value('stored_path');
        Storage::disk('public')->assertExists($stored);

        $show = $this->getJson("/api/logistics/rider/conversations/{$conversationId}")->assertOk();
        $this->assertSame('proof.pdf', $show->json('messages.0.attachments.0.name'));
    }

    public function test_rider_api_flat_messages_endpoint_uses_logistics_tables(): void
    {
        $user = $this->riderUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/logistics/rider/messages', ['body' => 'Flat endpoint'])
            ->assertCreated();

        $this->assertDatabaseHas('logistics_messages', [
            'sender_type' => 'rider',
            'sender_id' => $user->id,
            'body' => 'Flat endpoint',
        ]);
        $this->assertDatabaseMissing('messages', ['body' => 'Flat endpoint']);
    }

    private function validPdf(string $label = 'Attachment'): string
    {
        $content = "BT /F1 12 Tf 72 720 Td ({$label}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $number = $index + 1;
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;

        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    public function test_rider_web_attachment_preview_authorizes_own_conversation_only(): void
    {
        Storage::fake('public');

        $user = $this->riderUser();
        $conversation = $this->riderConversation($user);
        $message = $this->logisticsMessage($conversation, $user);
        $path = 'message-attachments/'.$conversation->id.'/license.pdf';
        $pdf = $this->validPdf();
        Storage::disk('public')->put($path, $pdf);

        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'original_filename' => 'license.pdf',
            'stored_path' => $path,
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
            'disk' => 'public',
        ]);

        $this->actingAs($user)
            ->get("/logistics/rider/attachments/{$attachment->id}/view")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $other = $this->riderUser('other-msg-rider@test.local');
        $this->riderConversation($other);

        $this->actingAs($other)
            ->get("/logistics/rider/attachments/{$attachment->id}/view")
            ->assertForbidden();
    }
}
