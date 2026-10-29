import '../core/network/api_client.dart';

/// Philippine Standard Geographic Code (PSGC) option row: id + name.
/// All three levels share the same shape.
class PsgcOption {
  const PsgcOption({required this.id, required this.name, this.regionCode});

  final int id;
  final String name;
  final String? regionCode;

  factory PsgcOption.fromJson(Map<String, dynamic> j) => PsgcOption(
    id: (j['id'] as num?)?.toInt() ?? 0,
    name: j['name'] as String? ?? '',
    regionCode: (j['region_code'] as String?)?.trim().isEmpty ?? true
        ? null
        : (j['region_code'] as String).trim(),
  );
}

/// Structured address selection: province / municipality / barangay names.
class AddressSelection {
  const AddressSelection({
    this.province,
    this.municipality,
    this.barangay,
    this.houseNumber = '',
    this.street = '',
  });

  final PsgcOption? province;
  final PsgcOption? municipality;
  final PsgcOption? barangay;
  final String houseNumber;
  final String street;

  bool get complete =>
      province != null && municipality != null && barangay != null;

  /// Single free-text address composed from the structured fields.
  String get composed {
    final parts = <String>[
      houseNumber.trim(),
      street.trim(),
      barangay?.name ?? '',
      municipality?.name ?? '',
      province?.name ?? '',
    ].where((p) => p.isNotEmpty);
    return parts.join(', ');
  }
}

class AddressService {
  AddressService(this._api);
  final ApiClient _api;

  Future<List<PsgcOption>> provinces() async {
    final data = await _api.get('/address/provinces') as Map<String, dynamic>;
    return _options(data['provinces']);
  }

  Future<List<PsgcOption>> municipalities(int provinceId) async {
    final data =
        await _api.get('/address/provinces/$provinceId/municipalities')
            as Map<String, dynamic>;
    return _options(data['municipalities']);
  }

  Future<List<PsgcOption>> barangays(int municipalityId) async {
    final data =
        await _api.get('/address/municipalities/$municipalityId/barangays')
            as Map<String, dynamic>;
    return _options(data['barangays']);
  }

  List<PsgcOption> _options(dynamic raw) {
    if (raw is! List) return const [];
    return raw
        .whereType<Map<String, dynamic>>()
        .map(PsgcOption.fromJson)
        .toList();
  }
}
