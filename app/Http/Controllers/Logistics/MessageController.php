<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Logistics\Conversation;
use App\Models\Logistics\Message;
use App\Models\MessageAttachment;
use App\Models\Notification;
use App\Models\Rider;
use App\Models\RiderNotification;
use App\Models\User;
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
        $userId = (int) $request->user()->id;

        $query = Conversation::query()
            ->where(function ($scope) use ($userId) {
                $scope->where('participant_type', '!=', 'logistics')
                    ->orWhere(function ($direct) use ($userId) {
                        $direct->where('participant_type', 'logistics')
                            ->where(function ($members) use ($userId) {
                                $members->where('participant_id', $userId)
                                    ->orWhere('logistics_user_id', $userId);
                            });
                    });
            })
            ->orderBy('last_message_at', 'desc');

        // Unread is an inbox state, not a participant type.
        if ($filter === 'unread') {
            $query->where(function ($unread) use ($userId) {
                $unread->where(function ($shared) {
                    $shared->where('participant_type', '!=', 'logistics')
                        ->where('unread_count', '>', 0);
                })->orWhere(function ($direct) use ($userId) {
                    $direct->where('participant_type', 'logistics')
                        ->whereHas('messages', function ($messages) use ($userId) {
                            $messages->where('sender_type', 'logistics')
                                ->where('sender_id', '!=', $userId)
                                ->where('is_read', false);
                        });
                });
            });
        }

        // Filter by participant type while retaining only shared support
        // threads and direct threads belonging to the current user.
        $available = $this->availableParticipantTypes();
        if ($filter !== 'all' && $filter !== 'unread' && in_array($filter, $available, true)) {
            $query->where('participant_type', $filter);
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $digits = preg_replace('/\D/', '', $search);

            $query->where(function ($w) use ($like, $digits) {
                $w->where('participant_name', 'like', $like)
                  ->orWhereHas('participantUser', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
                  ->orWhereHas('logisticsUser', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
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
        $messagingRoles = ['logistics', 'rider'];

        $inUse = Conversation::query()
            ->select('participant_type')
            ->distinct()
            ->orderBy('participant_type')
            ->pluck('participant_type')
            ->all();

        return array_values(array_unique(array_merge($messagingRoles, $inUse)));
    }

    private function conversationName(Conversation $conversation, int $userId): string
    {
        if ($conversation->participant_type !== 'logistics') {
            return $conversation->participant_name;
        }

        $other = (int) $conversation->logistics_user_id === $userId
            ? $conversation->participantUser
            : $conversation->logisticsUser;

        return $other?->displayName() ?: $other?->email ?: 'Logistics user';
    }

    private function conversationLabel(Conversation $conversation, int $userId): string
    {
        if ($conversation->participant_type !== 'logistics') {
            return ucfirst($conversation->participant_type);
        }

        $other = (int) $conversation->logistics_user_id === $userId
            ? $conversation->participantUser
            : $conversation->logisticsUser;

        return $other && ($other->isAdmin() || $other->isLogisticsAdmin()) ? 'Admin' : 'Logistics';
    }

    private function activePayload(Conversation $conversation): array
    {
        $userId = (int) auth()->id();

        return [
            'id' => $conversation->id,
            'name' => $this->conversationName($conversation, $userId),
            'type' => $conversation->participant_type,
            'label' => $this->conversationLabel($conversation, $userId),
            'order_id' => $conversation->order_id,
            'tracking' => $conversation->delivery?->tracking_number,
        ];
    }

    private function unreadFor(Conversation $conversation, int $userId): int
    {
        if ($conversation->participant_type !== 'logistics') {
            return (int) $conversation->unread_count;
        }

        return $conversation->messages()
            ->where('sender_type', 'logistics')
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    private function totalUnreadFor(User $user): int
    {
        return Conversation::unreadForLogisticsUser((int) $user->id);
    }

    private function authorizeConversation(User $user, Conversation $conversation): void
    {
        abort_unless($user->isLogisticsStaff(), 403);

        if ($conversation->participant_type === 'logistics') {
            abort_unless(
                (int) $conversation->participant_id === (int) $user->id
                    || (int) $conversation->logistics_user_id === (int) $user->id,
                403,
                'You are not a participant in this conversation.'
            );
        }
    }

    private function findConversationFor(User $user, int $conversationId): Conversation
    {
        $conversation = Conversation::findOrFail($conversationId);
        $this->authorizeConversation($user, $conversation);

        return $conversation;
    }

    private function conversationPayload(Conversation $c): array
    {
        $userId = (int) auth()->id();

        return [
            'id' => $c->id,
            'name' => $this->conversationName($c, $userId),
            'type' => $c->participant_type,
            'label' => $this->conversationLabel($c, $userId),
            'order_id' => $c->order_id,
            'tracking' => $c->delivery?->tracking_number,
            'preview' => $c->last_message_preview,
            'unread' => $this->unreadFor($c, $userId),
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

    private function markConversationRead(User $user, Conversation $conversation): void
    {
        $incoming = $conversation->messages()->where('is_read', false);
        if ($conversation->participant_type === 'logistics') {
            $incoming->where('sender_type', 'logistics')->where('sender_id', '!=', $user->id);
        } else {
            $incoming->where('sender_type', '!=', 'logistics');
        }

        $incoming->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        if ($conversation->participant_type !== 'logistics') {
            $conversation->update(['unread_count' => 0]);
        }
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = $this->perPage($request);
        $conversations = $this->conversationQuery($request)
            ->with(['delivery', 'latestMessage.attachments', 'participantUser', 'logisticsUser'])
            ->paginate($perPage)
            ->withQueryString();

        $activeId = (int) $request->get('conversation', 0);
        $activeConversation = $activeId > 0
            ? $this->findConversationFor($user, $activeId)
            : $conversations->first();

        $messages = collect();
        if ($activeConversation) {
            $this->authorizeConversation($user, $activeConversation);
            $activeConversation->load(['delivery', 'participantUser', 'logisticsUser']);
            $this->markConversationRead($user, $activeConversation);
            $messages = $activeConversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
        }

        return view('logistics.messages.index', [
            'conversations' => $conversations,
            'conversationsData' => $conversations->getCollection()->map(fn ($c) => $this->conversationPayload($c))->values(),
            'activeConversation' => $activeConversation,
            'activeData' => $activeConversation ? $this->activePayload($activeConversation) : null,
            'messagesData' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => $this->totalUnreadFor($user),
            'roleFilters' => $this->availableParticipantTypes(),
        ]);
    }

    /**
     * Polling endpoint: current conversation list state + active thread.
     */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $this->perPage($request);
        $conversations = $this->conversationQuery($request)
            ->with(['delivery', 'latestMessage.attachments', 'participantUser', 'logisticsUser'])
            ->paginate($perPage)
            ->withQueryString();

        $activeId = (int) $request->get('conversation', 0);
        $activeConversation = $activeId > 0 ? $this->findConversationFor($user, $activeId) : null;
        $messages = collect();

        if ($activeConversation) {
            $activeConversation->load(['delivery', 'participantUser', 'logisticsUser']);
            $this->markConversationRead($user, $activeConversation);
            $messages = $activeConversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();
        }

        return response()->json([
            'conversations' => $conversations->getCollection()->map(fn ($c) => $this->conversationPayload($c))->values(),
            'active' => $activeConversation ? $this->activePayload($activeConversation) : null,
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => $this->totalUnreadFor($user),
        ]);
    }

    public function recipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:admin,logistics,rider',
            'search' => 'nullable|string|max:100',
        ]);
        $type = $validated['type'];
        $search = trim((string) ($validated['search'] ?? ''));
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

        if ($type === 'rider') {
            $riders = Rider::query()
                ->with('user')
                ->when($search !== '', function ($query) use ($like) {
                    $query->where(function ($matches) use ($like) {
                        $matches->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhereHas('user', function ($user) use ($like) {
                                $user->where('name', 'like', $like)->orWhere('email', 'like', $like);
                            });
                    });
                })
                ->orderBy('name')
                ->limit(50)
                ->get();

            $recipients = $riders
                ->map(fn (Rider $rider) => [
                    // Use the rider profile ID. Older rider profiles may not
                    // have a login account linked yet, but logistics can still
                    // open a thread for that profile.
                    'id' => (int) $rider->id,
                    'name' => $rider->user?->displayName() ?: $rider->name ?: 'Rider',
                    'email' => $rider->user?->email ?: $rider->email,
                    'type' => 'rider',
                ])->values();

            return response()->json(['recipients' => $recipients]);
        }

        $query = User::query()
            ->whereKeyNot($request->user()->id)
            ->when($type === 'admin', function ($query) {
                $query->where(function ($admins) {
                    $admins->where('is_admin', true)
                        ->orWhereIn('role', ['admin', 'logistics_admin']);
                });
            }, function ($query) {
                $query->where(function ($roles) {
                    $roles->whereNull('role')->orWhereNotIn('role', ['admin', 'logistics_admin']);
                })
                    ->where(function ($staff) {
                        $staff->whereIn('role', ['staff', 'logistics_staff'])
                            ->orWhereNotNull('center_id');
                    })
                    ->where(function ($notAdmins) {
                        $notAdmins->whereNull('is_admin')->orWhere('is_admin', false);
                    });
            })
            ->when($search !== '', function ($query) use ($like) {
                $query->where(function ($matches) use ($like) {
                    $matches->where('name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(50);

        $recipients = $query->get()->map(fn (User $user) => [
            'id' => (int) $user->id,
            'name' => $user->displayName() ?: $user->name ?: 'Logistics user',
            'email' => $user->email,
            'type' => $type,
        ])->values();

        return response()->json(['recipients' => $recipients]);
    }

    public function start(Request $request): RedirectResponse|JsonResponse
    {
        $recipientIdRules = ['required', 'integer'];
        if ($request->input('recipient_type') !== 'rider') {
            $recipientIdRules[] = 'exists:users,id';
        }

        $validated = $request->validate([
            'recipient_type' => 'required|in:admin,logistics,rider',
            'recipient_id' => $recipientIdRules,
        ]);
        $user = $request->user();

        if ($validated['recipient_type'] === 'rider') {
            $rider = Rider::query()->with('user')->findOrFail($validated['recipient_id']);
            $recipient = $rider->user;

            abort_if($recipient && (int) $recipient->id === (int) $user->id, 422, 'Choose another user to start a conversation.');

            $conversation = Conversation::query()
                ->where('participant_type', 'rider')
                ->where('rider_id', $rider->id)
                ->first();

            // Reuse threads created before rider_id was recorded when the
            // rider already has a linked user account.
            if (! $conversation && $rider->user_id) {
                $conversation = Conversation::query()
                    ->where('participant_type', 'rider')
                    ->where('participant_id', $rider->user_id)
                    ->first();
            }

            if (! $conversation) {
                $conversation = Conversation::create([
                    'rider_id' => $rider->id,
                    'participant_type' => 'rider',
                    // Zero is a placeholder until a rider login is linked.
                    // rider_id remains the stable identity for this thread.
                    'participant_id' => $rider->user_id ?? 0,
                    'participant_name' => $recipient?->displayName() ?: $rider->name ?: 'Rider',
                    'subject' => 'Rider Support',
                    'last_message_at' => now(),
                ]);
            } else {
                $conversation->rider_id = $rider->id;
                if ($rider->user_id) {
                    $conversation->participant_id = $rider->user_id;
                }
                $conversation->participant_name = $recipient?->displayName() ?: $rider->name ?: 'Rider';
                $conversation->save();
            }
        } else {
            $recipient = User::findOrFail($validated['recipient_id']);
            abort_if((int) $recipient->id === (int) $user->id, 422, 'Choose another user to start a conversation.');

            $recipientIsAdmin = $recipient->isAdmin() || $recipient->isLogisticsAdmin();
            $recipientIsLogistics = $recipient->isLogisticsStaff() && ! $recipientIsAdmin;
            abort_unless(
                $validated['recipient_type'] === 'admin' ? $recipientIsAdmin : $recipientIsLogistics,
                422,
                'This user is not an eligible logistics recipient.'
            );

            $firstId = min((int) $user->id, (int) $recipient->id);
            $secondId = max((int) $user->id, (int) $recipient->id);
            $conversation = Conversation::firstOrCreate(
                ['direct_thread_key' => "logistics:{$firstId}:{$secondId}"],
                [
                    'logistics_user_id' => $user->id,
                    'participant_type' => 'logistics',
                    'participant_id' => $recipient->id,
                    'participant_name' => $recipient->displayName() ?: $recipient->name,
                    'subject' => 'Direct Message',
                    'last_message_at' => now(),
                ],
            );
        }

        $url = route('messages.index', ['conversation' => $conversation->id]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'conversation_id' => $conversation->id,
                'url' => $url,
            ]);
        }

        return redirect($url);
    }

    /**
     * Switch to a conversation (JSON) ??? marks it read.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($user, $conversation);
        $conversation->load(['delivery', 'participantUser', 'logisticsUser']);
        $this->markConversationRead($user, $conversation);

        $messages = $conversation->messages()->with('attachments')->orderBy('created_at')->orderBy('id')->get();

        return response()->json([
            'active' => $this->activePayload($conversation),
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m))->values(),
            'totalUnread' => $this->totalUnreadFor($user),
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
        $this->authorizeConversation($request->user(), $conversation);

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

            // Direct conversations keep their previews in the participant inbox.
            if ($conversation->participant_type !== 'logistics') {
                $centerId = $conversation->rider_id
                    ? Rider::query()->whereKey($conversation->rider_id)->value('center_id')
                    : ($conversation->participant_type === 'rider'
                        ? Rider::query()->where('user_id', $conversation->participant_id)->value('center_id')
                        : null);

                Notification::create([
                    'type' => 'new_message',
                    'title' => 'New Message',
                    'message' => str($validated['body'])->limit(120)->toString(),
                    'icon' => '💬',
                    'priority' => 'normal',
                    'center_id' => $centerId,
                    'audience' => $centerId ? 'center' : 'global',
                    'link' => route('messages.index', ['conversation' => $conversation->id]),
                ]);
            }

            // The rider side has no other signal: notify the participating
            // rider (if resolvable) through the existing rider_notifications
            // table. notifyOnce dedupes retried sends; rider-role sends never
            // reach this controller, so the actor is never notified.
            $riderId = $conversation->rider_id ?: (
                $conversation->participant_type === 'rider'
                    ? Rider::where('user_id', $conversation->participant_id)->value('id')
                    : null
            );

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
        $this->authorizeConversation($user, $message->conversation);
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
        $this->authorizeConversation($user, $message->conversation);
        abort_unless($message->canBeEditedBy($user), 403, 'You can only delete your own messages.');

        $message->update(['deleted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->authorizeConversation($user, $conversation);
        $this->markConversationRead($user, $conversation);

        return response()->json([
            'total_unread' => $this->totalUnreadFor($user),
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
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $this->authorizeConversation($user, $attachment->message->conversation);
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




