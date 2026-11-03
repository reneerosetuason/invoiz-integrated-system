import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'support/psgc_fixture.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/screens/apply/application_type_select_screen.dart';
import '../lib/screens/apply/apply_screen.dart';
import '../lib/screens/apply/hub_transport_driver_apply_screen.dart';

MockClient _hubApplyBackend() {
  return MockClient((http.BaseRequest request) async {
    http.Response json(Object data, int status) => http.Response(
      jsonEncode(data),
      status,
      headers: {'content-type': 'application/json'},
    );
    final path = request.url.path;
    if (path.endsWith('/vehicle-types')) {
      return json({
        'vehicle_types': [
          {'name': 'motorcycle', 'label': 'Motorcycle', 'capacity_kg': 30},
          {'name': 'tricycle', 'label': 'Tricycle', 'capacity_kg': 100},
        ],
      }, 200);
    }
    if (path.endsWith('/rider/email/request-code') ||
        path.endsWith('/rider/email/resend')) {
      return json({'message': 'Verification code sent.'}, 200);
    }
    if (path.endsWith('/rider/email/verify')) {
      return json({
        'message': 'Email verified successfully.',
        'email': 'driver.hub@example.com',
      }, 200);
    }
    final addressResponse = psgcFixture(request);
    if (addressResponse != null) return addressResponse;
    return json({'message': 'not found'}, 404);
  });
}

