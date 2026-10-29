import '../core/network/api_client.dart';
import '../models/rider_notification.dart';

/// Notification service.
class NotificationService {
  NotificationService(this._api);

  final ApiClient _api;

  Future<List<RiderNotification>> list() async {
    final data = await _api.get('/rider/notifications');

    List<RiderNotification> list = [];
    if (data['notifications'] is List) {
      list = (data['notifications'] as List)
          .whereType<Map<String, dynamic>>()
          .map(RiderNotification.fromJson)
          .toList();
    }
    return list;
  }

  Future<void> markRead(int id) async {
    await _api.patch('/rider/notifications/$id/read');
  }

  Future<void> markAllRead() async {
    await _api.patch('/rider/notifications/read-all');
  }
}