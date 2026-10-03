<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BuyerChatController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role === 'buyer') {
            return redirect('/buyer/chat');
        }
        return view('buyer.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if ($user->role !== 'buyer') {
                Auth::logout();
                return back()->withErrors(['email' => 'This is not a buyer account.']);
            }

            if (! $user->isActive()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Your account is not active.']);
            }

            $request->session()->regenerate();

            return redirect()->intended('/buyer/chat');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function chat(Request $request)
    {
        $me = Auth::id();
        $with = $request->integer('with');

        $conversations = Message::where('sender_id', $me)->orWhere('receiver_id', $me)
            ->get()
            ->groupBy(fn ($m) => $m->sender_id === $me ? $m->receiver_id : $m->sender_id)
            ->map(function ($msgs) use ($me) {
                $last = $msgs->sortByDesc('created_at')->first();
                $otherId = $last->sender_id === $me ? $last->receiver_id : $last->sender_id;
                return (object) [
                    'user' => User::find($otherId),
                    'last' => $last,
                    'unread' => $msgs->where('receiver_id', $me)->where('is_read', false)->count(),
                ];
            })
            ->filter(fn ($c) => $c->user)
            ->sortByDesc(fn ($c) => $c->last->created_at)
            ->values();

        $contacts = User::where('id', '!=', $me)
            ->whereIn('role', ['seller', 'admin'])
            ->where('account_status', 'active')
            ->orderBy('name')
            ->get();

        $thread = collect();
        $otherUser = null;
        if ($with) {
            $otherUser = User::find($with);
            if ($otherUser) {
                $thread = Message::where(function ($q) use ($me, $with) {
                    $q->where('sender_id', $me)->where('receiver_id', $with);
                })->orWhere(function ($q) use ($me, $with) {
                    $q->where('sender_id', $with)->where('receiver_id', $me);
                })->orderBy('created_at')->get();

                Message::where('sender_id', $with)->where('receiver_id', $me)
                    ->where('is_read', false)->update(['is_read' => true]);
            }
        }

        return view('buyer.chat', compact('conversations', 'contacts', 'thread', 'otherUser'));
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $data['receiver_id'],
            'body' => $data['body'],
            'is_read' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect('/buyer/chat?with=' . $data['receiver_id']);
    }

    public function messages(Request $request, User $user)
    {
        $me = Auth::id();
        $after = (int) $request->query('after', 0);

        $query = Message::where(function ($q) use ($me, $user) {
            $q->where('sender_id', $me)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($me, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $me);
        });

        if ($after > 0) {
            $query->where('id', '>', $after);
        }

        $messages = $query->orderBy('created_at')->get();

        Message::where('sender_id', $user->id)->where('receiver_id', $me)
            ->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['messages' => $messages]);
    }
}
