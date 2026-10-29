class Remittance {
  const Remittance({
    required this.id,
    required this.orderId,
    required this.amount,
    required this.status,
    this.deliveryId,
    this.reference,
    this.sentAt,
    this.confirmedAt,
    this.createdAt,
    this.trackingNumber,
    this.deliveryStatus,
  });

  final int id;
  final int orderId;
  final int? deliveryId;
  final double amount;
  final String status;
  final String? reference;
  final DateTime? sentAt;
  final DateTime? confirmedAt;
  final DateTime? createdAt;
  final String? trackingNumber;
  final String? deliveryStatus;

  bool get isPending => status == 'pending';
  bool get isSent => status == 'sent';
  bool get isConfirmed => status == 'confirmed';

  factory Remittance.fromJson(Map<String, dynamic> json) {
    final delivery = json['delivery'] is Map
        ? Map<String, dynamic>.from(json['delivery'] as Map)
        : const <String, dynamic>{};

    return Remittance(
      id: (json['id'] as num?)?.toInt() ?? 0,
      orderId: (json['order_id'] as num?)?.toInt() ?? 0,
      deliveryId: (json['delivery_id'] as num?)?.toInt(),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      status: json['status'] as String? ?? 'pending',
      reference: json['reference'] as String?,
      sentAt: _date(json['sent_at']),
      confirmedAt: _date(json['confirmed_at']),
      createdAt: _date(json['created_at']),
      trackingNumber: delivery['tracking_number'] as String?,
      deliveryStatus: delivery['status'] as String?,
    );
  }

  static DateTime? _date(dynamic value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}

class RemittanceSummary {
  const RemittanceSummary({
    this.totalDue = 0,
    this.totalConfirmed = 0,
    this.pendingCount = 0,
    this.sentCount = 0,
    this.confirmedCount = 0,
  });

  final double totalDue;
  final double totalConfirmed;
  final int pendingCount;
  final int sentCount;
  final int confirmedCount;

  factory RemittanceSummary.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const RemittanceSummary();

    return RemittanceSummary(
      totalDue: (json['total_due'] as num?)?.toDouble() ?? 0,
      totalConfirmed: (json['total_confirmed'] as num?)?.toDouble() ?? 0,
      pendingCount: (json['pending_count'] as num?)?.toInt() ?? 0,
      sentCount: (json['sent_count'] as num?)?.toInt() ?? 0,
      confirmedCount: (json['confirmed_count'] as num?)?.toInt() ?? 0,
    );
  }
}

class RemittancePage {
  const RemittancePage({
    required this.remittances,
    required this.summary,
  });

  final List<Remittance> remittances;
  final RemittanceSummary summary;
}
