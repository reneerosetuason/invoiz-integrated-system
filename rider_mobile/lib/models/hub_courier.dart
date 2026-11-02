Map<String, dynamic>? asJsonMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : null;

Map<String, dynamic> asJsonMapOrEmpty(dynamic value) =>
    asJsonMap(value) ?? const <String, dynamic>{};

/// Returns only the map entries of a JSON list. A response shape that drifted
/// from the contract must not crash the dashboard build; unknown entries are
/// skipped instead.
List<Map<String, dynamic>> asJsonMapList(dynamic value) => value is List
    ? value.map(asJsonMap).whereType<Map<String, dynamic>>().toList()
    : const <Map<String, dynamic>>[];

double asJsonDouble(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}

int asJsonInt(dynamic value, [int fallback = 0]) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    return int.tryParse(value) ?? double.tryParse(value)?.toInt() ?? fallback;
  }
  return fallback;
}

String asJsonString(dynamic value, [String fallback = '']) =>
    value == null ? fallback : value.toString();

bool asJsonBool(dynamic value) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    return value == '1' || value.toLowerCase() == 'true';
  }
  return false;
}

class HubCourier {
  const HubCourier({
    required this.id,
    required this.name,
    required this.company,
    required this.online,
    this.email = '',
    this.phone = '',
    this.licenseNumber,
    this.avatar,
    this.status = '',
    this.vehicleArrangement = 'company',
    this.verificationStatus,
    this.verificationNotes,
    this.hasAssignedTruck = false,
    this.vehicle,
  });

  final int id;
  final String name;
  final String company;
  final String email;
  final String phone;
  final String? licenseNumber;
  final String? avatar;
  final bool online;
  final String status;
  final String vehicleArrangement;
  final String? verificationStatus;
  final String? verificationNotes;
  final bool hasAssignedTruck;
  final Map<String, dynamic>? vehicle;

  bool get isDriverOwned => vehicleArrangement == 'driver_owned';
  bool get isVehicleApproved => verificationStatus == 'approved';
  bool get isVehiclePending => verificationStatus == 'pending_review';
  bool get isVehicleRejected => verificationStatus == 'rejected';

  factory HubCourier.fromJson(Map<String, dynamic> json) => HubCourier(
    id: asJsonInt(json['id']),
    name: asJsonString(json['name']),
    company: asJsonString(json['company']),
    email: asJsonString(json['email']),
    phone: asJsonString(json['phone']),
    licenseNumber: json['license_number'] == null
        ? null
        : asJsonString(json['license_number']),
    avatar: json['avatar'] == null ? null : asJsonString(json['avatar']),
    online: asJsonBool(json['is_online']),
    status: asJsonString(json['status']),
    vehicleArrangement: asJsonString(json['vehicle_arrangement'], 'company'),
    verificationStatus: json['verification_status'] == null
        ? null
        : asJsonString(json['verification_status']),
    verificationNotes: json['verification_notes'] == null
        ? null
        : asJsonString(json['verification_notes']),
    hasAssignedTruck: asJsonBool(json['has_assigned_truck']),
    vehicle: asJsonMap(json['vehicle']),
  );

  HubCourier copyWith({
    String? name,
    String? company,
    String? email,
    String? phone,
    String? licenseNumber,
    String? avatar,
    bool? online,
    String? status,
    String? vehicleArrangement,
    String? verificationStatus,
    String? verificationNotes,
    bool? hasAssignedTruck,
    Map<String, dynamic>? vehicle,
  }) => HubCourier(
    id: id,
    name: name ?? this.name,
    company: company ?? this.company,
    email: email ?? this.email,
    phone: phone ?? this.phone,
    licenseNumber: licenseNumber ?? this.licenseNumber,
    avatar: avatar ?? this.avatar,
    online: online ?? this.online,
    status: status ?? this.status,
    vehicleArrangement: vehicleArrangement ?? this.vehicleArrangement,
    verificationStatus: verificationStatus ?? this.verificationStatus,
    verificationNotes: verificationNotes ?? this.verificationNotes,
    hasAssignedTruck: hasAssignedTruck ?? this.hasAssignedTruck,
    vehicle: vehicle ?? this.vehicle,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'company': company,
    'email': email,
    'phone': phone,
    'license_number': licenseNumber,
    'avatar': avatar,
    'is_online': online,
    'status': status,
    'vehicle_arrangement': vehicleArrangement,
    'verification_status': verificationStatus,
    'verification_notes': verificationNotes,
    'has_assigned_truck': hasAssignedTruck,
    'vehicle': vehicle,
  };
}

class HubTransferJob {
  const HubTransferJob(this.data);
  final Map<String, dynamic> data;

