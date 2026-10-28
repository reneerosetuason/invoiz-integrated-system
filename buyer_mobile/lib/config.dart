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
  static String storageUrl(String? path) {
    if (path == null || path.isEmpty) return '';
    final base = apiBaseUrl.replaceFirst(RegExp(r'/api/?$'), '');
    final parsed = Uri.tryParse(path);
    if (parsed != null && parsed.hasScheme) {
      if (!['http', 'https'].contains(parsed.scheme)) return '';
      if (['localhost', '127.0.0.1', '10.0.2.2'].contains(parsed.host)) {
        return Uri.parse(base)
            .resolve(parsed.path)
            .replace(query: parsed.hasQuery ? parsed.query : null)
            .toString();
      }
      return parsed.toString();
    }
    final clean = path.replaceAll('\\', '/').replaceFirst(RegExp(r'^/+'), '');
    if (clean.split('/').contains('..')) return '';
    if (clean.startsWith('storage/') ||
        clean.startsWith('assets/') ||
        clean.startsWith('api/')) {
      return '$base/$clean';
    }
    return '$base/storage/$clean';
  }
}
