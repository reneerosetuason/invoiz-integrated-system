import '../widgets/product_gallery.dart';
import '../widgets/buyer_page.dart';
import '../widgets/shopping_ui.dart';
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
          : Column(
              children: [
                Expanded(child: _build(context)),
                _buyBar(context),
              ],
            ),
    );
  }

  Widget _buyBar(BuildContext context) => ShoppingDock(
    child: LayoutBuilder(
      builder: (context, constraints) {
        final add = OutlinedButton(
          onPressed: _adding || _availableStock == 0 ? null : _addToCart,
          child: _adding
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Add to Cart'),
        );
        final buy = FilledButton(
          onPressed: _adding || _availableStock == 0 ? null : _buyNow,
          child: Text(_availableStock == 0 ? 'Sold out' : 'Buy Now'),
        );
        if (MediaQuery.textScalerOf(context).scale(1) > 1.3) {
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [add, const SizedBox(height: 8), buy],
          );
        }
        return Row(
          children: [
            Expanded(child: add),
            const SizedBox(width: 12),
            Expanded(child: buy),
          ],
        );
      },
    ),
  );

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
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        _gallerySection(context, p),
        const SizedBox(height: 24),
        if (p.brand?.isNotEmpty == true) ...[
          Text(
            p.brand!,
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 8),
        ],
        Text(p.name, style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 12),
        ShoppingPrice(_fmt(_unitPrice), size: 30),
        const SizedBox(height: 12),
        Wrap(
          spacing: 14,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            if (p.rating != null)
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.star, color: AppColors.gold, size: 18),
                  const SizedBox(width: 4),
                  Text(
                    p.rating!.toStringAsFixed(1),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
            if (p.sold > 0) Text('${p.sold} sold'),
            Text('$_availableStock left in stock'),
          ],
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 12,
          runSpacing: 4,
          children: [
            TextButton.icon(
              onPressed: _toggleFavorite,
              icon: Icon(
                _isFavorited ? Icons.favorite : Icons.favorite_border,
                size: 21,
                color: _isFavorited ? AppColors.danger : AppColors.primaryDark,
              ),
              label: Text(
                _isFavorited ? 'Saved to wishlist' : 'Save to wishlist',
              ),
            ),
            TextButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => AuthServiceProvider.of(context).isLoggedIn
                      ? const ChatScreen()
                      : const LoginScreen(),
                ),
              ),
              icon: const Icon(Icons.chat_bubble_outline_rounded, size: 20),
              label: const Text('Chat'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        if (p.variants.isNotEmpty) ...[
          BuyerPanel(
            title: 'Choose an option',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: _buildVariantGroups(p.variants),
            ),
          ),
          const SizedBox(height: 16),
        ],
        BuyerPanel(
          title: 'Quantity',
          child: Wrap(
            spacing: 16,
            runSpacing: 12,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              ShoppingQuantity(
                quantity: _quantity,
                onDecrease: _quantity <= 1
                    ? null
                    : () => setState(() => _quantity--),
                onIncrease: _quantity >= _availableStock || _quantity >= 200
                    ? null
                    : () => setState(() => _quantity++),
              ),
              Text(
                '$_availableStock available',
                style: const TextStyle(fontSize: 14),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _sellerCard(context, p),
        const SizedBox(height: 16),
        BuyerPanel(
          title: 'Product Details',
          child: Text(
            p.description?.isNotEmpty == true
                ? p.description!
                : 'No description provided.',
            style: const TextStyle(
              fontSize: 15,
              height: 1.65,
              color: AppColors.textPrimary,
            ),
          ),
        ),
        _specificationsSection(context, p),
        const SizedBox(height: 16),
        BuyerPanel(title: 'Ratings & Reviews', child: _reviewsSection()),
      ],
    );
  }

  Widget _gallerySection(BuildContext context, Product product) => ClipRRect(
    borderRadius: BorderRadius.circular(20),
    child: ProductGallery(images: product.displayImages, label: product.name),
  );

  Widget _specificationsSection(BuildContext context, Product p) {
    final specs = <(String, String)>[
      if (p.brand?.isNotEmpty == true) ('Brand', p.brand!),
      if (p.model?.isNotEmpty == true) ('Model', p.model!),
      if (p.sku?.isNotEmpty == true) ('SKU', p.sku!),
      if (p.material?.isNotEmpty == true) ('Material', p.material!),
      if (p.dimensions?.isNotEmpty == true) ('Dimensions', p.dimensions!),
      if (p.weight?.isNotEmpty == true) ('Weight', p.weight!),
      if (p.warranty?.isNotEmpty == true) ('Warranty', p.warranty!),
      if (p.origin?.isNotEmpty == true) ('Origin', p.origin!),
      if (p.category != null) ('Category', p.category!.name),
    ];
    if (specs.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: BuyerPanel(
        title: 'Specifications',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: specs
              .map((spec) => ShoppingValueRow(spec.$1, spec.$2))
              .toList(),
        ),
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
                      fontSize: 14,
                      color: selected ? Colors.white : AppColors.textPrimary,
                    ),
                  ),
                  selected: selected,
                  selectedColor: AppColors.primaryDark,
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

  Widget _sellerCard(BuildContext context, Product p) {
    final shop = p.shop;
    if (shop == null) return const SizedBox.shrink();
    return BuyerPanel(
      title: 'Sold by',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: AppColors.paleTeal,
                foregroundColor: AppColors.primaryDark,
                radius: 24,
                child: Text(
                  shop.initial,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shop.businessName,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 12,
                      runSpacing: 4,
                      children: [
                        Text('${shop.rating.toStringAsFixed(1)} store rating'),
                        Text('${shop.productCount} products'),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          OutlinedButton(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => SellerStoreScreen(sellerId: shop.sellerId),
              ),
            ),
            child: const Text('Visit Store'),
          ),
        ],
      ),
    );
  }

  String _fmt(double value) => shoppingMoney(value);

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
              Wrap(
                spacing: 8,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
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
                    mainAxisSize: MainAxisSize.min,
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
                      fontSize: 15,
                      height: 1.6,
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
