import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

// ignore_for_file: avoid_relative_lib_imports
// (Same checkout-wide package-config note as the other runnable tests.)
import '../lib/core/network/api_client.dart';
import '../lib/screens/apply/apply_screen.dart';

/// Backend for the RIDER apply wizard: email OTP + PSGC address data.
/// Vehicle types fall back to the built-in list when the endpoint 404s.
MockClient _applyBackend() {
  return MockClient((http.BaseRequest request) async {
    http.Response json(Object data, int status) => http.Response(
      jsonEncode(data),
      status,
      headers: {'content-type': 'application/json'},
    );
    final path = request.url.path;
    if (path.endsWith('/rider/email/request-code') ||
        path.endsWith('/rider/email/resend')) {
      return json({'message': 'Verification code sent.'}, 200);
    }
    if (path.endsWith('/rider/email/verify')) {
      return json({
        'message': 'Email verified successfully.',
        'email': 'juan.test@example.com',
      }, 200);
    }
    if (path.endsWith('/provinces')) {
      return json({
        'provinces': [
          {'id': 120000000, 'name': 'SOCCSKSARGEN'},
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
    return json({'message': 'not found'}, 404);
  });
}

/// Selects Province → City/Municipality → Barangay by keyboard (overlay
/// menu taps are unreliable in the cascade). Index 0, then 1, then 2.
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
}

/// Sends a code and verifies it, clearing the success SnackBar overlay so
/// the next tap on Continue is not blocked.
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
  testWidgets('ApplyScreen builds without box error', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: ApplyScreen()));
    await tester.pumpAndSettle();
    // Wizard step 1 header (spec title).
    expect(find.text('Apply as a Rider'), findsWidgets);
    expect(find.text('Tell us about yourself'), findsOneWidget);
  });

  testWidgets('Wizard validates, navigates and updates requirements', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(
      MaterialApp(
        home: ApplyScreen(apiClient: ApiClient(client: _applyBackend())),
      ),
    );
    await tester.pumpAndSettle();

    // Empty continue is blocked with a validation message.
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Please enter your first name.'), findsOneWidget);
    // Let the snackbar expire so it never covers the nav bar.
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    // Digits are rejected by the shared Unicode-safe person rule.
    await tester.enterText(
      find.widgetWithText(TextField, 'First Name *'),
      'Juan123',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Last Name *'),
      'Dela Cruz',
    );
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Please enter a valid first name.'), findsOneWidget);
    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    // Fill step 1 (personal).
    await tester.enterText(
      find.widgetWithText(TextField, 'First Name *'),
      'Juan',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Last Name *'),
      'Dela Cruz',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Email Address *'),
      'juan.test@example.com',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'),
      '09178881111',
    );
    await tester.tap(find.text('Male'));
    await tester.pump();
    await tester.enterText(find.widgetWithText(TextField, 'House No.'), '123');
    await tester.enterText(find.widgetWithText(TextField, 'Street'), 'Test St');
    await _selectAddress(tester);
    // Date of birth via the date picker (defaults to 20 years ago).
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
    expect(find.text('Choose your Rider Type'), findsOneWidget);

    // Switch to part-time, requirements must follow the switch.
    await tester.tap(find.text('Part-time Rider'));
    await tester.pump();
    await tester.ensureVisible(find.text('Continue'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Vehicle Information'), findsOneWidget);

    await tester.enterText(
      find.widgetWithText(TextField, 'License Plate *'),
      'ABC1234',
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'License Number *'),
      'L123456',
    );
    // Pick a license expiry date (future date via the date picker).
    await tester.ensureVisible(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Select date').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('OK'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextField, 'Vehicle Registration *'),
      'REG-1',
    );
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Requirements step reflects part-time list.
    expect(find.text('Application Requirements'), findsOneWidget);
    expect(find.text('Barangay Clearance'), findsOneWidget);
    expect(find.text('Police or Barangay Clearance'), findsNothing);

    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    // Documents step shows the dynamic upload cards.
    expect(find.text('Upload Documents'), findsOneWidget);
    expect(find.text("Driver's License"), findsWidgets);
    expect(find.text('OR/CR'), findsWidgets);
  });
}
