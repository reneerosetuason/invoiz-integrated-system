import 'package:flutter/cupertino.dart';
import 'buyer_controls.dart';
import 'package:flutter/material.dart';
import '../models/product.dart';
import '../screens/login_screen.dart';
import '../screens/product_detail_screen.dart';
import '../services/api_service.dart';
import '../services/buyer_updates.dart';
import '../theme.dart';
import 'auth_service_provider.dart';
import 'product_photo.dart';

class ProductCard extends StatefulWidget {
  final Map<String, dynamic> data;
  final ApiService api;
  final VoidCallback? onReturned;
  const ProductCard({
    super.key,
    required this.data,
    required this.api,
    this.onReturned,
  });
  @override
  State<ProductCard> createState() => _ProductCardState();
}

class _ProductCardState extends State<ProductCard> {
  bool _saving = false;
  late bool _favorite;
  @override
  void initState() {
    super.initState();
    _favorite = widget.data['is_favorited'] == true;
  }

  @override
  void didUpdateWidget(ProductCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.data, widget.data))
      _favorite = widget.data['is_favorited'] == true;
  }

  Future<void> _save() async {
    if (!AuthServiceProvider.of(context).isLoggedIn) {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
      );
      widget.onReturned?.call();
      return;
    }
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final response = await widget.api.post('favorites/toggle', {
        'product_id': widget.data['id'],
      });
      if (mounted)
        setState(() {
          _favorite = response['is_favorited'] == true;
          widget.data['is_favorited'] = _favorite;
        });
      BuyerUpdates.wishlistChanged();
    } catch (error) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _open() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ProductDetailScreen(
          productId: widget.data['id'] as int,
          api: widget.api,
        ),
      ),
    );
    if (mounted) widget.onReturned?.call();
  }

  @override
  Widget build(BuildContext context) {
    final product = Product.fromJson(widget.data);
    final textScale = MediaQuery.textScalerOf(context).scale(1);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: _open,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                children: [
                  Positioned.fill(
                    child: ProductPhoto(
                      path: product.displayImages.firstOrNull,
                      label: product.name,
                    ),
                  ),
                  if (product.discountPercent > 0)
                    Positioned(
                      left: 8,
                      top: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.primaryDark,
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '−${product.discountPercent}%',
                          style: const TextStyle(
                            fontFamily: 'Inter',
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  Positioned(
                    right: 3,
                    top: 3,
                    child: BuyerButton.icon(
                      tooltip: _favorite
                          ? 'Remove from wishlist'
                          : 'Save to wishlist',
                      onPressed: _saving ? null : _save,
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white,
                      ),
                      icon: Icon(
                        _favorite
                            ? CupertinoIcons.heart_fill
                            : CupertinoIcons.heart,
                        size: 20,
                        color: _favorite
                            ? AppColors.danger
                            : AppColors.primaryDark,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    height: 36.4 * textScale,
                    child: Text(
                      product.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        height: 1.3,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    product.formattedPrice,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 16,
                      color: AppColors.primaryDark,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      if ((product.rating ?? 0) > 0) ...[
                        const Icon(
                          CupertinoIcons.star_fill,
                          size: 14,
                          color: AppColors.gold,
                        ),
                        const SizedBox(width: 3),
                        Text(
                          product.rating!.toStringAsFixed(1),
                          style: const TextStyle(fontSize: 12),
                        ),
                        const SizedBox(width: 7),
                      ],
                      Expanded(
                        child: Text(
                          product.availableStock == 0
                              ? 'Sold out'
                              : '${product.availableStock} available',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 12,
                            color: product.availableStock == 0
                                ? AppColors.danger
                                : AppColors.textSecondary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
