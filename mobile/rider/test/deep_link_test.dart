import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:invoize_rider/screens/auth/forgot_password_screen.dart';
import 'package:invoize_rider/screens/auth/login_screen.dart';
import 'package:invoize_rider/services/deep_link_service.dart';
import 'package:invoize_rider/widgets/deep_link_broker.dart';

/// Test double for [DeepLinkService]: deterministic cold-start link and a
/// stream the test can drive by hand.
class _FakeDeepLinks implements DeepLinkService {
  _FakeDeepLinks()
      : _controller = StreamController<Uri>.broadcast(),
        _resetController = StreamController<Uri>.broadcast();

  final StreamController<Uri> _controller;
  final StreamController<Uri> _resetController;
  Uri? _initial;
  Uri? _initialReset;

  void setInitial(Uri? uri) => _initial = uri;
  void setInitialReset(Uri? uri) => _initialReset = uri;
  void emit(Uri uri) => _controller.add(uri);
  void emitReset(Uri uri) => _resetController.add(uri);

  @override
  Future<Uri?> get initialLink async => _initial;

  @override
  Stream<Uri> get loginLinks => _controller.stream;

  @override
  Future<Uri?> get initialResetLink async => _initialReset;

  @override
  Stream<Uri> get resetLinks => _resetController.stream;
}

Future<void> _pumpBroker(WidgetTester tester, _FakeDeepLinks service,
    GlobalKey<NavigatorState> navKey) async {
  final tracker = RouteNameTracker();
  await tester.pumpWidget(MaterialApp(
    navigatorKey: navKey,
    navigatorObservers: [tracker],
    onGenerateRoute: (settings) {
      if (settings.name == DeepLinkService.loginRouteName) {
        return MaterialPageRoute(
            settings: settings, builder: (_) => const LoginScreen());
      }
      if (settings.name == DeepLinkService.resetRouteName) {
        final args = settings.arguments;
        final map = args is Map ? args : const {};
        return MaterialPageRoute(
          settings: settings,
          builder: (_) => ForgotPasswordScreen(
            initialEmail: map['email'] as String?,
            initialToken: map['token'] as String?,
          ),
        );
      }
      return null;
    },
    builder: (context, child) => AppDeepLinkBroker(
      service: service,
      navKey: navKey,
      routeTracker: tracker,
      child: child,
    ),
    home: const Scaffold(body: Text('home')),
  ));
  await tester.pump();
  await tester.pump();
}

