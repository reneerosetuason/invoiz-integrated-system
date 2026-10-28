import 'package:flutter/material.dart';
import '../config.dart';
import '../models/product.dart';
import '../services/api_service.dart';
import '../services/recently_viewed_service.dart';
import '../theme.dart';
import '../widgets/auth_service_provider.dart';
import '../widgets/main_layout.dart';
import 'cart_screen.dart';
import 'chat_screen.dart';
import 'login_screen.dart';
import 'seller_store_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;
  const ProductDetailScreen({super.key, required this.productId});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  final _api = ApiService();
  Product? _product;
  bool _loading = true;
  int _quantity = 1;
  int? _selectedVariant;
  bool _isFavorited = false;
  bool _adding = false;
  int _imageIndex = 0;
  final PageController _pageCtrl = PageController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('products/${widget.productId}');
      setState(() {
        _product = Product.fromJson(data['product'] as Map<String, dynamic>);
        _isFavorited = _product!.isFavorited;
        _loading = false;
      });
      await RecentlyViewedService.record(
        data['product'] as Map<String, dynamic>,
      );
    } catch (e) {
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

  Future<void> _addToCart() async {
    final auth = AuthServiceProvider.of(context);
    if (!auth.isLoggedIn) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please log in first to add items to your cart.'),
        ),
      );
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      return;
    }
    setState(() => _adding = true);
    try {
      await _api.post('cart/add', {
        'product_id': widget.productId,
        'variant_id': _selectedVariant,
        'quantity': _quantity,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('Added to cart.')));
    } on ApiException catch (e) {
      _show(e.message);
    } catch (_) {
      _show('Unable to connect to server.');
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
      setState(() => _isFavorited = r['is_favorited'] as bool);
    } catch (e) {
      _show(e.toString());
    }
  }

  void _show(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red.shade700),
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
              color: _isFavorited ? Colors.red : AppColors.textSecondary,
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
                onPressed: _adding ? null : _addToCart,
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
                onPressed: _adding ? null : _buyNow,
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
    await _addToCart();
    if (!mounted) return;
    final auth = AuthServiceProvider.of(context);
    if (auth.isLoggedIn) {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const CartScreen()),
      );
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
                  color: Color(0xFF212121),
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                p.name,
                style: const TextStyle(
                  fontSize: 15,
                  height: 1.35,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF212121),
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
                        color: Color(0xFF212121),
                      ),
                    ),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    p.sold > 0
                        ? '${p.sold} sold · ${p.stock} left in stock'
                        : '${p.stock} left in stock',
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
                    color: Color(0xFF212121),
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
                  color: Color(0xFF212121),
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
                        color: Color(0xFF212121),
                      ),
                    ),
                  ),
                  _qtyBtn(Icons.add, () {
                    if (_quantity < p.stock) setState(() => _quantity++);
                  }),
                  const SizedBox(width: 12),
                  Text(
                    '${p.stock} available',
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
                  color: Color(0xFF212121),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                p.description ?? 'No description.',
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.6,
                  color: Color(0xFF212121),
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
                  color: Color(0xFF212121),
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

  Widget _gallerySection(BuildContext context, Product p) {
    final images = p.displayImages;
    if (images.isEmpty) {
      return Container(
        height: 280,
        color: AppColors.surfaceSoft,
        child: const Icon(
          Icons.image_not_supported_outlined,
          color: Colors.grey,
          size: 48,
        ),
      );
    }
    return Container(
      height: 330,
      color: AppColors.surfaceSoft,
      child: Column(
        children: [
          Expanded(
            child: Stack(
              children: [
                Positioned.fill(
                  child: PageView.builder(
                    controller: _pageCtrl,
                    itemCount: images.length,
                    onPageChanged: (i) => setState(() => _imageIndex = i),
                    itemBuilder: (context, i) => GestureDetector(
                      onTap: () => _openImage(images, i),
                      child: _image(images[i]),
                    ),
                  ),
                ),
                if (images.length > 1)
                  Positioned(
                    right: 12,
                    bottom: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.55),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        '${_imageIndex + 1}/${images.length}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          if (images.length > 1)
            Container(
              height: 58,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: images.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, i) => GestureDetector(
                  onTap: () {
                    setState(() => _imageIndex = i);
                    _pageCtrl.animateToPage(
                      i,
                      duration: const Duration(milliseconds: 250),
                      curve: Curves.easeOut,
                    );
                  },
                  child: Container(
                    width: 46,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: i == _imageIndex
                            ? AppColors.primary
                            : AppColors.border,
                        width: i == _imageIndex ? 2 : 1,
                      ),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: _image(images[i]),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _openImage(List<String> images, int index) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _ImageViewerScreen(images: images, initialIndex: index),
      ),
    );
  }

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
              color: Color(0xFF212121),
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
                        color: Color(0xFF212121),
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
                color: Color(0xFF212121),
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
                    v.variantValue,
                    style: TextStyle(
                      fontSize: 13,
                      color: selected ? Colors.white : const Color(0xFF212121),
                    ),
                  ),
                  selected: selected,
                  selectedColor: AppColors.primary,
                  backgroundColor: Colors.white,
                  side: BorderSide(
                    color: selected ? AppColors.primary : AppColors.border,
                  ),
                  onSelected: (_) => setState(() => _selectedVariant = v.id),
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
                    color: Color(0xFF212121),
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
                        color: Color(0xFF212121),
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

  Widget _image(String? url) {
    if (url == null || url.isEmpty)
      return const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 48,
      );
    final src = AppConfig.storageUrl(url);
    return Image.network(
      src,
      fit: BoxFit.cover,
      errorBuilder: (_, __, ___) => const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 48,
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
                      color: Color(0xFF212121),
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
                      color: Color(0xFF212121),
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
    _pageCtrl.dispose();
    super.dispose();
  }
}

/// Full-screen swipeable image viewer with zoom support.
class _ImageViewerScreen extends StatefulWidget {
  final List<String> images;
  final int initialIndex;
  const _ImageViewerScreen({required this.images, required this.initialIndex});

  @override
  State<_ImageViewerScreen> createState() => _ImageViewerScreenState();
}

class _ImageViewerScreenState extends State<_ImageViewerScreen> {
  late final PageController _ctrl;
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _ctrl = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(
          children: [
            Positioned.fill(
              child: PageView.builder(
                controller: _ctrl,
                itemCount: widget.images.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) => InteractiveViewer(
                  maxScale: 4,
                  child: Center(child: _viewerImage(widget.images[i])),
                ),
              ),
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: Row(
                children: [
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  ),
                  const Spacer(),
                  Padding(
                    padding: const EdgeInsets.only(right: 16),
                    child: Text(
                      '${_index + 1}/${widget.images.length}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 14,
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
    );
  }

  Widget _viewerImage(String path) {
    if (path.isEmpty)
      return const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 48,
      );
    final src = AppConfig.storageUrl(path);
    return Image.network(
      src,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => const Icon(
        Icons.image_not_supported_outlined,
        color: Colors.grey,
        size: 48,
      ),
    );
  }
}
