import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/scan/parcel_scan_controller.dart';
import '../lib/screens/scan/scan_parcel_screen.dart';
import '../lib/services/delivery_service.dart';

class _StubClient extends http.BaseClient {
  _StubClient(this.handler);
  final Future<http.Response> Function(http.BaseRequest) handler;
  int requestCount = 0;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    requestCount++;
    final response = await handler(request);
    final payload = utf8.encode(response.body);
    return http.StreamedResponse(
      Stream<List<int>>.value(Uint8List.fromList(payload)),
      response.statusCode,
      headers: {'content-type': 'application/json'},
    );
  }
}

Map<String, dynamic> _wireDelivery() => {
  'id': 101,
  'tracking_number': 'TRK-20260917-NKTV',
  'order_id': null,
  'status': 'assigned',
  'status_label': 'Assigned',
  'shop': {'name': 'Scan Shop', 'phone': '09170000001', 'address': '1 Scan St'},
  'customer': {
    'name': 'Scan Customer',
    'phone': '09170000002',
    'address': '2 Scan Ave',
  },
  'items': [],
  'subtotal': 0,
  'total': 0,
  'payment_method': null,
  'amount_to_collect': null,
  'pickup_pin_required': false,
};

ParcelScanController _controller(_StubClient client) => ParcelScanController(
  DeliveryService(ApiClient(client: client)),
  requestCameraPermission: () async => PermissionStatus.granted,
  isCameraPermanentlyDenied: () async => false,
  openSystemSettings: () async {},
);

void main() {
  group('manual tracking entry', () {
    test('valid manual tracking resolves through the same lookup', () async {
      final client = _StubClient(
        (_) async =>
            http.Response(jsonEncode({'delivery': _wireDelivery()}), 200),
      );
      final controller = _controller(client);
      addTearDown(controller.dispose);
      await controller.init();

      await controller.submitManual('  trk-20260917-nktv ');

      expect(client.requestCount, 1);
      expect(controller.state, ParcelScanState.found);
      expect(controller.scannedTracking, 'TRK-20260917-NKTV');
    });

    test('invalid manual tracking never calls the backend', () async {
      final client = _StubClient(
        (_) async =>
            http.Response(jsonEncode({'delivery': _wireDelivery()}), 200),
      );
      final controller = _controller(client);
      addTearDown(controller.dispose);
      await controller.init();

      await controller.submitManual('not-a-tracking-number');

      expect(client.requestCount, 0);
      expect(controller.state, ParcelScanState.invalid);
    });

    test('manual entry is single-shot while a lookup is in flight', () async {
      final client = _StubClient(
        (_) async =>
            http.Response(jsonEncode({'delivery': _wireDelivery()}), 200),
      );
      final controller = _controller(client);
      addTearDown(controller.dispose);
      await controller.init();

      final first = controller.submitManual('TRK-20260917-NKTV');
      await controller.submitManual('TRK-20260917-NKTV');
      await first;

      expect(client.requestCount, 1);
      expect(controller.state, ParcelScanState.found);
    });
  });

  testWidgets('scanner offers manual entry when the camera is unavailable', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final client = _StubClient(
      (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200),
    );
    final controller = _controller(client);
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>.value(
        value: AuthProvider(
          api: ApiClient(
            client: MockClient((_) async => http.Response('{}', 200)),
          ),
          storage: TokenStorage(),
        ),
        child: MaterialApp(
          home: ScanParcelScreen(cameraEnabled: false, controller: controller),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Enter tracking number'));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.widgetWithText(TextField, 'Tracking number'),
      'not-a-number',
    );
    await tester.tap(find.text('Look Up'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Invalid parcel QR code'), findsOneWidget);
    expect(client.requestCount, 0);
  });
}
