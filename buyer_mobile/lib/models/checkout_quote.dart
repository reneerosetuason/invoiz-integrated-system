class CheckoutQuote {
  final double subtotal;
  final double shippingFee;
  final double discount;
  final double shippingDiscount;
  final double total;

  const CheckoutQuote({
    required this.subtotal,
    required this.shippingFee,
    required this.discount,
    required this.shippingDiscount,
    required this.total,
  });

  factory CheckoutQuote.fromJson(Map<String, dynamic> json) {
    double amount(String key) => double.tryParse('${json[key]}') ?? 0;
    return CheckoutQuote(
      subtotal: amount('subtotal'),
      shippingFee: amount('shipping_fee'),
      discount: amount('discount'),
      shippingDiscount: amount('shipping_discount'),
      total: amount('total'),
    );
  }
}
