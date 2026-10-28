class Address {
  final int id;
  final String recipientName;
  final String phone;
  final String addressLine;
  final String barangay;
  final String city;
  final String province;
  final String? postalCode;
  final bool isDefault;

  Address({
    required this.id,
    required this.recipientName,
    required this.phone,
    required this.addressLine,
    required this.barangay,
    required this.city,
    required this.province,
    this.postalCode,
    this.isDefault = false,
  });

  factory Address.fromJson(Map<String, dynamic> json) => Address(
    id: json['id'] as int,
    recipientName: json['recipient_name'] as String,
    phone: json['phone'] as String,
    addressLine: json['address_line'] as String,
    barangay: json['barangay'] as String,
    city: json['city'] as String,
    province: json['province'] as String,
    postalCode: json['postal_code'] as String?,
    isDefault: json['is_default'] as bool? ?? false,
  );

  String get fullAddress =>
      '$addressLine, $barangay, $city, $province${postalCode != null && postalCode!.isNotEmpty ? ' $postalCode' : ''}';
}
