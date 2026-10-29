import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/screens/apply/apply_screen.dart';
import 'package:invoize_rider/services/application_service.dart';

const _validCode = '123456';

MockClient _otpBackend() {
  return MockClient((http.BaseRequest request) async {
    Map<String, dynamic> body = {};
    try {
      body =
          jsonDecode((request as http.Request).body) as Map<String, dynamic>;
    } catch (_) {}
    final path = request.url.path;
    http.Response json(Object data, int status) => http.Response(
          jsonEncode(data),
          status,
          headers: {'content-type': 'application/json'},
        );
    if (path.endsWith('/rider/email/request-code') ||
        path.endsWith('/rider/email/resend')) {
      final email = (body['email'] ?? '') as String;
      if (!email.contains('@')) {
        return json({'message': 'The email field must be valid.'}, 422);
      }
      return json({'message': 'Verification code sent.'}, 200);
    }
    if (path.endsWith('/rider/email/verify')) {
      if (body['code'] == _validCode) {
        return json({
          'message': 'Email verified successfully.',
          'email': body['email']
        }, 200);
      }
      return json({
        'message': 'Invalid verification code. Please try again.',
        'errors': {
          'code': ['The verification code is incorrect.']
        }
      }, 422);
    }
    if (path.endsWith('/provinces')) {
      return json({
        'provinces': [
          {'id': 120000000, 'name': 'SOCCSKSARGEN'}
        ]
      }, 200);
    }
    if (!path.endsWith('/provinces') && path.endsWith('/municipalities')) {
      return json({
        'municipalities': [
          {'id': 1200100000, 'name': 'General Santos City'}
        ]
      }, 200);
    }
    if (path.endsWith('/barangays')) {
      return json({
        'barangays': [
          {'id': 1200100001, 'name': 'Barangay Poblacion'}
        ]
      }, 200);
    }
    return json({'message': 'not found'}, 404);
  });
}

Future<void> _pump(WidgetTester tester, MockClient backend) async {
  tester.view.physicalSize = const Size(1080, 1920);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
  });
  await tester.pumpWidget(MaterialApp(
    home: ApplyScreen(apiClient: ApiClient(client: backend)),
  ));
  await tester.pumpAndSettle();
}

