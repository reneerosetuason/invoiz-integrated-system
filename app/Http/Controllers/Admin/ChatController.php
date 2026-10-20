<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logistics\Conversation as LogisticsConversation;
use App\Models\Logistics\Message as LogisticsMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function logisticsConversationWith(int $firstUserId, int $secondUserId): ?LogisticsConversation
    {
        $firstId = min($firstUserId, $secondUserId);
        $secondId = max($firstUserId, $secondUserId);

        return LogisticsConversation::query()
            ->where('participant_type', 'logistics')
            ->where('direct_thread_key', "logistics:{$firstId}:{$secondId}")
            ->first();
    }

    public function index(Request $request)
    {
        $myId = auth()->id();
        $users = User::where('id', '!=', $myId)
            ->where('account_status', 'active')
            ->get()
            ->map(function ($u) use ($myId) {
                $last = Message::where(function ($q) use ($myId, $u) {
                    $q->where('sender_id', $myId)->where('receiver_id', $u->id);
                })->orWhere(function ($q) use ($myId, $u) {
                    $q->where('sender_id', $u->id)->where('receiver_id', $myId);
                })->orderByDesc('created_at')->first();
                $logisticsConversation = $this->logisticsConversationWith((int) $myId, (int) $u->id);
                $logisticsLast = $logisticsConversation
                    ? $logisticsConversation->messages()->latest('created_at')->first()
                    : null;

                if ($logisticsLast && (! $last || $logisticsLast->created_at->gt($last->created_at))) {
                    $last = $logisticsLast;
                }

                $u->last_message = $last;
                $u->last_time = $last ? $last->created_at : $u->created_at;
                $u->has_logistics_conversation = (bool) $logisticsConversation;
                return $u;
            })
            ->sortByDesc(fn ($u) => $u->last_message ? $u->last_message->created_at : $u->created_at)
            ->sortByDesc(fn ($u) => $u->last_message ? 1 : 0)
            ->values();

        // Ensure the selected user is at the very top (most recent)
        if ($request->filled('user_id')) {
            $selectedId = (int) $request->user_id;
            $users = $users->sortByDesc(fn ($u) => $u->id === $selectedId ? 2 : ($u->last_message ? 1 : 0))->values();
        }

        $selectedUser = null;
        $messages = collect();

        if ($request->filled('user_id')) {
            $selectedUser = User::find($request->user_id);
            if ($selectedUser) {
                $logisticsConversation = $this->logisticsConversationWith((int) $myId, (int) $selectedUser->id);

                if ($logisticsConversation) {
                    $messages = $logisticsConversation->messages()->orderBy('created_at')->orderBy('id')->get();
                    $logisticsConversation->messages()
                        ->where('sender_type', 'logistics')
                        ->where('sender_id', '!=', $myId)
                        ->where('is_read', false)
                        ->update(['is_read' => true, 'read_at' => now()]);
                } else {
                    $messages = Message::where(function ($q) use ($myId, $selectedUser) {
                        $q->where('sender_id', $myId)->where('receiver_id', $selectedUser->id);
                    })->orWhere(function ($q) use ($myId, $selectedUser) {
                        $q->where('sender_id', $selectedUser->id)->where('receiver_id', $myId);
                    })->orderBy('created_at')->get();

                    Message::where('sender_id', $selectedUser->id)
                        ->where('receiver_id', $myId)
                        ->where('is_read', false)
                        ->update(['is_read' => true]);
                }
            }
        }

        return view('admin.chat', compact('users', 'selectedUser', 'messages'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body' => 'required|string|max:2000',
        ]);

        $logisticsConversation = $this->logisticsConversationWith((int) auth()->id(), (int) $request->receiver_id);

        if ($logisticsConversation) {
            $message = LogisticsMessage::create([
                'conversation_id' => $logisticsConversation->id,
                'sender_type' => 'logistics',
                'sender_id' => auth()->id(),
                'body' => $request->body,
            ]);

            $logisticsConversation->update([
                'last_message_preview' => str($request->body)->limit(80),
                'last_message_at' => now(),
            ]);
        } else {
            $message = Message::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'body' => $request->body,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.chat', ['user_id' => $request->receiver_id]);
    }

    public function messages(Request $request, User $user)
    {
        $myId = auth()->id();
        $after = (int) $request->query('after', 0);

        $logisticsConversation = $this->logisticsConversationWith((int) $myId, (int) $user->id);

        if ($logisticsConversation) {
            $query = $logisticsConversation->messages();
            if ($after > 0) {
                $query->where('id', '>', $after);
            }
            $messages = $query->orderBy('created_at')->orderBy('id')->get();

            $logisticsConversation->messages()
                ->where('sender_type', 'logistics')
                ->where('sender_id', '!=', $myId)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            return response()->json(['messages' => $messages]);
        }

        $query = Message::where(function ($q) use ($myId, $user) {
            $q->where('sender_id', $myId)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($myId, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $myId);
        });

        if ($after > 0) {
            $query->where('id', '>', $after);
        }

        $messages = $query->orderBy('created_at')->get();

        Message::where('sender_id', $user->id)
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }
}