Future<void> _selectAddress(WidgetTester tester) async {
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

Future<void> _verifyEmail(WidgetTester tester) async {
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

void main() {
  testWidgets('ApplicationTypeSelectScreen renders role choices and navigates', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: ApplicationTypeSelectScreen(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Choose Application Type'), findsOneWidget);
    expect(find.text('Join the INVOIZ Fleet'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Hub Transport Driver'), findsOneWidget);
    expect(find.text('2-Wheel / Motorcycle'), findsOneWidget);
    expect(find.text('Four-Wheel Fleet Truck'), findsOneWidget);
    expect(find.text('Apply as Delivery Rider'), findsOneWidget);
    expect(find.text('Apply as Hub Transport Driver'), findsOneWidget);

    // Scroll to Hub Transport Driver button and tap
    await tester.ensureVisible(find.text('Apply as Hub Transport Driver'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Apply as Hub Transport Driver'));
    await tester.pumpAndSettle();

    // Reaches apply form
    expect(find.text('Tell us about yourself'), findsOneWidget);
  });

  testWidgets('Hub Transport Driver application does not require motorcycle info and collects driver license', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    final client = ApiClient(
      client: _hubApplyBackend(),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ApplyScreen(
          apiClient: client,
          initialApplicationType: 'hub_transport_driver',
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Step 1: Personal / Driver information
    await tester.enterText(
      find.widgetWithText(TextField, 'First Name *'),
      'Carlos',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Last Name *'),
      'Reyes',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Email Address *'),
      'driver.hub@example.com',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'),
      '09171234567',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'House No.'),
      '123',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Street'),
      'Linehaul Way',
    );
    await _selectAddress(tester);

    await tester.tap(find.text('Male'));
    await tester.pump();

    await tester.ensureVisible(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await _verifyEmail(tester);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 2: Work Schedule step (already hub_transport_driver from initialApplicationType)
    expect(find.text('Work Schedule'), findsWidgets);
    expect(find.text('Full-time Rider'), findsOneWidget);
    expect(find.text('Part-time Rider'), findsOneWidget);
    expect(find.text('Hub-to-Hub Transport Role'), findsOneWidget);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 3: Driver License Information (NOT Motorcycle Vehicle Information)
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('Company Fleet Assignment'), findsOneWidget);
    expect(find.text('License Number *'), findsOneWidget);
    expect(find.text('License Expiry Date *'), findsOneWidget);

    // Motorcycle/Vehicle fields must NOT be present
    expect(find.text('Plate Number *'), findsNothing);
    expect(find.text('License Plate *'), findsNothing);
    expect(find.text('Vehicle Registration *'), findsNothing);
    expect(find.text('Vehicle Ownership *'), findsNothing);
    expect(find.text('Vehicle Type *'), findsNothing);

    // Validation prevents empty license submission
    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    expect(find.text('Please enter the license number.'), findsOneWidget);

    // Enter valid driver's license
    await tester.enterText(
      find.widgetWithText(TextField, 'License Number *'),
      'N01-99-123456',
    );

    // Pick license expiry
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

    // Step 4: Requirements step (Driver's License, Valid Government ID, Clearance - no OR/CR)
    expect(find.text('Requirements for Hub Transport Drivers'), findsOneWidget);
    expect(find.text("Driver's License"), findsOneWidget);
    expect(find.text('Valid Government ID'), findsOneWidget);
    expect(find.text('OR/CR'), findsNothing);
  });

  testWidgets('Hub Transport Driver can select Driver-Owned Vehicle and provide truck info', (tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    final client = ApiClient(
      client: _hubApplyBackend(),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ApplyScreen(
          apiClient: client,
          initialApplicationType: 'hub_transport_driver',
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Fill Step 1 personal info
    await tester.enterText(find.widgetWithText(TextField, 'First Name *'), 'Carlos');
    await tester.enterText(find.widgetWithText(TextField, 'Last Name *'), 'Santos');
    await tester.enterText(find.widgetWithText(TextField, 'Email Address *'), 'driver.hub@example.com');
    await tester.enterText(find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'), '09171234567');
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '123');
    await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Linehaul Way');
    await _selectAddress(tester);

    await tester.tap(find.text('Male'));
    await tester.pump();

    await tester.ensureVisible(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await _verifyEmail(tester);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 2: Role confirmation
    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 3: Vehicle Arrangement selection
    expect(find.text('Company Fleet Assignment'), findsOneWidget);
    expect(find.text('Driver-Owned Vehicle (My Own Truck)'), findsOneWidget);

    // Tap Driver-Owned Vehicle
    await tester.tap(find.text('Driver-Owned Vehicle (My Own Truck)'));
    await tester.pumpAndSettle();

    // Now 4-wheel vehicle fields appear
    expect(find.text('Four-Wheel Vehicle Type *'), findsOneWidget);
    expect(find.text('License Plate *'), findsOneWidget);
    expect(find.text('Vehicle Registration (OR/CR Number) *'), findsOneWidget);

    // Enter plate and registration
    await tester.enterText(find.widgetWithText(TextField, 'License Plate *'), 'NBC 5678');
    await tester.enterText(find.widgetWithText(TextField, 'Vehicle Registration (OR/CR Number) *'), 'ORCR-987654');
    await tester.enterText(find.widgetWithText(TextField, 'License Number *'), 'N01-88-999999');

    // Pick license expiry
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

    // Step 4: Requirements step now includes OR/CR!
    expect(find.text('Requirements for Hub Transport Drivers'), findsOneWidget);
    expect(find.text('OR/CR (Vehicle Registration)'), findsOneWidget);
  });

  testWidgets('ApplicationTypeSelectScreen navigates Delivery Rider to ApplyScreen preserving existing flow', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: ApplicationTypeSelectScreen(),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Apply as a Rider'), findsOneWidget);
    expect(find.text('Delivery Rider'), findsOneWidget);
    expect(find.text('Handles seller pickups.'), findsOneWidget);
    expect(find.text('Handles last-mile deliveries to buyers.'), findsOneWidget);
    expect(find.text('Uses the Delivery Rider application and workflow.'), findsOneWidget);

    // Tap Apply as Delivery Rider
    await tester.tap(find.text('Apply as Delivery Rider'));
    await tester.pumpAndSettle();

    // Directly in existing ApplyScreen personal step
    expect(find.text('Apply as a Rider'), findsWidgets);
    expect(find.text('Tell us about yourself'), findsOneWidget);
  });

  testWidgets('HubTransportDriverApplyScreen enforces company-provided truck without OR/CR', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });

    final client = ApiClient(client: _hubApplyBackend());

    await tester.pumpWidget(
      MaterialApp(
        home: HubTransportDriverApplyScreen(apiClient: client),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Apply as Hub Transport Driver'), findsWidgets);
    expect(find.text('Tell us about yourself'), findsOneWidget);

    // Fill Step 0 personal info
    await tester.enterText(find.widgetWithText(TextField, 'First Name *'), 'Marco');
    await tester.enterText(find.widgetWithText(TextField, 'Last Name *'), 'Valdez');
    await tester.enterText(find.widgetWithText(TextField, 'Email Address *'), 'driver.hub@example.com');
    await tester.enterText(find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'), '09171234567');
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '456');
    await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Expressway Blvd');
    await _selectAddress(tester);

    await tester.tap(find.text('Male'));
    await tester.pump();

    await tester.ensureVisible(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await _verifyEmail(tester);

    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 1: Vehicle Arrangement (Company-provided truck vs Driver-owned truck)
    expect(find.text('Vehicle Arrangement'), findsOneWidget);
    expect(find.text('Company-provided truck'), findsOneWidget);
    expect(find.text('Driver-owned truck'), findsOneWidget);

    // Continue with default Company-provided truck
    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Step 2: License & Truck Details - Company truck info notice shown, no OR/CR
    expect(find.text("Driver's License & Truck Details"), findsOneWidget);
    expect(find.text('License Number *'), findsOneWidget);
    expect(find.text('Four-Wheel Vehicle Type *'), findsNothing);
    expect(find.text('Vehicle Registration (OR/CR Number) *'), findsNothing);

    // Enter license
    await tester.enterText(find.widgetWithText(TextField, 'License Number *'), 'N01-77-888888');
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
    expect(find.text('OR/CR (Vehicle Registration)'), findsNothing);
  });
}
