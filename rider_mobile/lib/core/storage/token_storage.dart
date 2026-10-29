import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Stores the auth token. Uses the OS secure store on mobile and falls back
/// to shared preferences on web/desktop.
class TokenStorage {
  TokenStorage({FlutterSecureStorage? secureStorage})
      : _secure = secureStorage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _secure;
  static const String _tokenKey = 'invoize_auth_token';
  static const String _userKey = 'invoize_user_cache';

  bool get _useSecure => !kIsWeb;

  Future<void> saveToken(String token) async {
    if (_useSecure) {
      await _secure.write(key: _tokenKey, value: token);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, token);
    }
  }

  Future<String?> readToken() async {
    if (_useSecure) {
      return _secure.read(key: _tokenKey);
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  Future<void> deleteToken() async {
    if (_useSecure) {
      await _secure.delete(key: _tokenKey);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
    }
  }

  Future<void> saveUser(String userJson) async {
    if (_useSecure) {
      await _secure.write(key: _userKey, value: userJson);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_userKey, userJson);
    }
  }

  Future<String?> readUser() async {
    if (_useSecure) {
      return _secure.read(key: _userKey);
    }
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_userKey);
  }

  Future<Map<String, dynamic>?> readCachedUser() async {
    final raw = await readUser();
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<void> deleteUser() async {
    if (_useSecure) {
      await _secure.delete(key: _userKey);
    } else {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_userKey);
    }
  }

  Future<void> clear() async {
    await deleteToken();
    await deleteUser();
  }
}