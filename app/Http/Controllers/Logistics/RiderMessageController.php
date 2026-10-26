<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Logistics\Conversation;
use App\Models\Logistics\Message;
use App\Models\MessageAttachment;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Minimal messaging interface for rider accounts. Riders can only ever see
 * and participate in their own conversation with Logistics (participant_id
 * is pinned to the authenticated rider's user id).
 */
class RiderMessageController extends Controller
{
    private function conversationFor(\Illuminate\Foundation\Auth\User $user): Conversation
    {
        $rider = Rider::query()->where('user_id', $user->id)->first();

        // Prefer the stable rider profile ID so a thread created before the
        // rider account was linked becomes visible as soon as they sign in.
        $conversation = $rider
            ? Conversation::query()
                ->where('participant_type', 'rider')
                ->where('rider_id', $rider->id)
                ->first()
            : null;

        $conversation ??= Conversation::query()
            ->where('participant_type', 'rider')
            ->where('participant_id', $user->id)
            ->first();

        if (! $conversation) {
            return Conversation::create([
                'rider_id' => $rider?->id,
                'participant_type' => 'rider',
                'participant_id' => $user->id,
                'participant_name' => $user->name,
                'subject' => 'Rider Support',
                'last_message_at' => now(),
            ]);
        }

        $conversation->participant_id = $user->id;
        $conversation->participant_name = $user->name;
        if ($rider) {
            $conversation->rider_id = $rider->id;
        }
        if ($conversation->isDirty()) {
            $conversation->save();
        }

        return $conversation;
    }

    private function messagesFor(Conversation $conversation)
    {
        return $conversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);
        $messages = $this->messagesFor($conversation);

        // Incoming messages become read when the rider opens the page.
        $conversation->messages()->where('is_read', false)->where('sender_type', '!=', 'rider')->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        $conversation->update(['unread_count' => 0]);

        return view('logistics.rider.messages', [
            'user' => $user,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $conversation->messages()->where('is_read', false)->where('sender_type', '!=', 'rider')->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'messages' => $this->messagesFor($conversation)->map(fn ($m) => $this->messagePayload($m, 'rider', $user->id))->values(),
            'logisticsUnread' => (int) $conversation->messages()->where('sender_type', 'rider')->where('is_read', false)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $conversation = $this->conversationFor($user);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv|max:5120',
        ], [
            'body.required' => 'Please type a message before sending.',
            'attachment.mimes' => 'Attachment must be a JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX or CSV file.',
            'attachment.max' => 'Attachment must not exceed 5 MB.',
        ]);

        DB::transaction(function () use ($validated, $request, $user, $conversation) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'rider',
                'sender_id' => $user->id,
                'body' => $validated['body'],
            ]);

            if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
                $file = $request->file('attachment');
                $filename = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
                $storedPath = $file->storeAs("message-attachments/{$conversation->id}", $filename);

                MessageAttachment::create([
                    'message_id' => $message->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'disk' => 'local',
                ]);
            }

            $conversation->update([
                'last_message_preview' => str($validated['body'])->limit(80),
                'last_message_at' => now(),
            ]);

            // Staff-side unread signal (the column is reset when Logistics
            // opens the thread). Rider's own view derives from message rows.
            $conversation->increment('unread_count');
        });

        return redirect()->route('rider.messages')->with('success', 'Message sent.');
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->canBeEditedBy($request->user()) && $request->user()->role === 'rider', 403);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        $message->conversation->update([
            'last_message_preview' => str($validated['body'])->limit(80),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->canBeEditedBy($request->user()) && $request->user()->role === 'rider', 403);

        $message->update(['deleted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function viewAttachment(MessageAttachment $attachment)
    {
        return $this->serveAttachment($attachment, 'inline');
    }

    public function downloadAttachment(MessageAttachment $attachment)
    {
        return $this->serveAttachment($attachment, 'attachment');
    }

    /**
     * Riders may only access attachments inside their own conversation.
     */
    private function serveAttachment(MessageAttachment $attachment, string $disposition)
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'rider', 403);

        $conversation = $this->conversationFor($user);
        abort_unless($attachment->message->conversation_id === $conversation->id, 403);
        abort_unless($attachment->fileExists(), 404, 'Attachment is missing or was deleted.');
        abort_if($attachment->fileSizeOnDisk() === 0, 422, 'Attachment file is empty and cannot be opened.');
        abort_unless($attachment->isServable(), 422, 'Attachment file is corrupt or its contents do not match the expected file type.');

        $inline = $disposition === 'inline' && $attachment->isBrowserPreviewable();

        return response()
            ->file($attachment->absolutePath(), [
                'Content-Type' => $attachment->contentMime(),
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->setContentDisposition(
                $inline
                    ? \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE
                    : \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->safeAttachmentFilename($attachment),
            );
    }

    private function safeAttachmentFilename(MessageAttachment $attachment): string
    {
        $name = basename((string) ($attachment->original_filename ?: $attachment->stored_path));

        return preg_replace('/[\x00-\x1F\x7F"]/', '', $name) ?: 'attachment';
    }

    private function messagePayload(Message $m, string $viewerType, int $viewerId): array
    {
        $deleted = $m->isDeleted();

        return [
            'id' => $m->id,
            'mine' => $m->sender_type === $viewerType && (int) $m->sender_id === $viewerId,
            'deleted' => $deleted,
            'body' => $deleted ? '' : $m->body,
            'is_read' => $m->is_read,
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
                'is_pdf' => $a->isPdf(),
                'view_url' => route('rider.messages.attachments.view', $a),
                'download_url' => route('rider.messages.attachments.download', $a),
            ]),
        ];
    }
}




