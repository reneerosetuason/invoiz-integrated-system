import 'package:flutter/foundation.dart';

import '../core/network/api_exception.dart';
import '../core/utils/geolocation.dart';
import '../models/delivery.dart';
import '../models/rider.dart';
import '../services/rider_service.dart';

/// Rider profile, availability and dashboard state.
class RiderProvider extends ChangeNotifier {
  RiderProvider(
    this._service, {
    Future<LocationReadiness> Function()? locationCheck,
    Future<LocationReadiness> Function()? locationMonitor,
  }) : _locationCheck = locationCheck ?? Geolocation.ensureReady,
       _locationMonitor = locationMonitor ?? Geolocation.checkReadiness;

  final RiderService _service;
  final Future<LocationReadiness> Function() _locationCheck;
  final Future<LocationReadiness> Function() _locationMonitor;

  Rider? _rider;
  DashboardSummary? _dashboard;
  bool _loading = false;
  String? _error;
  bool _statusBusy = false;
  String? _locationWarning;

  Rider? get rider => _rider;
  DashboardSummary? get dashboard => _dashboard;
  bool get loading => _loading;
  String? get error => _error;
  bool get statusBusy => _statusBusy;
  bool get isOnline => _rider?.isOnline ?? false;

  /// Warning shown while online but location is unavailable (M13). New job
  /// offers stop while the rider cannot be located, so this must not be
  /// silent.
  String? get locationWarning => _locationWarning;

  Future<void> loadProfile() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _rider = await _service.getProfile();
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Unable to load profile. Please try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Refresh the dashboard. Returns `true` on success.
  Future<bool> loadDashboard() async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      _dashboard = await _service.getDashboard();
      _rider = _dashboard!.rider;
      _loading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to load dashboard. Please try again.';
      _loading = false;
      notifyListeners();
      return false;
    }
  }

  /// Toggle the online/offline availability switch (synced with Laravel).
  ///
  /// Going online requires a usable location permission because the app sends
  /// GPS pings while the rider is available. Going offline never asks.
  Future<bool> toggleOnline() async {
    final goingOnline = _rider?.isOnline != true;
    _error = null;

    if (goingOnline) {
      final readiness = await _locationCheck();
      if (readiness != LocationReadiness.ready) {
        _error = Geolocation.readinessMessage(readiness);
        notifyListeners();
        return false;
      }
    }

    _statusBusy = true;
    notifyListeners();

    try {
      final target = goingOnline ? 'online' : 'offline';
      final updated = await _service.updateStatus(target);
      _rider = updated;
      if (target == 'offline') _locationWarning = null;
      if (_dashboard != null) {
        _dashboard = DashboardSummary(
          stats: _dashboard!.stats,
          todayEarnings: _dashboard!.todayEarnings,
          currentDelivery: _dashboard!.currentDelivery,
          upcomingPickup: _dashboard!.upcomingPickup,
          recentCompleted: _dashboard!.recentCompleted,
          unreadNotifications: _dashboard!.unreadNotifications,
          rider: updated,
        );
      }
      _statusBusy = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _statusBusy = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to update your status. Please try again.';
      _statusBusy = false;
      notifyListeners();
      return false;
    }
  }

  /// Warn when location is turned off mid-shift.
  ///
  /// While the rider is online the app sends GPS pings; if location is
  /// unavailable the backend can no longer place the rider, so no new offers
  /// arrive. Runs without prompting for permission.
  Future<void> refreshLocationWarning() async {
    if (_rider?.isOnline != true) {
      if (_locationWarning != null) {
        _locationWarning = null;
        notifyListeners();
      }
      return;
    }

    final readiness = await _locationMonitor();
    final next = readiness == LocationReadiness.ready
        ? null
        : Geolocation.readinessMessage(readiness);
    if (next != _locationWarning) {
      _locationWarning = next;
      notifyListeners();
    }
  }

  void dismissLocationWarning() {
    if (_locationWarning == null) return;
    _locationWarning = null;
    notifyListeners();
  }

  Future<bool> updateProfile({
    String? phone,
    String? vehicleType,
    String? licensePlate,
  }) async {
    _statusBusy = true;
    notifyListeners();

    try {
      final updated = await _service.updateProfile(
        phone: phone,
        vehicleType: vehicleType,
        licensePlate: licensePlate,
      );
      _rider = updated;
      _statusBusy = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _statusBusy = false;
      notifyListeners();
      return false;
    }
  }

  /// Change the rider's password. Returns `true` on success and exposes
  /// the backend message in [error] on failure.
  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    _statusBusy = true;
    _error = null;
    notifyListeners();

    try {
      await _service.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmPassword: confirmPassword,
      );
      _statusBusy = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _statusBusy = false;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to change password. Please try again.';
      _statusBusy = false;
      notifyListeners();
      return false;
    }
  }

  /// Called by the delivery provider when a delivery's status changes.
  void refreshDeliveryStatus(Delivery delivery) {
    if (_dashboard != null) {
      loadDashboard();
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
