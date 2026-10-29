import '../core/network/api_client.dart';

/// Keep PSGC codes as strings so their leading zeroes survive every lookup.
class PsgcOption {
  const PsgcOption({required this.code, required this.name});

  final String code;
  final String name;

  factory PsgcOption.fromJson(Map<String, dynamic> json) =>
      PsgcOption(code: '${json['code'] ?? ''}', name: '${json['name'] ?? ''}');
}

class AddressSelection {
  const AddressSelection({
    this.region,
    this.regionCode,
    this.province,
    this.municipality,
    this.barangay,
    this.provinceNotApplicable = false,
    this.houseNumber = '',
    this.street = '',
  });

  final String? region;
  final String? regionCode;
  final PsgcOption? province;
  final PsgcOption? municipality;
  final PsgcOption? barangay;
  final bool provinceNotApplicable;
  final String houseNumber;
  final String street;

  bool get complete =>
      regionCode != null &&
      region != null &&
      (province != null || provinceNotApplicable) &&
      municipality != null &&
      barangay != null;

  /// The application endpoints still receive human-readable address names.
  String get composed => [
    houseNumber.trim(),
    street.trim(),
    barangay?.name ?? '',
    municipality?.name ?? '',
    province?.name ?? '',
    region ?? '',
  ].where((part) => part.isNotEmpty).join(', ');
}

/// The same national PSGC proxy used by the buyer app and the website.
class AddressService {
  AddressService(this._api);

  final ApiClient _api;
  final Map<String, List<PsgcOption>> _cache = {};

  Future<List<PsgcOption>> regions() => _get('regions');

  Future<List<PsgcOption>> provinces(String regionCode) =>
      _get('regions/$regionCode/provinces');

  /// Regions without provinces, such as NCR, list cities directly.
  Future<List<PsgcOption>> regionCities(String regionCode) =>
      _get('regions/$regionCode/cities');

  Future<List<PsgcOption>> municipalities(String provinceCode) =>
      _get('provinces/$provinceCode/cities-municipalities');

  Future<List<PsgcOption>> barangays(String cityCode) =>
      _get('cities-municipalities/$cityCode/barangays');

  Future<List<PsgcOption>> _get(String path) async {
    final cached = _cache[path];
    if (cached != null) return cached;
    final data = await _api.getShared('psgc/$path');
    if (data is! List) throw const FormatException('Invalid PSGC response.');
    final items = data
        .whereType<Map<String, dynamic>>()
        .map(PsgcOption.fromJson)
        .where((item) => item.code.isNotEmpty && item.name.isNotEmpty)
        .toList();
    if (items.length != data.length) {
      throw const FormatException('Invalid PSGC address option.');
    }
    // Only province lists may legitimately be empty (e.g. NCR).
    if (items.isEmpty && !path.endsWith('/provinces')) {
      throw const FormatException('No PSGC address options returned.');
    }
    _cache[path] = items;
    return items;
  }
}