void main() {
  group('DeepLinkService.isLoginLink', () {
    test('accepts invoizrider://login', () {
      expect(DeepLinkService.isLoginLink(Uri.parse('invoizrider://login')),
          isTrue);
    });

    test('rejects other schemes, hosts and null', () {
      expect(DeepLinkService.isLoginLink(Uri.parse('https://login')), isFalse);
      expect(DeepLinkService.isLoginLink(Uri.parse('invoizrider://other')),
          isFalse);
      expect(DeepLinkService.isLoginLink(null), isFalse);
    });
  });

  group('AppDeepLinkBroker', () {
    testWidgets('cold-start initial link opens the login screen',
        (tester) async {
      final service = _FakeDeepLinks()
        ..setInitial(Uri.parse('invoizrider://login'));
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);

      expect(find.text('Rider Login'), findsOneWidget);
    });

    testWidgets('a live link while running opens the login screen',
        (tester) async {
      final service = _FakeDeepLinks();
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);
      expect(find.text('home'), findsOneWidget);

      service.emit(Uri.parse('invoizrider://login'));
      await tester.pump();
      await tester.pumpAndSettle();

      expect(find.text('Rider Login'), findsOneWidget);
      expect(find.text('home'), findsNothing);
    });

    testWidgets('links with other hosts are ignored', (tester) async {
      final service = _FakeDeepLinks()
        ..setInitial(Uri.parse('invoizrider://other'));
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);

      expect(find.text('home'), findsOneWidget);
      expect(find.text('Rider Login'), findsNothing);
    });

    testWidgets('repeated links do not stack duplicate logins', (tester) async {
      final service = _FakeDeepLinks();
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);

      service.emit(Uri.parse('invoizrider://login'));
      await tester.pumpAndSettle();
      expect(find.text('Rider Login'), findsOneWidget);

      // Second link while the login screen is already on top: no new route.
      final routesBefore = navKey.currentState!.widget.pages;
      service.emit(Uri.parse('invoizrider://login'));
      await tester.pumpAndSettle();
      expect(navKey.currentState!.widget.pages, same(routesBefore));
      expect(find.text('Rider Login'), findsOneWidget);
    });

    testWidgets('startup gate retains a cold-start link until released', (
      tester,
    ) async {
      final service = _FakeDeepLinks()
        ..setInitial(Uri.parse('invoizrider://login'));
      final navKey = GlobalKey<NavigatorState>();
      final tracker = RouteNameTracker();
      final gate = StartupDeepLinkGate();
      await tester.pumpWidget(MaterialApp(
        navigatorKey: navKey,
        navigatorObservers: [tracker],
        onGenerateRoute: (settings) {
          if (settings.name == DeepLinkService.loginRouteName) {
            return MaterialPageRoute(
              settings: settings,
              builder: (_) => const LoginScreen(),
            );
          }
          return null;
        },
        builder: (context, child) => AppDeepLinkBroker(
          service: service,
          navKey: navKey,
          routeTracker: tracker,
          startupGate: gate,
          child: child,
        ),
        home: const Scaffold(body: Text('tutorial')),
      ));
      await tester.pump();
      await tester.pump();
      expect(find.text('tutorial'), findsOneWidget);
      expect(find.text('Rider Login'), findsNothing);

      gate.release();
      await tester.pumpAndSettle();
      expect(find.text('Rider Login'), findsOneWidget);
    });

    testWidgets('startup gate retains password reset links until released', (
      tester,
    ) async {
      final service = _FakeDeepLinks()
        ..setInitialReset(Uri.parse(
          'invoizrider://reset-password?token=tok&email=rider%40invoiz.test',
        ));
      final navKey = GlobalKey<NavigatorState>();
      final tracker = RouteNameTracker();
      final gate = StartupDeepLinkGate();
      await tester.pumpWidget(MaterialApp(
        navigatorKey: navKey,
        navigatorObservers: [tracker],
        onGenerateRoute: (settings) {
          if (settings.name == DeepLinkService.resetRouteName) {
            final args = settings.arguments as Map;
            return MaterialPageRoute(
              settings: settings,
              builder: (_) => ForgotPasswordScreen(
                initialEmail: args['email'] as String?,
                initialToken: args['token'] as String?,
              ),
            );
          }
          return null;
        },
        builder: (context, child) => AppDeepLinkBroker(
          service: service,
          navKey: navKey,
          routeTracker: tracker,
          startupGate: gate,
          child: child,
        ),
        home: const Scaffold(body: Text('tutorial')),
      ));
      await tester.pump();
      await tester.pump();
      expect(find.text('tutorial'), findsOneWidget);
      expect(find.text('Reset Password'), findsNothing);

      gate.release();
      await tester.pumpAndSettle();
      expect(find.text('Reset Password'), findsWidgets);
    });
  });

  group('DeepLinkService.isResetLink', () {
    test('accepts reset links with token and email', () {
      expect(
          DeepLinkService.isResetLink(Uri.parse(
              'invoizrider://reset-password?token=abc&email=rider%40invoiz.test')),
          isTrue);
    });

    test('rejects other hosts and null', () {
      expect(DeepLinkService.isResetLink(Uri.parse('invoizrider://login')),
          isFalse);
      expect(DeepLinkService.isResetLink(null), isFalse);
    });

    test('resetCredentials extracts email and token', () {
      final creds = DeepLinkService.resetCredentials(Uri.parse(
          'invoizrider://reset-password?token=abc123&email=rider%40invoiz.test'));
      expect(creds?.email, 'rider@invoiz.test');
      expect(creds?.token, 'abc123');
    });

    test('resetCredentials rejects incomplete links', () {
      expect(
          DeepLinkService.resetCredentials(
              Uri.parse('invoizrider://reset-password?token=abc')),
          isNull);
      expect(
          DeepLinkService.resetCredentials(Uri.parse('invoizrider://login')),
          isNull);
    });
  });

  group('AppDeepLinkBroker reset links', () {
    testWidgets('cold-start reset link opens the reset screen', (
      tester,
    ) async {
      final service = _FakeDeepLinks()
        ..setInitialReset(Uri.parse(
            'invoizrider://reset-password?token=tok&email=rider%40invoiz.test'));
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);

      expect(find.text('Reset Password'), findsWidgets);
      expect(find.text('RESET CODE'), findsOneWidget);
    });

    testWidgets('a live reset link opens the reset screen', (tester) async {
      final service = _FakeDeepLinks();
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);
      expect(find.text('home'), findsOneWidget);

      service.emitReset(Uri.parse(
          'invoizrider://reset-password?token=tok&email=rider%40invoiz.test'));
      await tester.pump();
      await tester.pumpAndSettle();

      expect(find.text('Reset Password'), findsWidgets);
      expect(find.text('home'), findsNothing);
    });

    testWidgets('reset links without credentials are ignored', (
      tester,
    ) async {
      final service = _FakeDeepLinks()
        ..setInitialReset(Uri.parse('invoizrider://reset-password'));
      final navKey = GlobalKey<NavigatorState>();
      await _pumpBroker(tester, service, navKey);

      expect(find.text('home'), findsOneWidget);
      expect(find.text('RESET CODE'), findsNothing);
    });
  });

  group('platform wiring', () {
    test('Android manifest declares the invoizrider://login intent filter',
        () {
      final manifest = File('android/app/src/main/AndroidManifest.xml')
          .readAsStringSync();
      expect(manifest, contains('android:scheme="invoizrider"'));
      expect(manifest, contains('android:host="login"'));
      expect(manifest, contains('android.intent.action.VIEW'));
    });

    test('Android manifest declares the reset-password host', () {
      final manifest = File('android/app/src/main/AndroidManifest.xml')
          .readAsStringSync();
      expect(manifest, contains('android:host="reset-password"'));
    });

    test('pubspec depends on app_links', () {
      final pubspec = File('pubspec.yaml').readAsStringSync();
      expect(pubspec, contains('app_links:'));
    });
  });
}

