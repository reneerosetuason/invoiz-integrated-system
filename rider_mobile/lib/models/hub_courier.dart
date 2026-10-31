class HubCourier {
  const HubCourier({
    required this.id,
    required this.name,
    required this.company,
    required this.online,
    this.email = '',
    this.status = '',
    this.vehicle,
  });
  final int id;
  final String name;
  final String company;
  final String email;
  final bool online;
  final String status;
  final Map<String, dynamic>? vehicle;
  factory HubCourier.fromJson(Map<String, dynamic> json) => HubCourier(
    id: (json['id'] as num?)?.toInt() ?? 0,
    name: json['name'] as String? ?? '',
    company: json['company'] as String? ?? '',
    email: json['email'] as String? ?? '',
    online: json['is_online'] as bool? ?? false,
    status: json['status'] as String? ?? '',
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
