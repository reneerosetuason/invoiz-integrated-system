@extends('layouts.website')

@section('content')
@php
  $support = $support ?? config('support');
  $faqs = $support['faqs'] ?? [];
@endphp
<div style="max-width:860px;margin:0 auto">
  <h2 style="font-weight:800;margin:6px 0 2px">Help Center</h2>
  <p style="font-size:13px;color:var(--text2);margin:0 0 14px">Answers, ways to reach us, and a form to report order problems.</p>

  @if(session('success'))<div style="background:#f0fdf4;color:#166534;padding:10px;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
  @if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-bottom:10px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif
  @if($errors->any())
    <div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-bottom:10px;font-size:13px;font-weight:600">
      @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  {{-- Contact cards --}}
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:16px">
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--text3)">Email us</div>
      <a href="mailto:{{ $support['email'] }}" style="font-weight:700;font-size:13px;color:var(--green);word-break:break-all">{{ $support['email'] }}</a>
      <div style="font-size:11px;color:var(--text2);margin-top:4px">Replies {{ $support['response_time'] }}</div>
    </div>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--text3)">Hotline</div>
      @if(!empty($support['phone']))
        <a href="tel:{{ preg_replace('/[^+\d]/', '', $support['phone']) }}" style="font-weight:700;font-size:13px;color:var(--green)">{{ $support['phone'] }}</a>
      @else
        <div style="font-weight:700;font-size:13px">Coming soon</div>
      @endif
      <div style="font-size:11px;color:var(--text2);margin-top:4px">{{ $support['hours'] }}</div>
    </div>
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--text3)">Prefer chat?</div>
      <div style="font-weight:700;font-size:13px">Assistant is online now</div>
      <div style="font-size:11px;color:var(--text2);margin-top:4px">Tap the chat bubble at the bottom-right of any page.</div>
    </div>
  </div>

  {{-- FAQ search + accordion --}}
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px">
    <h3 style="margin:0 0 8px;font-size:14px">Frequently asked questions</h3>
    <input id="faqSearch" type="text" placeholder="Search answers… e.g. refund, shipping, voucher" oninput="filterFaqs(this.value)"
           style="width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px;margin-bottom:10px">
    <div id="faqEmpty" style="display:none;font-size:13px;color:var(--text2);padding:8px 0">No answers match — try different words, or ask the chatbot below.</div>
    @foreach($faqs as $gi => $group)
      <div class="faq-group" data-group="{{ strtolower($group['category']) }}">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--green);margin:14px 0 6px">{{ $group['category'] }}</div>
        @foreach($group['items'] as $ii => $item)
          <details class="faq-item" data-text="{{ strtolower($item['q'].' '.$item['a']) }}" style="border:1px solid var(--border-light);border-radius:10px;margin-bottom:6px;background:#fff">
            <summary style="cursor:pointer;padding:10px 12px;font-size:13px;font-weight:700;list-style:none">{{ $item['q'] }}</summary>
            <div style="padding:0 12px 12px;font-size:13px;color:var(--text2);line-height:1.6">{!! nl2br(e($item['a'])) !!}</div>
          </details>
        @endforeach
      </div>
    @endforeach
  </div>

  {{-- Complaint / contact form --}}
  <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px">
    <h3 style="margin:0 0 4px;font-size:14px">Report an order problem</h3>
    <p style="font-size:12px;color:var(--text2);margin:0 0 10px">Damaged, wrong, or missing item? Send details and photos description — it goes straight to our support queue.</p>
    @if($buyer)
      <form method="POST" action="{{ url('/help/complaint') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label style="font-size:12px;font-weight:700">Subject</label>
            <input type="text" name="subject" maxlength="150" required placeholder="e.g. Damaged item in order #123"
                   style="width:100%;margin-top:4px;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px">
          </div>
          <div>
            <label style="font-size:12px;font-weight:700">Related order <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
            <select name="order_id" style="width:100%;margin-top:4px;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px">
              <option value="">No specific order</option>
              @foreach($orders as $o)
                <option value="{{ $o->id }}">#{{ $o->id }} — {{ $o->status }} ({{ $o->created_at->format('M j, Y') }})</option>
              @endforeach
            </select>
          </div>
        </div>
        <div style="margin-top:10px">
          <label style="font-size:12px;font-weight:700">What happened?</label>
          <textarea name="description" rows="4" maxlength="2000" required placeholder="Describe the problem, including item names and what you received…"
                    style="width:100%;margin-top:4px;padding:10px;border:1px solid var(--border);border-radius:10px;font-size:13px"></textarea>
        </div>
        <button type="submit" style="margin-top:10px;padding:11px 18px;background:var(--green);color:#fff;border:none;border-radius:10px;font-weight:800;font-size:13px;cursor:pointer">Send complaint</button>
      </form>
    @else
      <a href="{{ url('/login') }}" style="display:inline-block;padding:11px 18px;background:var(--green);color:#fff;border-radius:10px;font-weight:800;font-size:13px;text-decoration:none">Login to file a complaint</a>
    @endif
  </div>

  {{-- Buyer's own reports + staff resolutions --}}
  @if($buyer)
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-top:16px">
      <h3 style="margin:0 0 4px;font-size:14px">Your reports</h3>
      <p style="font-size:12px;color:var(--text2);margin:0 0 10px">Track what our team did with each report. Replies from support appear here and in your notification bell.</p>
      @forelse($complaints as $c)
        <div style="border:1px solid var(--border-light);border-radius:10px;padding:10px 12px;margin-bottom:8px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:13px">{{ $c->subject }}</span>
            <span style="font-size:10px;font-weight:800;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:{{ $c->status === 'open' ? '#FCE9E4' : ($c->status === 'resolved' || $c->status === 'closed' ? '#E8F5EE' : '#FDF3E3') }};color:{{ $c->status === 'open' ? '#991b1b' : (($c->status === 'resolved' || $c->status === 'closed') ? '#166534' : '#92400e') }}">{{ str_replace('_', ' ', $c->status) }}</span>
            <span style="margin-left:auto;font-size:11px;color:var(--text3)">{{ $c->created_at->format('M j, Y') }}@if($c->order_id) · Order #{{ $c->order_id }}@endif</span>
          </div>
          @if($c->resolution)
            <div style="margin-top:8px;background:#E8F5EE;border:1px solid #BFE3D0;border-radius:8px;padding:8px 10px;font-size:12px;line-height:1.6">
              <div style="font-weight:800;color:#166534;margin-bottom:2px">Response from support{{ $c->resolved_at ? ' · '.$c->resolved_at->format('M j, Y g:i A') : '' }}</div>
              {{ $c->resolution }}
            </div>
          @elseif($c->status !== 'open')
            <div style="margin-top:8px;font-size:12px;color:var(--text2)">Our team is looking into this ({{ str_replace('_', ' ', $c->status) }}).</div>
          @else
            <div style="margin-top:8px;font-size:12px;color:var(--text2)">Waiting for our team to review.</div>
          @endif
        </div>
      @empty
        <p style="font-size:13px;color:var(--text2)">No reports yet — anything you file above will show up here.</p>
      @endforelse
    </div>
  @endif
</div>

<script>
function filterFaqs(q) {
  q = (q || '').toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('.faq-item').forEach(function (el) {
    const hit = !q || el.getAttribute('data-text').indexOf(q) !== -1;
    el.style.display = hit ? '' : 'none';
    if (hit) visible++;
  });
  document.querySelectorAll('.faq-group').forEach(function (g) {
    const anyVisible = Array.from(g.querySelectorAll('.faq-item')).some(function (el) { return el.style.display !== 'none'; });
    g.style.display = anyVisible ? '' : 'none';
  });
  document.getElementById('faqEmpty').style.display = visible ? 'none' : '';
}
</script>
@endsection
