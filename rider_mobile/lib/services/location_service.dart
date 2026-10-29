import '../core/network/api_client.dart';
import '../core/utils/geolocation.dart';

/// Sends the rider's live GPS position to Laravel.
///
/// Pings are only sent while the rider is online and an active delivery is
/// being worked on, to stay battery-friendly.
class LocationService {
  LocationService(this._api);

  final ApiClient _api;

  Future<void> report({int? deliveryId}) async {
    final pos = await Geolocation.currentPosition();
    if (pos == null) return;

    await _api.post('/rider/location', body: {
      'latitude': pos.latitude,
      'longitude': pos.longitude,
      'recorded_at': DateTime.now().toUtc().toIso8601String(),
      'delivery_id': ?deliveryId,
    });
  }
}