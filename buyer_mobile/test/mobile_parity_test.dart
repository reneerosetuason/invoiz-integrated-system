import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/models/product.dart';
import 'package:invoiz/screens/help_screen.dart';
import 'package:invoiz/screens/login_screen.dart';
import 'package:invoiz/screens/register_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/image_preview.dart';
import 'package:shared_preferences/shared_preferences.dart';

Widget app(Widget screen, {double scale = 1}) => AuthServiceProvider(
  authService: AuthService(),
  child: MaterialApp(
    theme: AppTheme.light.copyWith(splashFactory: NoSplash.splashFactory),
    builder: (context, child) => MediaQuery(
      data: MediaQuery.of(
        context,
      ).copyWith(textScaler: TextScaler.linear(scale)),
      child: RepaintBoundary(
        key: const ValueKey('parity-preview'),
        child: child!,
      ),
    ),
    home: screen,
  ),
);

Future<void> capture(WidgetTester tester, String name) async {
  final output = Platform.environment['INVOIZ_MOBILE_PARITY_SCREENSHOTS'];
  if (output == null) return;
  await tester.runAsync(() async {
    final boundary = tester.renderObject<RenderRepaintBoundary>(
      find.byKey(const ValueKey('parity-preview')),
    );
    final image = await boundary.toImage(pixelRatio: 1);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory(output).create(recursive: true);
    await File('$output/$name.png').writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  setUpAll(() async {
    for (final name in ['Inter', 'Fraunces']) {
      await (FontLoader(
        name,
      )..addFont(rootBundle.load('assets/fonts/$name.ttf'))).load();
    }
  });
  setUpAll(() async {
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    await (FontLoader('packages/cupertino_icons/CupertinoIcons')..addFont(
          rootBundle.load('packages/cupertino_icons/assets/CupertinoIcons.ttf'),
        ))
        .load();
  });
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });

  test(
    'sale prices use effective price and reject zero or misleading percentages',
    () {
      Product product(Map<String, dynamic> extra) => Product.fromJson({
        'id': 1,
        'name': 'Headphones',
        'price': 1000,
        ...extra,
      });
      final sale = product({
        'effective_price': 750,
        'compare_at_price': 1000,
        'discount_percent': 99,
      });
      expect(sale.price, 750);
      expect(sale.discountPercent, 25);
      expect(sale.isOnSale, isTrue);
      for (final extra in [
        {'price': 3748.97, 'compare_at_price': 3749, 'discount_percent': 10},
        {'price': 1000, 'compare_at_price': 1000, 'discount_percent': 10},
        {'price': 1000, 'discount_percent': 10},
        {'price': 750, 'compare_at_price': 1000, 'on_sale': false},
      ]) {
        final p = product(extra);
        expect(p.isOnSale, isFalse);
        expect(p.discountPercent, 0);
        expect(p.compareAtPrice, isNull);
      }
    },
  );

  test(
    'gallery photos support both backend image columns without losing the cover',
    () {
      final p = Product.fromJson({
        'id': 1,
        'name': 'Gallery product',
        'price': 100,
        'image': 'products/cover.jpg',
        'gallery': ['products/cover.jpg'],
        'images': [
          {'path': 'products/side.jpg'},
          {'image_path': 'products/back.jpg'},
        ],
      });
      expect(p.displayImages, [
        'products/cover.jpg',
        'products/side.jpg',
        'products/back.jpg',
      ]);
    },
  );

  for (final width in [320.0, 390.0]) {
    for (final scale in [1.0, 1.5]) {
      testWidgets('auth pages fit width $width and text $scale', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(app(const LoginScreen(), scale: scale));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(find.byType(LoginScreen), findsOneWidget);
        await capture(tester, 'login-$width-$scale');
        await tester.ensureVisible(find.text('Sign In'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Sign In'));
        await tester.pump();
        expect(
          find.text('Please enter your email and password.'),
          findsOneWidget,
        );
        await tester.pumpWidget(const SizedBox.shrink());
        await tester.pump();
        final client = MockClient((_) async => http.Response('[]', 200));
        await tester.pumpWidget(
          app(RegisterScreen(addressClient: client), scale: scale),
        );
        await tester.pumpAndSettle();
        await tester.pump(const Duration(seconds: 1));
        expect(tester.takeException(), isNull);
        expect(find.text('Create your account'), findsOneWidget);
        await capture(tester, 'signup-$width-$scale');
        await tester.ensureVisible(find.text('Complete Registration'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Complete Registration'));
        await tester.pumpAndSettle();
        expect(find.text('Required'), findsWidgets);
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox.shrink());
        client.close();
      });
    }
  }

  testWidgets(
    'receipt preview stays in app, resets zoom, and closes to the same screen',
    (tester) async {
      final bytes = (await rootBundle.load(
        'assets/logo.png',
      )).buffer.asUint8List();
      await tester.pumpWidget(
        app(
          Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => showBuyerImagePreview(
                  context,
                  title: 'GCash receipt',
                  bytes: bytes,
                ),
                child: const Text('View receipt'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('View receipt'));
      await tester.pumpAndSettle();
      expect(find.byType(BuyerImagePreview), findsOneWidget);
      final viewer = tester.widget<InteractiveViewer>(
        find.byType(InteractiveViewer),
      );
      viewer.transformationController!.value = Matrix4.diagonal3Values(2, 2, 1);
      await tester.tap(find.text('Reset'));
      await tester.pump();
      expect(viewer.transformationController!.value, Matrix4.identity());
      await capture(tester, 'receipt-preview');
      await tester.tap(find.byTooltip('Close image preview'));
      await tester.pumpAndSettle();
      expect(find.byType(BuyerImagePreview), findsNothing);
      expect(find.text('View receipt'), findsOneWidget);
    },
  );

  testWidgets('offline help explains GCash and the logistics return process', (
    tester,
  ) async {
    final api = ApiService(
      client: MockClient((_) async => http.Response('{}', 200)),
    );
    await tester.pumpWidget(app(HelpFaqScreen(api: api)));
    await tester.pumpAndSettle();
    final screen = tester.widget<HelpFaqScreen>(find.byType(HelpFaqScreen));
    expect(screen.api, api);
    await tester.ensureVisible(
      find.text('What payment methods do you accept?'),
    );
    await tester.tap(find.text('What payment methods do you accept?'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Your rider collects no cash'), findsOneWidget);
    expect(find.textContaining('COD) only'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
