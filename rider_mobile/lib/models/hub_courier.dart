class HubCourier {
  const HubCourier({
    required this.id,
    required this.name,
    required this.company,
    required this.online,
    this.email = '',
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
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? '',
    company: json['company'] as String? ?? '',
    email: json['email'] as String? ?? '',
    online: json['is_online'] as bool? ?? false,
    status: json['status'] as String? ?? '',
    vehicleArrangement: json['vehicle_arrangement'] as String? ?? 'company',
    verificationStatus: json['verification_status'] as String?,
    verificationNotes: json['verification_notes'] as String?,
    hasAssignedTruck: json['has_assigned_truck'] as bool? ?? false,
    vehicle: json['vehicle'] is Map
        ? Map<String, dynamic>.from(json['vehicle'] as Map)
        : null,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'company': company,
    'email': email,
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

  int get id => (data['id'] as num?)?.toInt() ?? 0;
  String get reference => data['reference']?.toString() ?? '';
  String get status => data['status']?.toString() ?? '';
  Map<String, dynamic> get origin =>
      Map<String, dynamic>.from(data['origin'] as Map? ?? {});
  Map<String, dynamic> get destination =>
      Map<String, dynamic>.from(data['destination'] as Map? ?? {});
  List<Map<String, dynamic>> get parcels => (data['parcels'] as List? ?? [])
      .map((e) => Map<String, dynamic>.from(e as Map))
      .toList();
  int get parcelCount =>
      (data['parcel_count'] as num?)?.toInt() ?? parcels.length;
  int get loadedCount => (data['loaded_count'] as num?)?.toInt() ?? 0;
}
