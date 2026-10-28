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
  final String status;

  Delivery({required this.status});

  factory Delivery.fromJson(Map<String, dynamic> json) =>
      Delivery(status: json['status'] as String);
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
  final List<StatusHistory> statusHistories;
  final double? discount;

  Order({
    required this.id,
    required this.totalAmount,
    required this.status,
    this.notes,
    required this.createdAt,
    required this.items,
    this.payment,
    this.delivery,
    this.statusHistories = const [],
    this.discount,
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
      statusHistories: hist is List
          ? hist
                .whereType<Map<String, dynamic>>()
                .map(StatusHistory.fromJson)
                .toList()
          : const [],
      discount: disc,
    );
  }

  double get subtotal => items.fold(0, (s, i) => s + i.subtotal);
}
