import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/screens/apply_center/apply_center_screen.dart';
import 'package:invoize_rider/screens/apply_center/center_application_status_screen.dart';
import 'package:invoize_rider/services/center_application_service.dart';

/// Fake doc picker — the real one resolves to native code at build time.
/// Yields [picked] docs in order, once per invocation (then null).
class _SeqPicker extends CenterDocPicker {
  const _SeqPicker(this.picked);
  final List<CenterPickedDoc> picked;
  @override
  Future<CenterPickedDoc?> pickSingle() async =>
      picked.isEmpty ? null : picked.removeAt(0);
}

CenterPickedDoc _doc(String name) => CenterPickedDoc(
  name: name,
  bytes: Uint8List.fromList(List.filled(64, 0x58)),
);

/// Backend for the center apply + status endpoints (PSGC data included so
/// the cascading address picker can be driven in widget tests).
MockClient _backend() {
  return MockClient((http.BaseRequest request) async {
    http.Response json(Object data, int status) => http.Response(
      jsonEncode(data),
      status,
      headers: {'content-type': 'application/json'},
    );
    final path = request.url.path;

    if (path.endsWith('/provinces')) {
      return json({
        'provinces': [
          {'id': 120000000, 'name': 'SOCCSKSARGEN', 'region_code': '120000000'},
        ],
      }, 200);
    }
    if (!path.endsWith('/provinces') && path.endsWith('/municipalities')) {
      return json({
        'municipalities': [
          {'id': 1200100000, 'name': 'General Santos City'},
        ],
      }, 200);
    }
    if (path.endsWith('/barangays')) {
      return json({
        'barangays': [
          {'id': 1200100001, 'name': 'Barangay Poblacion'},
        ],
      }, 200);
    }
    if (path.endsWith('/center/apply')) {
      return json({
        'message': 'Application submitted successfully.',
        'application': {'id': 7, 'status': 'pending', 'submitted_via': 'app'},
      }, 201);
    }
    if (path.endsWith('/center/application-status')) {
      final email = request.url.queryParameters['email'] ?? '';
      if (email != 'navy@example.com') {
        return json({'message': 'No application found.'}, 404);
      }
      return json({
        'application': {
          'id': 7,
          'business_name': 'Navy Logistics',
          'owner_name': 'Navy Owner',
          'email': email,
          'status': 'approved',
          'submitted_via': 'app',
          'created_at': '2026-09-12T00:00:00.000000Z',
          'reviewed_at': '2026-09-12T12:00:00.000000Z',
          'documents': [
            {'type': 'business_registration', 'name': 'dti.pdf'},
          ],
        },
      }, 200);
    }
    return json({'message': 'not found'}, 404);
  });
}

