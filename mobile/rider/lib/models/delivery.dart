import 'customer.dart';
import 'delivery_item.dart';
import 'shop.dart';

/// A delivery/shipment assigned to the rider.
class Delivery {
  const Delivery({
    required this.id,
    required this.trackingNumber,
    this.orderId,
    required this.status,
    required this.statusLabel,
    required this.shop,
    required this.customer,
    required this.items,
    required this.subtotal,
    this.deliveryFee,
    required this.total,
    this.paymentMethod,
    this.amountToCollect,
    this.otpRequired = false,
    this.pickupPinRequired = false,
    this.notes,
    this.weight,
    this.assignedAt,
    this.acceptedAt,
    this.pickedUpAt,
    this.deliveredAt,
    this.failedAt,
    this.failureReason,
    this.reattemptRequestedAt,
    this.returnedAt,
    this.returnReason,
    this.proofType,
    this.statusLogs = const [],
    this.parcelStatus,
    this.sortingCenterHandoffAt,
    this.sortingCenterHandoffRiderId,
    this.sortingCenterPickupAt,
    this.sortingCenterPickupRiderId,
    this.handlingCenterName,
    this.destinationCenterName,
  });

  final int id;
  final String trackingNumber;
  final int? orderId;
  final String status;
  final String statusLabel;
  final Shop shop;
  final Customer customer;
  final List<DeliveryItem> items;
  final double subtotal;
  final double? deliveryFee;
  final double total;
  final String? paymentMethod;
  final double? amountToCollect;
  final bool otpRequired;
  final bool pickupPinRequired;
  final String? notes;
  final String? weight;
  final DateTime? assignedAt;
  final DateTime? acceptedAt;
  final DateTime? pickedUpAt;
  final DateTime? deliveredAt;
  final DateTime? failedAt;
  final String? failureReason;
  final DateTime? reattemptRequestedAt;
  final DateTime? returnedAt;
  final String? returnReason;
  final String? proofType;

  /// A re-attempt was requested and is awaiting Logistics review.
  bool get hasReattemptRequest => reattemptRequestedAt != null;

  /// The parcel was returned to the sorting center.
  bool get hasCenterReturn => returnedAt != null;
  final List<StatusLog> statusLogs;
  final String? parcelStatus;
  final DateTime? sortingCenterHandoffAt;
  final int? sortingCenterHandoffRiderId;
  final DateTime? sortingCenterPickupAt;
  final int? sortingCenterPickupRiderId;

  /// Origin/handling center name when the backend includes it. Shown in
  /// sorting-center confirmations so the rider knows which center holds
  /// the parcel. Null when the payload omits the center objects.
  final String? handlingCenterName;
  final String? destinationCenterName;

  bool get isCashOnDelivery => paymentMethod == 'cash_on_delivery';

  bool get isActive => const [
    'assigned',
    'accepted',
    'going_to_pickup',
    'arrived_at_shop',
    'picked_up',
    'out_for_delivery',
    'arrived_at_customer',
  ].contains(status);

  /// Statuses that belong in the rider-facing Status History.
  ///
  /// Logistics parcel events (waiting_for_rider, received, scanned, sorted,
  /// archived, restored) are real records but are not rider workflow steps,
  /// so they are excluded here. Only actually recorded events are shown —
  /// never a predefined list of future statuses.
  static const riderHistoryStatuses = {
    'assigned',
    'accepted',
    'going_to_pickup',
    'arrived_at_shop',
    'picked_up',
    'sorting_center_handoff',
    'sorting_center_pickup',
    'out_for_delivery',
    'arrived_at_customer',
    'delivered',
    'delivery_failed',
    'cancelled',
  };

  /// Whether parcel has been handed over to sorting center by the pickup rider.
  bool get hasSortingHandoff => sortingCenterHandoffAt != null;

  /// Whether parcel has been picked up from sorting center by delivery rider.
  bool get hasSortingPickup => sortingCenterPickupAt != null;

