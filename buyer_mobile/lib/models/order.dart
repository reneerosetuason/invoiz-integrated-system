class OrderItem {
  final int id;
  final int productId;
  final String productName;
  final String? variantLabel;
  final int quantity;
  final double price;
  final double subtotal;
  final String? productImage;

  OrderItem({
    required this.id,
    required this.productId,
    required this.productName,
    this.variantLabel,
    required this.quantity,
    required this.price,
    required this.subtotal,
    this.productImage,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
    id: json['id'] as int,
    productId: json['product_id'] as int,
    productName: json['product_name'] as String,
    variantLabel: json['variant_label'] as String?,
    quantity: json['quantity'] as int,
    price: double.tryParse('${json['price']}') ?? 0,
    subtotal: double.tryParse('${json['subtotal']}') ?? 0,
    productImage: json['product_image'] as String?,
  );
}

class Payment {
  final String method;
  final String status;
  final double amount;

  Payment({required this.method, required this.status, required this.amount});

  factory Payment.fromJson(Map<String, dynamic> json) => Payment(
    method: json['method'] as String,
    status: json['status'] as String,
    amount: double.tryParse('${json['amount']}') ?? 0,
  );
}

class Delivery {
  final int? id;
  final String status;
  final String? trackingNumber;
  Delivery({this.id, required this.status, this.trackingNumber});
  bool get canShowOtp =>
      ['out_for_delivery', 'arrived_at_customer'].contains(status);
  factory Delivery.fromJson(Map<String, dynamic> json) => Delivery(
    id: json['id'] as int?,
    status: json['status'] as String? ?? 'pending',
    trackingNumber: json['tracking_number'] as String?,
  );
}

class StatusHistory {
  final String? fromStatus;
  final String toStatus;
  final String? note;
  final String createdAt;

  StatusHistory({
    this.fromStatus,
    required this.toStatus,
    this.note,
    required this.createdAt,
  });

  factory StatusHistory.fromJson(Map<String, dynamic> json) => StatusHistory(
    fromStatus: json['from_status'] as String?,
    toStatus: json['to_status'] as String,
    note: json['note'] as String?,
    createdAt: json['created_at'] as String,
  );
}

class Order {
  final int id;
  final double totalAmount;
  final String status;
  final String? notes;
  final String createdAt;
  final List<OrderItem> items;
  final Payment? payment;
  final Delivery? delivery;
  final List<Delivery> deliveries;
  final List<Map<String, dynamic>> returns;
  final bool? canCancel;
  final List<StatusHistory> statusHistories;
  final double? discount;
  final double? shippingFee;

  Order({
    required this.id,
    required this.totalAmount,
    required this.status,
    this.notes,
    required this.createdAt,
    required this.items,
    this.payment,
    this.delivery,
    this.deliveries = const [],
    this.returns = const [],
    this.canCancel,
    this.statusHistories = const [],
    this.discount,
    this.shippingFee,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    final pay = json['payment'];
    final del = json['delivery'];
    final hist = json['status_histories'];
    final ov = json['order_vouchers'];
    double? disc;
    if (ov is List) {
      disc = ov.fold<double>(0, (sum, v) {
        final d = double.tryParse('${v['discount_amount']}');
        return sum + (d ?? 0);
      });
    }
    return Order(
      id: json['id'] as int,
      totalAmount: double.tryParse('${json['total_amount']}') ?? 0,
      status: json['status'] as String,
      notes: json['notes'] as String?,
      createdAt: json['created_at'] as String,
      items: (json['items'] as List)
          .whereType<Map<String, dynamic>>()
          .map(OrderItem.fromJson)
          .toList(),
      payment: pay is Map<String, dynamic> ? Payment.fromJson(pay) : null,
      delivery: del is Map<String, dynamic> ? Delivery.fromJson(del) : null,
      deliveries: json['deliveries'] is List
          ? (json['deliveries'] as List)
                .whereType<Map<String, dynamic>>()
                .map(Delivery.fromJson)
                .toList()
          : del is Map<String, dynamic>
          ? [Delivery.fromJson(del)]
          : [],
      returns: (json['returns'] as List? ?? [])
          .whereType<Map<String, dynamic>>()
          .toList(),
      canCancel: json['can_cancel'] as bool?,
      statusHistories: hist is List
          ? hist
                .whereType<Map<String, dynamic>>()
                .map(StatusHistory.fromJson)
                .toList()
          : const [],
      discount: disc,
      shippingFee: double.tryParse('${json['shipping_fee']}'),
    );
  }

  double get subtotal => items.fold(0, (s, i) => s + i.subtotal);

  // Stored shipping is already reduced by shipping vouchers. Keep those
  // savings out of the merchandise discount so the displayed totals reconcile.
  double get merchandiseDiscount => shippingFee == null
      ? discount ?? 0
      : (subtotal + shippingFee! - totalAmount).clamp(0.0, subtotal).toDouble();
}
