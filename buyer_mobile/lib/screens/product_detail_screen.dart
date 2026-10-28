import '../widgets/product_gallery.dart';
import '../services/buyer_updates.dart';
import 'checkout_screen.dart';
import 'package:flutter/material.dart';
import '../models/product.dart';
import '../services/api_service.dart';
import '../services/recently_viewed_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import 'chat_screen.dart';
import 'login_screen.dart';
import 'seller_store_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;
  final ApiService? api;
  const ProductDetailScreen({super.key, required this.productId, this.api});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late final ApiService _api;
  Product? _product;
  bool _loading = true;
  int _quantity = 1;
  int? _selectedVariant;
  bool _isFavorited = false;
  bool _adding = false;

  @override
  void initState() {
    super.initState();
    _api = widget.api ?? ApiService();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('products/${widget.productId}');
      if (!mounted) return;
      setState(() {
        _product = Product.fromJson(data['product'] as Map<String, dynamic>);
        _isFavorited = _product!.isFavorited;
        _selectedVariant = _product!.variants
            .where((v) => v.isAvailable)
            .firstOrNull
            ?.id;
        _loading = false;
      });
      await RecentlyViewedService.record(
        data['product'] as Map<String, dynamic>,
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  double get _unitPrice {
    final base = _product?.price ?? 0;
    ProductVariant? v;
    if (_selectedVariant != null && _product != null) {
      for (final vt in _product!.variants) {
        if (vt.id == _selectedVariant) v = vt;
      }
    }
    return base + (v?.priceAdjustment ?? 0);
  }

  int get _availableStock {
    final product = _product;
    if (product == null) return 0;
    if (product.variants.isEmpty) return product.stock;
    return product.variants
            .where((v) => v.id == _selectedVariant && v.isAvailable)
            .firstOrNull
            ?.stock ??
        0;
  }

  Future<bool> _addToCart({bool showFeedback = true}) async {
    if (_adding || _product == null) return false;
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      return false;
    }
    if (_availableStock < 1 || _quantity > _availableStock || _quantity > 200) {
      _show('Choose an available option and quantity.');
      return false;
    }
    setState(() => _adding = true);
    try {
      await _api.post('cart/add', {
        'product_id': widget.productId,
        'variant_id': _selectedVariant,
        'quantity': _quantity,
      });
      BuyerUpdates.cartChanged();
      if (!mounted) return false;
      if (showFeedback)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Added to your cart.')));
      return true;
    } catch (error) {
      if (mounted) _show(error.toString());
      return false;
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  Future<void> _toggleFavorite() async {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please log in to save favorites.')),
      );
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      return;
    }
    try {
      final r = await _api.post('favorites/toggle', {
        'product_id': widget.productId,
      });
      if (!mounted) return;
      setState(() => _isFavorited = r['is_favorited'] as bool);
    } catch (e) {
      _show(e.toString());
    }
  }

  void _show(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: AppColors.danger),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MainLayout(
      showBottomNav: false,
      title: 'Product',
      child: _loading
          ? const Center(child: CircularProgressIndicator())
          : _product == null
          ? const Center(child: Text('Product not found.'))
          : Stack(
              children: [
                _build(context),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: _buyBar(context),
                ),
              ],
            ),
    );
  }

  Widget _buyBar(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.card,
        border: Border(top: BorderSide(color: AppColors.border)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      child: SafeArea(
        child: Row(
          children: [
            _buyBarIcon(
              icon: _isFavorited ? Icons.favorite : Icons.favorite_border,
              color: _isFavorited ? AppColors.danger : AppColors.textSecondary,
              label: 'Wishlist',
              onTap: _toggleFavorite,
            ),
            const SizedBox(width: 4),
            _buyBarIcon(
              icon: Icons.chat_bubble_outline_rounded,
              color: AppColors.textSecondary,
              label: 'Chat',
              onTap: () {
                final auth = AuthServiceProvider.of(context);
                if (!auth.isLoggedIn) {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  );
                  return;
                }
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ChatScreen()),
                );
              },
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton(
                onPressed: _adding || _availableStock == 0 ? null : _addToCart,
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(double.infinity, 46),
                  side: BorderSide(color: AppColors.primary, width: 1.2),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: _adding
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Text(
                        'Add to Cart',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton(
                onPressed: _adding || _availableStock == 0 ? null : _buyNow,
                style: ElevatedButton.styleFrom(
                  minimumSize: const Size(double.infinity, 46),
                  backgroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  elevation: 0,
                ),
                child: const Text(
                  'Buy Now',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buyBarIcon({
    required IconData icon,
    required Color color,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                color: color,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _buyNow() async {
    if (!await _addToCart(showFeedback: false) || !mounted) return;
    setState(() => _adding = true);
    try {
      final result = await _api.get('cart');
      final items = result['cart']['items'] as List;
      final line = items
          .where(
            (item) =>
                item['product_id'] == widget.productId &&
                item['variant_id'] == _selectedVariant,
          )
          .firstOrNull;
      if (line == null)
        throw Exception('The selected item could not be found in your cart.');
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(cartItemIds: [line['id'] as int]),
        ),
      );
    } catch (error) {
      if (mounted) _show(error.toString());
    } finally {
      if (mounted) setState(() => _adding = false);
    }
  }

  Widget _build(BuildContext context) {
    final p = _product!;
    return ListView(
      children: [
        _gallerySection(context, p),
        Container(
          width: double.infinity,
          color: Colors.white,
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _fmt(_unitPrice),
                style: const TextStyle(
                  color: AppColors.textPrimary,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                p.name,
                style: const TextStyle(
                  fontFamily: 'Fraunces',
                  fontSize: 15,
                  height: 1.35,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  if (p.rating != null) ...[
                    Icon(Icons.star, color: AppColors.gold, size: 16),
                    const SizedBox(width: 3),
                    Text(
                      p.rating!.toStringAsFixed(1),
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    p.sold > 0
                        ? '${p.sold} sold · ${_availableStock} left in stock'
                        : '${_availableStock} left in stock',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        _sellerCard(context, p),
        if (p.variants.isNotEmpty)
          Container(
            margin: const EdgeInsets.only(top: 6),
            color: AppColors.card,
            padding: const EdgeInsets.all(10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Variations',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 10),
                ..._buildVariantGroups(p.variants),
              ],
            ),
          ),
        Container(
          margin: const EdgeInsets.only(top: 6),
          color: AppColors.card,
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Quantity',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  _qtyBtn(Icons.remove, () {
                    if (_quantity > 1) setState(() => _quantity--);
                  }),
                  Container(
                    width: 44,
                    height: 36,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: AppColors.background,
                      border: Border.symmetric(
                        horizontal: BorderSide(color: AppColors.border),
                      ),
                    ),
                    child: Text(
                      '$_quantity',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  _qtyBtn(Icons.add, () {
                    if (_quantity < _availableStock && _quantity < 200)
                      setState(() => _quantity++);
                  }),
                  const SizedBox(width: 12),
                  Text(
                    '${_availableStock} available',
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        Container(
          margin: const EdgeInsets.only(top: 6),
          color: AppColors.card,
          padding: const EdgeInsets.all(10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Product Details',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                p.description ?? 'No description.',
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.6,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
        ),
        _specificationsSection(context, p),
        Container(
          margin: const EdgeInsets.only(top: 8, bottom: 80),
          color: AppColors.card,
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Ratings & Reviews',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              _reviewsSection(),
            ],
          ),
        ),
      ],
    );
  }

  Widget _gallerySection(BuildContext context, Product product) =>
      ProductGallery(images: product.displayImages, label: product.name);

  Widget _specificationsSection(BuildContext context, Product p) {
    final specs = <(String, String)>[
      if (p.brand != null && p.brand!.isNotEmpty) ('Brand', p.brand!),
      if (p.model != null && p.model!.isNotEmpty) ('Model', p.model!),
      if (p.sku != null && p.sku!.isNotEmpty) ('SKU', p.sku!),
      if (p.material != null && p.material!.isNotEmpty)
        ('Material', p.material!),
      if (p.dimensions != null && p.dimensions!.isNotEmpty)
        ('Dimensions', p.dimensions!),
      if (p.weight != null && p.weight!.isNotEmpty) ('Weight', p.weight!),
      if (p.warranty != null && p.warranty!.isNotEmpty)
        ('Warranty', p.warranty!),
      if (p.origin != null && p.origin!.isNotEmpty) ('Origin', p.origin!),
      if (p.category != null) ('Category', p.category!.name),
    ];
    if (specs.isEmpty) return const SizedBox.shrink();
    return Container(
      margin: const EdgeInsets.only(top: 6),
      color: AppColors.card,
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Specifications',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.bold,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 10),
          ...specs.map(
            (s) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 7),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: 100,
                    child: Text(
                      s.$1,
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      s.$2,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _buildVariantGroups(List<ProductVariant> variants) {
    // Group by variant_type.
    final groups = <String, List<ProductVariant>>{};
    for (final v in variants) {
      groups.putIfAbsent(v.variantType, () => []).add(v);
    }
    return groups.entries.map((entry) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${entry.key}:',
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: entry.value.map((v) {
                final selected = _selectedVariant == v.id;
                return ChoiceChip(
                  label: Text(
                    v.isAvailable
                        ? v.variantValue
                        : '${v.variantValue} · Sold out',
                    style: TextStyle(
                      fontSize: 13,
                      color: selected ? Colors.white : AppColors.textPrimary,
                    ),
                  ),
                  selected: selected,
                  selectedColor: AppColors.primary,
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: selected ? AppColors.primary : AppColors.border,
                  ),
                  onSelected: !v.isAvailable
                      ? null
                      : (_) => setState(() {
                          _selectedVariant = v.id;
                          _quantity = _quantity.clamp(
                            1,
                            v.stock < 200 ? v.stock : 200,
                          );
                        }),
                );
              }).toList(),
            ),
          ],
        ),
      );
    }).toList();
  }

  Widget _qtyBtn(IconData icon, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: AppColors.background,
          border: Border.all(color: AppColors.border),
        ),
        child: Icon(icon, size: 18),
      ),
    );
  }

  Widget _sellerCard(BuildContext context, Product p) {
    final shop = p.shop;
    if (shop == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(top: 6),
      color: AppColors.card,
      padding: const EdgeInsets.all(10),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [AppColors.primary, AppColors.primaryDark],
              ),
              shape: BoxShape.circle,
            ),
            alignment: Alignment.center,
            child: Text(
              shop.initial,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  shop.businessName,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    Icon(Icons.star, color: AppColors.gold, size: 14),
                    const SizedBox(width: 2),
                    Text(
                      shop.rating.toStringAsFixed(1),
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      '${shop.productCount} products',
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            children: [
              OutlinedButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => SellerStoreScreen(sellerId: shop.sellerId),
                  ),
                ),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(96, 38),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                ),
                child: const Text('Visit Store'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _fmt(double v) {
    final n = v.toStringAsFixed(2);
    return '₱${n.split('.')[0]}.${n.split('.')[1]}';
  }

  Widget _reviewsSection() {
    final reviews = _product!.reviews;
    if (reviews.isEmpty) {
      return Text(
        'No reviews yet.',
        style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: reviews.map((r) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    r.buyerName ?? 'Anonymous',
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
                        Icons.star,
                        size: 14,
                        color: i < r.rating ? AppColors.gold : AppColors.border,
                      ),
                    ),
                  ),
                ],
              ),
              if (r.comment != null && r.comment!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    r.comment!,
                    style: const TextStyle(
                      fontSize: 13,
                      height: 1.4,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
            ],
          ),
        );
      }).toList(),
    );
  }

  @override
  void dispose() {
    super.dispose();
  }
}
