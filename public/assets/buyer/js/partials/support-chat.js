var invoizData_b2cc3cfcfd = JSON.parse(document.currentScript.dataset.config);

(function () {
  const FAQS = invoizData_b2cc3cfcfd[0];
  const QUICK = invoizData_b2cc3cfcfd[1];
  const GREETING = invoizData_b2cc3cfcfd[2];
  const FALLBACK = invoizData_b2cc3cfcfd[3];
  const HELP_URL = invoizData_b2cc3cfcfd[4];
  const ORDERS_URL = invoizData_b2cc3cfcfd[5];
  const MSG_URL = invoizData_b2cc3cfcfd[6];
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
