import 'dart:convert';
import '../config.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import 'api_service.dart';

class AuthService extends ChangeNotifier {
  final ApiService _api;
  AuthService({ApiService? api}) : _api = api ?? ApiService();
  static String get _tokenKey => 'invoiz:${AppConfig.apiBaseUrl}:token';
  static String get _userKey => 'invoiz:${AppConfig.apiBaseUrl}:user';

  String? _token;
  User? _user;
  bool _loading = true;

  String? get token => _token;
  User? get user => _user;
  bool get isLoading => _loading;
  bool get isLoggedIn => _token != null;

  Future<void> loadFromStorage() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_tokenKey);
    final userJson = prefs.getString(_userKey);
    if (userJson != null) {
      try {
        _user = User.fromJson(jsonDecode(userJson) as Map<String, dynamic>);
      } catch (_) {
        _token = null;
        await prefs.remove(_tokenKey);
        await prefs.remove(_userKey);
      }
    }
    if (_user == null) _token = null;
    ApiService.setToken(_token);
    if (_token != null) await refreshUser();
    _loading = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final data = await _api.post('mobile/buyer/login', {
      'email': email,
      'password': password,
    });
    await _saveSession(data);
  }

  Future<void> saveAfterRegistration() async {
    // Registration requires email verification before the shared account can log in.
  }

  Future<void> _saveSession(dynamic data) async {
    _token = data['token'] as String;
    _user = User.fromJson(data['user'] as Map<String, dynamic>);
    ApiService.setToken(_token);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, _token!);
    await prefs.setString(_userKey, jsonEncode(data['user']));
    notifyListeners();
  }

  Future<void> refreshUser() async {
    try {
      final data = await _api.get('me');
      _user = User.fromJson(data['user'] as Map<String, dynamic>);
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_userKey, jsonEncode(data['user']));
      notifyListeners();
    } on ApiException catch (e) {
      if (e.statusCode == 401 || e.statusCode == 403) await _clearSession();
    } catch (_) {}
  }

  Future<void> logout() async {
    try {
      await _api.post('logout', {});
    } catch (_) {}
    await _clearSession();
  }

  Future<void> _clearSession() async {
    _token = null;
    _user = null;
    ApiService.setToken(null);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
    notifyListeners();
  }
}