  factory Delivery.fromJson(Map<String, dynamic> json) {
    List<DeliveryItem> items = [];
    if (json['items'] is List) {
      items = (json['items'] as List)
          .whereType<Map<String, dynamic>>()
          .map(DeliveryItem.fromJson)
          .toList();
    }

    List<StatusLog> logs = [];
    if (json['status_logs'] is List) {
      logs =
          (json['status_logs'] as List)
              .whereType<Map<String, dynamic>>()
              .map(StatusLog.fromJson)
              .where((log) => riderHistoryStatuses.contains(log.status))
              .toList()
            // Chronological (oldest first) by actual record timestamp.
            ..sort((a, b) {
              final ac = a.createdAt;
              final bc = b.createdAt;
              if (ac == null && bc == null) return 0;
              if (ac == null) return 1;
              if (bc == null) return -1;
              return ac.compareTo(bc);
            });
    }

    String? proofType;
    if (json['proof'] is Map<String, dynamic>) {
      proofType = json['proof']['type'] as String?;
    }

    return Delivery(
      id: (json['id'] as num?)?.toInt() ?? 0,
      trackingNumber: json['tracking_number'] as String? ?? '',
      orderId: (json['order_id'] as num?)?.toInt(),
      status: json['status'] as String? ?? 'waiting_for_rider',
      statusLabel: json['status_label'] as String? ?? '',
      shop: Shop.fromJson((json['shop'] as Map<String, dynamic>?) ?? const {}),
      customer: Customer.fromJson(
        (json['customer'] as Map<String, dynamic>?) ?? const {},
      ),
      items: items,
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble(),
      total: (json['total'] as num?)?.toDouble() ?? 0,
      paymentMethod: json['payment_method'] as String?,
      amountToCollect: (json['amount_to_collect'] as num?)?.toDouble(),
      otpRequired: json['otp_required'] as bool? ?? (json['order_id'] != null),
      pickupPinRequired: json['pickup_pin_required'] as bool? ?? false,
      notes: json['notes'] as String?,
      weight: json['weight']?.toString(),
      assignedAt: _parseDate(json['assigned_at']),
      acceptedAt: _parseDate(json['accepted_at']),
      pickedUpAt: _parseDate(json['picked_up_at']),
      deliveredAt: _parseDate(json['delivered_at']),
      failedAt: _parseDate(json['failed_at']),
      failureReason: json['failure_reason'] as String?,
      reattemptRequestedAt: _parseDate(json['reattempt_requested_at']),
      returnedAt: _parseDate(json['returned_at']),
      returnReason: json['return_reason'] as String?,
      proofType: proofType,
      statusLogs: logs,
      parcelStatus: json['parcel_status'] as String?,
      sortingCenterHandoffAt: _parseDate(json['sorting_center_handoff_at']),
      sortingCenterHandoffRiderId:
          (json['sorting_center_handoff_rider_id'] as num?)?.toInt(),
      sortingCenterPickupAt: _parseDate(json['sorting_center_pickup_at']),
      sortingCenterPickupRiderId:
          (json['sorting_center_pickup_rider_id'] as num?)?.toInt(),
      handlingCenterName: (json['logistics_center'] is Map<String, dynamic>)
          ? (json['logistics_center'] as Map<String, dynamic>)['name']
                as String?
          : null,
      destinationCenterName:
          (json['destination_center'] is Map<String, dynamic>)
          ? (json['destination_center'] as Map<String, dynamic>)['name']
                as String?
          : null,
    );
  }

  static DateTime? _parseDate(dynamic value) {
    if (value == null) return null;
    return DateTime.tryParse(value.toString())?.toLocal();
  }

  Delivery copyWith({String? status, String? statusLabel}) {
    return Delivery(
      id: id,
      trackingNumber: trackingNumber,
      orderId: orderId,
      status: status ?? this.status,
      statusLabel: statusLabel ?? this.statusLabel,
      shop: shop,
      customer: customer,
      items: items,
      subtotal: subtotal,
      deliveryFee: deliveryFee,
      total: total,
      paymentMethod: paymentMethod,
      amountToCollect: amountToCollect,
      otpRequired: otpRequired,
      pickupPinRequired: pickupPinRequired,
      notes: notes,
      weight: weight,
      assignedAt: assignedAt,
      acceptedAt: acceptedAt,
      pickedUpAt: pickedUpAt,
      deliveredAt: deliveredAt,
      failedAt: failedAt,
      failureReason: failureReason,
      reattemptRequestedAt: reattemptRequestedAt,
      returnedAt: returnedAt,
      returnReason: returnReason,
      proofType: proofType,
      statusLogs: statusLogs,
      parcelStatus: parcelStatus,
      sortingCenterHandoffAt: sortingCenterHandoffAt,
      sortingCenterHandoffRiderId: sortingCenterHandoffRiderId,
      sortingCenterPickupAt: sortingCenterPickupAt,
      sortingCenterPickupRiderId: sortingCenterPickupRiderId,
      handlingCenterName: handlingCenterName,
      destinationCenterName: destinationCenterName,
    );
  }
}

/// A single entry in the delivery's status history.
class StatusLog {
  const StatusLog({required this.status, this.notes, this.createdAt});

  final String status;
  final String? notes;
  final DateTime? createdAt;

  factory StatusLog.fromJson(Map<String, dynamic> json) {
    return StatusLog(
      status: json['status'] as String? ?? '',
      notes: json['notes'] as String?,
      createdAt: Delivery._parseDate(json['created_at']),
    );
  }
}
