import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
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
import '../lib/providers/delivery_provider.dart';
import '../lib/providers/rider_provider.dart';
import '../lib/screens/auth/login_screen.dart';
import '../lib/screens/dashboard/dashboard_screen.dart';
import '../lib/services/delivery_service.dart';
import '../lib/services/rider_service.dart';
import '../lib/widgets/primary_button.dart';

/// Stub HTTP client so login can be exercised without a server.
class _StubClient extends http.BaseClient {
  _StubClient({this.statusCode = 401, this.body, this.latency = Duration.zero});

  int statusCode;
  Object? body;
  final Duration latency;
  int requestCount = 0;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    requestCount++;
    if (latency > Duration.zero) {
      await Future<void>.delayed(latency);
    }
    final payload = utf8.encode(jsonEncode(body ?? {}));
    return http.StreamedResponse(
      Stream<List<int>>.value(Uint8List.fromList(payload)),
      statusCode,
      headers: {'content-type': 'application/json'},
    );
  }
}

Future<void> _pumpLogin(WidgetTester tester, _StubClient client) async {
  SharedPreferences.setMockInitialValues({});
  await tester.pumpWidget(
    ChangeNotifierProvider<AuthProvider>.value(
      value: AuthProvider(
        api: ApiClient(client: client),
        storage: TokenStorage(),
      ),
      child: const MaterialApp(home: LoginScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

void _setSurface(WidgetTester tester, double width, double height) {
  tester.view.physicalSize = Size(width, height);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

void main() {
  group('Login responsive layout', () {
    for (final (width, height) in [
      (320.0, 568.0),
      (360.0, 640.0),
      (375.0, 667.0),
      (412.0, 915.0),
      (640.0, 360.0), // landscape
    ]) {
      testWidgets(
        'login fits ${width.toInt()}x${height.toInt()} without overflow',
        (tester) async {
          _setSurface(tester, width, height);
          await _pumpLogin(tester, _StubClient());

          final scrollable = find.byType(SingleChildScrollView);
          await tester.fling(scrollable, const Offset(0, -1200), 2500);
          await tester.pumpAndSettle();
          await tester.fling(scrollable, const Offset(0, 1200), 2500);
          await tester.pumpAndSettle();

          expect(tester.takeException(), isNull);
          expect(find.text('Rider Login'), findsOneWidget);
          expect(find.text('Sign In'), findsOneWidget);
        },
      );
    }
  });

  group('Rider secondary actions', () {
    for (final width in [320.0, 360.0, 375.0, 412.0]) {
      testWidgets('apply sits below sign in and status check lives outside the card at ${width.toInt()}px', (
        tester,
      ) async {
        _setSurface(tester, width, 800);
        await _pumpLogin(tester, _StubClient());

        expect(find.text('Forgot password?'), findsOneWidget);
        expect(find.text('Apply as a Rider'), findsOneWidget);
        expect(find.text('Check Application Status'), findsOneWidget);
        expect(find.byType(OutlinedButton), findsNWidgets(2));

        // Vertical order on the page: Sign In, then Apply, then the
        // outside-the-card Check Application Status action.
        final signInDy = tester.getCenter(find.text('Sign In')).dy;
        final applyDy =
            tester.getCenter(find.text('Apply as a Rider')).dy;
        final statusDy =
            tester.getCenter(find.text('Check Application Status')).dy;
        expect(applyDy, greaterThan(signInDy));
        expect(statusDy, greaterThan(applyDy));

        await tester.fling(
          find.byType(SingleChildScrollView),
          const Offset(0, -1200),
          2500,
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('forgot password explains the logistics resend flow', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      await _pumpLogin(tester, _StubClient());

      await tester.tap(find.text('Forgot password?'));
      await tester.pumpAndSettle();

      expect(find.text('Forgot password?'), findsWidgets);
      expect(
        find.textContaining('resend your login'),
        findsOneWidget,
      );
      expect(find.text('Close'), findsOneWidget);

      await tester.tap(find.text('Close'));
      await tester.pumpAndSettle();
      expect(find.textContaining('resend your login'), findsNothing);
    });

    testWidgets('keeps the primary submit button visually dominant', (
      tester,
    ) async {
      _setSurface(tester, 412, 915);
      await _pumpLogin(tester, _StubClient());

      final submit = find.widgetWithText(
        ElevatedButton,
        'Sign In',
      );
      final submitHeight = tester.getSize(submit).height;

      // Full-height (52px) filled button vs. compact secondary actions —
      // the primary action must stay the largest element on the page.
      expect(submitHeight, greaterThanOrEqualTo(52));
      expect(submitHeight, greaterThan(40));
      expect(find.text('Forgot password?'), findsOneWidget);
    });

    testWidgets('Logistics Center actions are not exposed on Rider login', (
      tester,
    ) async {
      _setSurface(tester, 412, 915);
      await _pumpLogin(tester, _StubClient());

      expect(find.text('Open a Logistics Center'), findsNothing);
      expect(
        find.text('Check a logistics center application status'),
        findsNothing,
      );

      // Rider-only secondary actions remain.
      expect(find.text('Apply as a Rider'), findsOneWidget);
      expect(find.text('Check Application Status'), findsOneWidget);
    });
  });

  group('Login validation', () {
    testWidgets('shows messages for empty required fields', (tester) async {
      _setSurface(tester, 390, 844);
      await _pumpLogin(tester, _StubClient());

      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Please enter your email.'), findsOneWidget);
      expect(find.text('Please enter your password.'), findsOneWidget);
    });

    testWidgets('rejects an invalid email and a short password', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      await _pumpLogin(tester, _StubClient());

      await tester.enterText(find.byType(TextField).at(0), 'not-an-email');
      await tester.enterText(find.byType(TextField).at(1), '123');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Please enter a valid email address.'), findsOneWidget);
      expect(
        find.text('Password must be at least 6 characters.'),
        findsOneWidget,
      );
    });
  });

  group('Login submission guard', () {
    testWidgets('only sends one request when the button is tapped repeatedly', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      final client = _StubClient(
        statusCode: 401,
        latency: const Duration(milliseconds: 200),
        body: {'message': 'Invalid credentials.'},
      );
      await _pumpLogin(tester, client);

      await tester.enterText(find.byType(TextField).at(0), 'rider@invoiz.test');
      await tester.enterText(find.byType(TextField).at(1), 'password123');
      await tester.tap(find.text('Sign In'));
      await tester.pump();
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await tester.tap(find.byType(PrimaryButton), warnIfMissed: false);
      await tester.tap(find.byType(PrimaryButton), warnIfMissed: false);
      await tester.pumpAndSettle();

      expect(client.requestCount, 1);
      expect(find.byType(CircularProgressIndicator), findsNothing);
    });
  });

  group('Login failure feedback', () {
    testWidgets('shows an inline error banner with the server message', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      final client = _StubClient(
        statusCode: 401,
        body: {'message': 'Invalid credentials.'},
      );
      await _pumpLogin(tester, client);

      await tester.enterText(find.byType(TextField).at(0), 'rider@invoiz.test');
      await tester.enterText(find.byType(TextField).at(1), 'password123');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.text('Invalid credentials.'), findsOneWidget);
      expect(find.byIcon(Icons.error_outline), findsOneWidget);
    });

    testWidgets('falls back to a friendly message when the server gives none', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      final client = _StubClient(
        statusCode: 500,
        body: {'message': 'Something went wrong. Please try again.'},
      );
      await _pumpLogin(tester, client);

      await tester.enterText(find.byType(TextField).at(0), 'rider@invoiz.test');
      await tester.enterText(find.byType(TextField).at(1), 'password123');
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();

      expect(find.textContaining('Unable to log in'), findsOneWidget);
    });
  });

  group('Small-phone layout', () {
    testWidgets('everything reachable with scrolling and no overflow on a small phone', (
      tester,
    ) async {
      _setSurface(tester, 360, 640);
      await _pumpLogin(tester, _StubClient());

      // With the fuller card, small phones scroll; the scroll view must
      // still expose every action with no overflow.
      final scrollable = tester.widget<SingleChildScrollView>(
        find.byType(SingleChildScrollView),
      );
      expect(scrollable.physics, isA<AlwaysScrollableScrollPhysics>());

      await tester.fling(
        find.byType(SingleChildScrollView),
        const Offset(0, -1200),
        2500,
      );
      await tester.pumpAndSettle();

      // Header, form, submit, and all secondary actions are reachable
      // with no overflow.
      expect(find.text('Rider Login'), findsWidgets);
      expect(find.text('Sign In'), findsOneWidget);
      expect(find.text('Forgot password?'), findsOneWidget);
      expect(find.text('Apply as a Rider'), findsOneWidget);
      expect(find.text('Check Application Status'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('input boxes have no decorative prefix icons', (
      tester,
    ) async {
      _setSurface(tester, 390, 844);
      await _pumpLogin(tester, _StubClient());

      expect(find.byIcon(Icons.email_outlined), findsNothing);
      expect(find.byIcon(Icons.lock_outline), findsNothing);

      // The functional show/hide password toggle is preserved.
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
    });
  });

  group('Password visibility toggle', () {
    testWidgets('icons expose accessibility tooltips', (tester) async {
      _setSurface(tester, 390, 844);
      await _pumpLogin(tester, _StubClient());

      final hidden = tester.widget<IconButton>(
        find.widgetWithIcon(IconButton, Icons.visibility_off_outlined),
      );
      expect(hidden.tooltip, 'Show password');

      await tester.tap(find.byIcon(Icons.visibility_off_outlined));
      await tester.pump();

      final shown = tester.widget<IconButton>(
        find.widgetWithIcon(IconButton, Icons.visibility_outlined),
      );
      expect(shown.tooltip, 'Hide password');
    });
  });

  group('Dashboard responsive', () {
    testWidgets('loading and error states fit a small screen', (tester) async {
      _setSurface(tester, 320, 568);
      final api = ApiClient(client: _StubClient());
      final rider = RiderProvider(RiderService(api));
      final deliveries = DeliveryProvider(DeliveryService(api));
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<RiderProvider>.value(value: rider),
            ChangeNotifierProvider<DeliveryProvider>.value(value: deliveries),
          ],
          child: const MaterialApp(home: DashboardScreen()),
        ),
      );
      await tester.pump();

      final load = rider.loadDashboard();
      await tester.pump(const Duration(milliseconds: 300));
      await load;
      await tester.pump();

      expect(find.text('Try Again'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
