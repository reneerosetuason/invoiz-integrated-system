import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';
import '../core/storage/token_storage.dart';
import '../models/rider.dart';
import '../models/hub_courier.dart';
import '../services/auth_service.dart';
import '../services/rider_service.dart';
import '../services/hub_courier_service.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

/// Global authentication state.
class AuthProvider extends ChangeNotifier {
  AuthProvider({required ApiClient api, required this._storage})
    : _api = api,
      _authService = AuthService(api),
      _riderService = RiderService(api),
      _courierService = HubCourierService(api);

  final ApiClient _api;
  final TokenStorage _storage;
  final AuthService _authService;
  final RiderService _riderService;
  final HubCourierService _courierService;

  ApiClient get api => _api;

  AuthStatus _status = AuthStatus.unknown;
  Rider? _rider;
  HubCourier? _courier;
  String? _error;

  AuthStatus get status => _status;
  Rider? get rider => _rider;
  HubCourier? get courier => _courier;
  bool get isHubCourier => _courier != null;
  bool get isAuthenticated => _status == AuthStatus.authenticated;
  String? get error => _error;
  bool get isOnline => _rider?.isOnline ?? false;

  /// Restore the session from secure storage on app launch.
  Future<void> restoreSession() async {
    final token = await _storage.readToken();

    if (token == null || token.isEmpty) {
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }

    _api.setToken(token);

    // Fall back to the cached profile so the splash resolves offline.
    final cached = await _storage.readCachedUser();
    if (cached != null) {
      if (cached['workflow_type'] == 'hub_courier') {
        _courier = HubCourier.fromJson(
          Map<String, dynamic>.from(cached['courier'] as Map? ?? {}),
        );
      } else {
        _rider = Rider.fromJson(
          Map<String, dynamic>.from(cached['rider'] as Map? ?? {}),
        );
      }
    }

    try {
      if (_courier != null) {
        final courier = await _courierService.profile();
        _courier = courier;
        await _persistCourier(courier);
      } else {
        final profile = await _riderService.getProfile();
        _rider = profile;
        await _persistUser(profile);
      }
      _status = AuthStatus.authenticated;
    } on ApiException catch (e) {
      if (e.type == ApiErrorType.unauthorized) {
        await _storage.clear();
        _api.setToken(null);
        _status = AuthStatus.unauthenticated;
      } else if (cached != null) {
        // Keep the cached session during network failure.
        _status = AuthStatus.authenticated;
      } else {
        _status = AuthStatus.unauthenticated;
      }
    } catch (_) {
      if (cached != null) {
        _status = AuthStatus.authenticated;
      } else {
        _status = AuthStatus.unauthenticated;
      }
    }

    notifyListeners();
  }

  Future<bool> login({required String email, required String password}) async {
    _error = null;
    notifyListeners();

    try {
      try {
        final result = await _authService.login(
          email: email,
          password: password,
        );
        _api.setToken(result.token);
        await _storage.saveToken(result.token);
        _rider = result.rider;
        _courier = null;
        await _persistUser(result.rider);
        _status = AuthStatus.authenticated;
        notifyListeners();
        return true;
      } on ApiException catch (riderError) {
        // A Hub Transport Driver email is not a `users` account, so the delivery
        // rider endpoint answers with a validation error. Only then do we
        // try the separate Hub Transport Driver credential store; rider tokens and
        // permissions are never shared between the two workflows.
        if (riderError.type != ApiErrorType.validation) rethrow;
        final result = await _courierService.login(email, password);
        _api.setToken(result.token);
        await _storage.saveToken(result.token);
        _courier = result.courier;
        _rider = null;
        await _persistCourier(result.courier);
        _status = AuthStatus.authenticated;
        notifyListeners();
        return true;
      }
    } on ApiException catch (e) {
      _error = e.message;
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    } catch (_) {
      _error = 'Unable to log in. Please check your connection and try again.';
      _status = AuthStatus.unauthenticated;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    // Stop new assignments before revoking the token so dispatch does not
    // keep offering work to a signed-out rider.
    if (_courier?.online == true) {
      try {
        await _courierService.setStatus(false);
      } catch (_) {}
    } else if (_rider?.isOnline == true) {
      try {
        await _riderService.updateStatus('offline');
      } catch (_) {
        // Best-effort only: never block logout on a failed status update.
      }
    }

    try {
      await _authService.logout();
    } catch (_) {
      // Ignore logout API failures; clear locally regardless.
    }

    _api.setToken(null);
    await _storage.clear();
    _rider = null;
    _courier = null;
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void updateRider(Rider rider) {
    _rider = rider;
    _persistUser(rider);
    notifyListeners();
  }

  Future<void> _persistUser(Rider rider) async {
    final payload = {
      'workflow_type': 'delivery_rider',
      'rider': rider.toJson(),
    };
    await _storage.saveUser(jsonEncode(payload));
  }

  Future<void> _persistCourier(HubCourier courier) async {
    await _storage.saveUser(
      jsonEncode({'workflow_type': 'hub_courier', 'courier': courier.toJson()}),
    );
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
