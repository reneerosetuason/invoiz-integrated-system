import '../core/network/api_client.dart';
import '../models/delivery.dart';
import '../models/rider.dart';

/// Rider dashboard summary.
class DashboardSummary {
  const DashboardSummary({
    required this.stats,
    required this.todayEarnings,
    this.currentDelivery,
    this.upcomingPickup,
    required this.recentCompleted,
    required this.unreadNotifications,
    required this.rider,
  });

  final Map<String, int> stats;
  final double todayEarnings;
  final Delivery? currentDelivery;
  final Delivery? upcomingPickup;
  final List<Delivery> recentCompleted;
  final int unreadNotifications;
  final Rider rider;
}

/// Rider profile / status / dashboard service.
class RiderService {
  RiderService(this._api);

  final ApiClient _api;

  Future<Rider> getProfile() async {
    final data = await _api.get('/rider/profile');
    return Rider.fromJson(
        Map<String, dynamic>.from(data['rider'] as Map? ?? {}));
  }

  Future<Rider> updateProfile({
    String? phone,
    String? vehicleType,
    String? licensePlate,
  }) async {
    final data = await _api.patch('/rider/profile', body: {
      'phone': ?phone,
      'vehicle_type': ?vehicleType,
      'license_plate': ?licensePlate,
    });
    return Rider.fromJson(
        Map<String, dynamic>.from(data['rider'] as Map? ?? {}));
  }

  /// Change the authenticated rider's password. Throws [ApiException]
  /// with the backend message (e.g. wrong current password).
  /// The password is only ever sent in the request body, never stored.
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    await _api.patch('/rider/password', body: {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': confirmPassword,
    });
  }

  Future<Rider> updateStatus(String status) async {
    final data = await _api.patch('/rider/status', body: {'status': status});
    return Rider.fromJson(
        Map<String, dynamic>.from(data['rider'] as Map? ?? {}));
  }

  Future<DashboardSummary> getDashboard() async {
    final data = await _api.get('/rider/dashboard');

    final stats = <String, int>{};
    if (data['stats'] is Map) {
      (data['stats'] as Map).forEach((k, v) {
        stats[k.toString()] = (v as num?)?.toInt() ?? 0;
      });
    }

    final recent = <Delivery>[];
    if (data['recent_completed'] is List) {
      recent.addAll(
        (data['recent_completed'] as List)
            .whereType<Map<String, dynamic>>()
            .map(Delivery.fromJson),
      );
    }

    return DashboardSummary(
      stats: stats,
      todayEarnings: (data['today_earnings'] as num?)?.toDouble() ?? 0,
      currentDelivery: data['current_delivery'] is Map<String, dynamic>
          ? Delivery.fromJson(data['current_delivery'] as Map<String, dynamic>)
          : null,
      upcomingPickup: data['upcoming_pickup'] is Map<String, dynamic>
          ? Delivery.fromJson(data['upcoming_pickup'] as Map<String, dynamic>)
          : null,
      recentCompleted: recent,
      unreadNotifications:
          (data['unread_notifications'] as num?)?.toInt() ?? 0,
      rider: Rider.fromJson(
          Map<String, dynamic>.from(data['rider'] as Map? ?? {})),
    );
  }
}