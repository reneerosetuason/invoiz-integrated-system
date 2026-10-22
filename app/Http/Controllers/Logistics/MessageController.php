<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Logistics\Conversation;
use App\Models\Logistics\Message;
use App\Models\MessageAttachment;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\RiderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MessageController extends Controller
{
    private function perPage(Request $request): int
    {
        return in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;
    }

    private function conversationQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $filter = trim((string) $request->query('filter', 'all'));

        $query = Conversation::query()->orderBy('last_message_at', 'desc');

        // Unread is an inbox state, not a participant type.
        if ($filter === 'unread') {
            $query->where('unread_count', '>', 0);
        }

        // Dynamic participant-type filter ??? built from the roles that actually
        // exist in conversations (riders today; new roles appear automatically).
        $available = $this->availableParticipantTypes();
        if ($filter !== 'all' && $filter !== 'unread' && in_array($filter, $available, true)) {
            $query->where('participant_type', $filter);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $digits = preg_replace('/\D/', '', $search);

            $query->where(function ($w) use ($like, $digits) {
                $w->where('participant_name', 'like', $like)
                  ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $like));

                if ($digits !== '') {
                    $w->orWhere('order_id', (int) $digits);
                }
            });
        }

        return $query;
    }

    /**
     * Participant types for the filter chips. Built from:
     *  1. Roles that exist in the application and are valid messaging
     *     participants for Logistics (riders message Logistics; admin/staff
     *     ARE the Logistics side) ??? shown even with zero conversations;
     *  2. Participant types actually present in conversations (new roles
     *     appear automatically once they message Logistics).
     * No fake roles are invented.
     */
    private function availableParticipantTypes(): array
    {
        $messagingRoles = ['rider']; // valid external messaging participants

        $inUse = Conversation::query()
            ->select('participant_type')
            ->distinct()
            ->orderBy('participant_type')
            ->pluck('participant_type')
            ->all();

        return array_values(array_unique(array_merge($messagingRoles, $inUse)));
    }

    private function conversationPayload(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->participant_name,
            'type' => $c->participant_type,
            'order_id' => $c->order_id,
            'tracking' => $c->delivery?->tracking_number,
            'preview' => $c->last_message_preview,
            'unread' => (int) $c->unread_count,
            'last_message_at' => $c->last_message_at?->toISOString(),
            'time' => $c->last_message_at?->format('g:i A'),
            'has_attachment' => (bool) $c->latestMessage?->attachments()->exists(),
        ];
    }

    private function messagePayload(Message $m): array
    {
        $deleted = $m->isDeleted();

        return [
            'id' => $m->id,
            'mine' => $m->sender_type === 'logistics' && (int) $m->sender_id === auth()->id(),
            'sender_type' => $m->sender_type,
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
                'view_url' => route('messages.attachments.view', $a),
                'download_url' => route('messages.attachments.download', $a),
            ]),
        ];
    }

    private function markConversationRead(Conversation $conversation): void
    {
        $conversation->messages()->where('is_read', false)->where('sender_type', '!=', 'logistics')->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
        $conversation->update(['unread_count' => 0]);
    }

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);
        $conversations = $this->conversationQuery($request)->with(['delivery', 'latestMessage.attachments'])->paginate($perPage)->withQueryString();

        $activeId = (int) $request->get('conversation', 0);
        $activeConversation = $activeId > 0
            ? Conversation::find($activeId)
            : ($conversations->first() ? Conversation::find($conversations->first()->id) : null);

        $messages = collect();
        if ($activeConversation) {
            $this->markConversationRead($activeConversation);
            $messages = $activeConversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
        }

        return view('logistics.messages.index', [
            'conversations' => $conversations,
            'conversationsData' => $conversations->getCollection()->map(fn ($c) => $this->conversationPayload($c))->values(),
            'activeConversation' => $activeConversation,
            'messagesData' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => (int) Conversation::sum('unread_count'),
            'roleFilters' => $this->availableParticipantTypes(),
        ]);
    }

    /**
     * Polling endpoint: current conversation list state + active thread.
     */
    public function poll(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);
        $conversations = $this->conversationQuery($request)->with(['delivery', 'latestMessage.attachments'])->paginate($perPage)->withQueryString();

        $activeId = (int) $request->get('conversation', 0);
        $activeConversation = $activeId > 0 ? Conversation::find($activeId) : null;
        $messages = collect();

        if ($activeConversation) {
            $this->markConversationRead($activeConversation);
            $messages = $activeConversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
        }

        return response()->json([
            'conversations' => $conversations->getCollection()->map(fn ($c) => $this->conversationPayload($c))->values(),
            'active' => $activeConversation ? [
                'id' => $activeConversation->id,
                'name' => $activeConversation->participant_name,
                'type' => $activeConversation->participant_type,
                'order_id' => $activeConversation->order_id,
                'tracking' => $activeConversation->delivery?->tracking_number,
            ] : null,
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => (int) Conversation::sum('unread_count'),
        ]);
    }

    /**
     * Switch to a conversation (JSON) ??? marks it read.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->markConversationRead($conversation);

        $messages = $conversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();

        return response()->json([
            'active' => [
                'id' => $conversation->id,
                'name' => $conversation->participant_name,
                'type' => $conversation->participant_type,
                'order_id' => $conversation->order_id,
                'tracking' => $conversation->delivery?->tracking_number,
            ],
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => (int) Conversation::sum('unread_count'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|integer|exists:logistics_conversations,id',
            'body' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv|max:5120',
        ], [
            'body.required' => 'Please type a message before sending.',
            'attachment.mimes' => 'Attachment must be a JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX or CSV file.',
            'attachment.max' => 'Attachment must not exceed 5 MB.',
        ]);

        $conversation = Conversation::findOrFail($validated['conversation_id']);

        DB::transaction(function () use ($validated, $request, $conversation) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'logistics',
                'sender_id' => auth()->id(),
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

            Notification::create([
                'type' => 'new_message',
                'title' => 'New Message',
                'message' => str($validated['body'])->limit(120)->toString(),
                'icon' => '????',
                'priority' => 'normal',
                'link' => route('messages.index', ['conversation' => $conversation->id]),
            ]);

            // The rider side has no other signal: notify the participating
            // rider (if resolvable) through the existing rider_notifications
            // table. notifyOnce dedupes retried sends; rider-role sends never
            // reach this controller, so the actor is never notified.
            $riderId = $conversation->participant_type === 'rider'
                ? Rider::where('user_id', $conversation->participant_id)->value('id')
                : $conversation->rider_id;

            if ($riderId) {
                RiderNotification::notifyOnce(
                    (int) $riderId,
                    'new_message',
                    ['conversation_id' => $conversation->id],
                    'New Message',
                    str($validated['body'])->limit(120)->toString(),
                );
            }
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('messages.index', ['conversation' => $conversation->id]);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'staff', 'logistics_admin', 'logistics_staff']), 403);
        abort_unless($message->canBeEditedBy($user), 403, 'You can only edit your own messages.');

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
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'staff', 'logistics_admin', 'logistics_staff']), 403);
        abort_unless($message->canBeEditedBy($user), 403, 'You can only delete your own messages.');

        $message->update(['deleted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markRead(Conversation $conversation): JsonResponse
    {
        $this->markConversationRead($conversation);

        return response()->json([
            'total_unread' => (int) Conversation::sum('unread_count'),
        ]);
    }

    public function viewAttachment(MessageAttachment $attachment): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveAttachment($attachment, 'inline');
    }

    public function downloadAttachment(MessageAttachment $attachment): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->serveAttachment($attachment, 'attachment');
    }

    /**
     * Attachments are private: served through authorized routes only.
     * Logistics staff may access any conversation attachment.
     */
    private function serveAttachment(MessageAttachment $attachment, string $disposition): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'staff', 'logistics_admin', 'logistics_staff']), 403);
        abort_unless($attachment->fileExists(), 404, 'Attachment is missing or was deleted.');
        abort_if($attachment->fileSizeOnDisk() === 0, 422, 'Attachment file is empty and cannot be opened.');
        abort_unless($attachment->isServable(), 422, 'Attachment file is corrupt or its contents do not match the expected file type.');

        // Only genuine PDFs/images preview inline; everything else downloads
        // so the browser never renders an invalid preview.
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
}




