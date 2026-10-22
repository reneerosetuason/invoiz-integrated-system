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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Logistics message attachments: the View/Download buttons must serve the real
 * stored bytes inline/attached, corrupt placeholders must be reported instead
 * of handed to the browser, and attachment authorization must hold.
 */
class LogisticsMessageAttachmentsTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "msg-attach-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function riderUser(): User
    {
        $user = $this->user('rider');

        Rider::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+639171234567',
            'vehicle_type' => 'motorcycle',
            'approved_at' => now(),
        ]);

        return $user;
    }

    private function conversation(User $participant): Conversation
    {
        $this->seq++;

        return Conversation::create([
            'participant_type' => 'rider',
            'participant_id' => $participant->id,
            'participant_name' => $participant->name,
            'subject' => 'Rider Support '.$this->seq,
            'rider_id' => Rider::where('user_id', $participant->id)->value('id'),
            'last_message_at' => now(),
        ]);
    }

    private function message(Conversation $conversation, User $rider, string $body = 'Attachment message'): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'rider',
            'sender_id' => $rider->id,
            'body' => $body,
        ]);
    }

    private function attachment(
        Message $message,
        string $contents,
        bool $write = true,
        ?string $storedPath = null,
        string $filename = 'waybill.pdf',
        string $disk = 'local',
        string $mime = 'application/pdf',
    ): MessageAttachment {
        $storedPath ??= 'message-attachments/'.$message->conversation_id.'/'.uniqid('attachment-', true).'.pdf';

        if ($write) {
            Storage::disk($disk)->put($storedPath, $contents);
        }

        return MessageAttachment::create([
            'message_id' => $message->id,
            'original_filename' => $filename,
            'stored_path' => $storedPath,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
            'disk' => $disk,
        ]);
    }

    private function validPdf(string $label = 'Waybill'): string
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

    private function servedBytes(TestResponse $response): string
    {
        return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    // ------------------------------------------------------------- View/Download

    public function test_valid_pdf_attachment_can_be_viewed_inline(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $message = $this->message($this->conversation($rider), $rider);
        $pdf = $this->validPdf();
        $attachment = $this->attachment($message, $pdf);

        $response = $this->actingAs($admin)
            ->get(route('messages.attachments.view', $attachment))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame($pdf, $this->servedBytes($response));
    }

    public function test_valid_pdf_attachment_download_matches_stored_bytes(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $message = $this->message($this->conversation($rider), $rider);
        $pdf = $this->validPdf('Download waybill');
        $attachment = $this->attachment($message, $pdf);

        $response = $this->actingAs($admin)
            ->get(route('messages.attachments.download', $attachment))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame($pdf, $this->servedBytes($response));
        $this->assertSame(Storage::disk('local')->get($attachment->stored_path), $this->servedBytes($response));
    }

    public function test_placeholder_pdf_is_reported_as_corrupt(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $message = $this->message($this->conversation($rider), $rider);
        $attachment = $this->attachment($message, "%PDF-1.4\n% Demo waybill attachment\n");

        $this->actingAs($admin)
            ->get(route('messages.attachments.view', $attachment))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->get(route('messages.attachments.download', $attachment))
            ->assertStatus(422);
    }

    public function test_missing_and_empty_attachments_are_reported(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $conversation = $this->conversation($rider);

        $missing = $this->attachment($this->message($conversation, $rider), 'unused', write: false);
        $empty = $this->attachment($this->message($conversation, $rider), '');

        $this->actingAs($admin)
            ->get(route('messages.attachments.view', $missing))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('messages.attachments.view', $empty))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->get(route('messages.attachments.download', $empty))
            ->assertStatus(422);
    }

    public function test_traversal_stored_path_is_not_served(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $message = $this->message($this->conversation($rider), $rider);
        $attachment = $this->attachment(
            $message,
            'secret',
            write: false,
            storedPath: 'message-attachments/../../.env',
        );

        $this->actingAs($admin)
            ->get(route('messages.attachments.view', $attachment))
            ->assertNotFound();
    }

    // ------------------------------------------------------------- Authorization

    public function test_unauthorized_users_cannot_access_logistics_attachments(): void
    {
        Storage::fake('local');

        $buyer = $this->user('buyer');
        $rider = $this->riderUser();
        $message = $this->message($this->conversation($rider), $rider);
        $attachment = $this->attachment($message, $this->validPdf());

        $this->actingAs($buyer)
            ->get(route('messages.attachments.view', $attachment))
            ->assertRedirect('/logistics/login');

        $this->actingAs($buyer)
            ->get(route('messages.attachments.download', $attachment))
            ->assertRedirect('/logistics/login');
    }

    public function test_rider_cannot_view_another_riders_attachment(): void
    {
        Storage::fake('local');

        $owner = $this->riderUser();
        $other = $this->riderUser();
        $attachment = $this->attachment(
            $this->message($this->conversation($owner), $owner),
            $this->validPdf(),
        );

        $this->actingAs($other)
            ->get(route('rider.messages.attachments.view', $attachment))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('rider.messages.attachments.download', $attachment))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('rider.messages.attachments.view', $attachment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ------------------------------------------------------------- Upload flow

    public function test_web_message_upload_of_valid_pdf_is_viewable(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $conversation = $this->conversation($rider);
        $pdf = $this->validPdf('Uploaded waybill');

        $this->actingAs($admin)
            ->post(route('messages.store'), [
                'conversation_id' => $conversation->id,
                'body' => 'Here is the waybill.',
                'attachment' => UploadedFile::fake()->createWithContent('waybill.pdf', $pdf),
            ])
            ->assertRedirect(route('messages.index', ['conversation' => $conversation->id]));

        $attachment = MessageAttachment::query()->latest('id')->firstOrFail();

        $this->assertSame('waybill.pdf', $attachment->original_filename);
        $this->assertSame('local', $attachment->disk);
        $this->assertTrue(Storage::disk('local')->exists($attachment->stored_path));

        $response = $this->actingAs($admin)
            ->get(route('messages.attachments.view', $attachment))
            ->assertOk();

        $this->assertSame($pdf, $this->servedBytes($response));
    }

    public function test_conversation_selection_still_renders_the_thread(): void
    {
        Storage::fake('local');

        $admin = $this->user('logistics_admin');
        $rider = $this->riderUser();
        $conversation = $this->conversation($rider);
        $message = $this->message($conversation, $rider, 'Out for delivery.');
        $this->attachment($message, $this->validPdf());

        $this->actingAs($admin)
            ->get(route('messages.index', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertViewHas('activeConversation', fn ($active) => $active?->id === $conversation->id)
            ->assertViewHas('messagesData', fn ($messages) => $messages->contains(fn ($m) => $m['id'] === $message->id));
    }
}
