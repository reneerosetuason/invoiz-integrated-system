import 'dart:io';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_dotenv/flutter_dotenv.dart';

/// Environment-aware application configuration.
///
/// Resolution order for the API base URL:
///   1. `.env` file (`API_BASE_URL` key) — primary source.
///   2. `--dart-define=API_BASE_URL=...` at build time — fallback.
///   3. Hard-coded defaults per platform.
class AppConfig {
  AppConfig._();

  /// The environment: `development`, `testing` or `production`.
  static const String environment = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'development',
  );

  /// Dart-define fallback (used when `.env` does not contain the key).
  static const String _dartDefineUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: '',
  );

  /// Base URL for a production Laravel deployment.
  static const String prodHost = String.fromEnvironment(
    'API_PRODUCTION_URL',
    defaultValue: 'https://api.invoize.example/api/logistics',
  );

  /// The resolved API base URL.
  ///
  /// Prefers the value from `.env` (loaded by `flutter_dotenv`), then falls
  /// back to the dart-define value, then to platform-specific defaults.
  static String get apiBaseUrl {
    // 1. .env file (primary) — guarded so tests that don't load .env still work.
    try {
      final envUrl = dotenv.env['API_BASE_URL'];
      if (envUrl != null && envUrl.isNotEmpty) return envUrl;
    } on Error {
      // dotenv not initialized (e.g. in tests) — fall through.
    }

    // 2. Dart-define (build-time fallback)
    if (_dartDefineUrl.isNotEmpty) return _dartDefineUrl;

    // 3. Platform defaults
    if (kIsWeb) return 'http://127.0.0.1:8000/api/logistics';
    if (Platform.isAndroid) {
      return environment == 'production'
          ? prodHost
          : 'http://10.0.2.2:8000/api/logistics';
    }
    return environment == 'production'
        ? prodHost
        : 'http://127.0.0.1:8000/api/logistics';
  }
}
