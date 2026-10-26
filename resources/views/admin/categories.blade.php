@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<style>
  .admin-categories .admin-list-stats{margin-top:0}
  .admin-categories .admin-list-mobile-link{display:none}
  .admin-categories .category-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;align-items:start;gap:16px}
  .admin-categories .admin-list-panel{min-width:0}
  .admin-categories .cat-table-header{display:flex;min-height:58px;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-bottom:1px solid var(--list-hair)}
  .admin-categories .cat-table-header h2,.admin-categories .add-card h2,.admin-categories .archived-header h2{display:flex;align-items:center;gap:8px;margin:0;color:var(--list-deep);font-family:'Fraunces',Georgia,serif;font-size:18px;font-weight:560;line-height:1.25}
  .admin-categories .cat-table-header h2 svg,.admin-categories .add-card h2 svg,.admin-categories .archived-header h2 svg{width:18px;height:18px;flex:0 0 18px;fill:none;stroke:var(--list-teal);stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
  .admin-categories .shown-count{display:inline-flex;min-height:28px;align-items:center;padding:5px 9px;border:1px solid var(--list-hair);border-radius:999px;background:#fff;color:var(--list-deep);font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap;font-variant-numeric:tabular-nums}
  .admin-categories .cat-table-wrap{width:100%;min-width:0;overflow:visible}
  .admin-categories .category-table col.category-col{width:41%}
  .admin-categories .category-table col.products-col{width:19%}
  .admin-categories .category-table col.status-col{width:19%}
  .admin-categories .category-table col.actions-col{width:21%}
  .admin-categories .category-cell{display:flex;min-width:0;align-items:center;gap:10px}
  .admin-categories .category-avatar{display:flex;width:36px;height:36px;flex:0 0 36px;align-items:center;justify-content:center;border-radius:10px;background:#EAF3F2;color:var(--list-deep);font-size:13px;font-weight:700}
  .admin-categories .category-copy{min-width:0}
  .admin-categories .category-name{color:#1B1B1E;font-size:14.5px;font-weight:600;line-height:1.3;overflow-wrap:anywhere}
  .admin-categories .category-slug{margin-top:3px;color:var(--list-muted);font-size:12.5px;line-height:1.35;overflow-wrap:anywhere}
  .admin-categories .prod-count-badge{display:inline-flex;min-height:28px;max-width:100%;align-items:center;gap:5px;padding:5px 8px;border:1px solid var(--list-hair);border-radius:999px;background:#fff;color:var(--list-deep);font-size:12px;font-weight:600;line-height:1.2;white-space:nowrap}
  .admin-categories .prod-count-badge svg{width:13px;height:13px;flex:0 0 13px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}
  .admin-categories .archive-action{color:#43575A}
  .admin-categories .archive-action:hover{background:#EDF3F1;color:var(--list-deep)}
  .admin-categories .add-card{position:sticky;top:80px;min-width:0;padding:18px;border:1px solid var(--list-hair);border-radius:16px;background:#fff;scroll-margin-top:80px}
  .admin-categories .add-card h2{margin:0 0 17px}
  .admin-categories .field{margin:0 0 15px}
  .admin-categories .field label{display:block;margin:0 0 6px;color:#43575A;font-size:13px;font-weight:550;line-height:1.4}
  .admin-categories .field label .field-hint{color:var(--list-muted);font-size:12px;font-weight:400}
  .admin-categories .field input,.admin-categories .field textarea,.admin-categories .field select{display:block;width:100%;min-height:40px;padding:8px 2px;border:0;border-bottom:1px solid var(--list-hair);border-radius:0;outline:0;background:transparent;color:var(--list-ink);font-family:inherit;font-size:13.5px;line-height:1.4;box-shadow:none;transition:border-color .16s ease}
  .admin-categories .field input:focus,.admin-categories .field textarea:focus,.admin-categories .field select:focus{border-bottom-color:var(--list-teal);box-shadow:none}
  .admin-categories .field textarea{min-height:88px;padding:10px;border:1px solid var(--list-hair);border-radius:12px;resize:vertical}
  .admin-categories .field textarea:focus{border-color:var(--list-teal)}
  .admin-categories .parent-picker{position:relative}
  .admin-categories .parent-search-wrap{position:relative}
  .admin-categories .parent-search-wrap>svg{position:absolute;top:50%;left:2px;width:15px;height:15px;transform:translateY(-50%);fill:none;stroke:var(--list-muted);stroke-width:1.6;pointer-events:none}
  .admin-categories .parent-search-wrap input{padding-left:25px}
  .admin-categories #parentDropdown{position:absolute;z-index:20;top:calc(100% + 6px);right:0;left:0;max-height:200px;overflow-y:auto;border:1px solid var(--list-hair);border-radius:12px;background:#fff;box-shadow:0 9px 24px rgba(14,74,87,.12)}
  .admin-categories .parent-option{display:flex;min-height:40px;align-items:center;padding:8px 11px;border-bottom:1px solid rgba(15,76,92,.08);color:var(--list-ink);font-size:13px;line-height:1.35;overflow-wrap:anywhere;cursor:pointer}
  .admin-categories .parent-option:last-child{border-bottom:0}
  .admin-categories .parent-option:hover,.admin-categories .parent-option.is-active{background:#EAF3F2;color:var(--list-deep)}
  .admin-categories .parent-empty{padding:12px;color:var(--list-muted);font-size:13px;text-align:center}
  .admin-categories #parentSelected{display:none;min-height:36px;align-items:center;justify-content:space-between;gap:8px;margin-top:8px;padding:4px 7px 4px 10px;border:1px solid var(--list-hair);border-radius:999px;background:#EAF3F2;color:var(--list-deep)}
  .admin-categories .parent-selected-value{display:flex;min-width:0;align-items:center;gap:6px;font-size:12.5px;font-weight:600}
  .admin-categories .parent-selected-value>span:last-child{overflow-wrap:anywhere}
  .admin-categories .parent-selected-dot{width:7px;height:7px;flex:0 0 7px;border-radius:50%;background:var(--list-teal)}
  .admin-categories .parent-clear{min-width:36px;min-height:32px;padding:5px 8px;border:0;border-radius:999px;background:transparent;color:#536366;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer}
  .admin-categories .parent-clear:hover{background:rgba(15,76,92,.08);color:var(--list-deep)}
  .admin-categories .add-category-button{display:flex;width:100%;min-height:44px;align-items:center;justify-content:center;margin-top:3px;padding:10px 14px;border:0;border-radius:10px;background:var(--list-deep);color:#fff;font-family:inherit;font-size:13px;font-weight:650;cursor:pointer;transition:transform .16s cubic-bezier(.22,1,.36,1),background-color .16s ease}
  .admin-categories .add-category-button:hover{background:var(--list-teal)}
  .admin-categories .add-category-button:active{transform:scale(.98)}
  .admin-categories .form-helper{margin:10px 0 0;color:var(--list-muted);font-size:12.5px;line-height:1.45;text-align:left}
  .admin-categories .archived-panel{margin-top:18px}
  .admin-categories .archived-header{display:flex;min-height:58px;align-items:center;justify-content:space-between;gap:10px;padding:13px 16px;border-bottom:1px solid var(--list-hair)}
  .admin-categories .archived-header h2{font-size:18px}
  .admin-categories .archived-description{margin:0;padding:12px 16px 0;color:var(--list-muted);font-size:12.5px;line-height:1.5}
  .admin-categories .archived-table{margin-top:10px}
  .admin-categories .archived-table col.archived-category-col{width:25%}
  .admin-categories .archived-table col.archived-slug-col{width:22%}
  .admin-categories .archived-table col.archived-products-col{width:16%}
  .admin-categories .archived-table col.archived-date-col{width:18%}
  .admin-categories .archived-table col.archived-actions-col{width:19%}
  .admin-categories .archived-category{display:flex;min-width:0;align-items:center;gap:9px}
  .admin-categories .archived-avatar{display:flex;width:32px;height:32px;flex:0 0 32px;align-items:center;justify-content:center;border-radius:9px;background:#ECEDEA;color:#5F696A;font-size:12px;font-weight:700}
  .admin-categories .archived-name{min-width:0;color:var(--list-ink);font-weight:600;overflow-wrap:anywhere}
  .admin-categories .archived-slug,.admin-categories .archived-date{color:var(--list-muted);font-size:12.5px;overflow-wrap:anywhere}
  .admin-categories .restore-action{border-color:var(--list-hair);color:var(--list-deep)}
  @media(max-width:1099.98px){.admin-categories .category-layout{grid-template-columns:minmax(0,1fr)}.admin-categories .add-card{position:static;top:auto}.admin-categories .admin-list-mobile-link{display:inline-flex;min-height:36px;align-items:center;margin-top:9px;color:var(--list-deep);font-size:13px;font-weight:600;text-decoration:underline;text-decoration-color:rgba(15,76,92,.35);text-underline-offset:3px}.admin-categories .category-table col.category-col,.admin-categories .category-table col.products-col,.admin-categories .category-table col.status-col,.admin-categories .category-table col.actions-col{width:auto}.admin-categories .archived-table col.archived-category-col,.admin-categories .archived-table col.archived-slug-col,.admin-categories .archived-table col.archived-products-col,.admin-categories .archived-table col.archived-date-col,.admin-categories .archived-table col.archived-actions-col{width:auto}}
  @media(max-width:767.98px){.admin-categories .cat-table-header,.admin-categories .archived-header{padding:12px}.admin-categories .add-card{padding:15px}.admin-categories .archived-header{align-items:flex-start;flex-direction:column}.admin-categories .category-table tr.list-record{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"category status" "products actions"}.admin-categories .archived-table tr.list-record{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"category archived" "slug products" "actions actions"}.admin-categories .category-table td.actions-column,.admin-categories .archived-table td.actions-column{justify-self:start}.admin-categories .category-table td.status-column,.admin-categories .archived-table td.archived-column{justify-self:end;text-align:right}.admin-categories .category-table td.products-column{align-self:center}.admin-categories .archived-table td.products-column{justify-self:end}.admin-categories .category-table td.actions-column,.admin-categories .archived-table td.actions-column{width:100%}.admin-categories .category-table .archive-action,.admin-categories .archived-table .restore-action{min-width:82px}}
</style>

<div class="admin-list admin-categories" data-ui="admin-categories">
  <header class="admin-list-head">
    <h1>Manage Categories</h1>
    <p>Organize your catalog — create, archive, and restore categories. Group products *sama sama* for a clean storefront.</p>
    <a class="admin-list-mobile-link" href="#add-category">Add a category</a>
  </header>

  @if(session('status'))
    <div class="admin-list-flash" role="status" aria-live="polite">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
      <span>{{ session('status') }}</span>
    </div>
  @endif

  <nav class="admin-list-stats admin-list-stats--4" aria-label="Category totals">
    <div class="admin-list-stat"><span class="admin-list-stat-label">Total Categories</span><strong class="{{ $stats['all'] ? '' : 'is-zero' }}">{{ $stats['all'] }}</strong><small>including archived</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">Active</span><strong class="{{ $stats['active'] ? '' : 'is-zero' }}">{{ $stats['active'] }}</strong><small>visible</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">Archived</span><strong class="{{ $stats['archived'] ? '' : 'is-zero' }}">{{ $stats['archived'] }}</strong><small>hidden</small></div>
    <div class="admin-list-stat"><span class="admin-list-stat-label">With Products</span><strong class="{{ $stats['with_products'] ? '' : 'is-zero' }}">{{ $stats['with_products'] }}</strong><small>in use</small></div>
  </nav>

  <section class="admin-list-toolbar admin-list-toolbar--single" aria-label="Find categories">
    <form class="admin-list-search" method="GET" role="search">
      <div class="admin-list-search-field">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.6"/><path d="m16 16 4.2 4.2"/></svg>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search categories by name or slug..." aria-label="Search categories by name or slug">
        <button type="submit">Search</button>
      </div>
      @if(request('search'))<a class="admin-list-clear" href="{{ url('/admin/categories') }}">Clear</a>@endif
    </form>
  </section>

  <div class="category-layout">
    <section class="admin-list-panel" aria-labelledby="categories-list-title">
      <div class="cat-table-header">
        <h2 id="categories-list-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"/></svg>
          Categories List
        </h2>
        <span class="shown-count">{{ $categories->count() }} shown</span>
      </div>
      <div class="cat-table-wrap">
        <table class="admin-list-table category-table" role="table">
          <colgroup><col class="category-col"><col class="products-col"><col class="status-col"><col class="actions-col"></colgroup>
          <thead role="rowgroup">
            <tr role="row">
              <th scope="col" role="columnheader">Category</th>
              <th scope="col" role="columnheader">Products</th>
              <th scope="col" role="columnheader">Status</th>
              <th scope="col" role="columnheader">Actions</th>
            </tr>
          </thead>
          <tbody role="rowgroup">
            @forelse($categories as $category)
              <tr class="list-record" role="row">
                <td class="category-column" data-label="Category" role="cell">
                  <div class="category-cell">
                    <span class="category-avatar" aria-hidden="true">{{ strtoupper(mb_substr($category->name,0,1)) }}</span>
                    <span class="category-copy">
                      <span class="category-name">{{ $category->name }}</span>
                      <span class="category-slug">/{{ $category->slug }}@if($category->parent) · parent: {{ $category->parent->name }}@endif</span>
                    </span>
                  </div>
                </td>
                <td class="products-column" data-label="Products" role="cell">
                  <span class="prod-count-badge">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>
                    {{ $category->products_count }} {{ $category->products_count===1?'item':'items' }}
                  </span>
                </td>
                <td class="status-column" data-label="Status" role="cell">
                  @if($category->active)
                    <span class="admin-list-status admin-list-status--active"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 8 3 3 7-7"/></svg>Active</span>
                  @else
                    <span class="admin-list-status admin-list-status--neutral"><svg viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="4"/></svg>Inactive</span>
                  @endif
                </td>
                <td class="actions-column list-no-label" data-label="Actions" role="cell">
                  <form method="POST" action="{{ url('/admin/categories/'.$category->id.'/archive') }}" data-confirm="This category will be archived and hidden from the store. You can restore it anytime. Continue?" data-confirm-title="Archive Category?" data-confirm-ok="Archive" data-confirm-class="btn-warn">
                    @csrf
                    <button class="admin-list-action archive-action" type="submit" aria-label="Archive {{ $category->name }}">
                      <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 5h11v8.5h-11V5ZM1.5 2.5h13v2.5h-13V2.5ZM6 8h4"/></svg>Archive
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr role="row"><td class="list-empty" colspan="4" role="cell">No categories found<br><span style="font-weight:400;font-size:13px;">Try a different search.</span></td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="admin-list-pagination">
        @include('admin.partials.pager', ['paginator' => $categories])
      </div>
    </section>

    <section class="add-card" id="add-category" aria-labelledby="add-category-title">
      <h2 id="add-category-title">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Add New Category
      </h2>
      <form method="POST" action="{{ url('/admin/categories') }}">
        @csrf
        <div class="field">
          <label for="categoryName">Name</label>
          <input type="text" id="categoryName" name="name" required placeholder="e.g. Handbags" />
        </div>
        <div class="field">
          <label for="categoryDescription">Description <span class="field-hint">(optional)</span></label>
          <textarea id="categoryDescription" name="description" rows="3" placeholder="Short description for this category..."></textarea>
        </div>
        <div class="field">
          <label for="parentSearch">Parent Category <span class="field-hint">(leave empty for Top Level)</span></label>
          <div class="parent-picker">
            <div class="parent-search-wrap">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
              <input type="text" id="parentSearch" placeholder="Search parent category..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-haspopup="listbox" aria-expanded="false" aria-controls="parentDropdown" />
            </div>
            <input type="hidden" name="parent_id" id="parentId" value="">
            <div id="parentDropdown" role="listbox" aria-label="Parent categories" style="display:none;"></div>
            <div id="parentSelected" role="status" aria-live="polite">
              <span class="parent-selected-value"><span class="parent-selected-dot" aria-hidden="true"></span><span id="parentSelectedName"></span></span>
              <button class="parent-clear" type="button" onclick="clearParent()" aria-label="Clear parent category selection">Clear</button>
            </div>
          </div>
        </div>
        <button class="add-category-button" type="submit">Add Category</button>
        <p class="form-helper">New categories appear instantly in product forms and storefront filters.</p>
      </form>
    </section>
  </div>

  @if($archived->total())
    <section class="admin-list-panel archived-panel" aria-labelledby="archived-categories-title">
      <div class="archived-header">
        <h2 id="archived-categories-title">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
          Archived Categories ({{ $archived->total() }})
        </h2>
        <span class="admin-list-status admin-list-status--neutral">Hidden — can be restored anytime</span>
      </div>
      <p class="archived-description">Archived categories are hidden from the store but never permanently deleted. Restore them anytime to bring back their products.</p>
      <div class="cat-table-wrap archived-table-wrap">
        <table class="admin-list-table archived-table" role="table">
          <colgroup><col class="archived-category-col"><col class="archived-slug-col"><col class="archived-products-col"><col class="archived-date-col"><col class="archived-actions-col"></colgroup>
          <thead role="rowgroup">
            <tr role="row">
              <th scope="col" role="columnheader">Category</th>
              <th scope="col" role="columnheader">Slug</th>
              <th scope="col" role="columnheader">Products</th>
              <th scope="col" role="columnheader">Archived</th>
              <th scope="col" role="columnheader">Actions</th>
            </tr>
          </thead>
          <tbody role="rowgroup">
            @foreach($archived as $category)
              <tr class="list-record" role="row">
                <td class="category-column" data-label="Category" role="cell">
                  <span class="archived-category">
                    <span class="archived-avatar" aria-hidden="true">{{ strtoupper(mb_substr($category->name,0,1)) }}</span>
                    <span class="archived-name">{{ $category->name }}</span>
                  </span>
                </td>
                <td class="slug-column archived-slug" data-label="Slug" role="cell">/{{ $category->slug }}</td>
                <td class="products-column" data-label="Products" role="cell">
                  <span class="prod-count-badge"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v4H4V4Zm0 6h16v10H4V10Z"/></svg>{{ $category->products_count }} {{ $category->products_count===1?'item':'items' }}</span>
                </td>
                <td class="archived-column archived-date" data-label="Archived" role="cell">{{ optional($category->deleted_at)->format('M d, Y') }}</td>
                <td class="actions-column list-no-label" data-label="Actions" role="cell">
                  <form method="POST" action="{{ url('/admin/categories/'.$category->id.'/restore') }}">
                    @csrf
                    <button class="admin-list-action restore-action" type="submit" aria-label="Restore {{ $category->name }}">
                      <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 7V3m0 0h4M3 3l3 3a5 5 0 1 1-1 5"/></svg>Restore
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="admin-list-pagination">
        @include('admin.partials.pager', ['paginator' => $archived])
      </div>
    </section>
  @endif
</div>

<script>
  (function(){
    const cats = @json(($allCategories ?? collect())->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values());
    const input = document.getElementById('parentSearch');
    const hidden = document.getElementById('parentId');
    const dropdown = document.getElementById('parentDropdown');
    const selected = document.getElementById('parentSelected');
    const selectedName = document.getElementById('parentSelectedName');
    let activeOption = -1;
    let visibleCats = [];

    function setExpanded(expanded){
      input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if(!expanded) input.removeAttribute('aria-activedescendant');
    }

    function setActive(index){
      const options = dropdown.querySelectorAll('[role="option"]');
      if(!options.length){ activeOption = -1; input.removeAttribute('aria-activedescendant'); return; }
      activeOption = Math.max(0, Math.min(index, options.length - 1));
      options.forEach((option, i)=>{
        const active = i === activeOption;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      input.setAttribute('aria-activedescendant', options[activeOption].id);
      options[activeOption].scrollIntoView({block:'nearest'});
    }

    function render(list){
      visibleCats = list;
      activeOption = -1;
      dropdown.innerHTML = '';
      input.removeAttribute('aria-activedescendant');
      if(list.length === 0){
        const empty = document.createElement('div');
        empty.className = 'parent-empty';
        empty.textContent = 'No categories found';
        empty.setAttribute('aria-disabled','true');
        dropdown.appendChild(empty);
      } else {
        list.forEach((c,index)=>{
          const option = document.createElement('div');
          option.id = 'parent-option-' + c.id;
          option.className = 'parent-option';
          option.setAttribute('role','option');
          option.setAttribute('aria-selected','false');
          option.textContent = c.name;
          option.addEventListener('mouseenter', ()=> setActive(index));
          option.addEventListener('mousedown', (event)=> event.preventDefault());
          option.addEventListener('click', ()=> selectCat(c));
          dropdown.appendChild(option);
        });
      }
      dropdown.style.display = 'block';
      setExpanded(true);
    }

    function selectCat(c){
      hidden.value = c.id;
      selectedName.textContent = c.name;
      selected.style.display = 'flex';
      input.value = '';
      dropdown.style.display = 'none';
      setExpanded(false);
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
    input.addEventListener('keydown', (event)=>{
      if(event.key === 'ArrowDown' || event.key === 'ArrowUp'){
        event.preventDefault();
        if(dropdown.style.display === 'none'){
          const q = input.value.toLowerCase().trim();
          render(q ? cats.filter(c=>c.name.toLowerCase().includes(q)) : cats);
        }
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        setActive(activeOption < 0 ? (direction > 0 ? 0 : visibleCats.length - 1) : activeOption + direction);
      } else if(event.key === 'Enter' && activeOption >= 0 && visibleCats[activeOption]){
        event.preventDefault();
        selectCat(visibleCats[activeOption]);
      } else if(event.key === 'Escape' && dropdown.style.display !== 'none'){
        event.preventDefault();
        dropdown.style.display = 'none';
        setExpanded(false);
      }
    });
    document.addEventListener('click', (e)=>{
      if(!e.target.closest('#parentSearch') && !e.target.closest('#parentDropdown') && !e.target.closest('#parentSelected')){
        dropdown.style.display = 'none';
        setExpanded(false);
      }
    });
  })();
</script>
@endsection
