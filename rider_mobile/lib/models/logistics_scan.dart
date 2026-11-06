Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : const <String, dynamic>{};

String _text(dynamic value) => value == null ? '' : value.toString();

String? _time(dynamic value) {
  final raw = _text(value);
  if (raw.isEmpty) return null;
  return DateTime.tryParse(raw)?.toLocal().toString();
}

String _hubName(dynamic value) {
  final map = _map(value);
  return _text(map['name']);
}

/// Parcel snapshot shown after a successful lookup.
class ScanParcel {
  const ScanParcel({
    required this.trackingNumber,
    required this.recipientName,
    required this.recipientAddress,
    required this.parcelStatus,
    required this.status,
    required this.currentHub,
    required this.destinationHub,
    required this.serviceArea,
    required this.weight,
    this.scannedAt,
  });

  final String trackingNumber;
  final String recipientName;
  final String recipientAddress;
  final String parcelStatus;
  final String status;
  final String currentHub;
  final String destinationHub;
  final String serviceArea;
  final String weight;
  final String? scannedAt;

  factory ScanParcel.fromJson(dynamic raw) {
    final map = _map(raw);
    return ScanParcel(
      trackingNumber: _text(map['tracking_number']),
      recipientName: _text(map['recipient_name']),
      recipientAddress: _text(map['recipient_address']),
      parcelStatus: _text(map['parcel_status']),
      status: _text(map['status']),
      currentHub: _hubName(map['current_hub']),
      destinationHub: _hubName(map['destination_hub']),
      serviceArea: _text(map['service_area']),
      weight: _text(map['weight']),
      scannedAt: _time(map['scanned_at']),
    );
  }

  String get statusLabel => parcelStatus.isEmpty
      ? 'Unknown'
      : parcelStatus.replaceAll('_', ' ');
}

class ScanHistoryEntry {
  const ScanHistoryEntry({
    required this.status,
    required this.notes,
    required this.location,
    required this.actor,
    this.createdAt,
  });

  final String status;
  final String notes;
  final String location;
  final String actor;
  final String? createdAt;

  factory ScanHistoryEntry.fromJson(dynamic raw) {
    final map = _map(raw);
    return ScanHistoryEntry(
      status: _text(map['status']),
      notes: _text(map['notes']),
      location: _text(map['location']),
      actor: _text(map['actor']),
      createdAt: _time(map['created_at']),
    );
  }
}

/// Result of a lookup or a confirm call. Error statuses keep the parcel
/// details the backend returned so the operator still sees the current hub
/// and parcel status instead of a bare message.
class LogisticsScanOutcome {
  const LogisticsScanOutcome({
    required this.ok,
    required this.eligible,
    required this.already,
    required this.message,
    this.error,
    this.parcel,
    this.history = const [],
    this.facilityName = '',
  });

  final bool ok;
  final bool eligible;
  final bool already;
  final String message;
  final String? error;
  final ScanParcel? parcel;
  final List<ScanHistoryEntry> history;
  final String facilityName;

  /// True only for a completed scan. An eligible lookup is `ok` too, but it
  /// has `eligible: true`; the backend always answers a confirm with
  /// `eligible: false`.
  bool get confirmed => ok && !already && !eligible;

  factory LogisticsScanOutcome.fromJson(dynamic raw, {int? statusCode}) {
    final map = _map(raw);
    final historyRaw = map['history'];
    final facility = _map(map['facility']);
    return LogisticsScanOutcome(
      ok: map['ok'] == true && (statusCode == null || statusCode < 400),
      eligible: map['eligible'] == true,
      already: map['already'] == true,
      message: _text(map['message']),
      error: map['error'] == null ? null : _text(map['error']),
      parcel: map['delivery'] is Map ? ScanParcel.fromJson(map['delivery']) : null,
      history: historyRaw is List
          ? historyRaw
                .whereType<Map>()
                .map((entry) => ScanHistoryEntry.fromJson(entry))
                .toList()
          : const [],
      facilityName: _text(facility['name']),
    );
  }
}
