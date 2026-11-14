@extends($layout)
@section('title', 'Messages')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/messages.css') }}?v={{ filemtime(public_path('assets/shared/css/messages.css')) }}">
<section class="message-center" data-message-center data-base="{{ url('/message-center') }}">
    <header class="mc-heading"><div><h1>Messages</h1><p>Contact support, your store, or the people handling your delivery.</p></div><button type="button" data-new-message><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 5H5v14h14v-7M16 3l5 5-9 9-5 1 1-5Z"/></svg><span>New message</span></button></header>
    <p role="alert" data-chat-error hidden></p>
    <div class="mc-compose" data-contacts-panel hidden>
        <label for="mc-contact-search">Find a contact</label>
        <input id="mc-contact-search" type="search" placeholder="Search by name" maxlength="100" data-contact-search>
        <p>Parcel contacts follow the current handler. Sellers can also contact logistics separately about partnerships.</p>
        <div data-contact-list></div>
        <button type="button" data-close-contacts>Close contacts</button>
    </div>
    <div class="mc-compose" data-parcel-contacts hidden>
        <h2>Your parcel contacts</h2>
        <p>Private chats with the rider or logistics hub currently handling each parcel.</p>
        <div data-parcel-contact-list></div>
    </div>
    <div class="mc-workspace">
        <aside class="mc-inbox-pane"><header class="mc-inbox-heading"><h2>Conversations</h2><p>All your messages in one place</p></header><nav class="mc-inbox" aria-label="Conversations" data-thread-list><p>Loading conversations…</p></nav></aside>
        <article class="mc-thread">
            <header class="mc-thread-heading"><span class="mc-thread-avatar" aria-hidden="true" data-chat-avatar><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 11a8 8 0 0 1-8 8H7l-4 3V11a8 8 0 1 1 17 0Z"/></svg></span><div><h2 data-chat-title>Select a conversation</h2><p data-chat-context>Your messages stay private to the people involved.</p></div></header>
            <button type="button" data-older hidden>Load earlier messages</button>
            <div class="mc-history" data-chat-history role="log" aria-label="Messages" aria-live="polite"><div class="mc-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path d="M21 11a8 8 0 0 1-8 8H8l-5 3V11a9 9 0 1 1 18 0Z"/><path d="M8 10h8M8 14h5"/></svg><strong>Your conversations, together</strong><p>Choose a conversation to continue, or start a new message.</p></div></div>
            <form data-chat-form hidden>
                @csrf
                <label for="mc-body" class="mc-composer-label">Message</label>
                <textarea id="mc-body" name="body" rows="3" maxlength="2000" required placeholder="Write a message…"></textarea>
                <label data-attachment-label hidden>Attachment (up to 5 MB)<input type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" data-chat-attachment></label>
                <button type="submit"><span>Send message</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3"/></svg></button>
            </form>
            <p data-chat-readonly hidden>This contact is no longer available for messaging. Your history is still here.</p>
        </article>
    </div>
</section>
<script src="{{ asset('assets/shared/js/messages.js') }}?v={{ filemtime(public_path('assets/shared/js/messages.js')) }}" defer></script>
@endsection
