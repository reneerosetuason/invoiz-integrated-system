import 'product.dart';

class CartItem {
  final int id;
  final int productId;
  final int? variantId;
  final int quantity;
  final double unitPrice;
  final double lineTotal;
  final Product product;
  final ProductVariant? variant;

  CartItem({
    required this.id,
    required this.productId,
    this.variantId,
    required this.quantity,
    required this.unitPrice,
    required this.lineTotal,
    required this.product,
    this.variant,
  });

  factory CartItem.fromJson(Map<String, dynamic> json) {
    final prod = json['product'];
    final varJ = json['variant'];
    return CartItem(
      id: json['id'] as int,
      productId: json['product_id'] as int,
      variantId: json['variant_id'] as int?,
      quantity: json['quantity'] as int,
      unitPrice: double.tryParse('${json['unit_price']}') ?? 0,
      lineTotal: double.tryParse('${json['line_total']}') ?? 0,
      product: prod is Map<String, dynamic>
          ? Product.fromJson(prod)
          : Product(id: json['product_id'] as int, name: 'Product', price: 0),
      variant: varJ is Map<String, dynamic>
          ? ProductVariant.fromJson(varJ)
          : null,
    );
  }

  String get variantLabel => variant != null
      ? '${variant!.variantType}: ${variant!.variantValue}'
      : '';
}

class Cart {
  final int id;
  final double subtotal;
  final List<CartItem> items;

  Cart({required this.id, required this.subtotal, required this.items});

  Cart select(List<int>? selectedIds) {
    if (selectedIds == null) return this;
    final selected = items
        .where((item) => selectedIds.contains(item.id))
        .toList();
    return Cart(
      id: id,
      subtotal: selected.fold<double>(0, (sum, item) => sum + item.lineTotal),
      items: selected,
    );
  }

  factory Cart.fromJson(Map<String, dynamic> json) {
    final c = json['cart'];
    return Cart(
      id: c['id'] as int,
      subtotal: double.tryParse('${c['subtotal']}') ?? 0,
      items: (c['items'] as List)
          .whereType<Map<String, dynamic>>()
          .map(CartItem.fromJson)
          .toList(),
    );
  }
}
