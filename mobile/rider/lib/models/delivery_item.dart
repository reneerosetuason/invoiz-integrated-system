/// An order line item carried on a delivery.
class DeliveryItem {
  const DeliveryItem({
    required this.name,
    this.variantLabel,
    required this.quantity,
    required this.price,
  });

  final String name;
  final String? variantLabel;
  final int quantity;
  final double price;

  double get subtotal => price * quantity;

  factory DeliveryItem.fromJson(Map<String, dynamic> json) {
    return DeliveryItem(
      name: json['name'] as String? ?? '',
      variantLabel: json['variant_label'] as String?,
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      price: (json['price'] as num?)?.toDouble() ?? 0,
    );
  }
}