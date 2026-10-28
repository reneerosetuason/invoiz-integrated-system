import 'package:flutter/material.dart';
import '../config.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/main_layout.dart';
import 'product_detail_screen.dart';
import 'seller_store_screen.dart';

class ProductListScreen extends StatefulWidget {
  final int? categoryId;
  final String? categoryName;
  final String? search;
  final bool showAll;

  const ProductListScreen({
    super.key,
    this.categoryId,
    this.categoryName,
    this.search,
    this.showAll = false,
  });

  @override
  State<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends State<ProductListScreen> {
  final _api = ApiService();
  final _searchCtrl = TextEditingController();
  List _products = [];
  List _categories = [];
  List _vouchers = [];
  int? _categoryId;
  bool _loading = true;
  String _sort = 'discount';

  bool get _saleMode => !widget.showAll;

  @override
  void initState() {
    super.initState();
    _categoryId = widget.categoryId;
    _searchCtrl.text = widget.search ?? '';
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final cats = await _api.get('categories');
      final query = <String, String>{
        'per_page': '50',
        if (_saleMode) 'on_sale': '1',
        if (_categoryId != null) 'category_id': '$_categoryId',
        if (_searchCtrl.text.trim().isNotEmpty)
          'search': _searchCtrl.text.trim(),
        if (_sort == 'price_asc' ||
            _sort == 'price_desc' ||
            _sort == 'rating' ||
            _sort == 'discount')
          'sort': _sort,
      };
      final prods = await _api.get('products', query: query);
      List vouchers = [];
      try {
        if (ApiService.token != null) {
          final v = await _api.get('vouchers');
          vouchers = (v['vouchers'] as List?) ?? [];
        }
      } catch (_) {}
      setState(() {
        _categories = (cats['categories'] as List).cast<Map<String, dynamic>>();
        _products = (prods['data'] as List).cast<Map<String, dynamic>>();
        _vouchers = vouchers;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      currentIndex: 1,
      showAppBar: false,
      child: Column(
        children: [
          _saleHeader(context),
          if (_saleMode) _voucherStrip(),
          Container(
            color: AppColors.card,
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            child: Row(
              children: [
                Expanded(
                  child: Container(
                    height: 44,
                    decoration: BoxDecoration(
                      color: AppColors.surfaceSoft,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Row(
                      children: [
                        const SizedBox(width: 12),
                        Icon(
                          Icons.search,
                          color: AppColors.textSecondary,
                          size: 20,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: TextField(
                            controller: _searchCtrl,
                            onSubmitted: (_) => _load(),
                            textInputAction: TextInputAction.search,
                            style: const TextStyle(fontSize: 14),
                            decoration: InputDecoration(
                              hintText: _saleMode
                                  ? 'Search sale items...'
                                  : 'Search products...',
                              border: InputBorder.none,
                              isDense: true,
                              contentPadding: const EdgeInsets.symmetric(
                                vertical: 10,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.secondary,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: IconButton(
                    icon: const Icon(
                      Icons.search,
                      color: Colors.white,
                      size: 22,
                    ),
                    onPressed: _load,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              children: [
                _catChip(null, 'All'),
                ..._categories.map(
                  (c) => _catChip(c['id'] as int, c['name'] as String),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              children: [
                const Text('Sort:', style: TextStyle(fontSize: 13)),
                const SizedBox(width: 8),
                Expanded(
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        if (_saleMode)
                          _sortChip('discount', 'Biggest Discount'),
                        _sortChip('newest', 'Newest'),
                        _sortChip('price_asc', 'Price: Low-High'),
                        _sortChip('price_desc', 'Price: High-Low'),
                        _sortChip('rating', 'Rating'),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _products.isEmpty
                ? const Center(child: Text('No sale items found.'))
                : GridView.builder(
                    padding: const EdgeInsets.all(12),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          mainAxisSpacing: 14,
                          crossAxisSpacing: 14,
                          childAspectRatio: 0.62,
                        ),
                    itemCount: _products.length,
                    itemBuilder: (context, i) => _card(_products[i]),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _saleHeader(BuildContext context) {
    if (!_saleMode) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [const Color(0xFFE65100), const Color(0xFFFFB700)],
        ),
      ),
      padding: EdgeInsets.fromLTRB(
        16,
        MediaQuery.of(context).padding.top + 12,
        16,
        14,
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            onPressed: () => Navigator.pop(context),
          ),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Text(
                        'MEGA SALE',
                        style: TextStyle(
                          color: Color(0xFFE65100),
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 0.8,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Text(
                      'Up to 38% off',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '${_products.length} discounted item${_products.length == 1 ? '' : 's'} + vouchers below',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          const Icon(Icons.local_offer, color: Colors.white70, size: 40),
        ],
      ),
    );
  }

  Widget _voucherStrip() {
    if (_vouchers.isEmpty) return const SizedBox.shrink();
    return Container(
      color: AppColors.card,
      padding: const EdgeInsets.fromLTRB(0, 10, 0, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              'Claim a voucher',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: Color(0xFF212121),
              ),
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 86,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _vouchers.length,
              separatorBuilder: (_, __) => const SizedBox(width: 10),
              itemBuilder: (context, i) =>
                  _voucherCard(_vouchers[i] as Map<String, dynamic>),
            ),
          ),
        ],
      ),
    );
  }

  Widget _voucherCard(Map<String, dynamic> v) {
    final type = '${v['discount_type'] ?? ''}';
    final val = double.tryParse('${v['discount_value']}') ?? 0;
    final label = type == 'free_shipping'
        ? 'FREE SHIPPING'
        : type == 'percent'
        ? '${val.toStringAsFixed(0)}% OFF'
        : '₱${val.toStringAsFixed(0)} OFF';
    return GestureDetector(
      onTap: () async {
        try {
          await _api.post('vouchers/${v['id']}/claim', {});
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Voucher collected. Apply it at checkout!'),
            ),
          );
        } catch (e) {
          if (mounted)
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(e.toString())));
        }
      },
      child: Container(
        width: 200,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: AppColors.accent,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.primary, width: 1.2),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: AppColors.primary,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.confirmation_number_outlined,
                color: Colors.white,
                size: 24,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                    ),
                  ),
                  Text(
                    '${v['name'] ?? 'Store offer'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF212121),
                    ),
                  ),
                  Text(
                    'Min. ₱${double.tryParse('${v['min_spend']}')?.toStringAsFixed(0) ?? '0'} · tap to collect',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 10,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _catChip(int? id, String label) {
    final selected = _categoryId == id;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) {
          setState(() => _categoryId = id);
          _load();
        },
      ),
    );
  }

  Widget _sortChip(String value, String label) {
    final selected = _sort == value;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label, style: const TextStyle(fontSize: 12)),
        selected: selected,
        visualDensity: VisualDensity.compact,
        onSelected: (_) {
          setState(() => _sort = value);
          _load();
        },
      ),
    );
  }

  Widget _card(Map<String, dynamic> p) {
    final price = double.tryParse('${p['effective_price'] ?? p['price']}') ?? 0;
    final compare = p['compare_at_price'] != null
        ? double.tryParse('${p['compare_at_price']}')
        : null;
    final pct = (p['discount_percent'] as int?) ?? 0;
    final rating = p['rating'] != null
        ? double.tryParse('${p['rating']}')
        : null;
    final sold = p['sold'] is int ? (p['sold'] as int) : 0;
    final shop = p['shop'];
    final shopName = shop is Map<String, dynamic>
        ? (shop['business_name'] as String? ?? 'Invoiz Store')
        : null;
    final shopSellerId = shop is Map<String, dynamic>
        ? (shop['seller_id'] as int?)
        : null;
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ProductDetailScreen(productId: p['id'] as int),
        ),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.07),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 140,
              width: double.infinity,
              color: AppColors.surfaceSoft,
              child: Stack(
                children: [
                  Positioned.fill(child: _image(_cover(p))),
                  if (pct > 0)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE65100),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '-$pct%',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p['name'] as String,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12.5,
                        height: 1.2,
                        fontWeight: FontWeight.w500,
                        color: Color(0xFF212121),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            _fmt(price),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: AppColors.warning,
                              fontWeight: FontWeight.w800,
                              fontSize: 14,
                            ),
                          ),
                        ),
                        if (compare != null && compare > price) ...[
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              _fmt(compare),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 11,
                                color: AppColors.textSecondary,
                                decoration: TextDecoration.lineThrough,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 6),
                    if (shopName != null && shopSellerId != null)
                      GestureDetector(
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                SellerStoreScreen(sellerId: shopSellerId),
                          ),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 2),
                          child: Row(
                            children: [
                              Icon(
                                Icons.storefront,
                                size: 12,
                                color: AppColors.primary,
                              ),
                              const SizedBox(width: 3),
                              Expanded(
                                child: Text(
                                  shopName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: AppColors.primary,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    Row(
                      children: [
                        if (rating != null) ...[
                          Icon(Icons.star, color: AppColors.gold, size: 14),
                          const SizedBox(width: 2),
                          Text(
                            rating.toStringAsFixed(1),
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                          const SizedBox(width: 6),
                        ],
                        Flexible(
                          child: Text(
                            '${p['stock']} left',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                        if (sold > 0) ...[
                          const SizedBox(width: 6),
                          Flexible(
                            child: Text(
                              '$sold sold',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontSize: 11,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String? _cover(Map<String, dynamic> p) {
    final g = p['gallery'];
    if (g is List && g.isNotEmpty) return g.first as String;
    return p['image'] as String?;
  }

  Widget _image(String? url) {
    if (url == null || url.isEmpty)
      return const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 40,
      );
    final src = AppConfig.storageUrl(url);
    return Image.network(
      src,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 40,
      ),
    );
  }

  String _fmt(double v) {
    final n = v.toStringAsFixed(2);
    return '₱${n.split('.')[0]}.${n.split('.')[1]}';
  }
}
