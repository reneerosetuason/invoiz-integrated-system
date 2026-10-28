@extends('admin.layout')

@section('content')
@include('admin.partials.list-ui-styles')
<link rel="stylesheet" href="{{ asset('assets/admin/css/categories.css') }}">

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

<script src="{{ asset('assets/admin/js/categories.js') }}" data-config="{{ json_encode([(($allCategories ?? collect())->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values())], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endsection
