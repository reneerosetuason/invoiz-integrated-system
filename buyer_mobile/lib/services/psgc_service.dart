import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config.dart';

/// One selectable place from the Philippine Standard Geographic Code API.
class PsgcItem {
  final String code;
  final String name;
  const PsgcItem({required this.code, required this.name});

  factory PsgcItem.fromJson(Map<String, dynamic> json) =>
      PsgcItem(code: '${json['code'] ?? ''}', name: '${json['name'] ?? ''}');
}

/// Cascading Region -> Province -> City/Municipality -> Barangay lookups
/// backed by the same public PSGC dataset the website uses.
///
/// Every response is cached for the lifetime of the service so walking back
/// and forth between levels never refetches.
class PsgcService {
  final http.Client _client;
  final String _baseUrl;
  final Map<String, List<PsgcItem>> _cache = {};

  /// Defaults to this installation's `/api/psgc` proxy so phones that can only
  /// reach the LAN server still get the dropdown data.
  PsgcService({http.Client? client, String? baseUrl})
    : _client = client ?? http.Client(),
      _baseUrl = (baseUrl ?? '${AppConfig.apiBaseUrl}/psgc').replaceFirst(
        RegExp(r'/+$'),
        '',
      );

  Future<List<PsgcItem>> _get(String path) {
    final cached = _cache[path];
    if (cached != null) return Future.value(cached);
    return _fetch(path).then((items) {
      _cache[path] = items;
      return items;
    });
  }

  Future<List<PsgcItem>> _fetch(String path) async {
    try {
      final response = await _client
          .get(
            Uri.parse('$_baseUrl/$path'),
            headers: const {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 15));
      if (response.statusCode != 200) return const [];
      final decoded = jsonDecode(response.body);
      if (decoded is! List) return const [];
      return decoded
          .whereType<Map>()
          .map((e) => PsgcItem.fromJson(Map<String, dynamic>.from(e)))
          .where((item) => item.code.isNotEmpty && item.name.isNotEmpty)
          .toList();
    } catch (_) {
      return const [];
    }
  }

  Future<List<PsgcItem>> regions() => _get('regions');

  /// Provinces of a region. Empty for regions without provinces (e.g. NCR).
  Future<List<PsgcItem>> provinces(String regionCode) =>
      _get('regions/$regionCode/provinces');

  /// Cities of a region, used in place of provinces for NCR.
  Future<List<PsgcItem>> regionCities(String regionCode) =>
      _get('regions/$regionCode/cities');

  Future<List<PsgcItem>> cities(String provinceCode) =>
      _get('provinces/$provinceCode/cities-municipalities');

  Future<List<PsgcItem>> barangays(String cityCode) =>
      _get('cities-municipalities/$cityCode/barangays');

  /// Finds the region that owns a province (or, for NCR, a city) so addresses
  /// saved before the region field existed can still be restored.
  Future<String?> regionCodeFor(String place) async {
    final wanted = place.trim().toLowerCase();
    if (wanted.isEmpty) return null;
    for (final region in await regions()) {
      final provinceItems = await provinces(region.code);
      if (provinceItems.any((p) => p.name.toLowerCase() == wanted)) {
        return region.code;
      }
      if (provinceItems.isEmpty) {
        final cities = await regionCities(region.code);
        if (cities.any((c) => c.name.toLowerCase() == wanted)) {
          return region.code;
        }
      }
    }
    return null;
  }
}
