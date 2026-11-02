import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../lib/core/network/api_client.dart';
import '../lib/screens/apply/apply_screen.dart';
import 'support/psgc_fixture.dart';

class _FakeDocPicker implements DocFilePicker {
  const _FakeDocPicker();

  @override
  Future<PickedDoc?> pickSingle(List<String> allowedExtensions) async {
    final pdfHeader = <int>[0x25, 0x50, 0x44, 0x46, 0x2D, 0x31, 0x2E, 0x37];
    final bytes = Uint8List(1024);
    bytes.setRange(0, pdfHeader.length, pdfHeader);
    return PickedDoc(
      name: 'doc.pdf',
      readBytes: () async => bytes,
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  Future<void> selectAddress(WidgetTester tester) async {
    Future<void> pick(int index) async {
      await tester.ensureVisible(find.byType(DropdownButton<String>).first);
      await tester.pumpAndSettle();
      await tester.tap(
        find.byType(DropdownButton<String>).at(index),
        warnIfMissed: false,
      );
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.sendKeyEvent(LogicalKeyboardKey.arrowDown);
      await tester.pumpAndSettle();
      await tester.sendKeyEvent(LogicalKeyboardKey.enter);
      await tester.pumpAndSettle();
    }

    await pick(0);
    await pick(1);
    await pick(2);
    await pick(3);
  }

  Future<void> verifyEmail(WidgetTester tester) async {
    await tester.ensureVisible(find.text('Send Verification Code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send Verification Code'));
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await tester.enterText(
      find.widgetWithText(TextField, 'Verification Code'),
      '123456',
    );
    await tester.pump();
    await tester.ensureVisible(find.text('Verify Email'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Verify Email'));
    await tester.pumpAndSettle();
    final sm = tester.state<ScaffoldMessengerState>(
      find.byType(ScaffoldMessenger),
    );
    sm.clearSnackBars();
    await tester.pumpAndSettle();
  }

  testWidgets('Delivery Rider path: Carries forward role, shows Work Schedule, preserves full flow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    final capturedFields = <String, String>{};

    final client = ApiClient(
      client: MockClient((http.BaseRequest request) async {
        http.Response json(Object data, int status) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        if (path.endsWith('/vehicle-types')) {
          return json({
            'vehicle_types': [
              {'name': 'motorcycle', 'label': 'Motorcycle', 'capacity_kg': 20},
            ],
          }, 200);
        }
        if (path.endsWith('/rider/email/request-code') ||
            path.endsWith('/rider/email/resend')) {
          return json({'message': 'Verification code sent.'}, 200);
        }
        if (path.endsWith('/rider/email/verify')) {
          String email = 'del.rider@example.com';
          try {
            if (request is http.Request) {
              final parsed = jsonDecode(request.body) as Map;
              if (parsed['email'] != null) email = parsed['email'] as String;
            }
          } catch (_) {}
          return json({
            'message': 'Email verified successfully.',
            'email': email,
          }, 200);
        }
        if (path.endsWith('/rider/apply') && request.method == 'POST') {
          if (request is http.MultipartRequest) {
            capturedFields.addAll(request.fields);
          } else if (request is http.Request) {
            final body = request.body;
            final matchAppType = RegExp(r'name="application_type"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchAppType != null) capturedFields['application_type'] = matchAppType.group(1)!;
            final matchRiderType = RegExp(r'name="rider_type"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchRiderType != null) capturedFields['rider_type'] = matchRiderType.group(1)!;
            final matchOwnership = RegExp(r'name="vehicle_ownership"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchOwnership != null) capturedFields['vehicle_ownership'] = matchOwnership.group(1)!;
          }
          return json({'message': 'Application submitted.', 'id': 101, 'status': 'pending'}, 201);
        }
        final addressResponse = psgcFixture(request);
        if (addressResponse != null) return addressResponse;
        return json({'message': 'not found'}, 404);
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ApplyScreen(
          apiClient: client,
          picker: const _FakeDocPicker(),
          initialApplicationType: 'delivery_rider',
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Step 0: Personal
    await tester.enterText(find.widgetWithText(TextField, 'First Name *'), 'Juan');
    await tester.enterText(find.widgetWithText(TextField, 'Last Name *'), 'Dela Cruz');
    await tester.enterText(find.widgetWithText(TextField, 'Email Address *'), 'del.rider@example.com');
    await tester.enterText(find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'), '09171112222');
    await tester.tap(find.text('Male'));
    await tester.pump();
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '10');
    await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Rizal St');
    await selectAddress(tester);
    await tester.ensureVisible(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await verifyEmail(tester);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 1: Work Schedule
    // 1. Must NOT show repeated role selection cards
    expect(find.text('Choose your Rider Type'), findsNothing);
    // 2. Shows Work Schedule header and Selected Role
    expect(find.text('Work Schedule'), findsWidgets);
    expect(find.text('Selected Role'), findsOneWidget);
    expect(find.text('Delivery Rider (Customer Deliveries)'), findsOneWidget);
    // 3. Work Schedule options
    expect(find.text('Full-time Rider'), findsOneWidget);
    expect(find.text('Part-time Rider'), findsOneWidget);

    // Select Part-time Rider
    await tester.tap(find.text('Part-time Rider'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 2: Vehicle Information
    expect(find.text('Vehicle Information'), findsOneWidget);
    expect(find.text('License Plate *'), findsOneWidget);
    expect(find.text('License Number *'), findsOneWidget);
    expect(find.text('Vehicle Registration *'), findsOneWidget);
    expect(find.text('Company Fleet Assignment'), findsNothing);

    await tester.enterText(find.widgetWithText(TextField, 'License Plate *'), 'XYZ9876');
    await tester.enterText(find.widgetWithText(TextField, 'License Number *'), 'D01-99-888888');
    await tester.enterText(find.widgetWithText(TextField, 'Vehicle Registration *'), 'REG-123456');

    await tester.ensureVisible(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 3: Requirements
    expect(find.text('Requirements for Part-time Riders'), findsOneWidget);
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('OR/CR'), findsOneWidget);
    expect(find.text('Valid ID'), findsOneWidget);
    expect(find.text('Barangay Clearance'), findsOneWidget);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 4: Documents (Stage fake docs)
    expect(find.text('Upload Documents'), findsOneWidget);
    expect(find.text('Upload file'), findsWidgets);

    // Stage required docs
    while (find.text('Upload file').evaluate().isNotEmpty) {
      await tester.ensureVisible(find.text('Upload file').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload file').first);
      await tester.pumpAndSettle();
    }

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 5: Review
    expect(find.text('Review Your Application'), findsOneWidget);
    expect(find.text('ROLE & WORK SCHEDULE'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsWidgets);
    expect(find.text('Part-time Rider'), findsOneWidget);

    // Confirm terms and submit
    await tester.tap(find.byType(Checkbox));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Submit Application'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit Application'));
    await tester.pumpAndSettle();

    // Confirms success screen
    expect(find.text('Application Submitted'), findsOneWidget);

    // Verify backend payload
    expect(capturedFields['application_type'], 'delivery_rider');
    expect(capturedFields['rider_type'], 'part_time');
  });

  testWidgets('Hub Transport Driver path: Carries forward role, shows Work Schedule, preserves full flow', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    final capturedFields = <String, String>{};

    final client = ApiClient(
      client: MockClient((http.BaseRequest request) async {
        http.Response json(Object data, int status) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        if (path.endsWith('/vehicle-types')) {
          return json({
            'vehicle_types': [
              {'name': 'van', 'label': 'Van', 'capacity_kg': 500},
              {'name': 'truck', 'label': 'Truck', 'capacity_kg': 2000},
            ],
          }, 200);
        }
        if (path.endsWith('/rider/email/request-code') ||
            path.endsWith('/rider/email/resend')) {
          return json({'message': 'Verification code sent.'}, 200);
        }
        if (path.endsWith('/rider/email/verify')) {
          String email = 'hub.driver@example.com';
          try {
            if (request is http.Request) {
              final parsed = jsonDecode(request.body) as Map;
              if (parsed['email'] != null) email = parsed['email'] as String;
            }
          } catch (_) {}
          return json({
            'message': 'Email verified successfully.',
            'email': email,
          }, 200);
        }
        if (path.endsWith('/rider/apply') && request.method == 'POST') {
          if (request is http.MultipartRequest) {
            capturedFields.addAll(request.fields);
          } else if (request is http.Request) {
            final body = request.body;
            final matchAppType = RegExp(r'name="application_type"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchAppType != null) capturedFields['application_type'] = matchAppType.group(1)!;
            final matchRiderType = RegExp(r'name="rider_type"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchRiderType != null) capturedFields['rider_type'] = matchRiderType.group(1)!;
            final matchOwnership = RegExp(r'name="vehicle_ownership"\r\n\r\n([^\r\n]+)').firstMatch(body);
            if (matchOwnership != null) capturedFields['vehicle_ownership'] = matchOwnership.group(1)!;
          }
          return json({'message': 'Application submitted.', 'id': 102, 'status': 'pending'}, 201);
        }
        final addressResponse = psgcFixture(request);
        if (addressResponse != null) return addressResponse;
        return json({'message': 'not found'}, 404);
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ApplyScreen(
          apiClient: client,
          picker: const _FakeDocPicker(),
          initialApplicationType: 'hub_transport_driver',
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Step 0: Personal
    await tester.enterText(find.widgetWithText(TextField, 'First Name *'), 'Carlos');
    await tester.enterText(find.widgetWithText(TextField, 'Last Name *'), 'Mendoza');
    await tester.enterText(find.widgetWithText(TextField, 'Email Address *'), 'hub.driver@example.com');
    await tester.enterText(find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'), '09172223333');
    await tester.tap(find.text('Male'));
    await tester.pump();
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '88');
    await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Highway 1');
    await selectAddress(tester);
    await tester.ensureVisible(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await verifyEmail(tester);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 1: Work Schedule
    // 1. Must NOT show repeated role selection cards
    expect(find.text('Choose your Rider Type'), findsNothing);
    // 2. Shows Work Schedule header and Selected Role
    expect(find.text('Work Schedule'), findsWidgets);
    expect(find.text('Selected Role'), findsOneWidget);
    expect(find.text('Hub Transport Driver (Linehaul)'), findsOneWidget);
    // 3. Work Schedule options
    expect(find.text('Full-time Rider'), findsOneWidget);
    expect(find.text('Part-time Rider'), findsOneWidget);
    expect(find.text('Hub-to-Hub Transport Role'), findsOneWidget);

    // Keep Full-time Rider
    await tester.tap(find.text('Full-time Rider'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 2: License & Fleet Arrangement (Hub Transport Driver specific)
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('Company Fleet Assignment'), findsOneWidget);
    expect(find.text('Driver-Owned Vehicle (My Own Truck)'), findsOneWidget);
    expect(find.text('License Number *'), findsOneWidget);
    expect(find.text('Vehicle Registration *'), findsNothing);

    await tester.enterText(find.widgetWithText(TextField, 'License Number *'), 'N01-44-555555');

    await tester.ensureVisible(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 3: Requirements for Hub Transport Drivers
    expect(find.text('Requirements for Hub Transport Drivers'), findsOneWidget);
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('Valid Government ID'), findsOneWidget);
    expect(find.text('Police or Barangay Clearance'), findsOneWidget);
    expect(find.text('OR/CR'), findsNothing);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 4: Documents (Stage fake docs)
    expect(find.text('Upload Documents'), findsOneWidget);
    expect(find.text('Upload file'), findsWidgets);

    while (find.text('Upload file').evaluate().isNotEmpty) {
      await tester.ensureVisible(find.text('Upload file').first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload file').first);
      await tester.pumpAndSettle();
    }

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 5: Review
    expect(find.text('Review Your Application'), findsOneWidget);
    expect(find.text('ROLE & WORK SCHEDULE'), findsOneWidget);
    expect(find.text('Hub Transport Driver'), findsWidgets);
    expect(find.text('Full-time Rider'), findsOneWidget);
    expect(find.text('Company-Provided Truck'), findsOneWidget);

    // Confirm terms and submit
    await tester.tap(find.byType(Checkbox));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Submit Application'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Submit Application'));
    await tester.pumpAndSettle();

    // Confirms success screen
    expect(find.text('Application Submitted'), findsOneWidget);

    // Verify backend payload
    expect(capturedFields['application_type'], 'hub_transport_driver');
    expect(capturedFields['rider_type'], 'full_time');
    expect(capturedFields['vehicle_ownership'], 'company');
  });
}
