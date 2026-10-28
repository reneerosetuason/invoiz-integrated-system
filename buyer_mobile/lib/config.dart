import 'package:flutter/foundation.dart';

class AppConfig {
  static const _configuredApi = String.fromEnvironment('INVOIZ_API_BASE_URL');

  /// Every API and uploaded asset uses the same Laravel deployment.
  static String get apiBaseUrl {
    if (_configuredApi.isNotEmpty) {
      final uri = Uri.parse(_configuredApi);
      if (!['http', 'https'].contains(uri.scheme) ||
          !uri.hasAuthority ||
          uri.userInfo.isNotEmpty ||
          uri.hasQuery ||
          uri.hasFragment) {
        throw const FormatException(
          'INVOIZ_API_BASE_URL must be an HTTP(S) API URL.',
        );
      }
      return _configuredApi.replaceFirst(RegExp(r'/+$'), '');
    }
    if (kIsWeb && !['localhost', '127.0.0.1'].contains(Uri.base.host)) {
      return '${Uri.base.origin}/api';
    }
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api';
    }
    return 'http://127.0.0.1:8000/api';
  }

  // Philippine Standard Geographic Code (PSGC) API for address dropdowns
  // (Province -> Municipality -> Barangay).
  static const String psgcBaseUrl = 'https://psgc.gitlab.io/api';

  /// Builds a full URL for a stored file (e.g. "products/p1_1.png").
  /// Always resolves against [apiBaseUrl]'s host, so images work on
  /// physical devices too (LAN IP) instead of being hardcoded to 127.0.0.1.
  static String storageUrl(String? path) => resolveStorageUrl(path, apiBaseUrl);

  /// Rebase stored and development URLs to this installation, keeping real CDNs.
  static String resolveStorageUrl(String? path, String apiUrl) {
    if (path == null || path.trim().isEmpty) return '';
    var value = path.trim().replaceAll('\\', '/');
    final baseUri = Uri.parse(apiUrl);
    final origin = baseUri.origin;
    var parsed = Uri.tryParse(value);
    if (parsed == null || parsed.userInfo.isNotEmpty) return '';
    if (parsed.hasScheme || value.startsWith('//')) {
      if (value.startsWith('//'))
        parsed = Uri.parse('${baseUri.scheme}:$value');
      if (!['http', 'https'].contains(parsed.scheme)) return '';
      final host = parsed.host.toLowerCase();
      final local =
          host == 'localhost' ||
          host == '::1' ||
          host.startsWith('127.') ||
          host.startsWith('10.') ||
          host.startsWith('192.168.') ||
          RegExp(r'^172\.(1[6-9]|2[0-9]|3[01])\.').hasMatch(host);
      if (!local && host != baseUri.host) return parsed.toString();
      value = parsed.path;
    }
    var clean = value.replaceFirst(RegExp(r'^/+'), '');
    if (clean.startsWith('public/storage/')) clean = clean.substring(7);
    if (clean.startsWith('storage/app/public/')) clean = clean.substring(19);
    if (clean.startsWith('app/public/')) clean = clean.substring(11);
    try {
      if (Uri.decodeComponent(
        clean,
      ).split('/').any((part) => part == '..' || part == '.'))
        return '';
    } on FormatException {
      return '';
    } on ArgumentError {
      return '';
    }
    if (!clean.startsWith('storage/') &&
        !clean.startsWith('assets/') &&
        !clean.startsWith('api/'))
      clean = 'storage/$clean';
    return Uri.parse('$origin/').resolve(clean).toString();
  }
}
