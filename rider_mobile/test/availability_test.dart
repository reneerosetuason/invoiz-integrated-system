import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/utils/geolocation.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/services/rider_service.dart';

Map<String, dynamic> _rider({required bool online}) => {
  'id': 5,
  'name': 'Test Rider',
  'email': 'rider@example.com',
  'phone': '09171234567',
  'vehicle_type': 'motorcycle',
  'status': online ? 'available' : 'offline',
  'is_online': online,
  'can_go_online': true,
};

void main() {
  test('going online is blocked without a usable location', () async {
    var patchCalls = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'PATCH') {
          patchCalls++;
          return http.Response(
            jsonEncode({'rider': _rider(online: true)}),
            200,
          );
        }
        return http.Response(
          jsonEncode({'rider': _rider(online: false)}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final provider = RiderProvider(
      RiderService(api),
      locationCheck: () async => LocationReadiness.permissionDeniedForever,
    );
    await provider.loadProfile();

    final ok = await provider.toggleOnline();

    expect(ok, isFalse);
    expect(provider.isOnline, isFalse);
    expect(provider.statusBusy, isFalse);
    expect(provider.error, contains('Location permission is blocked'));
    expect(patchCalls, 0);
  });

  test('going online asks for location and then updates the backend', () async {
    var locationChecks = 0;
    var patchCalls = 0;
    String? patchBody;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'PATCH') {
          patchCalls++;
          patchBody = request.body;
          return http.Response(
            jsonEncode({'rider': _rider(online: true)}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response(
          jsonEncode({'rider': _rider(online: false)}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final provider = RiderProvider(
      RiderService(api),
      locationCheck: () async {
        locationChecks++;
        return LocationReadiness.ready;
      },
    );
    await provider.loadProfile();

    final ok = await provider.toggleOnline();

    expect(ok, isTrue);
    expect(locationChecks, 1);
    expect(patchCalls, 1);
    expect(patchBody, contains('"status":"online"'));
    expect(provider.isOnline, isTrue);
  });

  test('going offline never asks for location', () async {
    var locationChecks = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        if (request.method == 'PATCH') {
          return http.Response(
            jsonEncode({'rider': _rider(online: false)}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response(
          jsonEncode({'rider': _rider(online: true)}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final provider = RiderProvider(
      RiderService(api),
      locationCheck: () async {
        locationChecks++;
        return LocationReadiness.permissionDenied;
      },
    );
    await provider.loadProfile();
    expect(provider.isOnline, isTrue);

    final ok = await provider.toggleOnline();

    expect(ok, isTrue);
    expect(locationChecks, 0);
    expect(provider.isOnline, isFalse);
  });

  test('warns online riders when location is switched off mid-shift', () async {
    var readiness = LocationReadiness.serviceDisabled;
    final api = ApiClient(
      client: MockClient((request) async {
        return http.Response(
          jsonEncode({'rider': _rider(online: true)}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    final provider = RiderProvider(
      RiderService(api),
      locationMonitor: () async => readiness,
    );
    await provider.loadProfile();
    expect(provider.isOnline, isTrue);

    await provider.refreshLocationWarning();
    expect(provider.locationWarning, contains('Turn on your device location'));

    // Turning location back on clears the warning.
    readiness = LocationReadiness.ready;
    await provider.refreshLocationWarning();
    expect(provider.locationWarning, isNull);
  });

  test('offline riders never see a location warning', () async {
    var checks = 0;
    final api = ApiClient(
      client: MockClient((request) async {
        return http.Response(
          jsonEncode({'rider': _rider(online: false)}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    final provider = RiderProvider(
      RiderService(api),
      locationMonitor: () async {
        checks++;
        return LocationReadiness.serviceDisabled;
      },
    );
    await provider.loadProfile();

    await provider.refreshLocationWarning();

    expect(checks, 0);
    expect(provider.locationWarning, isNull);
  });
}