Future<void> _pump(
  WidgetTester tester,
  MockClient backend, {
  Widget? home,
}) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
  });
  await tester.pumpWidget(
    MaterialApp(
      home:
          home ??
          ApplyCenterScreen(
            apiClient: ApiClient(client: backend),
            picker: const _SeqPicker([]),
          ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('CenterApplicationService', () {
    test('submit posts multipart and parses the application id', () async {
      final captured = <http.Request>[];
      final svc = CenterApplicationService(
        ApiClient(
          client: MockClient((http.BaseRequest request) async {
            captured.add(request as http.Request);
            return http.Response(
              jsonEncode({
                'application': {
                  'id': 9,
                  'status': 'pending',
                  'submitted_via': 'app',
                },
              }),
              201,
              headers: {'content-type': 'application/json'},
            );
          }),
        ),
      );

      final result = await svc.submit(
        businessName: '  Navy   Logistics  ',
        ownerFirstName: '  Navy ',
        ownerMiddleName: ' S. ',
        ownerLastName: ' Owner ',
        secNumber: 'AB123456789',
        tin: '123-456-789-000',
        postalCode: '6000',
        region: '120000000',
        email: 'navy@example.com',
        phone: '0917-888-1111',
        address: "Baliwasan, Zamboanga City, Zamboanga del Sur",
        houseNumber: '12',
        street: 'Rizal Ave',
        barangay: 'Baliwasan',
        municipality: 'Zamboanga City',
        province: 'Zamboanga del Sur',
        documents: {
          'business_registration': (
            bytes: _doc('dti.pdf').bytes,
            filename: 'dti.pdf',
          ),
          'valid_id': (bytes: _doc('id.png').bytes, filename: 'id.png'),
        },
      );

      final req = captured.single;
      final ct = req.headers['content-type'] ?? '';
      expect(ct, startsWith('multipart/form-data; boundary='));
      final body = req.body;
      expect(body, contains('name="business_name"'));
      expect(body, contains('Navy Logistics'));
      expect(body, contains('name="owner_first_name"'));
      expect(body, contains('Navy'));
      expect(body, contains('name="owner_middle_name"'));
      expect(body, contains('S.'));
      expect(body, contains('name="owner_last_name"'));
      expect(body, contains('Owner'));
      expect(body, contains('name="sec_number"'));
      expect(body, contains('name="tin"'));
      expect(body, contains('name="postal_code"'));
      expect(body, contains('name="region"'));
      expect(body, contains('120000000'));
      expect(body, contains('name="phone"'));
      expect(body, contains('0917-888-1111'));
      expect(body, contains('name="province"'));
      expect(body, contains('Zamboanga del Sur'));
      expect(
        body,
        contains('name="documents[business_registration]"; filename="dti.pdf"'),
      );
      expect(body, contains('name="documents[valid_id]"; filename="id.png"'));
      expect(result.id, 9);
      expect(result.status, 'pending');
      expect(result.referenceNumber, 'CEN-2026-0009');
    });

    test('getStatus returns null when the backend 404s', () async {
      final svc = CenterApplicationService(ApiClient(client: _backend()));
      expect(await svc.getStatus('unknown@example.com'), isNull);
    });

    test('getStatus parses an approved application', () async {
      final svc = CenterApplicationService(ApiClient(client: _backend()));
      final app = await svc.getStatus('navy@example.com');
      expect(app, isNotNull);
      expect(app!.businessName, 'Navy Logistics');
      expect(app.status, 'approved');
      expect(app.referenceNumber, 'CEN-2026-0007');
      expect(app.documents.single.label, contains('BUSINESS'));
    });
  });

  group('ApplyCenterScreen', () {
    testWidgets('renders the center form fields', (tester) async {
      await _pump(tester, _backend());
      expect(find.text('Business Name *'), findsOneWidget);
      expect(find.text('Owner First Name *'), findsOneWidget);
      expect(find.text('Owner Last Name *'), findsOneWidget);
      expect(find.text('SEC Registration Number *'), findsOneWidget);
      expect(find.text('TIN * (000-000-000-000)'), findsOneWidget);
      expect(find.text('Postal Code *'), findsOneWidget);
      expect(find.text('Email *'), findsOneWidget);
      expect(find.text('Mobile Number *'), findsOneWidget);
      expect(find.text('Supporting Documents'), findsOneWidget);
    });

    testWidgets('submitting empty shows validation guidance', (tester) async {
      await _pump(tester, _backend());
      await tester.ensureVisible(find.text('Submit Application'));
      await tester.tap(find.text('Submit Application'));
      await tester.pump();
      expect(find.text('Please enter your business name.'), findsOneWidget);
    });

    testWidgets('submit posts the full application and shows the reference', (
      tester,
    ) async {
      await _pump(
        tester,
        _backend(),
        home: ApplyCenterScreen(
          apiClient: ApiClient(client: _backend()),
          picker: _SeqPicker([_doc('dti.pdf'), _doc('id.png')]),
        ),
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Business Name *'),
        'Navy Logistics',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Owner First Name *'),
        'Navy',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Owner Last Name *'),
        'Owner',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'SEC Registration Number *'),
        'AB123456789',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'TIN * (000-000-000-000)'),
        '123-456-789-000',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Postal Code *'),
        '6000',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Email *'),
        'navy@example.com',
      );
      await tester.enterText(
        find.widgetWithText(TextField, 'Mobile Number *'),
        '09178881111',
      );
      await tester.pump();

      // Select Province → City/Municipality → Barangay (dropdowns appear
      // progressively — 1, then 2, then 3 in the tree). The menu is driven
      // by keyboard because overlay taps are unreliable in the cascade.
      await tester.ensureVisible(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(
        find.byType(DropdownButton<String>).first,
        warnIfMissed: false,
      );
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(
        find.byType(DropdownButton<String>).at(1),
        warnIfMissed: false,
      );
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(
        find.byType(DropdownButton<String>).at(2),
        warnIfMissed: false,
      );
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
      await tester.pumpAndSettle();

      // Attach the required documents — business_registration first, then
      // valid_id (the first remaining "Upload" after each pick).
      await tester.ensureVisible(find.text('Upload').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload').at(0));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Upload').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload').at(0));
      await tester.pumpAndSettle();

      await tester.ensureVisible(find.text('Submit Application'));
      await tester.tap(find.text('Submit Application'));
      await tester.pumpAndSettle();

      expect(
        find.text('Your center application has been submitted!'),
        findsOneWidget,
      );
      expect(find.text('Reference number: CEN-2026-0007'), findsOneWidget);
    });
  });

  group('CenterApplicationStatusScreen', () {
    testWidgets('no email shows an error', (tester) async {
      await _pump(
        tester,
        _backend(),
        home: CenterApplicationStatusScreen(
          apiClient: ApiClient(client: _backend()),
        ),
      );
      await tester.ensureVisible(find.text('Check Status'));
      await tester.tap(find.text('Check Status'));
      await tester.pump();
      expect(find.text('Enter your email to check status.'), findsOneWidget);
    });

    testWidgets('approved application renders the status card', (tester) async {
      await _pump(
        tester,
        _backend(),
        home: CenterApplicationStatusScreen(
          email: 'navy@example.com',
          apiClient: ApiClient(client: _backend()),
        ),
      );
      expect(find.text('Congratulations!'), findsOneWidget);
      expect(find.text('CEN-2026-0007'), findsOneWidget);
      expect(find.text('APPROVED'), findsOneWidget);
      expect(find.textContaining('BUSINESS REGISTRATION'), findsOneWidget);
    });

    testWidgets('unknown email shows a not-found message', (tester) async {
      await _pump(
        tester,
        _backend(),
        home: CenterApplicationStatusScreen(
          email: 'unknown@example.com',
          apiClient: ApiClient(client: _backend()),
        ),
      );
      expect(find.text('No application found for this email.'), findsOneWidget);
    });
  });
}
