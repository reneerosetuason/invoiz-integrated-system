import 'buyer_dialog.dart';
import 'buyer_controls.dart';
import 'buyer_search.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/recently_viewed_service.dart';
import '../theme.dart';
import 'product_card.dart';

class CatalogBrowser extends StatefulWidget {
  final ApiService? api;
  final bool onSale;
  final bool showIntro;
  final int? categoryId;
  final String? initialSearch;
  final double bottomPadding;

  /// Hosts the same search field in a page's navigation instead of its body.
  final Widget Function(BuildContext context, Widget search, Widget content)?
  scaffoldBuilder;
  const CatalogBrowser({
    super.key,
    this.api,
    this.onSale = false,
    this.showIntro = false,
    this.categoryId,
    this.initialSearch,
    this.bottomPadding = 24,
    this.scaffoldBuilder,
  });
  @override
  State<CatalogBrowser> createState() => _CatalogBrowserState();
}

class _CatalogBrowserState extends State<CatalogBrowser> {
  late final ApiService _api;
  late final TextEditingController _search;
  final _scroll = ScrollController();
  List<Map<String, dynamic>> _products = [],
      _categories = [],
      _recent = [],
      _vouchers = [];
  int? _category;
  int _page = 1, _lastPage = 1, _total = 0, _request = 0;
  bool _loading = true, _more = false;
  String? _error;
  late String _sort;
  static const _sorts = {
    'newest': 'Newest',
    'price_asc': 'Price: low to high',
    'price_desc': 'Price: high to low',
    'rating': 'Top rated',
    'most_reviewed': 'Most reviewed',
    'discount': 'Biggest discount',
  };
  static const _compactSorts = {
    'price_asc': 'Price ↑',
    'price_desc': 'Price ↓',
    'most_reviewed': 'Reviews',
    'discount': 'Discount',
  };
  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _search = TextEditingController(text: widget.initialSearch ?? '');
    _category = widget.categoryId;
    _sort = widget.onSale ? 'discount' : 'newest';
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    _scroll.dispose();
    _request++;
    super.dispose();
  }

  Future<void> _load({bool append = false}) async {
    final request = ++_request;
    final page = append ? _page + 1 : 1;
    setState(() {
      if (append) {
        _more = true;
      } else {
        _loading = true;
      }
      _error = null;
    });
    try {
      final query = <String, String>{
        'page': '$page',
        'per_page': '12',
        'sort': _sort,
        if (_category != null) 'category_id': '$_category',
        if (widget.onSale) 'on_sale': '1',
        if (_search.text.trim().isNotEmpty) 'search': _search.text.trim(),
      };
      final response = await _api.get('products', query: query);
      List<Map<String, dynamic>> categories = _categories;
      if (_categories.isEmpty) {
        final result = await _api.get('categories');
        categories = (result['categories'] as List)
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
      }
      final recent = widget.showIntro
          ? await RecentlyViewedService.available(_api)
          : <Map<String, dynamic>>[];
      List<Map<String, dynamic>> vouchers = _vouchers;
      if (widget.onSale && ApiService.token != null && !append) {
        try {
          final result = await _api.get('vouchers');
          vouchers = (result['vouchers'] as List? ?? [])
              .map((v) => Map<String, dynamic>.from(v as Map))
              .toList();
        } catch (_) {}
      }
      if (!mounted || request != _request) return;
      final products = (response['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() {
        _products = append ? [..._products, ...products] : products;
        _categories = categories;
        _recent = recent;
        _vouchers = vouchers;
        _page = response['current_page'] as int? ?? page;
        _lastPage = response['last_page'] as int? ?? page;
        _total = response['total'] as int? ?? _products.length;
        _loading = false;
        _more = false;
      });
    } catch (error) {
      if (!mounted || request != _request) return;
      setState(() {
        _error = error.toString();
        _loading = false;
        _more = false;
      });
    }
  }

  Future<void> _returned() async {
    if (!mounted) return;
    final recent = await RecentlyViewedService.available(_api);
    if (mounted) setState(() => _recent = recent);
  }

  Future<void> _claim(Map<String, dynamic> voucher) async {
    try {
      await _api.post('vouchers/${voucher['id']}/claim', {});
      if (mounted)
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Voucher saved. You can apply it at checkout.'),
          ),
        );
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
    }
  }

  @override
  Widget build(BuildContext context) {
    final content = _content(context);
    return widget.scaffoldBuilder?.call(
          context,
          _searchField(compact: true),
          content,
        ) ??
        content;
  }

  Widget _content(BuildContext context) => Column(
    children: [
      // Categories stay in place while the products scroll. Search can be
      // hosted by the navigation above them.
      _floatingHeader(context),
      Expanded(
        child: RefreshIndicator.adaptive(
          onRefresh: () => _load(),
          child: CustomScrollView(
            controller: _scroll,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (widget.onSale)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'A little more, for less',
                                style: Theme.of(
                                  context,
                                ).textTheme.headlineSmall,
                              ),
                              const SizedBox(height: 4),
                              const Text(
                                'Current offers from the INVOIZ collection.',
                              ),
                            ],
                          ),
                        ),
                      if (_vouchers.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 12),
                          child: SizedBox(
                            height: 56,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: _vouchers.length,
                              separatorBuilder: (_, i) =>
                                  const SizedBox(width: 8),
                              itemBuilder: (_, i) => BuyerButton.secondary(
                                onPressed: () => _claim(_vouchers[i]),
                                icon: const Icon(
                                  Icons.confirmation_number_outlined,
                                ),
                                label: Text(
                                  '${_vouchers[i]['name'] ?? 'Store voucher'} · Collect',
                                ),
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: Wrap(
                              spacing: 8,
                              runSpacing: 2,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                Text(
                                  _category == null
                                      ? (widget.onSale
                                            ? 'On sale'
                                            : 'The collection')
                                      : '${_categories.where((c) => c['id'] == _category).firstOrNull?['name'] ?? 'Products'}',
                                  style: Theme.of(context).textTheme.titleLarge
                                      ?.copyWith(fontSize: 18),
                                ),
                                Text(
                                  '$_total ${_total == 1 ? 'product' : 'products'}',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ],
                            ),
                          ),
                          BuyerActionMenu<String>(
                            tooltip: 'Sort products',
                            initialValue: _sort,
                            onSelected: (value) {
                              _sort = value;
                              _load();
                            },
                            itemBuilder: (_) => _sorts.entries
                                .where(
                                  (e) => widget.onSale || e.key != 'discount',
                                )
                                .map(
                                  (e) => CheckedPopupMenuItem(
                                    value: e.key,
                                    checked: e.key == _sort,
                                    child: Text(e.value),
                                  ),
                                )
                                .toList(),
                            child: ConstrainedBox(
                              constraints: const BoxConstraints(
                                minHeight: 44,
                                maxWidth: 124,
                              ),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const Icon(
                                      CupertinoIcons.sort_down,
                                      size: 20,
                                    ),
                                    const SizedBox(width: 5),
                                    Flexible(
                                      child: Text(
                                        MediaQuery.textScalerOf(
                                                  context,
                                                ).scale(1) >
                                                1.2
                                            ? 'Sort'
                                            : _compactSorts[_sort] ??
                                                  _sorts[_sort] ??
                                                  'Sort',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          fontSize: 12,
                                          color: AppColors.primaryDark,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              if (_loading)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(40),
                    child: Center(child: CupertinoActivityIndicator()),
                  ),
                )
              else if (_error != null)
                SliverToBoxAdapter(
                  child: _message(
                    CupertinoIcons.cloud,
                    'Unable to load products',
                    _error!,
                    'Try again',
                    () => _load(),
                  ),
                )
              else if (_products.isEmpty)
                SliverToBoxAdapter(
                  child: _message(
                    CupertinoIcons.search,
                    'No products found',
                    'Try another search or category.',
                    'Clear filters',
                    () {
                      _search.clear();
                      _category = null;
                      _load();
                    },
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  sliver: SliverLayoutBuilder(
                    builder: (_, constraints) {
                      var columns = constraints.crossAxisExtent >= 900
                          ? 4
                          : constraints.crossAxisExtent >= 600
                          ? 3
                          : 2;
                      final textScale = MediaQuery.textScalerOf(
                        context,
                      ).scale(1);
                      if (constraints.crossAxisExtent / columns <
                          120 * textScale) {
                        columns =
                            (constraints.crossAxisExtent / (120 * textScale))
                                .floor()
                                .clamp(1, columns);
                      }
                      final width =
                          (constraints.crossAxisExtent - (columns - 1) * 12) /
                          columns;
                      return SliverGrid(
                        delegate: SliverChildBuilderDelegate(
                          (_, i) => ProductCard(
                            key: ValueKey(_products[i]['id']),
                            data: _products[i],
                            api: _api,
                            onReturned: _returned,
                          ),
                          childCount: _products.length,
                        ),
                        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: columns,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          mainAxisExtent:
                              width *
                                  (constraints.crossAxisExtent < 600
                                      ? .72
                                      : .95) +
                              110 * textScale,
                        ),
                      );
                    },
                  ),
                ),
              if (!_loading && _error == null && _page < _lastPage)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: BuyerButton.secondary(
                      onPressed: _more ? null : () => _load(append: true),
                      child: _more
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CupertinoActivityIndicator(),
                            )
                          : const Text('Load more products'),
                    ),
                  ),
                ),
              if (widget.showIntro && _recent.isNotEmpty)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Recently viewed',
                                style: Theme.of(context).textTheme.titleLarge,
                              ),
                            ),
                            BuyerButton.plain(
                              onPressed: () async {
                                await RecentlyViewedService.clear();
                                if (mounted) setState(() => _recent = []);
                              },
                              child: const Text('Clear'),
                            ),
                          ],
                        ),
                        SizedBox(
                          height:
                              146 * .72 +
                              MediaQuery.textScalerOf(context).scale(1) * 110,
                          child: ListView.separated(
                            scrollDirection: Axis.horizontal,
                            itemCount: _recent.length,
                            separatorBuilder: (_, i) =>
                                const SizedBox(width: 12),
                            itemBuilder: (_, i) => SizedBox(
                              width: 146,
                              child: ProductCard(
                                data: _recent[i],
                                api: _api,
                                onReturned: _returned,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              SliverToBoxAdapter(child: SizedBox(height: widget.bottomPadding)),
            ],
          ),
        ),
      ),
    ],
  );
  Widget _floatingHeader(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 2),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (widget.scaffoldBuilder == null) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: _searchField(),
          ),
          const SizedBox(height: 2),
        ],
        // Each category is its own pill; nothing wraps the row.
        SingleChildScrollView(
          key: const ValueKey('catalog-categories'),
          scrollDirection: Axis.horizontal,
          // Space around the individual pills keeps their shadows visible.
          padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
          child: Row(
            children: [
              _categoryChip(null, 'All'),
              ..._categories.map(
                (category) =>
                    _categoryChip(category['id'] as int, '${category['name']}'),
              ),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _searchField({bool compact = false}) => BuyerSearch(
    key: const ValueKey('catalog-search'),
    controller: _search,
    onSubmitted: (_) => _load(),
    hint: compact ? 'Search' : 'Search products',
    onSearch: () => _load(),
    onClear: compact
        ? () {
            _search.clear();
            _load();
          }
        : null,
    bare: !compact,
    compact: compact,
  );

  Widget _categoryChip(int? id, String name) {
    final selected = _category == id;
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: Semantics(
        selected: selected,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: selected ? AppColors.primaryDark : AppColors.paper,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.primaryDark
                  : AppColors.border.withValues(alpha: .8),
              width: .5,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primaryDark.withValues(
                  alpha: selected ? .16 : .10,
                ),
                blurRadius: 14,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: CupertinoButton(
            minimumSize: const Size(44, 44),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            onPressed: () {
              _category = id;
              _load();
            },
            child: Text(
              name,
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 13,
                height: 1.2,
                fontWeight: FontWeight.w600,
                color: selected ? Colors.white : AppColors.primaryDark,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _message(
    IconData icon,
    String title,
    String detail,
    String action,
    VoidCallback callback,
  ) => Padding(
    padding: const EdgeInsets.all(28),
    child: Column(
      children: [
        Icon(icon, size: 36, color: AppColors.textMuted),
        const SizedBox(height: 12),
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        Text(detail, textAlign: TextAlign.center),
        const SizedBox(height: 12),
        BuyerButton.secondary(onPressed: callback, child: Text(action)),
      ],
    ),
  );
}
