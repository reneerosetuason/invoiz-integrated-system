@extends('admin.layout')

@section('content')
<style>
  .cat-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
  .c-stat { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 16px; text-align:center; transition:transform .15s, box-shadow .15s; }
  .c-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); }
  .c-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-secondary); }
  .c-stat strong { display:block; font-size:22px; font-weight:800; margin-top:4px; }
  .cat-grid { display:grid; grid-template-columns:1.55fr .85fr; gap:16px; align-items:start; }
  .cat-table-wrap { background:#fff; border:1px solid var(--border); border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(16,24,40,.04); }
  .cat-table-header { padding:16px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .cat-table-header h2 { font-size:15px; font-weight:800; letter-spacing:-.3px; margin:0; display:flex; align-items:center; gap:8px; }
  .cat-table-header h2 svg { width:18px; height:18px; stroke:#16697A; fill:none; stroke-width:1.8; }
  .cat-icon { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#16697A 0%,#1A9CB0 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; flex-shrink:0; }
  .cat-name { font-weight:700; font-size:13.5px; line-height:1.2; }
  .cat-slug { font-size:11px; color:#9CA3AF; font-family:monospace; }
  .prod-count-badge { display:inline-flex; align-items:center; gap:4px; background:#F0FAFA; border:1px solid #E6F1F2; color:#16697A; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
  .add-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:20px; box-shadow:0 1px 3px rgba(16,24,40,.04); position:sticky; top:20px; }
  .add-card h2 { font-size:15px; font-weight:800; letter-spacing:-.3px; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
  .add-card h2 svg { width:18px; height:18px; stroke:#16697A; fill:none; stroke-width:1.8; }
  .field { margin-bottom:14px; }
  .field label { display:block; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#374151; margin-bottom:6px; }
  .field input, .field textarea, .field select { width:100%; padding:11px 14px; border:1px solid #D1D5DB; border-radius:10px; font-size:13.5px; font-family:inherit; background:#fff; outline:none; transition:border-color .15s, box-shadow .15s; box-sizing:border-box; }
  .field input:focus, .field textarea:focus, .field select:focus { border-color:#16697A; box-shadow:0 0 0 3px rgba(22,105,122,.12); }
  .archived-card { background:#FFFBF0; border:1px solid #FDE68A; border-radius:16px; overflow:hidden; margin-top:18px; }
  .archived-header { padding:14px 18px; background:#FFFBEB; border-bottom:1px solid #FDE68A; display:flex; align-items:center; gap:10px; }
  .archived-header strong { color:#92400e; font-size:13.5px; }
  @media (max-width: 900px) { .cat-stats { grid-template-columns:repeat(2,1fr); } .cat-grid { grid-template-columns:1fr; } .add-card { position:static; } }
</style>

<div class="card" style="background:transparent; border:none; padding:0; box-shadow:none;">
  <div style="margin-bottom:4px;">
    <h1 class="page-title" style="margin-bottom:4px;">Manage Categories</h1>
    <p style="color:var(--text-secondary); font-size:13.5px; margin:0;">Organize your catalog — create, archive, and restore categories. Group products *sama sama* for a clean storefront.</p>
  </div>

  @if(session('status'))
  <div style="padding:12px 16px;background:#E8F5EE;color:#166534;border-radius:12px;margin:16px 0 0;border:1px solid #BFE3D0;">{{ session('status') }}</div>
  @endif

  <div class="cat-stats" style="margin-top:18px;">
    <div class="c-stat">
      <span>Total Categories</span><strong>{{ $stats['all'] }}</strong><small style="color:var(--text-secondary); font-size:11px;">including archived</small>
    </div>
    <div class="c-stat" style="border-left:3px solid #2E8B57;">
      <span>Active</span><strong style="color:#166534;">{{ $stats['active'] }}</strong><small style="color:var(--text-secondary); font-size:11px;">visible</small>
    </div>
    <div class="c-stat" style="border-left:3px solid #F59E0B;">
      <span>Archived</span><strong style="color:#92400e;">{{ $stats['archived'] }}</strong><small style="color:var(--text-secondary); font-size:11px;">hidden</small>
    </div>
    <div class="c-stat" style="border-left:3px solid #16697A;">
      <span>With Products</span><strong>{{ $stats['with_products'] }}</strong><small style="color:var(--text-secondary); font-size:11px;">in use</small>
    </div>
  </div>

  <form method="GET" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="position:relative; flex:1; min-width:220px;">
      <svg style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="search" value="{{ request('search') }}" placeholder="Search categories by name or slug..." style="padding:9px 14px 9px 36px;border:1px solid #d1d5db;border-radius:10px;width:100%;background:#fff;" />
    </div>
    <button type="submit" style="padding:9px 18px;background:#16697A;color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;">Search</button>
    @if(request('search'))<a href="{{ url('/admin/categories') }}" style="align-self:center;color:#6E6E73;font-size:13px;font-weight:600;">Clear</a>@endif
  </form>

  <div class="cat-grid">
    <!-- Categories List -->
    <div class="cat-table-wrap">
      <div class="cat-table-header">
        <h2>
          <svg viewBox="0 0 24 24"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
          Categories List
        </h2>
        <span style="font-size:12px; font-weight:700; background:#F0FAFA; color:#16697A; padding:4px 10px; border-radius:999px; border:1px solid #E6F1F2;">{{ $categories->count() }} shown</span>
      </div>
      <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;min-width:520px;">
        <thead>
          <tr style="border-bottom:2px solid #E8E6E0;text-align:left; background:#FAFAF8;">
            <th style="padding:11px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Category</th>
            <th style="padding:11px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Products</th>
            <th style="padding:11px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Status</th>
            <th style="padding:11px 14px; font-size:11px; font-weight:700; color:#6B7280; text-transform:uppercase; letter-spacing:.6px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categories as $category)
          <tr style="border-bottom:1px solid #F1F2F4; transition:background .12s;" onmouseover="this.style.background='#FAFAF8'" onmouseout="this.style.background=''">
            <td style="padding:12px 14px;">
              <div style="display:flex; align-items:center; gap:10px;">
                <div class="cat-icon">{{ strtoupper(mb_substr($category->name,0,1)) }}</div>
                <div>
                  <div class="cat-name">{{ $category->name }}</div>
                  <div class="cat-slug">/{{ $category->slug }}@if($category->parent) · parent: {{ $category->parent->name }}@endif</div>
                </div>
              </div>
            </td>
            <td style="padding:12px 14px;">
              <span class="prod-count-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#16697A" stroke-width="2"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
                {{ $category->products_count }} {{ $category->products_count===1?'item':'items' }}
              </span>
            </td>
            <td style="padding:12px 14px;">
              <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; background:{{ $category->active ? '#E8F5EE' : '#F3F4F6' }}; color:{{ $category->active ? '#166534' : '#6B7280' }}; border:1px solid {{ $category->active ? '#BFE3D0' : '#E5E7EB' }};">
                <span style="width:7px; height:7px; border-radius:50%; background:{{ $category->active ? '#2E8B57' : '#9CA3AF' }}; display:inline-block;"></span>
                {{ $category->active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td style="padding:12px 14px;">
              <form method="POST" action="{{ url('/admin/categories/'.$category->id.'/archive') }}" style="display:inline;" data-confirm="This category will be archived and hidden from the store. You can restore it anytime. Continue?" data-confirm-title="Archive Category?" data-confirm-ok="Archive" data-confirm-class="btn-warn">
                @csrf
                <button type="submit" style="padding:5px 12px;background:#F0A202;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:11.5px;font-weight:700;">Archive</button>
              </form>
            </td>
          </tr>
          @empty
          <tr><td colspan="4" style="padding:36px;text-align:center;">
            <div style="width:48px;height:48px;border-radius:50%;background:#F3F4F6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="1.6"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
            </div>
            <div style="font-weight:600; color:#6B7280;">No categories found</div>
            <div style="font-size:13px; color:#9CA3AF; margin-top:4px;">Try a different search.</div>
          </td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <!-- Add New Category -->
    <div class="add-card">
      <h2>
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add New Category
      </h2>
      <form method="POST" action="{{ url('/admin/categories') }}">
        @csrf
        <div class="field">
          <label>Name</label>
          <input type="text" name="name" required placeholder="e.g. Handbags" />
        </div>
        <div class="field">
          <label>Description <span style="font-weight:500; text-transform:none; letter-spacing:0; color:#9CA3AF;">(optional)</span></label>
          <textarea name="description" rows="3" placeholder="Short description for this category..."></textarea>
        </div>
        <div class="field">
          <label>Parent Category <span style="font-weight:500; text-transform:none; letter-spacing:0; color:#9CA3AF;">(leave empty for Top Level)</span></label>
          <div style="position:relative;">
            <div style="position:relative;">
              <svg style="position:absolute; left:11px; top:50%; transform:translateY(-50%); width:14px; height:14px; stroke:#9CA3AF; fill:none; stroke-width:1.8;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
              <input type="text" id="parentSearch" placeholder="Search parent category..." autocomplete="off" style="padding-left:34px;" />
            </div>
            <input type="hidden" name="parent_id" id="parentId" value="">
            <div id="parentDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:20; background:#fff; border:1px solid #D1D5DB; border-radius:10px; margin-top:6px; max-height:200px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.08);"></div>
            <div id="parentSelected" style="display:none; margin-top:8px; font-size:12px; background:#F0FAFA; border:1px solid #E6F1F2; padding:6px 10px; border-radius:999px; align-items:center; justify-content:space-between;">
              <span style="display:flex; align-items:center; gap:6px;"><span style="width:8px; height:8px; border-radius:50%; background:#16697A; display:inline-block;"></span> <span id="parentSelectedName" style="font-weight:600; color:#16697A;"></span></span>
              <button type="button" onclick="clearParent()" style="background:none; border:none; color:#6B7280; cursor:pointer; font-weight:700; font-size:12px;">Clear</button>
            </div>
          </div>
        </div>
        <button type="submit" style="width:100%; padding:11px; background:#16697A; color:#fff; border:none; border-radius:10px; cursor:pointer; font-weight:700; font-size:14px; box-shadow:0 2px 8px rgba(22,105,122,.15);">Add Category</button>
        <p style="font-size:11.5px; color:#9CA3AF; margin:10px 0 0; text-align:center; line-height:1.4;">New categories appear instantly in product forms and storefront filters.</p>
      </form>
    </div>
  </div>

  @if($archived->count())
  <div class="archived-card">
    <div class="archived-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="1.8"><path d="M3 6h18M8 6V4h8v2M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
      <strong>Archived Categories ({{ $archived->count() }})</strong>
      <span style="font-size:12px; color:#92400e; background:#fff; padding:3px 8px; border-radius:999px; border:1px solid #FDE68A; font-weight:600;">Hidden — can be restored anytime</span>
    </div>
    <p style="color:#92400e; font-size:12.5px; margin:10px 18px 0;">Archived categories are hidden from the store but never permanently deleted. Restore them anytime to bring back their products.</p>
    <div style="overflow-x:auto; margin-top:12px;">
    <table style="width:100%;border-collapse:collapse;min-width:500px;">
      <thead>
        <tr style="border-bottom:2px solid #FDE68A;text-align:left; background:#FFFBEB;">
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.6px;">Category</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.6px;">Slug</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.6px;">Products</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.6px;">Archived</th>
          <th style="padding:10px 14px; font-size:11px; font-weight:700; color:#92400e; text-transform:uppercase; letter-spacing:.6px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($archived as $category)
        <tr style="border-bottom:1px solid #FDE68A;">
          <td style="padding:10px 14px; font-weight:600; color:#78350F; display:flex; align-items:center; gap:8px;">
            <span style="width:28px; height:28px; border-radius:8px; background:#FEF3C7; color:#92400e; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; flex-shrink:0;">{{ strtoupper(mb_substr($category->name,0,1)) }}</span>
            {{ $category->name }}
          </td>
          <td style="padding:10px 14px;color:#92400e;font-family:monospace;font-size:12.5px;">/{{ $category->slug }}</td>
          <td style="padding:10px 14px;">
            <span style="background:#fff; border:1px solid #FDE68A; color:#92400e; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700;">{{ $category->products_count }} items</span>
          </td>
          <td style="padding:10px 14px;color:#92400e;font-size:12.5px;">{{ optional($category->deleted_at)->format('M d, Y') }}</td>
          <td style="padding:10px 14px;">
            <form method="POST" action="{{ url('/admin/categories/'.$category->id.'/restore') }}" style="display:inline;">
              @csrf
              <button type="submit" style="padding:5px 12px;background:#2E8B57;color:white;border:none;border-radius:8px;cursor:pointer;font-size:11.5px;font-weight:700;">Restore</button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>
    </div>
  @endif
</div>

<script>
  (function(){
    const cats = @json($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values());
    const input = document.getElementById('parentSearch');
    const hidden = document.getElementById('parentId');
    const dropdown = document.getElementById('parentDropdown');
    const selected = document.getElementById('parentSelected');
    const selectedName = document.getElementById('parentSelectedName');

    function render(list){
      dropdown.innerHTML = '';
      if(list.length === 0){
        dropdown.innerHTML = '<div style="padding:12px; color:#9CA3AF; font-size:13px; text-align:center;">No categories found</div>';
      } else {
        list.forEach(c=>{
          const div = document.createElement('div');
          div.textContent = c.name;
          div.style.cssText = 'padding:9px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #F3F4F6;';
          div.onmouseenter = ()=> div.style.background='#F0FAFA';
          div.onmouseleave = ()=> div.style.background='';
          div.onclick = ()=> selectCat(c);
          dropdown.appendChild(div);
        });
      }
      dropdown.style.display = 'block';
    }

    function selectCat(c){
      hidden.value = c.id;
      selectedName.textContent = c.name;
      selected.style.display = 'flex';
      input.value = '';
      dropdown.style.display = 'none';
    }

    window.clearParent = function(){
      hidden.value = '';
      selected.style.display = 'none';
      input.value = '';
      input.focus();
    };

    input.addEventListener('focus', ()=> render(cats));
    input.addEventListener('input', ()=>{
      const q = input.value.toLowerCase().trim();
      const filtered = q ? cats.filter(c=> c.name.toLowerCase().includes(q)) : cats;
      render(filtered);
    });
    document.addEventListener('click', (e)=>{
      if(!e.target.closest('#parentSearch') && !e.target.closest('#parentDropdown') && !e.target.closest('#parentSelected')){
        dropdown.style.display = 'none';
      }
    });
  })();
</script>
@endsection
