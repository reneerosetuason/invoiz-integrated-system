import 'dart:math';

import 'package:geolocator/geolocator.dart';
import 'package:url_launcher/url_launcher.dart';

/// GPS + navigation helpers. Returns null-safe values so the UI can degrade
/// gracefully when location is unavailable.
enum LocationReadiness {
  ready,
  serviceDisabled,
  permissionDenied,
  permissionDeniedForever,
}

class Geolocation {
  Geolocation._();

  /// Check (and, when possible, request) the permission required to go online.
  /// The backend receives GPS pings while the rider is online, so we never
  /// turn availability on without a usable location signal.
  static Future<LocationReadiness> ensureReady() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return LocationReadiness.serviceDisabled;
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      return switch (permission) {
        LocationPermission.always ||
        LocationPermission.whileInUse => LocationReadiness.ready,
        LocationPermission.deniedForever =>
          LocationReadiness.permissionDeniedForever,
        _ => LocationReadiness.permissionDenied,
      };
    } catch (_) {
      return LocationReadiness.permissionDenied;
    }
  }

  /// Read the current location readiness **without prompting** the rider.
  ///
  /// Used while online to detect that location was turned off mid-shift, so
  /// the app can warn the rider that no new offers will arrive.
  static Future<LocationReadiness> checkReadiness() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return LocationReadiness.serviceDisabled;
      }
      return _readinessFor(await Geolocator.checkPermission());
    } catch (_) {
      return LocationReadiness.permissionDenied;
    }
  }

  static LocationReadiness _readinessFor(LocationPermission permission) {
    return switch (permission) {
      LocationPermission.always ||
      LocationPermission.whileInUse => LocationReadiness.ready,
      LocationPermission.deniedForever =>
        LocationReadiness.permissionDeniedForever,
      _ => LocationReadiness.permissionDenied,
    };
  }

  static String readinessMessage(LocationReadiness readiness) {
    return switch (readiness) {
      LocationReadiness.ready => '',
      LocationReadiness.serviceDisabled =>
        'Turn on your device location (GPS) to go online. '
            'Riders share their position while available for deliveries.',
      LocationReadiness.permissionDeniedForever =>
        'Location permission is blocked. Enable it for Invoiz Rider in '
            'system settings, then try again.',
      LocationReadiness.permissionDenied =>
        'Location permission is required to go online. '
            'Allow it when prompted, then try again.',
    };
  }

  /// Open this app's system settings page (for a permanently denied
  /// permission).
  static Future<bool> openAppSettings() => Geolocator.openAppSettings();

  /// Open the device location-services settings page.
  static Future<bool> openLocationSettings() =>
      Geolocator.openLocationSettings();

  static Future<Position?> currentPosition() async {
    try {
      var serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return null;

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return null;
      }

      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );
    } catch (_) {
      return null;
    }
  }

  /// Opens the Google Maps navigation app to a destination.
  static Future<bool> openNavigation(double lat, double lng) async {
    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng',
    );
    return launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  /// Approximate straight-line distance between two coordinates (km).
  static double distanceInKm(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    const r = 6371.0;
    final dLat = _rad(lat2 - lat1);
    final dLng = _rad(lng2 - lng1);
    final a =
        pow(sin(dLat / 2), 2) +
        cos(_rad(lat1)) * cos(_rad(lat2)) * pow(sin(dLng / 2), 2);
    return 2 * r * asin(sqrt(a.toDouble()));
  }

  static double _rad(double deg) => deg * pi / 180.0;
}
