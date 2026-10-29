import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import '../models/product.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/buyer_controls.dart';
import '../widgets/buyer_page.dart';
import '../widgets/inbox_status.dart';
import '../widgets/main_layout.dart';
import '../widgets/product_photo.dart';
import 'login_screen.dart';
import 'product_detail_screen.dart';
import 'product_list_screen.dart';

/// The buyer's saved products, mirroring the website wishlist: a grid of
/// saved items with a heart to remove and a one-tap "Add to cart".
class WishlistScreen extends StatefulWidget {
  final ApiService? api;
  const WishlistScreen({super.key, this.api});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  late final ApiService _api;
  List<Product> _items = [];
  bool _loading = true;
  String? _error;
  final _removing = <int>{};
  final _adding = <int>{};

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await _api.get('favorites');
      if (!mounted) return;
      setState(() {
        _items = (data['favorites'] as List)
            .whereType<Map<String, dynamic>>()
            .map((f) => f['product'])
            .whereType<Map<String, dynamic>>()
            .map(Product.fromJson)
            .toList();
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  Future<void> _remove(Product product) async {
    if (!_removing.add(product.id)) return;
    setState(() {});
    try {
      final result = await _api.post('favorites/toggle', {
        'product_id': product.id,
      });
      if (!mounted) return;
      if (result['is_favorited'] == false) {
        setState(() => _items.removeWhere((p) => p.id == product.id));
        BuyerUpdates.wishlistChanged();
        _notify('Removed ${product.name} from your wishlist.');
      } else {
        await _load();
        BuyerUpdates.wishlistChanged();
      }
    } catch (e) {
      if (mounted) _notify(e.toString(), error: true);
    } finally {
      _removing.remove(product.id);
      if (mounted) setState(() {});
    }
  }

  Future<void> _addToCart(Product product) async {
    if (_adding.contains(product.id)) return;
    // Variations need a choice, so hand that over to the product page.
    if (product.variants.isNotEmpty) {
      await _open(product);
      return;
    }
    setState(() => _adding.add(product.id));
    try {
      await _api.post('cart/add', {'product_id': product.id, 'quantity': 1});
      BuyerUpdates.cartChanged();
      if (mounted) _notify('${product.name} added to your cart.');
    } catch (e) {
      if (mounted) _notify(e.toString(), error: true);
    } finally {
      _adding.remove(product.id);
      if (mounted) setState(() {});
    }
  }

  Future<void> _open(Product product) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailScreen(api: _api, productId: product.id),
      ),
    );
    if (mounted) await _load();
  }

  void _notify(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AppColors.danger : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      return MainLayout(
        showBottomNav: false,
        title: 'Wishlist',
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                CupertinoIcons.heart,
                size: 60,
                color: AppColors.textSecondary,
              ),
              const SizedBox(height: 12),
              const Text('Please log in to view your wishlist.'),
              const SizedBox(height: 16),
              BuyerButton.primary(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                ),
                child: const Text('Login'),
              ),
            ],
          ),
        ),
      );
    }
    return MainLayout(
      showBottomNav: false,
      showWishlistIcon: false,
      title: 'Wishlist',
      actions: [
        BuyerButton.icon(
          tooltip: 'Refresh wishlist',
          onPressed: _loading ? null : _load,
          icon: const Icon(CupertinoIcons.refresh),
        ),
      ],
      child: _loading
          ? const Center(child: CupertinoActivityIndicator())
          : _error != null
          ? InboxStatus(message: _error!, onRetry: _load)
          : _items.isEmpty
          ? BuyerEmptyState(
              icon: CupertinoIcons.heart,
              title: 'Your wishlist is empty',
              description:
                  'Save products while browsing and they’ll be collected here.',
              action: BuyerButton.primary(
                onPressed: () => Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const ProductListScreen(showAll: true),
                  ),
                ),
                child: const Text('Browse products'),
              ),
            )
          : RefreshIndicator.adaptive(
              onRefresh: _load,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final textScale = MediaQuery.textScalerOf(context).scale(1);
                  final width = constraints.maxWidth;
                  final columns = width >= 1000
                      ? 4
                      : width >= 700
                      ? 3
                      : textScale > 1.2
                      ? 1
                      : 2;
                  const padding = 16.0;
                  const gap = 12.0;
                  final tileWidth =
                      (width - padding * 2 - (columns - 1) * gap) / columns;
                  final imageHeight = tileWidth * (width < 600 ? .68 : .86);
                  return CustomScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    slivers: [
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
                        sliver: SliverToBoxAdapter(
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Expanded(
                                child: BuyerPageHeading(
                                  title: 'Saved products',
                                  description: 'Your saved items',
                                ),
                              ),
                              const SizedBox(width: 8),
                              _SavedCount(count: _items.length),
                            ],
                          ),
                        ),
                      ),
                      SliverPadding(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                        sliver: SliverGrid(
                          gridDelegate:
                              SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: columns,
                                mainAxisSpacing: gap,
                                crossAxisSpacing: gap,
                                mainAxisExtent:
                                    imageHeight + 70 * textScale + 76,
                              ),
                          delegate: SliverChildBuilderDelegate(
                            (context, i) => _card(_items[i], imageHeight),
                            childCount: _items.length,
                          ),
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
    );
  }

  Widget _card(Product product, double imageHeight) {
    final scale = MediaQuery.textScalerOf(context).scale(1);
    final available =
        (product.status ?? 'active') == 'active' && product.availableStock > 0;
    final removing = _removing.contains(product.id);
    final adding = _adding.contains(product.id);
    return GestureDetector(
      onTap: () => _open(product),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.border),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: imageHeight,
              width: double.infinity,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Container(
                    color: AppColors.surfaceSoft,
                    child: ProductPhoto(
                      path: product.displayImages.firstOrNull,
                      label: product.name,
                    ),
                  ),
                  Positioned(
                    top: 4,
                    right: 4,
                    child: GestureDetector(
                      onTap: removing ? null : () => _remove(product),
                      child: Container(
                        constraints: const BoxConstraints(
                          minWidth: 44,
                          minHeight: 44,
                        ),
                        alignment: Alignment.center,
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                        ),
                        child: removing
                            ? const CupertinoActivityIndicator(radius: 8)
                            : const Icon(
                                CupertinoIcons.heart_fill,
                                color: AppColors.danger,
                                size: 20,
                              ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      (product.category?.name ?? 'Product').toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 10,
                        letterSpacing: .6,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textMuted,
                      ),
                    ),
                    const SizedBox(height: 2),
                    SizedBox(
                      height: 36.4 * scale,
                      child: Text(
                        product.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 14,
                          height: 1.3,
                          fontWeight: FontWeight.w500,
                          color: AppColors.primaryDark,
                        ),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      product.formattedPrice,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                        height: 1.3,
                      ),
                    ),
                    const Spacer(),
                    SizedBox(
                      height: 38,
                      width: double.infinity,
                      child: available
                          ? BuyerButton.primary(
                              onPressed: adding
                                  ? null
                                  : () => _addToCart(product),
                              style: ElevatedButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size.fromHeight(38),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                textStyle: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              child: adding
                                  ? const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CupertinoActivityIndicator(
                                        color: Colors.white,
                                        radius: 8,
                                      ),
                                    )
                                  : const FittedBox(
                                      fit: BoxFit.scaleDown,
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(
                                            CupertinoIcons.cart,
                                            size: 15,
                                            color: Colors.white,
                                          ),
                                          SizedBox(width: 6),
                                          Text('Add to cart'),
                                        ],
                                      ),
                                    ),
                            )
                          : const Center(
                              child: Text(
                                'Unavailable',
                                style: TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ),
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
}

class _SavedCount extends StatelessWidget {
  final int count;
  const _SavedCount({required this.count});
  @override
  Widget build(BuildContext context) => Container(
    key: const ValueKey('wishlist-count'),
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
    decoration: BoxDecoration(
      color: AppColors.paleTeal,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      '$count saved ${count == 1 ? 'product' : 'products'}',
      style: const TextStyle(
        fontFamily: 'Inter',
        fontSize: 12,
        fontWeight: FontWeight.w600,
        color: AppColors.primaryDark,
      ),
    ),
  );
}
