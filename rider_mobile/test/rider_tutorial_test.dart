import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:provider/provider.dart';
import 'package:http/testing.dart';
import 'package:http/http.dart' as http;
import 'package:flutter/services.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/screens/onboarding/rider_tutorial_screen.dart';
import '../lib/services/tutorial_completion_store.dart';
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/screens/splash/splash_screen.dart';

Future<void> _openTutorial(WidgetTester tester, int riderId) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => RiderTutorialScreen(riderId: riderId),
              ),
            ),
            child: const Text('Open tutorial'),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Open tutorial'));
  await tester.pumpAndSettle();
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('tutorial completion is installation-wide and persists', () async {
    const store = TutorialCompletionStore();
    expect(await store.isCompleted(12), isFalse);
    await store.markCompleted(12);
    expect(await store.isCompleted(12), isTrue);
    expect(await store.isCompleted(13), isTrue);
    expect(await const TutorialCompletionStore().isCompleted(), isTrue);
  });

  test('a legacy rider completion migrates to the installation flag', () async {
    SharedPreferences.setMockInitialValues({
      'invoiz_rider_tutorial_completed_31': true,
    });
    const store = TutorialCompletionStore();
    expect(await store.isCompleted(), isTrue);
    final prefs = await SharedPreferences.getInstance();
    expect(prefs.getBool('invoiz_rider_tutorial_completed'), isTrue);
  });

  testWidgets('first launch shows tutorial before login and Skip continues', (
    tester,
  ) async {
    final channel = MethodChannel(
      'plugins.it_nomads.com/flutter_secure_storage',
    );
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => TestDefaultBinaryMessengerBinding.instance
        .defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null));
    final auth = AuthProvider(
      api: ApiClient(client: MockClient((_) async => http.Response('', 404))),
      storage: TokenStorage(),
    );

    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>.value(
        value: auth,
        child: const MaterialApp(home: SplashScreen()),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Welcome to INVOIZ Rider!'), findsOneWidget);
    expect(find.text('Rider Login'), findsNothing);

    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    expect(find.text('Rider Login'), findsOneWidget);
    expect(await const TutorialCompletionStore().isCompleted(), isTrue);
  });

  testWidgets('completed installation skips tutorial and restores login flow', (
    tester,
  ) async {
    await const TutorialCompletionStore().markCompleted();
    final channel = MethodChannel(
      'plugins.it_nomads.com/flutter_secure_storage',
    );
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => TestDefaultBinaryMessengerBinding.instance
        .defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null));
    final auth = AuthProvider(
      api: ApiClient(client: MockClient((_) async => http.Response('', 404))),
      storage: TokenStorage(),
    );

    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>.value(
        value: auth,
        child: const MaterialApp(home: SplashScreen()),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Rider Login'), findsOneWidget);
    expect(find.text('Welcome to INVOIZ Rider!'), findsNothing);
  });

  testWidgets('all five tutorial pages render and Next/Previous navigate', (
    tester,
  ) async {
    await _openTutorial(tester, 4);
    expect(find.text('Welcome to INVOIZ Rider!'), findsOneWidget);
    await tester.tap(find.text('Get Started'));
    await tester.pumpAndSettle();
    expect(find.text('Control Your Availability'), findsOneWidget);
    await tester.tap(find.text('Previous'));
    await tester.pumpAndSettle();
    expect(find.text('Welcome to INVOIZ Rider!'), findsOneWidget);

    for (final title in [
      'Control Your Availability',
      'Manage Your Deliveries',
      'Deliver and Confirm',
      'Track Your Work',
    ]) {
      await tester.tap(
        find.text(_indexFor(title) == 1 ? 'Get Started' : 'Next'),
      );
      await tester.pumpAndSettle();
      expect(find.text(title), findsOneWidget);
    }
    expect(find.text('Finish Tutorial'), findsOneWidget);
    expect(
      find.text(
        "You're ready to get started. Ride safely and deliver responsibly!",
      ),
      findsOneWidget,
    );
  });

  testWidgets('Skip saves tutorial completion', (tester) async {
    await _openTutorial(tester, 21);
    await tester.tap(find.text('Skip'));
    await tester.pumpAndSettle();
    expect(await const TutorialCompletionStore().isCompleted(21), isTrue);
  });

  testWidgets('Finish Tutorial saves completion', (tester) async {
    await _openTutorial(tester, 22);
    for (var i = 0; i < 4; i++) {
      await tester.tap(find.text(i == 0 ? 'Get Started' : 'Next'));
      await tester.pumpAndSettle();
    }
    await tester.tap(find.text('Finish Tutorial'));
    await tester.pumpAndSettle();
    expect(await const TutorialCompletionStore().isCompleted(22), isTrue);
  });

  testWidgets('tutorial scrolls on small screens and supports large text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MediaQuery(
        data: const MediaQueryData(textScaler: TextScaler.linear(1.6)),
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const RiderTutorialScreen(riderId: 4),
                  ),
                ),
                child: const Text('Open tutorial'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open tutorial'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.text('Get Started'), findsOneWidget);
  });
}

int _indexFor(String title) => switch (title) {
  'Control Your Availability' => 1,
  'Manage Your Deliveries' => 2,
  'Deliver and Confirm' => 3,
  'Track Your Work' => 4,
  _ => 0,
};
