import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../core/network/api_client.dart';
import '../core/network/api_exception.dart';
import '../core/storage/token_storage.dart';
import '../models/rider.dart';
import '../services/auth_service.dart';
import '../services/rider_service.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

/// Global authentication state.
class AuthProvider extends ChangeNotifier {
  AuthProvider({required ApiClient api, required this._storage})
    : _api = api,
      _authService = AuthService(api),
      _riderService = RiderService(api);

  final ApiClient _api;
  final TokenStorage _storage;
  final AuthService _authService;
  final RiderService _riderService;

  ApiClient get api => _api;

  AuthStatus _status = AuthStatus.unknown;
  Rider? _rider;
  String? _error;

  AuthStatus get status => _status;
  Rider? get rider => _rider;
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
      _rider = Rider.fromJson(
        Map<String, dynamic>.from(cached['rider'] as Map? ?? {}),
      );
    }

    try {
      final profile = await _riderService.getProfile();
      _rider = profile;
      await _persistUser(profile);
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
      final result = await _authService.login(email: email, password: password);

      _api.setToken(result.token);
      await _storage.saveToken(result.token);
      _rider = result.rider;
      await _persistUser(result.rider);
      _status = AuthStatus.authenticated;
      notifyListeners();
      return true;
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
    if (_rider?.isOnline == true) {
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
    _status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  void updateRider(Rider rider) {
    _rider = rider;
    _persistUser(rider);
    notifyListeners();
  }

  Future<void> _persistUser(Rider rider) async {
    final payload = {'rider': rider.toJson()};
    await _storage.saveUser(jsonEncode(payload));
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