Future<void> _fillPersonal(WidgetTester tester,
    {String email = 'otp.user@example.com'}) async {
  await tester.enterText(
      find.widgetWithText(TextField, 'First Name *'), 'Juan');
  await tester.enterText(
      find.widgetWithText(TextField, 'Last Name *'), 'Dela Cruz');
  await tester.enterText(
      find.widgetWithText(TextField, 'Email Address *'), email);
  await tester.pump();
  await tester.enterText(
      find.widgetWithText(TextField, 'Phone Number *  (+639 / 09)'),
      '09178881111');
  await tester.ensureVisible(find.text('Male'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Male'));
  await tester.pump();
  await tester.ensureVisible(find.text('Select date'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Select date'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
  await tester.enterText(
      find.widgetWithText(TextField, 'House No.'), '123');
  await tester.enterText(
      find.widgetWithText(TextField, 'Street'), 'Test St');
  await _selectAddress(tester);
}

/// Drives Province → City/Municipality → Barangay with the keyboard because
/// overlay menu-item taps are unreliable in the cascade. The dropdowns are
/// built progressively — index 0, then 1, then 2 in the tree.
Future<void> _selectAddress(WidgetTester tester) async {
  Future<void> pick(int index) async {
    await tester.ensureVisible(find.byType(DropdownButton<String>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButton<String>).at(index),
        warnIfMissed: false);
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

/// Tap Send and wait for the OTP section to appear.
Future<void> _sendCode(WidgetTester tester) async {
  await tester.ensureVisible(find.text('Send Verification Code'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Send Verification Code'));
  for (var i = 0; i < 10; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  expect(find.text('Verify Your Email'), findsOneWidget);
}

/// Enter a code and tap Verify, then pump enough for the async result
/// and SnackBar to appear.
Future<void> _verifyWith(WidgetTester tester, String code) async {
  await tester.ensureVisible(
      find.widgetWithText(TextField, 'Verification Code'));
  await tester.pumpAndSettle();
  await tester.enterText(
      find.widgetWithText(TextField, 'Verification Code'), code);
  await tester.pump();
  await tester.ensureVisible(find.text('Verify Email'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Verify Email'));
  for (var i = 0; i < 20; i++) {
    await tester.pump(const Duration(milliseconds: 200));
  }
}

void main() {
  test('ApplicationService OTP contract', () async {
    final requests = <http.BaseRequest>[];
    final svc = ApplicationService(ApiClient(
        client: MockClient((http.BaseRequest request) async {
      requests.add(request);
      return http.Response(jsonEncode({'message': 'ok', 'email': 'a@b.c'}),
          200,
          headers: {'content-type': 'application/json'});
    })));
    await svc.requestEmailCode(email: 'a@b.c', name: 'Ann');
    await svc.resendEmailCode(email: 'a@b.c');
    final verified =
        await svc.verifyEmailCode(email: 'a@b.c', code: '123456');
    expect(requests.map((r) => r.url.path).toList(), [
      endsWith('/rider/email/request-code'),
      endsWith('/rider/email/resend'),
      endsWith('/rider/email/verify'),
    ]);
    expect(verified, 'a@b.c');
  });

  testWidgets('send section renders with helper text', (tester) async {
    await _pump(tester, _otpBackend());
    expect(find.text('Send Verification Code'), findsOneWidget);
    expect(
        find.textContaining('send a verification code to this email'),
        findsOneWidget);
  });

  testWidgets('send requires a valid email first', (tester) async {
    await _pump(tester, _otpBackend());
    await tester.ensureVisible(find.text('Send Verification Code'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send Verification Code'));
    await tester.pumpAndSettle();
    expect(find.text('Please enter a valid email address first.'),
        findsOneWidget);
    expect(find.text('Verify Your Email'), findsNothing);
  });

  testWidgets('invalid OTP shows the backend message', (tester) async {
    await _pump(tester, _otpBackend());
    await _fillPersonal(tester);
    await _sendCode(tester);
    await _verifyWith(tester, '000000');
    expect(find.text('Invalid verification code. Please try again.'),
        findsOneWidget);
  });

  testWidgets('valid OTP verifies and Continue is no longer email-blocked',
      (tester) async {
    await _pump(tester, _otpBackend());
    await _fillPersonal(tester);
    await _sendCode(tester);
    await _verifyWith(tester, _validCode);

    // Let the success SnackBar render and settle.
    await tester.pumpAndSettle();
    expect(find.text('Email verified'), findsOneWidget);

    // The success SnackBar creates a full-screen overlay entry that
    // blocks taps on the Continue button below it. Clear the SnackBar
    // via the ScaffoldMessenger so the overlay entry is removed.
    final smState = tester.state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger));
    smState.clearSnackBars();
    await tester.pumpAndSettle();

    // Now Continue should be reachable and _validatePersonal should pass.
    await tester.ensureVisible(find.text('Continue'));
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Choose your Rider Type'), findsOneWidget);
  });

  testWidgets('Continue without verification is rejected', (tester) async {
    await _pump(tester, _otpBackend());
    await _fillPersonal(tester);
    await tester.ensureVisible(find.text('Continue'));
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Please verify your email address first.'),
        findsOneWidget);
    expect(find.text('Choose your Rider Type'), findsNothing);
  });

  testWidgets('changing email resets verification', (tester) async {
    await _pump(tester, _otpBackend());
    await _fillPersonal(tester);
    await _sendCode(tester);
    await _verifyWith(tester, _validCode);
    await tester.pumpAndSettle();
    expect(find.text('Email verified'), findsOneWidget);

    // Dismiss the "Email verified" SnackBar overlay before interacting.
    final smState = tester.state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger));
    smState.clearSnackBars();
    await tester.pumpAndSettle();

    await tester.enterText(
        find.widgetWithText(TextField, 'Email Address *'),
        'changed@example.com');
    await tester.pumpAndSettle();
    expect(find.text('Email verified'), findsNothing);
    expect(find.text('Send Verification Code'), findsOneWidget);

    await tester.ensureVisible(find.text('Continue'));
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(find.text('Please verify your email address first.'),
        findsOneWidget);
  });

  testWidgets('resend shows a 60s cooldown', (tester) async {
    await _pump(tester, _otpBackend());
    await tester.enterText(
        find.widgetWithText(TextField, 'Email Address *'),
        'otp.user@example.com');
    await tester.pump();
    await _sendCode(tester);
    expect(find.textContaining('Resend Code in'), findsOneWidget);

    // After 5 more seconds the cooldown should have decreased.
    final before = find.textContaining('Resend Code in').evaluate();
    final textBefore = (before.first.widget as Text).data;
    await tester.pump(const Duration(seconds: 5));
    final after = find.textContaining('Resend Code in').evaluate();
    final textAfter = (after.first.widget as Text).data;
    expect(textAfter, isNot(equals(textBefore)));
  });

  testWidgets('OTP section fits 360px width without overflow',
      (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(MaterialApp(
      home: ApplyScreen(apiClient: ApiClient(client: _otpBackend())),
    ));
    await tester.pumpAndSettle();
    await tester.enterText(
        find.widgetWithText(TextField, 'Email Address *'),
        'otp.user@example.com');
    await tester.pump();
    await _sendCode(tester);
    expect(tester.takeException(), isNull);
  });
}
