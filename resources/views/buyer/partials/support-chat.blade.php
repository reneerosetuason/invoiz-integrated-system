{{-- Invoiz Assistant: rule-based chatbot. Answers come from config/support.php
      (same content as /help), so editing that file updates bot + FAQ together.
      No external API or key needed. --}}
@php
  $botFaqs = [];
  foreach ((config('support.faqs') ?? []) as $group) {
      foreach (($group['items'] ?? []) as $item) {
          $botFaqs[] = [
              'q' => $item['q'], 'a' => $item['a'],
              'keywords' => $item['keywords'] ?? [],
              'category' => $group['category'] ?? '',
          ];
      }
  }
@endphp
<button id="supportChatBtn" onclick="toggleSupportChat()" aria-label="Chat with support"
        style="position:fixed;right:18px;bottom:18px;z-index:9999;width:52px;height:52px;border-radius:50%;background:#FBF9F6;color:#0E4A57;border:1px solid rgba(15,76,92,.2);cursor:pointer;box-shadow:0 10px 28px rgba(10,47,58,.28);display:flex;align-items:center;justify-content:center">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
  <span id="supportChatDot" style="position:absolute;top:2px;right:2px;width:12px;height:12px;border-radius:50%;background:#F0A202;border:2px solid #FBF9F6"></span>
</button>
<div id="supportChatPanel" style="display:none;position:fixed;right:18px;bottom:78px;z-index:9999;width:min(360px,calc(100vw - 36px));height:480px;max-height:calc(100vh - 110px);background:#FBF9F6;border:1px solid rgba(15,76,92,.14);border-radius:16px;box-shadow:0 18px 50px rgba(10,47,58,.3);overflow:hidden;display:none;flex-direction:column">
  <div style="background:var(--green,#16697A);color:#fff;padding:12px 14px;display:flex;align-items:center;gap:10px">
    <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:800">I</div>
    <div style="flex:1">
      <div style="font-weight:800;font-size:13px">{{ config('support.bot_name', 'Invoiz Assistant') }}</div>
      <div style="font-size:11px;opacity:.85">Online · replies instantly</div>
    </div>
    <button onclick="toggleSupportChat()" aria-label="Close chat" style="background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1">×</button>
  </div>
  <div id="supportChatBody" style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;background:#f7f7f5"></div>
  <div id="supportChatChips" style="padding:8px 12px;display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid var(--border-light,#eee);background:#fff"></div>
  <div style="display:flex;gap:8px;padding:10px 12px;border-top:1px solid var(--border,#e5e5e5);background:#fff">
    <input id="supportChatInput" type="text" placeholder="Type your question…" maxlength="300" autocomplete="off"
           style="flex:1;padding:10px;border:1px solid var(--border,#ddd);border-radius:10px;font-size:13px">
    <button onclick="sendSupportChat()" aria-label="Send"
            style="padding:0 14px;background:var(--green,#16697A);color:#fff;border:none;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </div>
</div>
<script src="{{ asset('assets/buyer/js/partials/support-chat.js') }}" data-config="{{ json_encode([($botFaqs), (config('support.quick_replies', [])), (config('support.bot_greeting', 'Hi! How can I help?')), (config('support.bot_fallback', 'Let me connect you with support.')), (url('/help')), (url('/orders')), (url('/messages'))], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
