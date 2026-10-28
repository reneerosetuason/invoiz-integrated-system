import 'package:flutter/material.dart';
import '../screens/product_list_screen.dart';
import '../services/api_service.dart';
import '../services/recently_viewed_service.dart';
import '../theme.dart';
import 'invoiz_logo.dart';
import 'product_card.dart';

class CatalogBrowser extends StatefulWidget {
  final ApiService? api;
  final bool onSale;
  final bool showIntro;
  final int? categoryId;
  final String? initialSearch;
  const CatalogBrowser({
    super.key,
    this.api,
    this.onSale = false,
    this.showIntro = false,
    this.categoryId,
    this.initialSearch,
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
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => _load(),
    child: CustomScrollView(
      controller: _scroll,
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
          sliver: SliverToBoxAdapter(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  key: const ValueKey('catalog-search'),
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    hintText: 'Search products',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                      tooltip: 'Search products',
                      onPressed: () => _load(),
                      icon: const Icon(Icons.arrow_forward),
                    ),
                  ),
                ),
                if (widget.showIntro) ...[const SizedBox(height: 16), _intro()],
                if (widget.onSale)
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'A little more, for less',
                          style: Theme.of(context).textTheme.headlineSmall,
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
                        separatorBuilder: (_, i) => const SizedBox(width: 8),
                        itemBuilder: (_, i) => OutlinedButton.icon(
                          onPressed: () => _claim(_vouchers[i]),
                          icon: const Icon(Icons.confirmation_number_outlined),
                          label: Text(
                            '${_vouchers[i]['name'] ?? 'Store voucher'} · Collect',
                          ),
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: 14),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _categoryChip(null, 'All'),
                      ..._categories.map(
                        (category) => _categoryChip(
                          category['id'] as int,
                          '${category['name']}',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _category == null
                                ? (widget.onSale ? 'On sale' : 'The collection')
                                : '${_categories.where((c) => c['id'] == _category).firstOrNull?['name'] ?? 'Products'}',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          Text(
                            '$_total ${_total == 1 ? 'product' : 'products'}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    PopupMenuButton<String>(
                      tooltip: 'Sort products',
                      initialValue: _sort,
                      onSelected: (value) {
                        _sort = value;
                        _load();
                      },
                      itemBuilder: (_) => _sorts.entries
                          .where((e) => widget.onSale || e.key != 'discount')
                          .map(
                            (e) => CheckedPopupMenuItem(
                              value: e.key,
                              checked: e.key == _sort,
                              child: Text(e.value),
                            ),
                          )
                          .toList(),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          vertical: 14,
                          horizontal: 8,
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.sort, size: 20),
                            const SizedBox(width: 5),
                            Text(
                              _sorts[_sort] ?? 'Sort',
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.primaryDark,
                              ),
                            ),
                          ],
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
              child: Center(child: CircularProgressIndicator()),
            ),
          )
        else if (_error != null)
          SliverToBoxAdapter(
            child: _message(
              Icons.cloud_off_outlined,
              'Unable to load products',
              _error!,
              'Try again',
              () => _load(),
            ),
          )
        else if (_products.isEmpty)
          SliverToBoxAdapter(
            child: _message(
              Icons.search_off,
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
                final columns = constraints.crossAxisExtent >= 900
                    ? 4
                    : constraints.crossAxisExtent >= 600
                    ? 3
                    : 2;
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
                    mainAxisExtent: width + 132,
                  ),
                );
              },
            ),
          ),
        if (!_loading && _error == null && _page < _lastPage)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: OutlinedButton(
                onPressed: _more ? null : () => _load(append: true),
                child: _more
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Load more products'),
              ),
            ),
          ),
        if (widget.showIntro && _recent.isNotEmpty)
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 24, 16, 0),
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
                      TextButton(
                        onPressed: () async {
                          await RecentlyViewedService.clear();
                          if (mounted) setState(() => _recent = []);
                        },
                        child: const Text('Clear'),
                      ),
                    ],
                  ),
                  SizedBox(
                    height: 275,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: _recent.length,
                      separatorBuilder: (_, i) => const SizedBox(width: 12),
                      itemBuilder: (_, i) => SizedBox(
                        width: 160,
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
        const SliverToBoxAdapter(child: SizedBox(height: 24)),
      ],
    ),
  );
  Widget _categoryChip(int? id, String name) => Padding(
    padding: const EdgeInsets.only(right: 8),
    child: ChoiceChip(
      label: Text(name),
      selected: _category == id,
      showCheckmark: false,
      onSelected: (_) {
        _category = id;
        _load();
      },
    ),
  );
  Widget _intro() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: AppColors.surfaceTint,
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Curated for you',
                style: Theme.of(context).textTheme.headlineMedium,
              ),
              const SizedBox(height: 7),
              const Text(
                'Everyday essentials. Honest prices.',
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 8),
              TextButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProductListScreen()),
                ),
                style: TextButton.styleFrom(
                  padding: EdgeInsets.zero,
                  alignment: Alignment.centerLeft,
                ),
                label: const Text('Explore sale'),
                icon: const Icon(Icons.arrow_forward, size: 18),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        InvoizLogo.logoWidget(size: 70, radius: 16),
      ],
    ),
  );
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
        OutlinedButton(onPressed: callback, child: Text(action)),
      ],
    ),
  );
}
