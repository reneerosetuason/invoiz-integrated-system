<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiderMessageController extends Controller
{
    private function conversationFor(\Illuminate\Foundation\Auth\User $user): Conversation
    {
        return Conversation::firstOrCreate(
            ['participant_type' => 'rider', 'participant_id' => $user->id],
            [
                'participant_name' => $user->name,
                'subject' => 'Rider Support',
                'last_message_at' => now(),
                'rider_id' => $user->rider?->id,
            ],
        );
    }

    private function messagesFor(Conversation $conversation)
    {
        return $conversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $conversation->messages()->where('is_read', false)->where('sender_type', '!=', 'rider')->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $this->conversationPayload($conversation),
            'messages' => $this->messagesFor($conversation)->map(fn ($m) => $this->payload($m, $user))->values(),
        ]);
    }

    /**
     * Describe the rider's conversation and, importantly, WHO the rider is
     * messaging (the other party). This lets the Driver_app header identify the
     * recipient instead of showing an anonymous thread.
     *
     * The rider's only conversation is a rider <-> Logistics support thread, so
     * the recipient is the Invoiz logistics/support team. Delivery context is
     * surfaced when the thread is tied to a delivery (order_id).
     */
    private function conversationPayload(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            // Recipient (the party the rider is messaging).
            'recipient_name' => 'Logistics',
            'recipient_type' => 'logistics',
            'recipient_label' => 'Invoiz Logistics Support',
            // Delivery context when this thread is tied to a delivery.
            'order_id' => $conversation->order_id,
            'tracking' => $conversation->delivery?->tracking_number,
        ];
    }

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $after = $request->query('after');
        $query = $conversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id');
        if ($after) {
            $query->where('id', '>', (int) $after);
        }

        $conversation->messages()->where('is_read', false)->where('sender_type', '!=', 'rider')->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'messages' => $query->get()->map(fn ($m) => $this->payload($m, $user))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv|max:5120',
        ]);

        $message = null;
        DB::transaction(function () use ($validated, $request, $user, $conversation, &$message) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'rider',
                'sender_id' => $user->id,
                'body' => $validated['body'],
            ]);

            if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
                $file = $request->file('attachment');
                $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
                $storedPath = $file->storeAs("message-attachments/{$conversation->id}", $filename, 'public');

                MessageAttachment::create([
                    'message_id' => $message->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'disk' => 'public',
                ]);
            }

            $conversation->update([
                'last_message_preview' => str($validated['body'])->limit(80),
                'last_message_at' => now(),
            ]);

            // Staff-side unread signal (reset when Logistics opens the thread).
            $conversation->increment('unread_count');
        });

        $message->load('attachments');

        return response()->json([
            'message' => 'Message sent.',
            'data' => $this->payload($message, $user),
        ], 201);
    }

    private function payload(Message $m, $user): array
    {
        $deleted = $m->isDeleted();
        return [
            'id' => $m->id,
            'mine' => $m->sender_type === 'rider' && (int) $m->sender_id === (int) $user->id,
            'sender_type' => $m->sender_type,
            'body' => $deleted ? '' : $m->body,
            'deleted' => $deleted,
            'is_read' => (bool) $m->is_read,
            'edited_at' => $m->edited_at?->toISOString(),
            'created_at' => $m->created_at->toISOString(),
            'time' => $m->created_at->format('g:i A'),
            'day' => $m->created_at->format('Y-m-d'),
            'dayLabel' => $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('F j, Y')),
            'attachments' => $deleted ? [] : $m->attachments->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_filename,
                'size' => $a->humanSize(),
                'is_image' => $a->isImage(),
                'url' => url('storage/' . $a->stored_path),
            ])->values(),
        ];
    }
}





