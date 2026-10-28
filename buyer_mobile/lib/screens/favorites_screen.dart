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
                Icons.favorite_border,
                size: 60,
                color: AppColors.textSecondary,
              ),
              const SizedBox(height: 12),
              const Text('Please log in to view your favorites.'),
              const SizedBox(height: 16),
              ElevatedButton(
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
        IconButton(
          tooltip: 'Refresh favorites',
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? InboxStatus(message: _error!, onRetry: _load)
          : _favorites.isEmpty
          ? const BuyerEmptyState(
              icon: Icons.favorite_border,
              title: 'No favorites yet.',
              description: 'Tap the heart on a product to save it here.',
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: GridView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 14,
                  crossAxisSpacing: 14,
                  mainAxisExtent: 320,
                ),
                itemCount: _favorites.length,
                itemBuilder: (context, i) => _card(_favorites[i]),
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
            Stack(
              children: [
                Container(
                  height: 175,
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
                      padding: const EdgeInsets.all(10),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.favorite,
                        color: AppColors.primaryDark,
                        size: 20,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      p.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontFamily: 'Fraunces',
                        fontSize: 17,
                        fontWeight: FontWeight.w500,
                        color: AppColors.primaryDark,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      p.formattedPrice,
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 4),
                    if (p.shop != null)
                      GestureDetector(
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                SellerStoreScreen(sellerId: p.shop!.sellerId),
                          ),
                        ),
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
                                p.shop!.businessName,
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
