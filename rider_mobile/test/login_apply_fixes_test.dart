import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
// Relative first-party imports are intentional: the generated package
// config in this checkout drops the root package entry whenever the tool
// regenerates it, which breaks package: self-imports for the analyzer and
// test runner. Third-party package: imports resolve normally.
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/apply/apply_screen.dart';
import '../lib/screens/auth/login_screen.dart';

/// Focused tests for: password eye toggle, supported attachment formats,
/// and removal of the login helper text.
void main() {
  Future<void> pumpLogin(WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) =>
            AuthProvider(api: ApiClient(), storage: TokenStorage()),
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.pumpAndSettle();
  }

  TextField passwordField(WidgetTester tester) =>
      tester.widget<TextField>(find.byType(TextField).at(1));

  group('Password visibility eye', () {
    testWidgets('initially hidden with slashed-eye icon', (tester) async {
      await pumpLogin(tester);
      expect(passwordField(tester).obscureText, isTrue);
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
      expect(find.byIcon(Icons.visibility_outlined), findsNothing);
    });

    testWidgets('tap eye toggles visible then hidden with matching icons',
        (tester) async {
      await pumpLogin(tester);

      await tester.tap(find.byIcon(Icons.visibility_off_outlined));
      await tester.pump();
      expect(passwordField(tester).obscureText, isFalse);
      expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);
      expect(find.byIcon(Icons.visibility_off_outlined), findsNothing);

      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pump();
      expect(passwordField(tester).obscureText, isTrue);
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
      expect(find.byIcon(Icons.visibility_outlined), findsNothing);
    });
  });

  group('Document attachment formats', () {
    test('pdf, jpg, jpeg and png are accepted', () {
      expect(
        ApplyScreen.supportedDocExtensions,
        containsAll(['pdf', 'jpg', 'jpeg', 'png']),
      );
    });

    test('no executable or unexpected formats sneak in', () {
      expect(ApplyScreen.supportedDocExtensions, isNot(contains('exe')));
      expect(ApplyScreen.supportedDocExtensions.length, 4);
    });
  });

  group('Login helper text removed', () {
    testWidgets('no test-account or seller-center text', (tester) async {
      await pumpLogin(tester);
      expect(find.textContaining('Test account'), findsNothing);
      expect(find.textContaining('rider@invoiz.test'), findsNothing);
      expect(find.textContaining('Seller Center'), findsNothing);
      expect(find.textContaining('Sellers:'), findsNothing);
    });

    testWidgets('login actions remain intact', (tester) async {
      await pumpLogin(tester);
      expect(find.text('Rider Login'), findsOneWidget);
      expect(find.text('Sign In'), findsOneWidget);
      expect(find.text('Apply as a Rider'), findsOneWidget);
      expect(find.text('Check Application Status'), findsOneWidget);
    });
  });
}
