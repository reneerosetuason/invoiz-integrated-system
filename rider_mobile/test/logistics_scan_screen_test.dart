import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/logistics/logistics_scan_screen.dart';
import '../lib/screens/scan/parcel_tracking.dart';

const _tracking = 'TRK-20260101-ABCD';

http.Response _json(Map<String, dynamic> body, {int status = 200}) =>
    http.Response(
      jsonEncode(body),
      status,
      headers: {'content-type': 'application/json'},
    );

Map<String, dynamic> _account() => {
  'account_type': 'logistics_staff',
  'role': 'logistics_staff',
  'user': {
    'id': 42,
    'name': 'Calamba Staff',
    'email': 'calamba.staff@test.local',
    'phone': '09171234567',
    'role': 'logistics_staff',
    'account_type': 'logistics_staff',
    'logistics': {
      'role': 'logistics_staff',
      'scope': 'hub',
      'can_scan': true,
      'company': {'id': 3, 'name': 'INVOIZ Logistics'},
      'facility': {
        'id': 9,
        'name': 'Calamba Hub',
        'address': '1 Calamba Road',
        'city': 'Calamba',
      },
    },
  },
  'logistics': {
    'role': 'logistics_staff',
    'scope': 'hub',
    'can_scan': true,
    'company': {'id': 3, 'name': 'INVOIZ Logistics'},
    'facility': {
      'id': 9,
      'name': 'Calamba Hub',
      'address': '1 Calamba Road',
      'city': 'Calamba',
    },
  },
};

Map<String, dynamic> _parcel({String parcelStatus = 'received'}) => {
  'tracking_number': _tracking,
  'recipient_name': 'Test Recipient',
  'recipient_address': '2 Test Avenue',
  'parcel_status': parcelStatus,
  'status': 'waiting_for_rider',
  'current_hub': {'id': 9, 'name': 'Calamba Hub'},
  'destination_hub': {'id': 10, 'name': 'Batangas Hub'},
  'service_area': 'Calamba',
  'weight': '1.50',
  'scanned_at': null,
};

Map<String, dynamic> _eligible() => {
  'ok': true,
  'eligible': true,
  'already': false,
  'error': null,
  'message': 'Parcel is ready to scan.',
  'facility': {'id': 9, 'name': 'Calamba Hub'},
  'delivery': _parcel(),
  'history': <Object>[],
};

Map<String, dynamic> _confirmed() => {
  'ok': true,
  'eligible': false,
  'already': false,
  'error': null,
  'message': 'Parcel scanned successfully.',
  'facility': {'id': 9, 'name': 'Calamba Hub'},
  'delivery': _parcel(parcelStatus: 'scanned'),
  'history': [
    {
      'status': 'scanned',
      'notes': 'Parcel scanned and verified via mobile scanner.',
      'location': 'Calamba Hub',
      'actor': 'Calamba Staff',
      'created_at': '2026-01-01T10:00:00+00:00',
    },
  ],
};

/// Records the scan API traffic so tests can assert the app sends the
/// canonical tracking number to the single lookup/confirm pair.
class _ScanCalls {
  final List<String> paths = [];
  final List<Map<String, dynamic>> lookupBodies = [];
  final List<Map<String, dynamic>> confirmBodies = [];

  int get confirmCalls =>
      paths.where((p) => p.endsWith('/logistics/scan/confirm')).length;
}

MockClient _scanClient(
  _ScanCalls calls, {
  int lookupStatus = 200,
  Map<String, dynamic>? lookupBody,
}) {
  return MockClient((http.Request request) async {
    final path = request.url.path;
    calls.paths.add(path);

    if (path.endsWith('/logistics/scan/context')) {
      return _json(_account());
    }

    final body = request.body.isEmpty
        ? <String, dynamic>{}
        : Map<String, dynamic>.from(jsonDecode(request.body) as Map);

    if (path.endsWith('/logistics/scan/lookup')) {
      calls.lookupBodies.add(body);
      if (lookupStatus != 200) {
        return _json(
          lookupBody ?? const {'message': 'Forbidden.'},
          status: lookupStatus,
        );
      }
      return _json(_eligible());
    }

    if (path.endsWith('/logistics/scan/confirm')) {
      calls.confirmBodies.add(body);
      return _json(_confirmed());
    }

    return http.Response('{}', 404);
  });
}

