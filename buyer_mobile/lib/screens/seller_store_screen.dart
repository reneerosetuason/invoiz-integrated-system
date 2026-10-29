import '../widgets/buyer_dialog.dart';
import '../widgets/buyer_controls.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_scaffold.dart';
import '../widgets/buyer_search.dart';
import '../widgets/product_photo.dart';
import 'package:flutter/material.dart';
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
  final ApiService? api;
  const SellerStoreScreen({super.key, required this.sellerId, this.api});

  @override
  State<SellerStoreScreen> createState() => _SellerStoreScreenState();
}

class _SellerStoreScreenState extends State<SellerStoreScreen>
    with SingleTickerProviderStateMixin {
  late final _api = widget.api ?? ApiService();
  final _searchCtrl = TextEditingController();

  Map<String, dynamic>? _store;
  List<Map<String, dynamic>> _products = [];
  List<Map<String, dynamic>> _reviews = [];
  bool _loading = true;
  bool _reviewsLoading = false;
  String? _reviewsError;
  int _productsPage = 1;
  int _productsLastPage = 1;
  int _reviewsPage = 0;
  int _reviewsLastPage = 1;
  bool _loadingMore = false;
  int _loadVersion = 0;
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

  Future<void> _load({bool more = false}) async {
    if (more && (_loadingMore || _loading)) return;
    final version = ++_loadVersion;
    setState(() {
      if (more) {
        _loadingMore = true;
      } else {
        _loading = true;
        _loadingMore = false;
      }
    });
    try {
      final query = <String, String>{
        'per_page': '24',
        'page': '${more ? _productsPage + 1 : 1}',
        if (_sort == 'price_asc' || _sort == 'price_desc' || _sort == 'rating')
          'sort': _sort,
        if (_searchCtrl.text.trim().isNotEmpty)
          'search': _searchCtrl.text.trim(),
      };
      final data = await _api.get('stores/${widget.sellerId}', query: query);
      if (!mounted || version != _loadVersion) return;
      final store = data['store'] as Map<String, dynamic>;
      final products = data['products'];
      setState(() {
        _store = store;
        final items = (products['data'] as List).cast<Map<String, dynamic>>();
        _products = more ? [..._products, ...items] : items;
        _productsPage = products['current_page'] as int? ?? 1;
        _productsLastPage = products['last_page'] as int? ?? 1;
        _loadingMore = false;
        _following = store['is_following'] as bool? ?? false;
        _followers = (store['followers'] as int?) ?? 0;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      if (version != _loadVersion) return;
      setState(() {
        _loading = false;
        _loadingMore = false;
      });
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _toggleFollow() async {
    if (_followBusy) return;
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
      if (!mounted) return;
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
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _followBusy = false);
    }
  }

  Future<void> _loadReviews({bool more = false}) async {
    if (_reviewsLoading) return;
    setState(() {
      _reviewsLoading = true;
      _reviewsError = null;
    });
    try {
      final data = await _api.get(
        'stores/${widget.sellerId}/reviews',
        query: {'page': '${more ? _reviewsPage + 1 : 1}'},
      );
      final result = data['reviews'];
      final rows = result is Map ? result['data'] : result;
      if (!mounted) return;
      setState(() {
        final items = (rows as List).cast<Map<String, dynamic>>();
        _reviews = more ? [..._reviews, ...items] : items;
        _reviewsPage = result is Map ? result['current_page'] as int? ?? 1 : 1;
        _reviewsLastPage = result is Map ? result['last_page'] as int? ?? 1 : 1;
      });
    } catch (e) {
      if (mounted) setState(() => _reviewsError = e.toString());
    } finally {
      if (mounted) setState(() => _reviewsLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return BuyerScaffold(
      backgroundColor: AppColors.background,
      leading: BuyerButton.icon(
        icon: const Icon(CupertinoIcons.chevron_left),
        onPressed: () => Navigator.pop(context),
      ),
      titleWidget: BuyerSearch(
        controller: _searchCtrl,
        onSubmitted: (_) => _load(),
        hint: 'Search this store',
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          BuyerButton.icon(
            icon: const Icon(CupertinoIcons.cart),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const CartScreen()),
            ),
          ),
          const SizedBox(width: 4),
        ],
      ),
      child: _loading
          ? const Center(child: CupertinoActivityIndicator())
          : _store == null
          ? const Center(child: Text('Store not found.'))
          : Column(
              children: [
                Offstage(
                  offstage: MediaQuery.viewInsetsOf(context).bottom > 0,
                  child: _storeHeader(context),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 6,
                  ),
                  child: SizedBox(
                    width: double.infinity,
                    child: CupertinoSlidingSegmentedControl<int>(
                      groupValue: _tab,
                      backgroundColor: AppColors.surfaceTint,
                      thumbColor: AppColors.surface,
                      onValueChanged: (value) {
                        if (value != null) _tabController.animateTo(value);
                      },
                      children: const {
                        0: Padding(
                          padding: EdgeInsets.symmetric(vertical: 6),
                          child: Text(
                            'Products',
                            style: TextStyle(fontFamily: 'Inter', fontSize: 13),
                          ),
                        ),
                        1: Padding(
                          padding: EdgeInsets.symmetric(vertical: 6),
                          child: Text(
                            'Reviews',
                            style: TextStyle(fontFamily: 'Inter', fontSize: 13),
                          ),
                        ),
                      },
                    ),
                  ),
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
            height: (64 * MediaQuery.textScalerOf(context).scale(1))
                .clamp(64.0, 96.0)
                .toDouble(),
            width: double.infinity,
            decoration: BoxDecoration(color: AppColors.surfaceTint),
            child: Stack(
              children: [
                Positioned(
                  right: -16,
                  bottom: -22,
                  child: Icon(
                    CupertinoIcons.building_2_fill,
                    size: 80,
                    color: AppColors.primary.withValues(alpha: 0.08),
                  ),
                ),
                Positioned(
                  left: 16,
                  right: 16,
                  bottom: 12,
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
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
                                      fontFamily: 'Fraunces',
                                      color: AppColors.primaryDark,
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
                                color: AppColors.textSecondary,
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
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: MediaQuery.textScalerOf(context).scale(1) > 1.2
                ? Column(
                    children: [
                      Row(
                        children: [
                          _statCell(rating.toStringAsFixed(1), 'Rating'),
                          _statCell('$_followers', 'Followers'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          _statCell('$productCount', 'Products'),
                          _statCell('$totalSold', 'Sold'),
                        ],
                      ),
                    ],
                  )
                : Row(
                    children: [
                      _statCell(rating.toStringAsFixed(1), 'Rating'),
                      _statCell('$_followers', 'Followers'),
                      _statCell('$productCount', 'Products'),
                      _statCell('$totalSold', 'Sold'),
                    ],
                  ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Row(
              children: [
                Expanded(
                  child: BuyerButton.secondary(
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
                      minimumSize: const Size.fromHeight(44),
                    ),
                    icon: _followBusy
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CupertinoActivityIndicator(),
                          )
                        : Icon(
                            _following
                                ? CupertinoIcons.check_mark
                                : CupertinoIcons.add,
                            size: 18,
                          ),
                    label: Text(_following ? 'Following' : 'Follow'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: BuyerButton.secondary(
                    onPressed: () => openSellerChat(
                      context,
                      sellerId: sellerId,
                      subject: 'Inquiry to $name',
                      initialBody:
                          'Hello $name! I have a question about your products.',
                    ),
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(44),
                      foregroundColor: AppColors.primary,
                    ),
                    icon: const Icon(CupertinoIcons.chat_bubble, size: 18),
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
                  Icon(
                    CupertinoIcons.building_2_fill,
                    size: 14,
                    color: AppColors.primary,
                  ),
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
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
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
                child: BuyerActionMenu<String>(
                  initialValue: _sort,
                  onSelected: (v) {
                    setState(() => _sort = v);
                    _load();
                  },
                  icon: const Icon(CupertinoIcons.sort_down, size: 18),
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
              : LayoutBuilder(
                  builder: (context, constraints) {
                    final textScale = MediaQuery.textScalerOf(context).scale(1);
                    final columns = constraints.maxWidth >= 1000
                        ? 4
                        : constraints.maxWidth >= 700
                        ? 3
                        : textScale > 1.2
                        ? 1
                        : 2;
                    final tileWidth =
                        (constraints.maxWidth - 32 - (columns - 1) * 12) /
                        columns;
                    return GridView.builder(
                      padding: const EdgeInsets.all(16),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: columns,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        mainAxisExtent:
                            tileWidth *
                                (constraints.maxWidth < 600 ? .72 : .95) +
                            112 * textScale,
                      ),
                      itemCount:
                          _products.length +
                          (_productsPage < _productsLastPage ? 1 : 0),
                      itemBuilder: (context, i) => i < _products.length
                          ? _productCard(_products[i])
                          : Center(
                              child: BuyerButton.secondary(
                                onPressed: _loadingMore
                                    ? null
                                    : () => _load(more: true),
                                child: Text(
                                  _loadingMore ? 'Loading…' : 'More products',
                                ),
                              ),
                            ),
                    );
                  },
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
    final rawBreakdown = s['rating_breakdown'];
    final breakdown = rawBreakdown is Map
        ? rawBreakdown.map(
            (k, v) => MapEntry(int.parse('$k'), int.tryParse('$v') ?? 0),
          )
        : <int, int>{};
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
                  color: AppColors.textPrimary,
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
                            CupertinoIcons.star_fill,
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
        if (_reviewsLoading) const Center(child: CupertinoActivityIndicator()),
        if (_reviewsError != null) ...[
          Text(_reviewsError!),
          BuyerButton.plain(
            onPressed: _loadReviews,
            child: const Text('Retry reviews'),
          ),
        ],
        if (_reviews.isEmpty && !_reviewsLoading && _reviewsError == null)
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
        if (_reviewsPage < _reviewsLastPage && _reviews.isNotEmpty)
          BuyerButton.plain(
            onPressed: _reviewsLoading ? null : () => _loadReviews(more: true),
            child: const Text('More reviews'),
          ),
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
          Icon(CupertinoIcons.star_fill, size: 13, color: AppColors.gold),
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
    final rating = int.tryParse('${r['rating']}') ?? 0;
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
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(width: 8),
              Row(
                children: List.generate(
                  5,
                  (i) => Icon(
                    CupertinoIcons.star_fill,
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
                  color: AppColors.textPrimary,
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
            Expanded(
              child: Container(
                width: double.infinity,
                color: AppColors.surfaceSoft,
                child: _productImage(p['image'] as String?),
              ),
            ),
            SizedBox(
              height: 112 * MediaQuery.textScalerOf(context).scale(1),
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: 36.4 * MediaQuery.textScalerOf(context).scale(1),
                      child: Text(
                        p['name'] as String,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 14,
                          height: 1.3,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary,
                        ),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _formatPrice(price),
                      style: TextStyle(
                        color: AppColors.primaryDark,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        if (rating != null) ...[
                          Icon(
                            CupertinoIcons.star_fill,
                            color: AppColors.gold,
                            size: 12,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            rating.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
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

  Widget _productImage(String? url) => ProductPhoto(path: url);

  String _formatPrice(double value) {
    final n = value.toStringAsFixed(2);
    final parts = n.split('.');
    return '₱${parts[0]}.${parts[1]}';
  }
}
