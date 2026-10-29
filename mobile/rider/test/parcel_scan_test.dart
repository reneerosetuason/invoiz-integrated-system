import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/core/storage/token_storage.dart';
import 'package:invoize_rider/providers/auth_provider.dart';
import 'package:invoize_rider/screens/scan/parcel_scan_controller.dart';
import 'package:invoize_rider/screens/scan/parcel_tracking.dart';
import 'package:invoize_rider/screens/scan/scan_parcel_screen.dart';
import 'package:invoize_rider/services/delivery_service.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Counting stub client so duplicate lookups are observable.
class _StubClient extends http.BaseClient {
  _StubClient({required this.handler});

  Future<http.Response> Function(http.BaseRequest request) handler;
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

Map<String, dynamic> _wireDelivery({String tracking = 'TRK-20260917-NKTV'}) => {
      'id': 101,
      'tracking_number': tracking,
      'order_id': null,
      'status': 'assigned',
      'status_label': 'Assigned',
      'shop': {'name': 'Scan Shop', 'phone': '09170000001', 'address': '1 Scan St'},
      'customer': {'name': 'Scan Customer', 'phone': '09170000002', 'address': '2 Scan Ave'},
      'items': [],
      'subtotal': 0,
      'total': 0,
      'payment_method': null,
      'amount_to_collect': null,
      'pickup_pin_required': false,
    };

DeliveryService _service(_StubClient client) =>
    DeliveryService(ApiClient(client: client));

ParcelScanController _controller(
  DeliveryService service, {
  PermissionStatus permission = PermissionStatus.granted,
  bool permanentlyDenied = false,
}) =>
    ParcelScanController(
      service,
      requestCameraPermission: () async => permission,
      isCameraPermanentlyDenied: () async => permanentlyDenied,
      openSystemSettings: () async {},
    );

void main() {
  group('ParcelTracking validation', () {
    test('accepts a well-formed INVOIZ tracking number', () {
      expect(ParcelTracking.isValid('TRK-20260917-NKTV'), isTrue);
      expect(ParcelTracking.validated('TRK-20260917-NKTV'),
          'TRK-20260917-NKTV');
    });

    test('normalizes lowercase and surrounding whitespace', () {
      expect(ParcelTracking.validated('  trk-20260917-nktv\n'),
          'TRK-20260917-NKTV');
    });

    test('rejects invalid payloads without a tracking number', () {
      expect(ParcelTracking.isValid(null), isFalse);
      expect(ParcelTracking.isValid(''), isFalse);
      expect(ParcelTracking.isValid('   '), isFalse);
      expect(ParcelTracking.isValid('HELLO-WORLD'), isFalse);
      expect(ParcelTracking.isValid('https://example.com/parcel/1'), isFalse);
      expect(ParcelTracking.isValid('TRK-2026-SHORT'), isFalse);
      expect(ParcelTracking.isValid('TRK-20260917-NKTV-EXTRA'), isFalse);
      expect(ParcelTracking.validated('not-a-qr'), isNull);
    });
  });

  group('DeliveryService.lookupByTracking', () {
    test('resolves the delivery for a known tracking number', () async {
      final client = _StubClient(handler: (request) async {
        expect(request.url.path, endsWith('/rider/deliveries/lookup'));
        expect(request.url.queryParameters['tracking_number'],
            'TRK-20260917-NKTV');
        return http.Response(jsonEncode({'delivery': _wireDelivery()}), 200);
      });

      final delivery =
          await _service(client).lookupByTracking('TRK-20260917-NKTV');

      expect(client.requestCount, 1);
      expect(delivery.id, 101);
      expect(delivery.trackingNumber, 'TRK-20260917-NKTV');
      expect(delivery.customer.name, 'Scan Customer');
    });

    test('maps 404 / 403 / 500 to typed errors', () async {
      Future<String> lookupType(int status) async {
        final client = _StubClient(
            handler: (_) async =>
                http.Response(jsonEncode({'message': 'nope'}), status));
        try {
          await _service(client).lookupByTracking('TRK-20260917-NKTV');
          return 'no-throw';
        } on Object catch (e) {
          return e.runtimeType.toString();
        }
      }

      expect(await lookupType(404), 'ApiException');
      expect(await lookupType(403), 'ApiException');
      expect(await lookupType(500), 'ApiException');
    });
  });

  group('ParcelScanController', () {
    test('granted permission enters scanning', () async {
      final client = _StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200));
      final controller = _controller(_service(client));

      await controller.init();

      expect(controller.state, ParcelScanState.scanning);
      expect(client.requestCount, 0);
      controller.dispose();
    });

    test('denied permission surfaces guidance state', () async {
      final client = _StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200));
      final controller = _controller(
        _service(client),
        permission: PermissionStatus.permanentlyDenied,
        permanentlyDenied: true,
      );

      await controller.init();

      expect(controller.state, ParcelScanState.permissionDenied);
      expect(controller.cameraPermanentlyDenied, isTrue);
      controller.dispose();
    });

    test('invalid payload is rejected without any lookup', () async {
      final client = _StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200));
      final controller = _controller(_service(client));
      await controller.init();

      await controller.handleDetection('https://example.com/x');

      expect(controller.state, ParcelScanState.invalid);
      expect(client.requestCount, 0);

      controller.resume();
      expect(controller.state, ParcelScanState.scanning);
      controller.dispose();
    });

    test('duplicate detections trigger a single lookup', () async {
      final client = _StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200));
      final controller = _controller(_service(client));
      await controller.init();

      // Second detection arrives while the first lookup is in flight.
      final first = controller.handleDetection('TRK-20260917-NKTV');
      await controller.handleDetection('TRK-20260917-NKTV');
      await first;

      expect(controller.state, ParcelScanState.found);
      expect(controller.delivery?.trackingNumber, 'TRK-20260917-NKTV');
      expect(client.requestCount, 1);
      controller.dispose();
    });

    test('backend outcomes map to notFound / forbidden / networkError', () async {
      Future<ParcelScanState> stateFor(int status) async {
        final client = _StubClient(
            handler: (_) async =>
                http.Response(jsonEncode({'message': 'x'}), status));
        final controller = _controller(_service(client));
        await controller.init();
        await controller.handleDetection('TRK-20260917-NKTV');
        final state = controller.state;
        controller.dispose();
        return state;
      }

      expect(await stateFor(404), ParcelScanState.notFound);
      expect(await stateFor(403), ParcelScanState.forbidden);
      expect(await stateFor(500), ParcelScanState.networkError);
    });
  });

  group('ScanParcelScreen widget', () {
    Future<void> pumpScan(
      WidgetTester tester,
      ParcelScanController controller,
    ) async {
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: MockClient((_) async => http.Response('{}', 200))),
            storage: TokenStorage(),
          ),
          child: MaterialApp(
            home: ScanParcelScreen(
              cameraEnabled: false,
              controller: controller,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('scanner screen opens with instructions', (tester) async {
      final service = _service(_StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200)));
      final controller = _controller(service);
      addTearDown(controller.dispose);

      await pumpScan(tester, controller);

      expect(find.text('Scan Parcel'), findsOneWidget);
      expect(
          find.text('Scan the QR code on the parcel label'), findsOneWidget);
    });

    testWidgets('permission denial shows guidance with retry', (tester) async {
      final service = _service(_StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200)));
      final controller = _controller(
        service,
        permission: PermissionStatus.denied,
      );
      addTearDown(controller.dispose);

      await pumpScan(tester, controller);

      expect(find.textContaining('Camera access is needed'), findsOneWidget);
      expect(find.text('Try Again'), findsOneWidget);
    });

    testWidgets('invalid scan shows rescan card', (tester) async {
      final service = _service(_StubClient(handler: (_) async =>
          http.Response(jsonEncode({'delivery': _wireDelivery()}), 200)));
      final controller = _controller(service);
      addTearDown(controller.dispose);

      await pumpScan(tester, controller);
      await controller.handleDetection('not-a-qr');
      await tester.pumpAndSettle();

      expect(find.textContaining('Invalid parcel QR code'), findsOneWidget);
      expect(find.text('Scan Again'), findsOneWidget);
    });
  });
}
