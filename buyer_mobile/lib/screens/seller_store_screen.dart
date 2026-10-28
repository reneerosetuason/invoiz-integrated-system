import 'package:flutter/material.dart';
import '../config.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import 'cart_screen.dart';
import 'chat_screen.dart';
import 'login_screen.dart';
import 'product_detail_screen.dart';

/// A seller's storefront (Shopee/Lazada-style): header + stats, follow button,
/// tabs for Products / Reviews, with in-store search + sort.
class SellerStoreScreen extends StatefulWidget {
  final int sellerId;
  const SellerStoreScreen({super.key, required this.sellerId});

  @override
  State<SellerStoreScreen> createState() => _SellerStoreScreenState();
}

class _SellerStoreScreenState extends State<SellerStoreScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  final _searchCtrl = TextEditingController();

  Map<String, dynamic>? _store;
  List<Map<String, dynamic>> _products = [];
  List<Map<String, dynamic>> _reviews = [];
  bool _loading = true;
  bool _following = false;
  bool _followBusy = false;
  int _followers = 0;
  int _tab = 0;
  String _sort = 'newest';

  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(() {
      if (_tabController.indexIsChanging) {
        final next = _tabController.index;
        setState(() => _tab = next);
        if (next == 1 && _reviews.isEmpty) _loadReviews();
      }
    });
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final query = <String, String>{
        'per_page': '24',
        if (_sort == 'price_asc' || _sort == 'price_desc' || _sort == 'rating')
          'sort': _sort,
        if (_searchCtrl.text.trim().isNotEmpty)
          'search': _searchCtrl.text.trim(),
      };
      final data = await _api.get('stores/${widget.sellerId}', query: query);
      final store = data['store'] as Map<String, dynamic>;
      final products = data['products'];
      setState(() {
        _store = store;
        _products = (products['data'] as List).cast<Map<String, dynamic>>();
        _following = store['is_following'] as bool? ?? false;
        _followers = (store['followers'] as int?) ?? 0;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _toggleFollow() async {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please log in to follow stores.')),
      );
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      return;
    }
    setState(() => _followBusy = true);
    try {
      final method = _following ? 'DELETE' : 'POST';
      final r = await _api.request(
        'stores/${widget.sellerId}/follow',
        method: method,
      );
      setState(() {
        _following = r['is_following'] as bool;
        _followers = (r['followers'] as int?) ?? _followers;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_following ? 'Store followed.' : 'Store unfollowed.'),
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _followBusy = false);
    }
  }

  Future<void> _loadReviews() async {
    try {
      final data = await _api.get('stores/${widget.sellerId}/reviews');
      setState(() {
        _reviews = (data['reviews'] as List).cast<Map<String, dynamic>>();
      });
    } catch (_) {
      // Reviews are best-effort; keep empty on failure.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.card,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
        title: Container(
          height: 38,
          decoration: BoxDecoration(
            color: AppColors.surfaceSoft,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Row(
            children: [
              const SizedBox(width: 10),
              Icon(Icons.search, size: 18, color: AppColors.textSecondary),
              const SizedBox(width: 6),
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  style: const TextStyle(fontSize: 13),
                  decoration: const InputDecoration(
                    hintText: 'Search in this store...',
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(vertical: 9),
                  ),
                ),
              ),
            ],
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_cart_outlined),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const CartScreen()),
            ),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _store == null
          ? const Center(child: Text('Store not found.'))
          : Column(
              children: [
                _storeHeader(context),
                TabBar(
                  controller: _tabController,
                  labelColor: AppColors.primary,
                  unselectedLabelColor: AppColors.textSecondary,
                  indicatorColor: AppColors.primary,
                  tabs: const [
                    Tab(text: 'Products'),
                    Tab(text: 'Reviews'),
                  ],
                ),
                Expanded(
                  child: _tab == 0
                      ? _productsTab(context)
                      : _reviewsTab(context),
                ),
              ],
            ),
    );
  }

  // ---------------------------------------------------------------------
  // Store header (banner + info + stats + follow/chat)
  // ---------------------------------------------------------------------
  Widget _storeHeader(BuildContext context) {
    final s = _store!;
    final name = s['business_name'] as String? ?? 'Store';
    final initial = name.trim().isEmpty
        ? 'S'
        : name.trim().substring(0, 1).toUpperCase();
    final rating = double.tryParse('${s['rating']}') ?? 0;
    final productCount = (s['product_count'] as int?) ?? 0;
    final totalSold = (s['total_sold'] as int?) ?? 0;
    final sellerId = s['seller_id'] as int;
    final auth = AuthServiceProvider.of(context);

    return Container(
      width: double.infinity,
      color: AppColors.card,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 72,
            width: double.infinity,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [AppColors.primary, AppColors.primaryDark],
              ),
            ),
            child: Stack(
              children: [
                Positioned(
                  right: -16,
                  bottom: -22,
                  child: Icon(
                    Icons.storefront,
                    size: 110,
                    color: Colors.white.withValues(alpha: 0.12),
                  ),
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 12,
                  child: Row(
                    children: [
                      Container(
                        width: 54,
                        height: 54,
                        decoration: BoxDecoration(
                          color: AppColors.card,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.5),
                            width: 2,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          initial,
                          style: TextStyle(
                            color: AppColors.primary,
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Flexible(
                                  child: Text(
                                    name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: Colors.white,
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 4),
                                Icon(
                                  Icons.verified,
                                  size: 16,
                                  color: AppColors.secondary,
                                ),
                              ],
                            ),
                            const SizedBox(height: 3),
                            Text(
                              s['line_of_business'] as String? ?? '',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Row(
              children: [
                _statCell(rating.toStringAsFixed(1), 'Rating'),
                _statCell('$_followers', 'Followers'),
                _statCell('$productCount', 'Products'),
                _statCell('$totalSold', 'Sold'),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _followBusy ? null : _toggleFollow,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _following
                          ? AppColors.textSecondary
                          : AppColors.primary,
                      side: BorderSide(
                        color: _following
                            ? AppColors.border
                            : AppColors.primary,
                      ),
                      minimumSize: const Size.fromHeight(42),
                    ),
                    icon: _followBusy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Icon(_following ? Icons.check : Icons.add, size: 18),
                    label: Text(_following ? 'Following' : 'Follow'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => openSellerChat(
                      context,
                      sellerId: sellerId,
                      subject: 'Inquiry to $name',
                      initialBody:
                          'Hello $name! I have a question about your products.',
                    ),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(42),
                      foregroundColor: AppColors.primary,
                    ),
                    icon: const Icon(Icons.chat_bubble_outline, size: 18),
                    label: const Text('Chat'),
                  ),
                ),
              ],
            ),
          ),
          if (auth.isLoggedIn && auth.user?.id == sellerId)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: Row(
                children: [
                  Icon(Icons.storefront, size: 14, color: AppColors.primary),
                  const SizedBox(width: 6),
                  Text(
                    'This is your own store.',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.primary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _statCell(String value, String label) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: Color(0xFF212121),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------
  // Products tab (search + sort + grid)
  // ---------------------------------------------------------------------
  Widget _productsTab(BuildContext context) {
    return Column(
      children: [
        Container(
          color: AppColors.card,
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
          child: Row(
            children: [
              const Spacer(),
              Container(
                height: 36,
                padding: const EdgeInsets.symmetric(horizontal: 10),
                decoration: BoxDecoration(
                  color: AppColors.surfaceSoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: PopupMenuButton<String>(
                  initialValue: _sort,
                  onSelected: (v) {
                    setState(() => _sort = v);
                    _load();
                  },
                  icon: const Icon(Icons.sort, size: 18),
                  tooltip: 'Sort',
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: 'newest', child: Text('Newest')),
                    PopupMenuItem(
                      value: 'price_asc',
                      child: Text('Price: Low to High'),
                    ),
                    PopupMenuItem(
                      value: 'price_desc',
                      child: Text('Price: High to Low'),
                    ),
                    PopupMenuItem(value: 'rating', child: Text('Rating')),
                  ],
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: _products.isEmpty
              ? const Center(child: Text('No products in this store.'))
              : GridView.builder(
                  padding: const EdgeInsets.all(16),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 14,
                    crossAxisSpacing: 14,
                    childAspectRatio: 0.64,
                  ),
                  itemCount: _products.length,
                  itemBuilder: (context, i) => _productCard(_products[i]),
                ),
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------
  // Reviews tab (summary + breakdown bars + list)
  // ---------------------------------------------------------------------
  Widget _reviewsTab(BuildContext context) {
    final s = _store!;
    final breakdown =
        (s['rating_breakdown'] as Map?)?.map(
          (k, v) => MapEntry(int.parse('$k'), v as int),
        ) ??
        <int, int>{};
    final reviewCount = (s['review_count'] as int?) ?? 0;
    final rating = double.tryParse('${s['rating']}') ?? 0;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Store Rating',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF212121),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Text(
                    rating.toStringAsFixed(1),
                    style: TextStyle(
                      color: AppColors.warning,
                      fontSize: 34,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: List.generate(
                          5,
                          (i) => Icon(
                            Icons.star,
                            size: 18,
                            color: i < rating.round()
                                ? AppColors.gold
                                : AppColors.border,
                          ),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '$reviewCount review${reviewCount == 1 ? '' : 's'}',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 16),
              for (int star = 5; star >= 1; star--)
                _ratingBar(star, breakdown[star] ?? 0, reviewCount),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (_reviews.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 20),
            child: Center(
              child: Text(
                'No reviews yet.',
                style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
              ),
            ),
          )
        else
          ..._reviews.map((r) => _reviewTile(r)),
      ],
    );
  }

  Widget _ratingBar(int star, int count, int total) {
    final pct = total > 0 ? (count / total) * 100 : 0.0;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Text('$star', style: const TextStyle(fontSize: 12)),
          Icon(Icons.star, size: 13, color: AppColors.gold),
          const SizedBox(width: 8),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: pct / 100,
                minHeight: 7,
                backgroundColor: AppColors.surfaceSoft,
                color: AppColors.gold,
              ),
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            width: 26,
            child: Text(
              '$count',
              textAlign: TextAlign.end,
              style: const TextStyle(fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }

  Widget _reviewTile(Map<String, dynamic> r) {
    final rating = (r['rating'] as int?) ?? 0;
    final buyer = r['buyer'];
    final name = buyer is Map<String, dynamic>
        ? '${buyer['first_name'] ?? ''} ${buyer['last_name'] ?? ''}'
        : 'Anonymous';
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                name,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF212121),
                ),
              ),
              const SizedBox(width: 8),
              Row(
                children: List.generate(
                  5,
                  (i) => Icon(
                    Icons.star,
                    size: 13,
                    color: i < rating ? AppColors.gold : AppColors.border,
                  ),
                ),
              ),
            ],
          ),
          if (r['comment'] != null && (r['comment'] as String).isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                r['comment'] as String,
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.4,
                  color: Color(0xFF212121),
                ),
              ),
            ),
          if (r['product'] != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                (r['product'] as Map)['name'] as String? ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
              ),
            ),
        ],
      ),
    );
  }

  Widget _productCard(Map<String, dynamic> p) {
    final price = double.tryParse('${p['effective_price'] ?? p['price']}') ?? 0;
    final rating = p['rating'] != null
        ? double.tryParse('${p['rating']}')
        : null;
    final sold = p['sold'] is int ? (p['sold'] as int) : 0;
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
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.border),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 138,
              width: double.infinity,
              color: AppColors.surfaceSoft,
              child: _productImage(p['image'] as String?),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p['name'] as String,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        height: 1.25,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF212121),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _formatPrice(price),
                      style: TextStyle(
                        color: AppColors.warning,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        if (rating != null) ...[
                          Icon(Icons.star, color: AppColors.gold, size: 12),
                          const SizedBox(width: 3),
                          Text(
                            rating.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF212121),
                            ),
                          ),
                          const SizedBox(width: 8),
                        ],
                        if (sold > 0)
                          Text(
                            '$sold sold',
                            style: TextStyle(
                              fontSize: 10,
                              color: AppColors.textSecondary,
                            ),
                          ),
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

  Widget _productImage(String? url) {
    if (url == null || url.isEmpty) {
      return const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 40,
      );
    }
    return Image.network(
      AppConfig.storageUrl(url),
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 40,
      ),
    );
  }

  String _formatPrice(double value) {
    final n = value.toStringAsFixed(2);
    final parts = n.split('.');
    return '₱${parts[0]}.${parts[1]}';
  }
}
