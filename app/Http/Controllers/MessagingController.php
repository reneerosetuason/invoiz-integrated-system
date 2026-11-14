<?php

namespace App\Http\Controllers;

use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\HubTransportDriver;
use App\Models\Logistics\Conversation as LogisticsConversation;
use App\Models\Logistics\Message as LogisticsMessage;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Support\MessagingAccess;
use App\Support\ParcelChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingController extends Controller
{
    private function actor(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && MessagingAccess::active($actor), 403);
        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        if ($request->expectsJson()) return $this->inbox($request);
        $layout = $actor instanceof User && $actor->isAdmin() ? 'admin.layout'
            : ($actor instanceof User && ($actor->isLogisticsStaff() || $actor->role === 'rider') ? 'logistics.layouts.app'
                : ($actor instanceof User && $actor->isSeller() ? 'seller.layouts.app' : 'buyer.layouts.website'));
        return view('shared.messages.index', compact('layout'));
    }

    /** Lightweight unread badge count for every messaging-capable actor. */
    public function unread(Request $request)
    {
        $actor = $this->actor($request);
        return response()->json(['unread_count' => MessagingAccess::unreadCount($actor)]);
    }

    public function contacts(Request $request)
    {
        $actor = $this->actor($request);
        $search = $request->validate(['search' => 'nullable|string|max:100'])['search'] ?? '';
        $users = User::query()->when($search !== '', fn ($q) => $q->where(fn ($q) =>
            $q->where('name', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')))
            ->orderByDesc('is_admin')->orderBy('name')->get()->filter(function ($other) use ($actor) {
                if ($actor instanceof User && ($actor->isBuyer() || $actor->canSell()) && ! $other->isAdmin()) {
                    if ($other->role === 'rider' || ($actor->isBuyer() && ! $actor->canSell() && $other->isLogisticsStaff())) return false;
                }
                return MessagingAccess::canContact($actor, $other);
            })
            ->take(60)->map(fn ($other) => [
                'key' => 'user:'.$other->id, 'name' => $other->displayName() ?: $other->email,
                'role' => $other->isAdmin() ? 'Platform support' : $other->roleLabel(),
            ])->values();
        if ($actor instanceof User && $actor->isLogisticsStaff()) {
            $drivers = HubTransportDriver::query()->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
                ->get()->filter(fn ($other) => MessagingAccess::canContact($actor, $other))->take(60)
                ->map(fn ($other) => ['key' => 'driver:'.$other->id, 'name' => $other->name, 'role' => 'Hub Transport Driver']);
            $users = $users->concat($drivers);
        }
        if ($actor instanceof User && ($actor->isBuyer() || $actor->canSell())) {
            $parcels = ParcelChat::deliveries($actor)->map(function ($parcel) {
                $holder = ParcelChat::holder($parcel);
                return $holder ? ['key' => 'parcel:'.$parcel->id, 'name' => $holder['name'], 'role' => $holder['label'].' · '.$parcel->tracking_number,
                    'delivery_id' => $parcel->id, 'tracking' => $parcel->tracking_number, 'action_label' => $holder['label']] : null;
            })->filter()->filter(fn ($row) => $search === '' || str_contains(mb_strtolower($row['name'].' '.$row['role']), mb_strtolower($search)));
            $users = $parcels->concat($users);
        }
        return response()->json(['contacts' => $users->values()]);
    }

    public function start(Request $request)
    {
        $actor = $this->actor($request);
        $data = $request->validate(['recipient' => ['required', 'regex:/^(user|driver|parcel):[1-9][0-9]*$/']]);
        [$type, $id] = explode(':', $data['recipient']);
        if ($type === 'parcel') {
            abort_unless($actor instanceof User, 403);
            $thread = DB::transaction(function () use ($actor, $id) {
                $parcel = Delivery::whereKey($id)->lockForUpdate()->firstOrFail();
                return ParcelChat::open($actor, $parcel);
            });
            return response()->json(['thread_key' => 'logistics:'.$thread->id], 201);
        }
        $other = $type === 'driver' ? HubTransportDriver::findOrFail($id) : User::findOrFail($id);
        abort_unless(MessagingAccess::canContact($actor, $other), 403, 'This contact is not available for your role or assignment.');
        foreach ([[$actor, $other], [$other, $actor]] as [$customer, $handler]) {
            if ($customer instanceof User && ($customer->isBuyer() || $customer->canSell())
                && ! ($customer->canSell() && $handler instanceof User && $handler->isLogisticsStaff())
                && ($parcel = ParcelChat::related($customer, $handler))) {
                $thread = ParcelChat::open($customer, $parcel);
                return response()->json(['thread_key' => 'logistics:'.$thread->id], 201);
            }
        }
        // Blocked users cannot open new marketplace threads (admins exempt).
        if ($actor instanceof User && $other instanceof User && ChatBlock::blocksBetween($actor, $other)) {
            abort(403, 'You can no longer exchange messages with this user.');
        }
        if ($actor instanceof HubTransportDriver || $other instanceof HubTransportDriver) {
            $driver = $actor instanceof HubTransportDriver ? $actor : $other;
            $staff = $actor instanceof User ? $actor : $other;
            $thread = LogisticsConversation::firstOrCreate(
                ['direct_thread_key' => 'hub_driver:'.$driver->id.':user:'.$staff->id],
                ['participant_type' => 'hub_driver', 'participant_id' => $driver->id,
                    'participant_name' => $driver->name, 'logistics_user_id' => $staff->id,
                    'subject' => 'Hub transport support', 'last_message_at' => now()]
            );
            $key = 'logistics:'.$thread->id;
        } else {
            $riderUser = $actor->rider ? $actor : ($other->rider ? $other : null);
            $customer = $riderUser?->id === $actor->id ? $other : $actor;
            if ($riderUser && $customer->isLogisticsStaff()) {
                $support = LogisticsConversation::firstOrCreate(
                    ['participant_type' => 'rider', 'participant_id' => $riderUser->id],
                    ['rider_id' => $riderUser->rider->id, 'participant_name' => $riderUser->displayName(),
                        'subject' => 'Rider Support', 'last_message_at' => now()]);
                return response()->json(['thread_key' => 'logistics:'.$support->id], 201);
            }
            if ($riderUser) {
                $deliveryThread = LogisticsConversation::where('rider_id', $riderUser->rider->id)
                    ->whereIn('participant_type', ['buyer', 'seller'])->where('participant_id', $customer->id)
                    ->get()->first(fn ($thread) => MessagingAccess::canSendLogistics($actor, $thread));
                if ($deliveryThread) return response()->json(['thread_key' => 'logistics:'.$deliveryThread->id], 201);
            }
            // Reuse existing logistics DMs so opening the shared inbox does not
            // split a staff-to-staff history into two independent chats.
            $ids = [(int) $actor->id, (int) $other->id]; sort($ids);
            $thread = LogisticsConversation::where('participant_type', 'logistics')
                ->where('direct_thread_key', 'logistics:'.implode(':', $ids))->first();
            if ($thread) {
                $key = 'logistics:'.$thread->id;
            } else {
                $thread = Conversation::where(fn ($q) => $q->where('buyer_id', $actor->id)->where('seller_id', $other->id))
                    ->orWhere(fn ($q) => $q->where('buyer_id', $other->id)->where('seller_id', $actor->id))->first();
                if (! $thread) {
                    $first = $actor->isBuyer() ? $actor : ($other->isBuyer() ? $other : $actor);
                    $second = $first->id === $actor->id ? $other : $actor;
                    $thread = Conversation::create(['buyer_id' => $first->id, 'seller_id' => $second->id, 'subject' => 'Direct message']);
                }
                $key = 'marketplace:'.$thread->id;
            }
        }
        return response()->json(['thread_key' => $key], 201);
    }

    public function inbox(Request $request)
    {
        $actor = $this->actor($request);
        if ($actor instanceof User && $actor->rider && $actor->hasApprovedRiderProfile()) {
            app(\App\Http\Controllers\Logistics\Api\RiderConversationController::class)
                ->ensureConversationsForRider((int) $actor->rider->id, (int) $actor->id);
        }
        if ($actor instanceof User && ($actor->isBuyer() || $actor->canSell())) {
            ParcelChat::sync($actor);
        }
        $threads = collect();
        if ($actor instanceof User) {
            \App\Support\ChatBridge::linkExistingForBuyer((int) $actor->id);
            $threads = Conversation::where(fn ($q) => $q->where('buyer_id', $actor->id)->orWhere('seller_id', $actor->id))
                ->with(['buyer', 'seller', 'lastMessage'])->get()->map(function ($thread) use ($actor) {
                    $other = (int) $thread->buyer_id === (int) $actor->id ? $thread->seller : $thread->buyer;
                    return $this->threadPayload($thread, 'marketplace', $other?->displayName() ?: $thread->subject ?: 'Support',
                        $other ? MessagingAccess::canContact($actor, $other) : false,
                        $thread->messages()->where('sender_id', '!=', $actor->id)->where('is_read', false)->count());
                });
        }
        $logistics = MessagingAccess::logisticsQuery($actor)->with('latestMessage')->get()
            ->filter(fn ($thread) => MessagingAccess::canReadLogistics($actor, $thread))
            ->map(function ($thread) use ($actor) {
                $mineType = MessagingAccess::senderType($actor, $thread);
                $name = $thread->participant_name;
                if ($thread->participant_type === 'logistics') {
                    $otherId = (int) $thread->participant_id === (int) $actor->id ? $thread->logistics_user_id : $thread->participant_id;
                    $name = User::find($otherId)?->displayName() ?: 'Logistics';
                } elseif ($thread->participant_type === 'hub_driver' && $actor instanceof HubTransportDriver) {
                    $name = User::find($thread->logistics_user_id)?->displayName() ?: 'Logistics';
                } elseif ($thread->participant_type === 'rider' && (int) $thread->participant_id === (int) $actor->id) {
                    $name = 'Logistics support';
                } elseif (in_array($thread->participant_type, ['buyer', 'seller'], true) && $actor instanceof User && (int) $thread->participant_id === (int) $actor->id) {
                    $identity = ParcelChat::parse($thread->direct_thread_key);
                    $name = match ($identity['kind'] ?? 'r') {
                        'h' => \App\Models\LogisticsCenter::find($identity['id'])?->name ?? 'Logistics hub',
                        'd' => HubTransportDriver::find($identity['id'])?->name ?? 'Transport driver',
                        default => \App\Models\Rider::find($thread->rider_id)?->name ?? 'Previous delivery rider',
                    };
                }
                $unread = $thread->messages()->where('is_read', false)->where(fn ($q) =>
                    $q->where('sender_type', '!=', $mineType)->orWhere('sender_id', '!=', $actor->id))->count();
                return $this->threadPayload($thread, 'logistics', $name, MessagingAccess::canSendLogistics($actor, $thread), $unread);
            });
        $threads = $threads->concat($logistics)->sortByDesc('last_message_at')->values();
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') $threads = $threads->filter(fn ($t) => str_contains(mb_strtolower($t['name'].' '.$t['preview']), mb_strtolower($search)))->values();
        return response()->json(['conversations' => $threads, 'unread_count' => $threads->sum('unread'),
            'pagination' => ['current_page' => 1, 'last_page' => 1]]);
    }

    private function threadPayload($thread, string $source, string $name, bool $canSend, int $unread): array
    {
        $last = $source === 'marketplace' ? $thread->lastMessage : $thread->latestMessage;
        $preview = $source === 'logistics' && $last?->isDeleted() ? 'Message deleted' : ($last?->body ?? '');
        $identity = $source === 'logistics' ? ParcelChat::parse($thread->direct_thread_key) : null;
        $parcel = $identity ? Delivery::find($identity['delivery_id']) : null;
        $previousShared = $source === 'logistics' && ! $identity && in_array($thread->participant_type, ['buyer', 'seller'], true)
            && $thread->messages()->where('sender_type', 'logistics')->exists();
        if ($previousShared) $name = 'Previous shared conversation';
        return ['delivery_id' => $parcel?->id, 'tracking' => $parcel?->tracking_number,
            'action_label' => $identity ? match ($identity['kind']) { 'h' => 'Chat logistics hub', 'd' => 'Chat transport driver', default => 'Chat rider' } : null,
            'id' => $thread->id, 'thread_key' => $source.':'.$thread->id, 'name' => $name,
            'subject' => $thread->subject, 'type' => $source === 'marketplace' ? 'direct' : $thread->participant_type,
            'readonly_reason' => $previousShared ? 'This previous shared conversation is closed. Use the parcel contact for a private chat with its current handler.'
                : ($identity && ! $canSend ? 'This parcel has moved to another handler. Use its current parcel chat; this conversation is now read-only.' : null),
            'type_label' => $identity ? 'Private parcel chat' : 'MESSAGE', 'preview' => $preview, 'unread' => $unread, 'can_send' => $canSend,
            'last_message_at' => ($last?->created_at ?? $thread->updated_at)?->toISOString()];
    }

    private function thread(Request $request, string $key): array
    {
        $actor = $this->actor($request);
        abort_unless(preg_match('/^(marketplace|logistics):([1-9][0-9]*)$/', $key, $match), 404);
        $thread = $match[1] === 'marketplace' ? Conversation::findOrFail($match[2]) : LogisticsConversation::findOrFail($match[2]);
        abort_unless($match[1] === 'marketplace'
            ? ($actor instanceof User && MessagingAccess::canReadMarketplace($actor, $thread))
            : MessagingAccess::canReadLogistics($actor, $thread), 403);
        return [$actor, $thread, $match[1]];
    }

    public function messages(Request $request, string $key)
    {
        [$actor, $thread, $source] = $this->thread($request, $key);
        $incoming = $thread->messages()->where('is_read', false);
        if ($source === 'marketplace') $incoming->where('sender_id', '!=', $actor->id);
        else $incoming->where(fn ($q) => $q->where('sender_type', '!=', MessagingAccess::senderType($actor, $thread))->orWhere('sender_id', '!=', $actor->id));
        $incoming->update(['is_read' => true]);
        if ($source === 'logistics' && in_array($thread->participant_type, ['rider', 'hub_driver'], true)
            && $actor instanceof User && $actor->isLogisticsStaff()) {
            $thread->update(['unread_count' => 0]);
        }
        $page = $thread->messages()->reorder()->orderByDesc('id')->paginate(30);
        return response()->json(['can_send' => $source === 'logistics' ? MessagingAccess::canSendLogistics($actor, $thread) : (($other = User::find((int) $thread->buyer_id === (int) $actor->id ? $thread->seller_id : $thread->buyer_id)) && MessagingAccess::canContact($actor, $other)),
            'messages' => $page->getCollection()->map(fn ($message) => $this->messagePayload($message, $actor, $source, $thread)),
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function send(Request $request, string $key)
    {
        [$actor, $thread, $source] = $this->thread($request, $key);
        $otherId = $source === 'marketplace' ? ((int) $thread->buyer_id === (int) $actor->id ? $thread->seller_id : $thread->buyer_id) : null;
        $other = $otherId ? User::find($otherId) : null;
        abort_unless($source === 'marketplace' ? ($other && MessagingAccess::canContact($actor, $other)) : MessagingAccess::canSendLogistics($actor, $thread), 403);
        // Blocked users cannot exchange marketplace messages (admins exempt).
        if ($other && ChatBlock::blocksBetween($actor, $other)) {
            abort(403, 'You can no longer exchange messages with this user.');
        }
        $data = $request->validate(['body' => ['required', 'string', 'max:2000', new \App\Rules\CleanMessage],
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv|max:5120']);
        if ($source === 'marketplace' && $request->hasFile('attachment')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['attachment' => 'Attachments are available in logistics conversations.']);
        }
        $message = DB::transaction(function () use ($request, $actor, $thread, $source, $otherId, $data) {
            if ($source === 'marketplace') {
                $message = Message::create(['conversation_id' => $thread->id, 'sender_id' => $actor->id, 'receiver_id' => $otherId, 'body' => $data['body'], 'is_read' => false]);
                $thread->touch();
            } else {
                ParcelChat::lockAndAuthorize($actor, $thread);
                $message = LogisticsMessage::create(['conversation_id' => $thread->id, 'sender_id' => $actor->id,
                    'sender_type' => MessagingAccess::senderType($actor, $thread), 'body' => $data['body'], 'is_read' => false]);
                $thread->update(['last_message_preview' => mb_substr($data['body'], 0, 80), 'last_message_at' => now()]);
                if ($request->hasFile('attachment')) {
                    $file = $request->file('attachment');
                    $path = $file->store('message-attachments/'.$thread->id, 'local');
                    MessageAttachment::create(['message_id' => $message->id, 'original_filename' => $file->getClientOriginalName(),
                        'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'disk' => 'local']);
                }
                if (in_array($thread->participant_type, ['rider', 'hub_driver'], true) && ! ($actor instanceof User && $actor->isLogisticsStaff())) $thread->increment('unread_count');
            }
            return $message;
        });
        return response()->json(['data' => $this->messagePayload($message, $actor, $source, $thread)], 201);
    }

    public function update(Request $request, string $key, int $id)
    {
        [$actor, $thread, $source] = $this->thread($request, $key);
        $message = $thread->messages()->findOrFail($id);
        $payload = $this->messagePayload($message, $actor, $source, $thread);
        abort_unless($payload['mine'] && ! $payload['deleted'], 403);
        if ($request->isMethod('delete')) {
            abort_unless($source === 'logistics', 422, 'This message cannot be deleted.');
            $message->update(['deleted_at' => now()]);
        } else {
            $data = $request->validate(['body' => ['required', 'string', 'max:2000', new \App\Rules\CleanMessage]]);
            $message->update($data + ($source === 'logistics' ? ['edited_at' => now()] : []));
        }
        if ($source === 'logistics' && (int) $thread->messages()->reorder()->latest('id')->value('id') === (int) $message->id) {
            $thread->update(['last_message_preview' => $message->isDeleted() ? 'Message deleted' : mb_substr($message->body, 0, 80)]);
        }
        return response()->json(['ok' => true]);
    }

    private function messagePayload($message, $actor, string $source, $thread): array
    {
        $mine = (int) $message->sender_id === (int) $actor->id
            && ($source === 'marketplace' || $message->sender_type === MessagingAccess::senderType($actor, $thread));
        $deleted = $source === 'logistics' && $message->isDeleted();
        $sender = $source === 'logistics' && $message->sender_type === 'hub_driver'
            ? HubTransportDriver::find($message->sender_id) : User::find($message->sender_id);
        return ['id' => $message->id, 'mine' => $mine, 'sender_type' => $message->sender_type ?? 'user',
            'sender' => ['first_name' => $sender instanceof User ? $sender->displayName() : $sender?->name],
            'sender_id' => $message->sender_id, 'conversation_id' => $thread->id, 'body' => $deleted ? '' : $message->body,
            'product_card' => $source === 'marketplace' ? $message->product_card : null,
            'is_read' => (bool) $message->is_read, 'deleted' => $deleted,
            'created_at' => $message->created_at?->toISOString(), 'time' => $message->created_at?->format('g:i A'),
            'dayLabel' => $message->created_at?->format('M j, Y'),
            'attachments' => $source === 'logistics' && ! $deleted ? $message->attachments->map(fn ($file) => [
                'id' => $file->id, 'name' => $file->original_filename, 'size' => $file->humanSize(), 'is_image' => $file->isImage(),
                'url' => route('messaging.attachment', $file),
            ])->values() : []];
    }

    public function attachment(Request $request, MessageAttachment $attachment)
    {
        $actor = $this->actor($request);
        $message = $attachment->message;
        abort_unless($message && ! $message->isDeleted() && MessagingAccess::canReadLogistics($actor, $message->conversation), 403);
        abort_unless($attachment->fileExists(), 404);
        abort_unless($attachment->isServable(), 422, 'This attachment is empty or corrupt.');
        return response()->file($attachment->absolutePath(), ['Content-Type' => $attachment->contentMime(),
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'])
            ->setContentDisposition($attachment->isBrowserPreviewable() ? 'inline' : 'attachment',
                preg_replace('/[\x00-\x1F\x7F"]/', '', basename($attachment->original_filename)) ?: 'attachment');
    }
}
