import '../core/network/api_client.dart';
import '../models/rider_notification.dart';

/// Notification service.
class NotificationService {
  NotificationService(this._api);

  final ApiClient _api;

  Future<List<RiderNotification>> list({bool isCourier = false}) async {
    final path = isCourier ? '/courier/notifications' : '/rider/notifications';
    final data = await _api.get(path);

    List<RiderNotification> list = [];
    if (data['notifications'] is List) {
      list = (data['notifications'] as List)
          .whereType<Map<String, dynamic>>()
          .map(RiderNotification.fromJson)
          .toList();
    }
    return list;
  }

  Future<void> markRead(int id, {bool isCourier = false}) async {
    final path = isCourier
        ? '/courier/notifications/$id/read'
        : '/rider/notifications/$id/read';
    await _api.patch(path);
  }

  Future<void> markAllRead({bool isCourier = false}) async {
    final path = isCourier
        ? '/courier/notifications/read-all'
        : '/rider/notifications/read-all';
    await _api.patch(path);
  }
}