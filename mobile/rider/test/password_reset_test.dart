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
import '../lib/screens/auth/forgot_password_screen.dart';
import '../lib/screens/auth/login_screen.dart';
import '../lib/services/auth_service.dart';

const _genericOk =
    'If an active rider account exists for that email address, a password reset link has been sent.';
const _invalidTokenMsg =
    'This password reset link is invalid or has expired.';
const _resetOk = 'Password reset successfully.';

MockClient _backend({int forgotStatus = 200, int resetStatus = 200}) {
  http.Response json(Object data, int status) => http.Response(
        jsonEncode(data),
        status,
        headers: {'content-type': 'application/json'},
      );
  return MockClient((http.BaseRequest request) async {
    final path = request.url.path;
    if (path.endsWith('/rider/password/forgot')) {
      return json({'message': _genericOk}, forgotStatus);
    }
    if (path.endsWith('/rider/password/reset')) {
      if (resetStatus != 200) {
        return json({'message': _invalidTokenMsg}, resetStatus);
      }
      return json({'message': _resetOk}, 200);
    }
    return json({}, 404);
  });
}

AuthService _service(MockClient backend) =>
    AuthService(ApiClient(client: backend));

Future<void> _pumpReset(WidgetTester tester, MockClient backend,
    {String? email, String? token}) async {
  SharedPreferences.setMockInitialValues({});
  await tester.pumpWidget(
    ChangeNotifierProvider<AuthProvider>.value(
      value: AuthProvider(
        api: ApiClient(client: backend),
        storage: TokenStorage(),
      ),
      child: MaterialApp(
        home: ForgotPasswordScreen(initialEmail: email, initialToken: token),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('AuthService password reset', () {
    test('request posts the email and returns the generic message',
        () async {
      var posted = <String, dynamic>{};
      final backend = MockClient((http.BaseRequest request) async {
        posted = jsonDecode((request as http.Request).body);
        return http.Response(jsonEncode({'message': _genericOk}), 200,
            headers: {'content-type': 'application/json'});
      });
      final message = await _service(backend)
          .requestPasswordReset(email: 'rider@invoiz.test');
      expect(posted['email'], 'rider@invoiz.test');
      expect(message, _genericOk);
    });

    test('reset posts email, token and confirmed password', () async {
      var posted = <String, dynamic>{};
      final backend = MockClient((http.BaseRequest request) async {
        posted = jsonDecode((request as http.Request).body);
        return http.Response(jsonEncode({'message': _resetOk}), 200,
            headers: {'content-type': 'application/json'});
      });
      final message = await _service(backend).resetPassword(
        email: 'rider@invoiz.test',
        token: 'tok',
        newPassword: 'new-password-123',
      );
      expect(posted['email'], 'rider@invoiz.test');
      expect(posted['token'], 'tok');
      expect(posted['password'], 'new-password-123');
      expect(posted['password_confirmation'], 'new-password-123');
      expect(message, _resetOk);
    });
  });

  group('ForgotPasswordScreen request step', () {
    testWidgets('validates the email before requesting', (tester) async {
      await _pumpReset(tester, _backend());

      await tester.tap(find.text('Send Reset Link'));
      await tester.pumpAndSettle();
      expect(find.text('Please enter your email.'), findsOneWidget);

      await tester.enterText(
          find.widgetWithText(TextField, 'you@example.com'), 'bad');
      await tester.tap(find.text('Send Reset Link'));
      await tester.pumpAndSettle();
      expect(
          find.text('Please enter a valid email address.'), findsOneWidget);
    });

    testWidgets('success shows the generic confirmation', (tester) async {
      await _pumpReset(tester, _backend());

      await tester.enterText(
          find.widgetWithText(TextField, 'you@example.com'), 'rider@invoiz.test');
      await tester.tap(find.text('Send Reset Link'));
      await tester.pumpAndSettle();

      expect(find.text('Check your email'), findsOneWidget);
      expect(find.text(_genericOk), findsOneWidget);
      expect(find.text('I Have a Reset Code'), findsOneWidget);
    });

    testWidgets('code entry opens from the confirmation', (tester) async {
      await _pumpReset(tester, _backend());

      await tester.enterText(
          find.widgetWithText(TextField, 'you@example.com'), 'rider@invoiz.test');
      await tester.tap(find.text('Send Reset Link'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('I Have a Reset Code'));
      await tester.pumpAndSettle();

      expect(find.text('RESET CODE'), findsOneWidget);
      expect(find.text('NEW PASSWORD'), findsOneWidget);
    });
  });

  group('ForgotPasswordScreen reset step', () {
    testWidgets('deep link prefill starts at the code step', (tester) async {
      await _pumpReset(tester, _backend(),
          email: 'rider@invoiz.test', token: 'tok123');

      expect(find.text('RESET CODE'), findsOneWidget);
      expect(find.text('tok123'), findsOneWidget);
    });

    testWidgets('short passwords are rejected', (tester) async {
      await _pumpReset(tester, _backend(),
          email: 'rider@invoiz.test', token: 'tok123');

      await tester.enterText(
          find.widgetWithText(TextField, 'At least 8 characters'), 'short');
      await tester.enterText(
          find.widgetWithText(TextField, 'Repeat the new password'), 'short');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Reset Password'));
      await tester.pumpAndSettle();
      expect(find.text('Password must be at least 8 characters.'),
          findsOneWidget);
    });

    testWidgets('mismatched passwords are rejected', (tester) async {
      await _pumpReset(tester, _backend(),
          email: 'rider@invoiz.test', token: 'tok123');

      await tester.enterText(
          find.widgetWithText(TextField, 'At least 8 characters'),
          'long-enough-1');
      await tester.enterText(
          find.widgetWithText(TextField, 'Repeat the new password'),
          'different-2');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Reset Password'));
      await tester.pumpAndSettle();
      expect(find.text('Passwords do not match.'), findsOneWidget);
    });

    testWidgets('successful reset shows confirmation', (tester) async {
      await _pumpReset(tester, _backend(),
          email: 'rider@invoiz.test', token: 'tok123');

      await tester.enterText(
          find.widgetWithText(TextField, 'At least 8 characters'),
          'new-password-123');
      await tester.enterText(
          find.widgetWithText(TextField, 'Repeat the new password'),
          'new-password-123');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Reset Password'));
      await tester.pumpAndSettle();

      expect(find.text('Password reset'), findsOneWidget);
      expect(find.text(_resetOk), findsOneWidget);
      expect(find.text('Back to Login'), findsOneWidget);
    });

    testWidgets('invalid token surfaces the generic backend message',
        (tester) async {
      await _pumpReset(tester, _backend(resetStatus: 422),
          email: 'rider@invoiz.test', token: 'stale');

      await tester.enterText(
          find.widgetWithText(TextField, 'At least 8 characters'),
          'new-password-123');
      await tester.enterText(
          find.widgetWithText(TextField, 'Repeat the new password'),
          'new-password-123');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Reset Password'));
      await tester.pumpAndSettle();

      expect(find.text(_invalidTokenMsg), findsOneWidget);
      expect(find.text('Password reset'), findsNothing);
    });
  });

  group('Login sheet reset entry', () {
    testWidgets('sheet offers reset via email and opens the screen',
        (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      SharedPreferences.setMockInitialValues({});
      await tester.pumpWidget(
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(
            api: ApiClient(client: _backend()),
            storage: TokenStorage(),
          ),
          child: const MaterialApp(home: LoginScreen()),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Forgot password?'));
      await tester.pumpAndSettle();
      expect(find.text('Reset Password via Email'), findsOneWidget);

      await tester.tap(find.text('Reset Password via Email'));
      await tester.pumpAndSettle();
      expect(find.text('Send Reset Link'), findsOneWidget);
    });
  });
}


