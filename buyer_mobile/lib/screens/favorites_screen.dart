import '../widgets/buyer_controls.dart';
import 'package:flutter/cupertino.dart';
import '../widgets/buyer_page.dart';
import '../widgets/product_photo.dart';
import 'package:flutter/material.dart';
import '../models/product.dart';
import '../services/api_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import '../widgets/inbox_status.dart';
import 'login_screen.dart';
import 'product_detail_screen.dart';
import 'seller_store_screen.dart';

class FavoritesScreen extends StatefulWidget {
  final ApiService? api;
  const FavoritesScreen({super.key, this.api});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late final ApiService _api;
  List<Product> _favorites = [];
  bool _loading = true;
  String? _error;
  final _removing = <int>{};

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
        _favorites = (data['favorites'] as List)
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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _remove(int productId) async {
    if (!_removing.add(productId)) return;
    setState(() {});
    try {
      final result = await _api.post('favorites/toggle', {
        'product_id': productId,
      });
      if (!mounted) return;
      if (result['is_favorited'] == false) {
        setState(() => _favorites.removeWhere((p) => p.id == productId));
      } else {
        await _load();
      }
    } catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      _removing.remove(productId);
      if (mounted) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      return MainLayout(
        showBottomNav: false,
        title: 'Favorites',
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                CupertinoIcons.heart,
                size: 60,
                color: AppColors.textSecondary,
              ),
              const SizedBox(height: 12),
              const Text('Please log in to view your favorites.'),
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
      title: 'Favorites',
      actions: [
        BuyerButton.icon(
          tooltip: 'Refresh favorites',
          onPressed: _loading ? null : _load,
          icon: const Icon(CupertinoIcons.refresh),
        ),
      ],
      child: _loading
          ? const Center(child: CupertinoActivityIndicator())
          : _error != null
          ? InboxStatus(message: _error!, onRetry: _load)
          : _favorites.isEmpty
          ? const BuyerEmptyState(
              icon: CupertinoIcons.heart,
              title: 'No favorites yet.',
              description: 'Tap the heart on a product to save it here.',
            )
          : RefreshIndicator.adaptive(
              onRefresh: _load,
              child: LayoutBuilder(
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
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: columns,
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      mainAxisExtent:
                          tileWidth * (constraints.maxWidth < 600 ? .72 : .95) +
                          116 * textScale,
                    ),
                    itemCount: _favorites.length,
                    itemBuilder: (context, i) => _card(_favorites[i]),
                  );
                },
              ),
            ),
    );
  }

  Widget _card(Product p) {
    return GestureDetector(
      onTap: () =>
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => ProductDetailScreen(api: _api, productId: p.id),
            ),
          ).then((_) {
            if (mounted) _load();
          }),
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
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Container(
                    width: double.infinity,
                    color: AppColors.surfaceSoft,
                    child: ProductPhoto(
                      path: p.displayImages.firstOrNull,
                      label: p.name,
                    ),
                  ),
                  Positioned(
                    top: 4,
                    right: 4,
                    child: GestureDetector(
                      onTap: _removing.contains(p.id)
                          ? null
                          : () => _remove(p.id),
                      child: Container(
                        constraints: const BoxConstraints(
                          minWidth: 44,
                          minHeight: 44,
                        ),
                        padding: const EdgeInsets.all(10),
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          CupertinoIcons.heart_fill,
                          color: AppColors.primaryDark,
                          size: 20,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(
              height: 116 * MediaQuery.textScalerOf(context).scale(1),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(
                      height: 36.4 * MediaQuery.textScalerOf(context).scale(1),
                      child: Text(
                        p.name,
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
                      p.formattedPrice,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                        height: 1.3,
                      ),
                    ),
                    if (p.shop != null)
                      GestureDetector(
                        behavior: HitTestBehavior.opaque,
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                SellerStoreScreen(sellerId: p.shop!.sellerId),
                          ),
                        ),
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(minHeight: 44),
                          child: Row(
                            children: [
                              Icon(
                                CupertinoIcons.building_2_fill,
                                size: 12,
                                color: AppColors.primary,
                              ),
                              const SizedBox(width: 3),
                              Expanded(
                                child: Text(
                                  p.shop!.businessName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: AppColors.primary,
                                    fontWeight: FontWeight.w600,
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
            ),
          ],
        ),
      ),
    );
  }
}
