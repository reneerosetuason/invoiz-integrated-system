class Voucher {
  final int id;
  final String code;
  final String name;
  final String? description;
  final String discountType;
  final double discountValue;
  final double minSpend;
  final double? maxDiscount;
  final double? checkoutSavings;

  Voucher({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    required this.discountType,
    required this.discountValue,
    this.minSpend = 0,
    this.maxDiscount,
    this.checkoutSavings,
  });

  factory Voucher.fromJson(Map<String, dynamic> json) => Voucher(
    id: json['id'] as int,
    code: json['code'] as String? ?? json['name'] as String? ?? 'Store offer',
    name: json['name'] as String,
    description: json['description'] as String?,
    discountType: json['discount_type'] as String,
    discountValue: double.tryParse('${json['discount_value']}') ?? 0,
    minSpend: double.tryParse('${json['min_spend']}') ?? 0,
    maxDiscount: json['max_discount'] != null
        ? double.tryParse('${json['max_discount']}')
        : null,
    checkoutSavings: double.tryParse('${json['checkout_savings']}'),
  );

  String get descriptionText {
    if (discountType == 'free_shipping') {
      return 'Free shipping (min spend ?${minSpend.toStringAsFixed(0)})';
    }
    if (discountType == 'fixed') {
      return '₱${discountValue.toStringAsFixed(0)} off (min spend ₱${minSpend.toStringAsFixed(0)})';
    }
    final cap = maxDiscount != null
        ? ' up to ₱${maxDiscount!.toStringAsFixed(0)}'
        : '';
    return '${discountValue.toStringAsFixed(0)}% off$cap (min spend ₱${minSpend.toStringAsFixed(0)})';
  }
}
