/// Shop information carried on a delivery (maps to the sender on the backend).
class Shop {
  const Shop({
    required this.name,
    this.phone,
    this.address,
    this.latitude,
    this.longitude,
  });

  final String name;
  final String? phone;
  final String? address;
  final double? latitude;
  final double? longitude;

  factory Shop.fromJson(Map<String, dynamic> json) {
    return Shop(
      name: json['name'] as String? ?? '',
      phone: json['phone'] as String?,
      address: json['address'] as String?,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
    );
  }
}