Future<void> _pumpScanner(WidgetTester tester, http.Client client) async {
  SharedPreferences.setMockInitialValues({});
  final auth = AuthProvider(
    api: ApiClient(client: client),
    storage: TokenStorage(),
  );
  addTearDown(auth.dispose);

  await tester.pumpWidget(
    ChangeNotifierProvider<AuthProvider>.value(
      value: auth,
      child: const MaterialApp(home: LogisticsScanScreen(cameraEnabled: false)),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _lookupManually(WidgetTester tester, String value) async {
  await tester.tap(find.text('Enter tracking number'));
  await tester.pumpAndSettle();
  await tester.enterText(
    find.widgetWithText(TextField, 'Tracking number'),
    value,
  );
  await tester.tap(find.text('Look Up'));
  await tester.pumpAndSettle();
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('waybill barcode payloads', () {
    test('the Code128 value resolves to the canonical tracking number', () {
      // The single label barcode (CODE128) encodes `$delivery->tracking_number`,
      // so the scanned value is the existing backend identifier.
      const barcodeValue = _tracking;

      expect(ParcelTracking.validated(barcodeValue), _tracking);
    });

    test('scanned values normalize safely before lookup', () {
      expect(ParcelTracking.validated('  trk-20260101-abcd\n'), _tracking);
    });

    test('arbitrary URL payloads are rejected and never followed', () {
      expect(
        ParcelTracking.validated('https://example.com/parcel/$_tracking'),
        isNull,
      );
      expect(
        ParcelTracking.validated('https://evil.test/?tracking=$_tracking'),
        isNull,
      );
      expect(ParcelTracking.validated('javascript:alert(1)'), isNull);
    });
  });

  group('LogisticsScanScreen', () {
    testWidgets('manual lookup shows details and requires explicit confirm', (
      tester,
    ) async {
      final calls = _ScanCalls();
      await _pumpScanner(tester, _scanClient(calls));

      expect(find.text('Logistics Scanner'), findsOneWidget);
      expect(find.text('Calamba Hub'), findsOneWidget);

      await _lookupManually(tester, '  trk-20260101-abcd ');

      // The app sends the canonical tracking number and no facility data.
      expect(calls.lookupBodies, hasLength(1));
      expect(calls.lookupBodies.single['tracking_number'], _tracking);
      expect(calls.lookupBodies.single.containsKey('facility_id'), isFalse);
      expect(calls.lookupBodies.single.containsKey('center_id'), isFalse);

      expect(find.text('Parcel ready to scan'), findsOneWidget);
      expect(find.text('Test Recipient'), findsOneWidget);
      expect(find.text('Confirm Scan'), findsOneWidget);

      // Nothing has been confirmed yet; the lookup alone wrote nothing.
      expect(calls.confirmCalls, 0);

      await tester.tap(find.text('Confirm Scan'));
      await tester.pumpAndSettle();

      expect(calls.confirmCalls, 1);
      expect(calls.confirmBodies.single['tracking_number'], _tracking);
      expect(calls.confirmBodies.single.containsKey('facility_id'), isFalse);
      expect(find.text('Scan recorded'), findsOneWidget);
    });

    testWidgets(
      'unauthorized parcel shows the backend message without confirm',
      (tester) async {
        final calls = _ScanCalls();
        await _pumpScanner(
          tester,
          _scanClient(
            calls,
            lookupStatus: 403,
            lookupBody: const {
              'ok': false,
              'error': 'forbidden',
              'message': 'This parcel belongs to another logistics hub.',
            },
          ),
        );

        await _lookupManually(tester, _tracking);

        expect(
          find.text('This parcel belongs to another logistics hub.'),
          findsOneWidget,
        );
        expect(find.text('Confirm Scan'), findsNothing);
        expect(calls.confirmCalls, 0);
      },
    );
  });
}