  int get id => asJsonInt(data['id']);
  String get reference => asJsonString(data['reference']);
  String get status => asJsonString(data['status']);
  String get statusLabel => asJsonString(
    data['status_label'],
    status.replaceAll('_', ' '),
  );
  Map<String, dynamic> get origin => asJsonMapOrEmpty(data['origin']);
  Map<String, dynamic> get destination =>
      asJsonMapOrEmpty(data['destination']);
  Map<String, dynamic> get vehicle => asJsonMapOrEmpty(data['vehicle']);
  List<Map<String, dynamic>> get parcels => asJsonMapList(data['parcels']);
  int get parcelCount {
    final explicit = data['parcel_count'];
    if (explicit is num) return explicit.toInt();
    if (explicit is String) {
      final parsed = int.tryParse(explicit);
      if (parsed != null) return parsed;
    }
    return parcels.length;
  }

  int get loadedCount => asJsonInt(data['loaded_count']);
  int get receivedCount => asJsonInt(data['received_count']);
  int get missingCount => asJsonInt(data['missing_count']);
  double? get totalWeightKg => data['total_weight_kg'] == null
      ? null
      : asJsonDouble(data['total_weight_kg']);
  String? get plannedDispatchAt => data['planned_dispatch_at']?.toString();
  String? get dispatchedAt => data['dispatched_at']?.toString();
  String? get destinationArrivedAt =>
      data['destination_arrived_at']?.toString();
  String? get receivedAt => data['received_at']?.toString();
  String get notes => asJsonString(data['notes']).trim();
  String? get originArrivedAt => data['origin_arrived_at']?.toString();
}

/// Parcel-level journey label carried by the API. Falls back to the raw
/// loading/receiving flags for older payloads that predate `journey_label`.
String parcelJourneyLabel(Map<String, dynamic> parcel) {
  final label = asJsonString(parcel['journey_label']);
  if (label.isNotEmpty) return label;
  final stage = asJsonString(parcel['journey_status']);
  if (stage.isNotEmpty) return stage.replaceAll('_', ' ');
  if (asJsonString(parcel['receiving_status']) == 'received') {
    return 'Received and verified';
  }
  if (asJsonString(parcel['loading_status']) == 'loaded') {
    return 'Loaded / verified';
  }
  return 'Added to manifest';
}

String parcelJourneyStatus(Map<String, dynamic> parcel) {
  final explicit = asJsonString(parcel['journey_status']);
  if (explicit.isNotEmpty) return explicit;
  if (asJsonString(parcel['receiving_status']) == 'received') return 'received';
  if (asJsonString(parcel['loading_status']) == 'loaded') return 'loaded';
  return 'added';
}

double? parcelWeightKg(Map<String, dynamic> parcel) =>
    parcel['weight_kg'] == null ? null : asJsonDouble(parcel['weight_kg']);

/// Parsed hub transport dashboard payload.
///
/// The API response is normalised once, outside of `build()`, so an
/// unexpected or drifted response shape surfaces as the dashboard error state
/// instead of throwing during widget construction and blanking the screen.
class HubCourierDashboard {
  const HubCourierDashboard({
    required this.courier,
    required this.activeTransfer,
    required this.availableTransfers,
    required this.completedTransfers,
    required this.earnings,
    required this.recentIncidents,
  });

  final HubCourier courier;
  final HubTransferJob? activeTransfer;
  final List<HubTransferJob> availableTransfers;
  final List<HubTransferJob> completedTransfers;
  final HubEarningsSummary earnings;
  final List<Map<String, dynamic>> recentIncidents;

  bool get hasTransfers =>
      activeTransfer != null ||
      availableTransfers.isNotEmpty ||
      completedTransfers.isNotEmpty;

  factory HubCourierDashboard.fromJson(Map<String, dynamic> json) {
    final courierJson = asJsonMap(json['courier']);
    if (courierJson == null || courierJson.isEmpty) {
      throw const FormatException(
        'The dashboard response did not include the driver profile. Please retry.',
      );
    }
    final activeJson = asJsonMap(json['active_transfer']);

    return HubCourierDashboard(
      courier: HubCourier.fromJson(courierJson),
      activeTransfer: activeJson == null ? null : HubTransferJob(activeJson),
      availableTransfers: _transferJobs(json['available_transfers']),
      completedTransfers: _transferJobs(json['completed_transfers']),
      earnings: HubEarningsSummary.fromJson(
        asJsonMapOrEmpty(json['earnings_summary']),
      ),
      recentIncidents: asJsonMapList(json['recent_incidents']),
    );
  }

  static List<HubTransferJob> _transferJobs(dynamic value) =>
      asJsonMapList(value).map(HubTransferJob.new).toList();
}

class HubEarningsSummary {
  const HubEarningsSummary({
    required this.total,
    required this.base,
    required this.allowance,
    required this.completedTrips,
  });

  final double total;
  final double base;
  final double allowance;
  final int completedTrips;

  factory HubEarningsSummary.fromJson(Map<String, dynamic> json) =>
      HubEarningsSummary(
        total: asJsonDouble(json['total_earnings']),
        base: asJsonDouble(json['base_earnings']),
        allowance: asJsonDouble(json['vehicle_allowance']),
        completedTrips: asJsonInt(json['completed_trips_count']),
      );
}
