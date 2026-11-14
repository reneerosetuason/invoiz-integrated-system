@extends('buyer.layouts.website')

@section('content')
@php
  $support = $support ?? config('support');
  $faqs = $support['faqs'] ?? [];
@endphp
<div class="helpx buyerfeature-page">
  <div class="bfp-wrap help-wrap">
    <header class="help-head">
      <h1>Help Center</h1>
      <p>Answers, ways to reach us, and a form to report order problems.</p>
    </header>

    @if(session('success'))<div class="help-feedback help-feedback-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="help-feedback help-feedback-error" role="alert">{{ session('error') }}</div>@endif
    @if(!empty($errors) && $errors->any())
      <div class="help-feedback help-feedback-error" role="alert">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
      </div>
    @endif

    <nav class="help-jumps" aria-label="Help topics">
      <a href="#faq">Frequently asked questions</a>
      <a href="#report">Report an order problem</a>
      @if($buyer)<a href="#reports">Your reports</a>@endif
    </nav>

    <section class="help-contact" aria-label="Contact support">
      <div class="help-contact-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
        <div class="help-contact-copy">
          <div class="help-contact-label">Email us</div>
          <a href="mailto:{{ $support['email'] }}" class="help-inline-link">{{ $support['email'] }}</a>
          <p>Replies {{ $support['response_time'] }}</p>
        </div>
      </div>
      <div class="help-contact-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M7 3h3l2 5-2.5 1.5a14 14 0 0 0 5 5L16 12l5 2v3c0 1.1-.9 2-2 2C10.7 19 5 13.3 5 5c0-1.1.9-2 2-2Z"/></svg>
        <div class="help-contact-copy">
          <div class="help-contact-label">Hotline</div>
          @if(!empty($support['phone']))
            <a href="tel:{{ preg_replace('/[^+\d]/', '', $support['phone']) }}" class="help-contact-value">{{ $support['phone'] }}</a>
          @else
            <div class="help-contact-value">Coming soon</div>
          @endif
          <p>{{ $support['hours'] }}</p>
        </div>
      </div>
      <div class="help-contact-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div class="help-contact-copy">
          <div class="help-contact-label">Prefer chat?</div>
          <div class="help-contact-value">Assistant is online now</div>
          <p>Tap the chat bubble at the bottom-right of any page.</p>
        </div>
      </div>
    </section>

    <section id="faq" class="help-section help-faq" aria-labelledby="faqTitle">
      <header class="help-section-head">
        <h2 id="faqTitle">Frequently asked questions</h2>
        <label class="help-search-field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
          <input id="faqSearch" type="search" placeholder="Search answers… e.g. refund, shipping, voucher" aria-label="Search answers" oninput="filterFaqs(this.value)" autocomplete="off">
        </label>
      </header>
      <div id="faqEmpty" class="help-no-results" style="display:none">No answers match — try different words, or ask the chatbot below.</div>
      <div class="faq-groups">
        @foreach($faqs as $gi => $group)
          <div class="faq-group" data-group="{{ strtolower($group['category']) }}">
            <h3 class="faq-category">{{ $group['category'] }}</h3>
            <div class="faq-questions">
              @foreach($group['items'] as $ii => $item)
                <details class="faq-item" data-text="{{ strtolower($item['q'].' '.$item['a']) }}">
                  <summary class="faq-question">{{ $item['q'] }}</summary>
                  <div class="faq-answer">{!! nl2br(e($item['a'])) !!}</div>
                </details>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <section id="report" class="help-section help-report" aria-labelledby="reportTitle">
      <header class="help-section-intro">
        <h2 id="reportTitle">Report an order problem</h2>
        <p>Damaged, wrong, or missing item? Send details and photos description — it goes straight to our support queue.</p>
      </header>
      <div class="help-form-area">
        @if($buyer)
          <form method="POST" action="{{ url('/help/complaint') }}" class="help-form" id="helpForm">
            @csrf
            <input type="hidden" id="helpSubject" name="subject" value="">
            <input type="hidden" id="helpSellerId" name="seller_id" value="">

            <div class="help-form-row">
              <div class="help-field">
                <label for="helpOrder">Subject <span style="font-weight:normal;color:#6B7280;font-size:12px;">(Select order to report)</span></label>
                <span class="help-select-wrap">
                  <select id="helpOrder" name="order_id" required>
                    @if($orders->isEmpty())
                      <option value="" disabled selected>No orders found on your account</option>
                    @else
                      <option value="">Select an order to report...</option>
                      @foreach($orders as $o)
                        <option value="{{ $o->id }}" data-store="{{ $o->store_name_display }}" data-ordernum="{{ $o->order_number ?? $o->id }}">
                          Order #{{ $o->order_number ?? $o->id }} — Store: {{ $o->store_name_display }} ({{ ucfirst($o->status) }})
                        </option>
                      @endforeach
                    @endif
                  </select>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </span>
                @if($orders->isEmpty())
                  <small style="color:#6E6E73;font-size:12px;margin-top:4px;display:block;">You have no orders yet to report.</small>
                @endif
              </div>
              <div class="help-field" id="helpItemField">
                <label for="helpItem">Item with problem <span style="font-weight:normal;color:#6B7280;font-size:12px;">(optional)</span></label>
                <span class="help-select-wrap">
                  <select id="helpItem" name="order_item_id" disabled>
                    <option value="">Select an order first</option>
                  </select>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </span>
              </div>
            </div>
            <div class="help-field help-description-field" style="margin-top:14px;">
              <label for="helpDescription">What happened?</label>
              <textarea id="helpDescription" name="description" rows="4" maxlength="2000" required placeholder="Describe the problem, including item names, damage, or what was received…"></textarea>
            </div>
            <button type="submit" class="btn-p btn-solid help-submit" {{ $orders->isEmpty() ? 'disabled' : '' }}>Send complaint</button>
          </form>
        @else
          <a href="{{ url('/login') }}" class="btn-p btn-line help-login">Login to file a complaint</a>
        @endif
      </div>
    </section>

    @if($buyer)
      <section id="reports" class="help-section help-reports" aria-labelledby="reportsTitle">
        <header class="help-section-intro">
          <h2 id="reportsTitle">Your reports</h2>
          <p>Track what our team did with each report. Replies from support appear here and in your notification bell.</p>
        </header>
        <div class="help-report-list">
          @forelse($complaints as $c)
            @php
              $reportStatus = strtolower((string) $c->status);
              $reportResolved = in_array($reportStatus, ['resolved', 'closed'], true);
              $reportStatusType = $reportStatus === 'open' ? 'neutral' : ($reportResolved ? 'resolved' : 'unknown');
            @endphp
            <article class="help-report-row">
              <div class="help-report-top">
                <span class="help-report-subject">{{ $c->subject }}</span>
                <span class="help-status help-status-{{ $reportStatusType }}">
                  @if($reportResolved)<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>@endif
                  {{ str_replace('_', ' ', $c->status) }}
                </span>
                <span class="help-report-meta">
                  {{ $c->created_at->format('M j, Y') }}
                  &bull; Store: <strong>{{ $c->store_name }}</strong>
                  @if($c->order_id) &bull; Order #{{ $c->order->order_number ?? $c->order_id }}@endif
                </span>
              </div>
              @if($c->reported_product_name)
                <div style="font-size:12.5px;color:#0E4A57;margin-top:4px;">
                  <strong>Reported Item:</strong> {{ $c->reported_product_name }}
                </div>
              @endif
              @if($c->resolution)
                <div class="help-response">
                  <div class="help-response-label">Response from support{{ $c->resolved_at ? ' · '.$c->resolved_at->format('M j, Y g:i A') : '' }}</div>
                  <div class="help-response-text">{{ $c->resolution }}</div>
                </div>
              @elseif($c->status !== 'open')
                <p class="help-report-state">Our team is looking into this ({{ str_replace('_', ' ', $c->status) }}).</p>
              @else
                <p class="help-report-state">Waiting for our team to review.</p>
              @endif
            </article>
          @empty
            <p class="help-report-empty">No reports yet — anything you file above will show up here.</p>
          @endforelse
        </div>
      </section>
    @endif
  </div>
