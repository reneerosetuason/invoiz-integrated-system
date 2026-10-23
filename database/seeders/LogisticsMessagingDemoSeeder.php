<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logistics inbox conversations for riders, buyers and sellers, with
 * messages, read state and file attachments.
 */
class LogisticsMessagingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('LogisticsMessagingDemoSeeder skipped outside local/testing.');

            return;
        }

        $riders = DB::table('riders')->get()->keyBy('email');
        $riderUsers = DB::table('users')->where('role', 'rider')->pluck('id', 'email');
        $adminId = DB::table('users')->where('role', 'logistics_admin')->value('id');
        $buyer = DB::table('users')->where('email', 'maria@invoiz.test')->first();
        $seller = DB::table('sellers')->where('id', 1)->first();
        $sellerUser = $seller ? DB::table('users')->where('id', $seller->user_id)->first() : null;

        $conversations = [
            [
                'order_id' => 2,
                'rider' => 'rider1@test.com',
                'participant_type' => 'rider',
                'participant_id' => $riderUsers['rider1@test.com'] ?? null,
                'participant_name' => 'Rider One',
                'subject' => 'Delivery TRK-20260819-0001',
                'unread_count' => 2,
                'messages' => [
                    ['sender_type' => 'logistics', 'sender_id' => $adminId, 'body' => 'Good morning! Please confirm the pickup for order ORD-00002.', 'is_read' => true, 'minutes' => 180],
                    ['sender_type' => 'rider', 'sender_id' => $riderUsers['rider1@test.com'] ?? null, 'body' => 'Confirmed. On my way to the seller now.', 'is_read' => true, 'minutes' => 170],
                    ['sender_type' => 'logistics', 'sender_id' => $adminId, 'body' => 'Great. The customer requested delivery before 5 PM.', 'is_read' => false, 'minutes' => 160],
                    ['sender_type' => 'rider', 'sender_id' => $riderUsers['rider1@test.com'] ?? null, 'body' => 'Noted, I will prioritize this route.', 'is_read' => false, 'minutes' => 155],
                ],
            ],
            [
                'order_id' => 3,
                'rider' => 'rider2@test.com',
                'participant_type' => 'rider',
                'participant_id' => $riderUsers['rider2@test.com'] ?? null,
                'participant_name' => 'Rider Two',
                'subject' => 'Delivery TRK-20260819-0002',
                'unread_count' => 0,
                'messages' => [
                    ['sender_type' => 'rider', 'sender_id' => $riderUsers['rider2@test.com'] ?? null, 'body' => 'The package is out for delivery.', 'is_read' => true, 'minutes' => 90],
                    ['sender_type' => 'logistics', 'sender_id' => $adminId, 'body' => 'Thanks for the update.', 'is_read' => true, 'minutes' => 80],
                ],
            ],
            [
                'order_id' => 26,
                'rider' => 'rider3@test.com',
                'participant_type' => 'rider',
                'participant_id' => $riderUsers['rider3@test.com'] ?? null,
                'participant_name' => 'Rider Three',
                'subject' => 'Cebu to Manila transfer',
                'unread_count' => 1,
                'messages' => [
                    ['sender_type' => 'logistics', 'sender_id' => $adminId, 'body' => 'Please scan the parcels at the Cebu sorting center.', 'is_read' => true, 'minutes' => 60],
                    ['sender_type' => 'rider', 'sender_id' => $riderUsers['rider3@test.com'] ?? null, 'body' => 'All parcels scanned and sorted.', 'is_read' => false, 'minutes' => 45],
                ],
            ],
            [
                'order_id' => 2,
                'rider' => 'rider1@test.com',
                'participant_type' => 'buyer',
                'participant_id' => $buyer->id ?? null,
                'participant_name' => $buyer->name ?? 'Maria Buyer',
                'subject' => 'Question about my delivery',
                'unread_count' => 1,
                'messages' => [
                    ['sender_type' => 'buyer', 'sender_id' => $buyer->id ?? null, 'body' => 'Hi, can the rider call me before arriving?', 'is_read' => false, 'minutes' => 30],
                ],
            ],
            [
                'order_id' => 2,
                'rider' => 'rider1@test.com',
                'participant_type' => 'seller',
                'participant_id' => $sellerUser->id ?? null,
                'participant_name' => $seller?->store_name ?? 'Demo Seller',
                'subject' => 'Pickup schedule',
                'unread_count' => 0,
                'messages' => [
                    ['sender_type' => 'seller', 'sender_id' => $sellerUser->id ?? null, 'body' => 'The package is ready for pickup.', 'is_read' => true, 'minutes' => 240],
                    ['sender_type' => 'logistics', 'sender_id' => $adminId, 'body' => 'A rider has been assigned.', 'is_read' => true, 'minutes' => 230],
                ],
            ],
        ];

        foreach ($conversations as $conversation) {
            $rider = $conversation['rider'] ? ($riders[$conversation['rider']] ?? null) : null;
            $last = end($conversation['messages']);
            $lastAt = now()->subMinutes($last['minutes']);

            DB::table('logistics_conversations')->updateOrInsert(
                [
                    'order_id' => $conversation['order_id'],
                    'participant_type' => $conversation['participant_type'],
                    'participant_id' => $conversation['participant_id'],
                ],
                [
                    'rider_id' => $rider->id ?? null,
                    'participant_name' => $conversation['participant_name'],
                    'subject' => $conversation['subject'],
                    'last_message_preview' => $last['body'],
                    'last_message_at' => $lastAt,
                    'unread_count' => $conversation['unread_count'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $conversationId = DB::table('logistics_conversations')
                ->where('order_id', $conversation['order_id'])
                ->where('participant_type', $conversation['participant_type'])
                ->where('participant_id', $conversation['participant_id'])
                ->value('id');

            foreach ($conversation['messages'] as $index => $message) {
                $createdAt = now()->subMinutes($message['minutes']);

                DB::table('logistics_messages')->updateOrInsert(
                    [
                        'conversation_id' => $conversationId,
                        'sender_type' => $message['sender_type'],
                        'sender_id' => $message['sender_id'],
                        'body' => $message['body'],
                    ],
                    [
                        'is_read' => $message['is_read'],
                        'read_at' => $message['is_read'] ? $createdAt->copy()->addMinute() : null,
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );

                if ($conversation['participant_type'] === 'rider' && $index === 0) {
                    $messageRecord = DB::table('logistics_messages')
                        ->where('conversation_id', $conversationId)
                        ->where('body', $message['body'])
                        ->first();

                    if ($messageRecord) {
                        $path = "message-attachments/{$conversationId}/waybill.pdf";
                        $pdf = $this->demoPdf('Demo waybill attachment');
                        Storage::disk('local')->put($path, $pdf);

                        DB::table('logistics_message_attachments')->updateOrInsert(
                            ['message_id' => $messageRecord->id, 'original_filename' => 'waybill.pdf'],
                            [
                                'stored_path' => $path,
                                'mime_type' => 'application/pdf',
                                'file_size' => strlen($pdf),
                                'disk' => 'local',
                                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * A structurally valid single-page PDF so demo attachments actually open
     * in a browser viewer instead of being a bare header.
     */
    private function demoPdf(string $label): string
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
}
