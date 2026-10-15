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
            style="padding:0 14px;background:var(--green,#16697A);color:#fff;border:none;border-radius:10px;font-weight:800;cursor:pointer">➤</button>
  </div>
</div>
<script>
(function () {
  const FAQS = @json($botFaqs);
  const QUICK = @json(config('support.quick_replies', []));
  const GREETING = @json(config('support.bot_greeting', 'Hi! How can I help?'));
  const FALLBACK = @json(config('support.bot_fallback', 'Let me connect you with support.'));
  const HELP_URL = "{{ url('/help') }}";
  const ORDERS_URL = "{{ url('/orders') }}";
  const MSG_URL = "{{ url('/messages') }}";
  let opened = false;

  window.toggleSupportChat = function () {
    const p = document.getElementById('supportChatPanel');
    const open = p.style.display === 'none';
    p.style.display = open ? 'flex' : 'none';
    document.getElementById('supportChatDot').style.display = 'none';
    if (open && !opened) {
      opened = true;
      botSay(GREETING);
      renderChips(QUICK);
    }
    if (open) setTimeout(() => document.getElementById('supportChatInput').focus(), 50);
  };

  function bubble(html, me) {
    const body = document.getElementById('supportChatBody');
    const d = document.createElement('div');
    d.style.cssText = me
      ? 'align-self:flex-end;background:var(--green,#16697A);color:#fff;padding:9px 12px;border-radius:12px 12px 4px 12px;font-size:13px;max-width:85%;line-height:1.5'
      : 'align-self:flex-start;background:#fff;color:#222;padding:9px 12px;border-radius:12px 12px 12px 4px;font-size:13px;max-width:88%;line-height:1.55;border:1px solid #eee';
    d.innerHTML = html;
    body.appendChild(d);
    body.scrollTop = body.scrollHeight;
  }

  function renderChips(items) {
    const box = document.getElementById('supportChatChips');
    box.innerHTML = '';
    (items || []).forEach(function (t) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = t;
      b.style.cssText = 'font-size:11px;font-weight:700;padding:6px 10px;border-radius:999px;border:1px solid var(--green,#16697A);color:var(--green,#16697A);background:#fff;cursor:pointer';
      b.onclick = function () { userSay(t); };
      box.appendChild(b);
    });
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function botSay(html) { bubble(html, false); }
  function userSay(text) {
    bubble(esc(text), true);
    setTimeout(() => botSay(answerFor(text)), 350);
  }
  window.sendSupportChat = function () {
    const inp = document.getElementById('supportChatInput');
    const t = (inp.value || '').trim();
    if (!t) return;
    inp.value = '';
    userSay(t);
  };

  function norm(s) {
    return ' ' + String(s || '').toLowerCase().replace(/[^a-z0-9ñ\s]/g, ' ') + ' ';
  }

  function answerFor(raw) {
    const text = norm(raw);
    // Greetings.
    if (/(^|\s)(hi|hello|hey|kumusta|kamusta|good\s?(morning|afternoon|evening))(\s|$)/.test(text)) {
      return esc(GREETING);
    }
    // Human / contact handoff.
    if (/(human|agent|person|tawag|tumawag|contact|support|help|tulong|complaint|reklamo)/.test(text)) {
      return 'You can reach our team via the <a href="' + HELP_URL + '">Help Center</a> (email, hotline, hours), file a complaint there with your order number, or <a href="' + MSG_URL + '">message a seller</a> directly.';
    }
    // Order tracking shortcut.
    if (/(track|status|nasaan|saan|dumating|parcel)/.test(text)) {
      return 'Track everything under <a href="' + ORDERS_URL + '">My Orders</a> — pending → confirmed → processing → ready → out for delivery → delivered. Want the full list? <a href="' + HELP_URL + '">Open the FAQ</a>.';
    }
    // Score FAQs by keyword hits (longer/rarer keywords weigh more).
    let best = null, bestScore = 0;
    FAQS.forEach(function (f) {
      let score = 0;
      (f.keywords || []).forEach(function (k) {
        const kw = norm(k).trim();
        if (kw && text.indexOf(kw) !== -1) score += kw.length;
      });
      // Small bonus when the question itself shares words.
      norm(f.q).trim().split(/\s+/).forEach(function (w) {
        if (w.length > 4 && text.indexOf(' ' + w + ' ') !== -1) score += 2;
      });
      if (score > bestScore) { bestScore = score; best = f; }
    });
    if (best && bestScore > 0) {
      return '<b>' + esc(best.q) + '</b><br>' + esc(best.a) + '<br><span style="font-size:11px;color:#888">More in the <a href="' + HELP_URL + '">Help Center</a></span>';
    }
    return esc(FALLBACK);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const inp = document.getElementById('supportChatInput');
    if (inp) inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendSupportChat(); });
  });
})();
</script>