</div>

@php
  $ordersPayload = ($orders ?? collect())->mapWithKeys(function($o) {
      return [
          (string)$o->id => [
              'order_number' => $o->order_number ?? (string)$o->id,
              'seller_id' => $o->seller_id,
              'store_name' => $o->store_name_display,
              'items' => $o->items->map(function($it) {
                  return [
                      'id' => $it->id,
                      'product_name' => $it->product_name,
                      'seller_id' => $it->seller_id,
                      'store_name' => $it->store_name,
                      'price' => $it->unit_price ?? $it->price,
                      'quantity' => $it->quantity,
                  ];
              })->values()->all(),
          ]
      ];
  })->all();
@endphp

<script>
(function() {
  var ordersData = @json($ordersPayload);

  var orderSelect = document.getElementById('helpOrder');
  var itemSelect = document.getElementById('helpItem');
  var subjectInput = document.getElementById('helpSubject');
  var sellerInput = document.getElementById('helpSellerId');

  function updateSubject() {
    if (!orderSelect || !subjectInput) return;
    var orderId = String(orderSelect.value || '');
    if (!orderId || !ordersData[orderId]) {
      subjectInput.value = '';
      return;
    }
    var data = ordersData[orderId];
    var storeName = data.store_name || 'Store';
    var orderNum = data.order_number || orderId;

    var itemId = itemSelect ? itemSelect.value : '';
    if (itemId && data.items) {
      var itemObj = data.items.find(function(i) { return String(i.id) === String(itemId); });
      if (itemObj) {
        storeName = itemObj.store_name || storeName;
        subjectInput.value = 'Order #' + orderNum + ' (' + itemObj.product_name + ') — Store: ' + storeName;
        return;
      }
    }

    subjectInput.value = 'Order #' + orderNum + ' — Store: ' + storeName;
  }

  if (orderSelect && itemSelect) {
    orderSelect.addEventListener('change', function() {
      var orderId = String(this.value || '');
      itemSelect.innerHTML = '<option value="">All items / Entire order</option>';
      if (sellerInput) sellerInput.value = '';

      if (!orderId || !ordersData[orderId]) {
        itemSelect.innerHTML = '<option value="">Select an order first</option>';
        itemSelect.disabled = true;
        updateSubject();
        return;
      }

      itemSelect.disabled = false;
      var data = ordersData[orderId];
      if (sellerInput && data.seller_id) {
        sellerInput.value = data.seller_id;
      }

      var items = data.items || [];
      if (items.length > 0) {
        items.forEach(function(it) {
          var opt = document.createElement('option');
          opt.value = it.id;
          opt.dataset.sellerId = it.seller_id || '';
          opt.dataset.storeName = it.store_name || '';
          opt.textContent = it.product_name + ' — Store: ' + it.store_name + (it.quantity ? ' (Qty: ' + it.quantity + ')' : '');
          itemSelect.appendChild(opt);
        });

        if (items.length === 1) {
          itemSelect.value = items[0].id;
          if (sellerInput && items[0].seller_id) {
            sellerInput.value = items[0].seller_id;
          }
        }
      }

      updateSubject();
    });

    itemSelect.addEventListener('change', function() {
      var selectedOpt = this.options[this.selectedIndex];
      if (selectedOpt && selectedOpt.dataset.sellerId && sellerInput) {
        sellerInput.value = selectedOpt.dataset.sellerId;
      } else if (orderSelect.value && ordersData[orderSelect.value] && sellerInput) {
        sellerInput.value = ordersData[orderSelect.value].seller_id || '';
      }
      updateSubject();
    });
  }
})();
</script>
<script src="{{ asset('assets/buyer/js/help.js') }}"></script>
@endsection
