/// Earnings summary returned by `/api/rider/earnings`.
class EarningsSummary {
  const EarningsSummary({
    required this.today,
    required this.thisWeek,
    required this.thisMonth,
    required this.history,
  });

  final double today;
  final double thisWeek;
  final double thisMonth;
  final List<EarningsDay> history;

  factory EarningsSummary.fromJson(Map<String, dynamic> json) {
    List<EarningsDay> history = [];
    if (json['history'] is List) {
      history = (json['history'] as List)
          .whereType<Map<String, dynamic>>()
          .map(EarningsDay.fromJson)
          .toList();
    }
    return EarningsSummary(
      today: (json['today'] as num?)?.toDouble() ?? 0,
      thisWeek: (json['this_week'] as num?)?.toDouble() ?? 0,
      thisMonth: (json['this_month'] as num?)?.toDouble() ?? 0,
      history: history,
    );
  }
}

/// A single day's earnings.
class EarningsDay {
  const EarningsDay({
    required this.date,
    required this.amount,
    required this.deliveries,
  });

  final DateTime date;
  final double amount;
  final int deliveries;

  factory EarningsDay.fromJson(Map<String, dynamic> json) {
    return EarningsDay(
      date: DateTime.tryParse(json['date']?.toString() ?? '')?.toLocal() ??
          DateTime.now(),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      deliveries: (json['deliveries'] as num?)?.toInt() ?? 0,
    );
  }
}

/// A row in the delivery history screen.
class HistoryEntry {
  const HistoryEntry({
    required this.id,
    required this.trackingNumber,
    required this.shopName,
    required this.customerName,
    this.deliveredAt,
    required this.status,
    required this.statusLabel,
    this.paymentMethod,
    this.total,
    this.earned,
  });

  final int id;
  final String trackingNumber;
  final String shopName;
  final String customerName;
  final DateTime? deliveredAt;
  final String status;
  final String statusLabel;
  final String? paymentMethod;
  final double? total;
  final double? earned;

  factory HistoryEntry.fromJson(Map<String, dynamic> json) {
    return HistoryEntry(
      id: (json['id'] as num?)?.toInt() ?? 0,
      trackingNumber: json['tracking_number'] as String? ?? '',
      shopName: json['shop_name'] as String? ?? '',
      customerName: json['customer_name'] as String? ?? '',
      deliveredAt: json['delivered_at'] != null
          ? DateTime.tryParse(json['delivered_at'].toString())?.toLocal()
          : null,
      status: json['status'] as String? ?? '',
      statusLabel: json['status_label'] as String? ?? '',
      paymentMethod: json['payment_method'] as String?,
      total: (json['total'] as num?)?.toDouble(),
      earned: (json['earned'] as num?)?.toDouble(),
    );
  }
}