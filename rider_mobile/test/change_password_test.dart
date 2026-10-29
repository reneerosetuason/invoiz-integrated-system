import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/providers/rider_provider.dart';
import 'package:invoize_rider/screens/profile/change_password_screen.dart';
import 'package:invoize_rider/services/rider_service.dart';

/// Change-password coverage: validation, eye toggles, service contract
/// (PATCH /rider/password), and the success flow. Passwords only travel
/// in the request body — nothing here (or in the UI) ever logs them.
RiderProvider providerWith(MockClient client) =>
    RiderProvider(RiderService(ApiClient(client: client)));

Future<void> pumpPasswordScreen(
    WidgetTester tester, RiderProvider provider) async {
  await tester.pumpWidget(
    ChangeNotifierProvider.value(
      value: provider,
      child: const MaterialApp(
        home: Scaffold(
          body: ChangePasswordScreen(),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> fillAll(WidgetTester tester,
    {String current = 'old-password-123',
    String next = 'new-password-123',
    String confirm = 'new-password-123'}) async {
  final fields = find.byType(TextFormField);
  await tester.enterText(fields.at(0), current);
  await tester.enterText(fields.at(1), next);
  await tester.enterText(fields.at(2), confirm);
  await tester.pump();
}

void main() {
  group('validation', () {
    testWidgets('empty fields are rejected', (tester) async {
      await pumpPasswordScreen(
          tester,
          providerWith(MockClient((_) async =>
              http.Response('{}', 200,
                  headers: {'content-type': 'application/json'}))));
      await tester.tap(find.widgetWithText(ElevatedButton, 'Change Password'));
      await tester.pump();
      expect(find.text('Please enter your current password.'),
          findsOneWidget);
    });

    testWidgets('short new password is rejected', (tester) async {
      await pumpPasswordScreen(
          tester,
          providerWith(MockClient((_) async =>
              http.Response('{}', 200,
                  headers: {'content-type': 'application/json'}))));
      await fillAll(tester, next: 'short', confirm: 'short');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Change Password'));
      await tester.pump();
      expect(find.text('Password must be at least 8 characters.'),
          findsOneWidget);
    });

    testWidgets('mismatched confirmation is rejected', (tester) async {
      await pumpPasswordScreen(
          tester,
          providerWith(MockClient((_) async =>
              http.Response('{}', 200,
                  headers: {'content-type': 'application/json'}))));
      await fillAll(tester, confirm: 'different-password');
      await tester.tap(find.widgetWithText(ElevatedButton, 'Change Password'));
      await tester.pump();
      expect(find.text('Passwords do not match.'), findsOneWidget);
    });
  });

  group('visibility icons', () {
    testWidgets('all three fields start hidden with slashed eyes',
        (tester) async {
      await pumpPasswordScreen(
          tester,
          providerWith(MockClient((_) async =>
              http.Response('{}', 200,
                  headers: {'content-type': 'application/json'}))));
      expect(find.byIcon(Icons.visibility_off_outlined), findsNWidgets(3));
      expect(find.byIcon(Icons.visibility_outlined), findsNothing);
    });

    testWidgets('each eye toggles its own field', (tester) async {
      await pumpPasswordScreen(
          tester,
          providerWith(MockClient((_) async =>
              http.Response('{}', 200,
                  headers: {'content-type': 'application/json'}))));
      await tester.tap(find.byIcon(Icons.visibility_off_outlined).first);
      await tester.pump();
      expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);
      expect(find.byIcon(Icons.visibility_off_outlined), findsNWidgets(2));
    });
  });

  group('service contract', () {
    test('PATCH /rider/password sends the three fields', () async {
      http.BaseRequest? captured;
      final service = RiderService(ApiClient(
          client: MockClient((http.BaseRequest request) async {
        captured = request;
        return http.Response(
            jsonEncode({'message': 'Password changed successfully.'}), 200,
            headers: {'content-type': 'application/json'});
      })));

      await service.changePassword(
        currentPassword: 'old-password-123',
        newPassword: 'new-password-123',
        confirmPassword: 'new-password-123',
      );

      expect(captured?.method, 'PATCH');
      expect(captured?.url.path, endsWith('/rider/password'));
      final body = jsonDecode(
          (captured! as http.Request).body) as Map<String, dynamic>;
      expect(body['current_password'], 'old-password-123');
      expect(body['password'], 'new-password-123');
      expect(body['password_confirmation'], 'new-password-123');
    });

    testWidgets('wrong current password surfaces the backend message',
        (tester) async {
      final provider = providerWith(MockClient((_) async =>
          http.Response(
              jsonEncode({
                'message': 'The current password is incorrect.',
                'errors': {
                  'current_password': ['The current password is incorrect.']
                }
              }),
              422,
              headers: {'content-type': 'application/json'})));
      await pumpPasswordScreen(tester, provider);
      await fillAll(tester);
      await tester.tap(find.widgetWithText(ElevatedButton, 'Change Password'));
      await tester.pumpAndSettle();

      // Backend message shown, and it never echoes the password.
      expect(
          find.text('The current password is incorrect.'), findsOneWidget);
      final snack =
          tester.widget<SnackBar>(find.byType(SnackBar));
      expect((snack.content as Text).data,
          isNot(contains('old-password-123')));
    });

    testWidgets('success shows confirmation and returns to profile',
        (tester) async {
      final provider = providerWith(MockClient((_) async =>
          http.Response(
              jsonEncode(
                  {'message': 'Password changed successfully.'}),
              200,
              headers: {'content-type': 'application/json'})));
      await tester.pumpWidget(
        ChangeNotifierProvider.value(
          value: provider,
          child: MaterialApp(
            home: const Scaffold(body: Text('PROFILE-MARKER')),
            routes: const {},
          ),
        ),
      );
      await tester.pumpAndSettle();
      // Push the password screen over the marker page.
      final context = tester.element(find.text('PROFILE-MARKER'));
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
      );
      await tester.pumpAndSettle();

      await fillAll(tester);
      await tester.tap(find.widgetWithText(ElevatedButton, 'Change Password'));
      await tester.pumpAndSettle();

      expect(
          find.text('Password changed successfully.'), findsOneWidget);
      // Popped back to the marker page.
      expect(find.text('PROFILE-MARKER'), findsOneWidget);
    });
  });
}
