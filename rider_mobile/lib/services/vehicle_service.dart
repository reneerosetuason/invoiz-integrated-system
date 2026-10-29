import '../core/network/api_client.dart';

class VehicleType {
  const VehicleType({required this.name, required this.label, required this.capacityKg});
  final String name;
  final String label;
  final double capacityKg;
  factory VehicleType.fromJson(Map<String, dynamic> j) => VehicleType(
        name: j['name'] as String? ?? '',
        label: j['label'] as String? ?? '',
        capacityKg: (j['capacity_kg'] as num?)?.toDouble() ?? 0,
      );
}

class VehicleService {
  VehicleService(this._api);
  final ApiClient _api;
  Future<List<VehicleType>> getActive() async {
    final data = await _api.get('/vehicle-types');
    final list = data['vehicle_types'] as List? ?? [];
    return list.whereType<Map<String, dynamic>>().map(VehicleType.fromJson).toList();
  }
}
