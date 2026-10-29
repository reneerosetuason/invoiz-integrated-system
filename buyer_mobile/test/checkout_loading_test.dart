import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/rendering.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'checkout_voucher_scope_test.dart' as fixture;

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    for (final name in ['Inter', 'Fraunces']) {
      await (FontLoader(
        name,
      )..addFont(rootBundle.load('assets/fonts/$name.ttf'))).load();
    }
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    await (FontLoader('packages/cupertino_icons/CupertinoIcons')..addFont(
          rootBundle.load('packages/cupertino_icons/assets/CupertinoIcons.ttf'),
        ))
        .load();
  });
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets(
    'pickup failure preserves cart items and retry recovers checkout',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      await fixture.openCheckout(
        tester,
        requests,
        previewFailures: 1,
        skipVoucherScroll: true,
      );
      expect(find.text('Checkout unavailable'), findsOneWidget);
      expect(
        find.textContaining('pickup address is incomplete'),
        findsOneWidget,
      );
      expect(find.text('Your cart is empty.'), findsNothing);
      expect(find.text('Product 11'), findsOneWidget);
      expect(find.text('Your items are still in your cart.'), findsOneWidget);
      expect(find.text('Place order · Cash on delivery'), findsNothing);
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();
      expect(find.text('Checkout unavailable'), findsNothing);
      expect(find.text('Review your order'), findsOneWidget);
      final previews = requests.where(
        (r) => r['path'].endsWith('/orders/preview'),
      );
      expect(previews.length, 2);
      expect(
        previews.every((r) => r['body']['cart_item_ids'].single == 11),
        true,
      );
      expect(
        requests.any((r) => r['path'].endsWith('/orders/checkout')),
        false,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('cart request failure is not displayed as an empty cart', (
    tester,
  ) async {
    await fixture.openCheckout(
      tester,
      [],
      cartFailure: true,
      skipVoucherScroll: true,
    );
    expect(find.text('Server temporarily unavailable.'), findsOneWidget);
    expect(find.text('Your cart is empty.'), findsNothing);
    expect(find.text('Try again'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a genuinely empty cart stays distinct from a checkout failure', (
    tester,
  ) async {
    final requests = <Map<String, dynamic>>[];
    await fixture.openCheckout(
      tester,
      requests,
      emptyCart: true,
      skipVoucherScroll: true,
    );
    expect(find.text('Your cart is empty.'), findsOneWidget);
    expect(find.text('Checkout unavailable'), findsNothing);
    expect(requests.any((r) => r['path'].endsWith('/orders/preview')), false);
  });

  for (final width in [320.0, 360.0]) {
    testWidgets('pickup error and actions fit ${width}px with large text', (
      tester,
    ) async {
      await fixture.openCheckout(
        tester,
        [],
        previewFailures: 1,
        skipVoucherScroll: true,
        width: width,
        textScale: 1.5,
      );
      await tester.scrollUntilVisible(
        find.text('Go back'),
        160,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(find.text('Try again'), findsOneWidget);
      expect(tester.takeException(), isNull);
      if (Platform.environment['INVOIZ_CHECKOUT_SCREENSHOTS'] != null) {
        final boundary = tester.renderObject<RenderRepaintBoundary>(
          find.byType(RepaintBoundary).first,
        );
        await tester.runAsync(() async {
          final image = await boundary.toImage(pixelRatio: 1);
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          final folder = Directory(
            Platform.environment['INVOIZ_CHECKOUT_SCREENSHOTS']!,
          );
          await folder.create(recursive: true);
          await File(
            '${folder.path}/checkout-error-${width.toInt()}.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
    });
  }
}
