import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/screens/cart_screen.dart';
import 'package:invoiz/models/order.dart';
import 'package:invoiz/screens/checkout_screen.dart';
import 'package:invoiz/screens/orders_screen.dart';
import 'package:invoiz/screens/order_detail_screen.dart';
import 'package:invoiz/screens/product_detail_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';

class _SignedIn extends AuthService {
  @override
  bool get isLoggedIn => true;
}

Map<String, dynamic> _product(int id) => {
  'id': id,
  'name': id == 1
      ? 'Linen everyday bag with an adjustable shoulder strap'
      : 'Unselected item',
  'price': 11999.50,
  'stock': 4,
  'rating': 4.7,
  'sold': 186,
  'brand': 'Fixture store',
  'description':
      'A detailed product description that stays readable on a small screen. '
      'Choose an available option before adding it to your cart.',
  'material': 'Cotton and linen with an extra long specification value',
  'image': null,
  'gallery': [],
  'shop': {
    'seller_id': 8,
    'business_name': 'The everyday linen and home essentials store',
    'rating': 4.8,
    'product_count': 24,
  },
  'variants': [
    {'id': 7, 'variant_type': 'Size', 'variant_value': 'Large', 'stock': 4},
  ],
  'reviews': [
    {
      'id': 1,
      'rating': 5,
      'created_at': '2026-10-29T06:00:00Z',
      'comment': 'An actual fixture review with useful details.',
      'buyer': {
        'first_name': 'A buyer with a longer name',
        'last_name': 'Fixture',
      },
    },
  ],
};

Map<String, dynamic> _line(int id, int productId) => {
  'id': id,
  'product_id': productId,
  'variant_id': 7,
  'quantity': 1,
  'unit_price': 11999.50,
  'line_total': 11999.50,
  'product': _product(productId),
  'variant': {
    'id': 7,
    'variant_type': 'Size',
    'variant_value': 'Large',
    'stock': 4,
  },
};

Map<String, dynamic> _order({String status = 'processing'}) => {
  'id': 42,
  'status': status,
  'total_amount': 12049.50,
  'shipping_fee': 50,
  'created_at': '2026-10-29T06:00:00Z',
  'can_cancel': true,
  'items': [
    {
      'id': 98,
      'product_id': 1,
      'product_name': _product(1)['name'],
      'variant_label': 'Size: Large',
      'quantity': 1,
      'price': 11999.50,
      'subtotal': 11999.50,
    },
  ],
  'payment': {
    'method': 'cash_on_delivery',
    'status': 'pending',
    'amount': 12049.50,
  },
  'deliveries': [
    {
      'id': 83,
      'status': 'out_for_delivery',
      'tracking_number': 'INVOIZ-FIXTURE-20261029-000042',
    },
  ],
  'status_histories': [
    {
      'to_status': 'processing',
      'note': 'The store is preparing your items.',
      'created_at': '2026-10-29T07:00:00Z',
    },
  ],
};

http.Response _json(Object data) => http.Response(
  jsonEncode(data),
  200,
  headers: {'content-type': 'application/json'},
);

ApiService _api({
  List<Map<String, dynamic>>? requests,
  String orderStatus = 'processing',
}) => ApiService(
  client: MockClient((r) async {
    requests?.add({'method': r.method, 'path': r.url.path, 'body': r.body});
    if (r.url.path.endsWith('/cart')) {
      return _json({
        'cart': {
          'id': 1,
          'subtotal': 23999,
          'items': [_line(11, 1), _line(12, 2)],
        },
      });
    }
    if (r.url.path.endsWith('/orders/preview')) {
      return _json({
        'quote': {
          'subtotal': 11999.50,
          'shipping_fee': 50,
          'discount': 0,
          'shipping_discount': 0,
          'total': 12049.50,
        },
      });
    }
    if (r.url.path.endsWith('/addresses')) {
      return _json({
        'addresses': [
          {
            'id': 4,
            'recipient_name': 'Fixture buyer',
            'phone': '09171234567',
            'address_line': '123 A longer street and building name',
            'barangay': 'Barangay One',
            'city': 'Quezon City',
            'province': 'Metro Manila',
            'postal_code': '1100',
            'is_default': true,
          },
        ],
      });
    }
    if (r.url.path.endsWith('/vouchers')) return _json({'vouchers': []});
    if (r.url.path.endsWith('/otp')) return _json({'otp': '123456'});
    if (r.url.path.endsWith('/orders/42'))
      return _json({'order': _order(status: orderStatus)});
    if (r.url.path.endsWith('/orders'))
      return _json({
        'orders': [
          _order(),
          {..._order(status: 'delivered'), 'id': 43},
        ],
      });
    if (r.url.path.endsWith('/products/1'))
      return _json({'product': _product(1)});
    return _json({});
  }),
);

Widget _app(Widget child, {double scale = 1}) => AuthServiceProvider(
  authService: _SignedIn(),
  child: RepaintBoundary(
    key: const ValueKey('shopping-preview'),
    child: MaterialApp(
      theme: AppTheme.light,
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(
          context,
        ).copyWith(textScaler: TextScaler.linear(scale)),
        child: child!,
      ),
      home: child,
    ),
  ),
);

Future<void> _capture(WidgetTester tester, String name) async {
  final output = Platform.environment['INVOIZ_SHOPPING_SCREENSHOTS'];
  if (output == null || output.isEmpty) return;
  await tester.runAsync(() async {
    final boundary = tester.renderObject<RenderRepaintBoundary>(
      find.byKey(const ValueKey('shopping-preview')),
    );
    final image = await boundary.toImage(pixelRatio: 1);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final dir = Directory(output);
    await dir.create(recursive: true);
    await File(
      '${dir.path}/$name.png',
    ).writeAsBytes(bytes!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  WidgetController.hitTestWarningShouldBeFatal = true;
  setUpAll(() async {
    for (final name in ['Inter', 'Fraunces']) {
      final loader = FontLoader(name)
        ..addFont(rootBundle.load('assets/fonts/$name.ttf'));
      await loader.load();
    }
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await icons.load();
  });
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });
  test('order summary keeps shipping vouchers out of the item discount', () {
    final data = _order()
      ..['total_amount'] = 11899.50
      ..['shipping_fee'] = 0
      ..['order_vouchers'] = [
        {'discount_amount': 100},
        {'discount_amount': 50},
      ];
    final order = Order.fromJson(data);
    expect(order.discount, 150);
    expect(order.merchandiseDiscount, 100);
    expect(
      order.subtotal - order.merchandiseDiscount + order.shippingFee!,
      order.totalAmount,
    );
  });
  final screens = <String, Widget Function(ApiService)>{
    'cart': (api) => CartScreen(api: api),
    'orders': (api) => OrdersScreen(api: api),
    'product': (api) => ProductDetailScreen(productId: 1, api: api),
    'order': (api) => OrderDetailScreen(orderId: 42, api: api),
    'checkout': (api) => CheckoutScreen(cartItemIds: [11], api: api),
  };
  for (final size in [320.0, 390.0, 768.0]) {
    for (final entry in screens.entries) {
      testWidgets('${entry.key} reads and scrolls at ${size}px', (
        tester,
      ) async {
        tester.view.physicalSize = Size(size, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(_app(entry.value(_api())));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await _capture(tester, '${entry.key}-${size.toInt()}');
        final list = find.byType(ListView).first;
        for (var step = 0; step < 6; step++) {
          await tester.drag(list, const Offset(0, -400));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
        }
        await _capture(tester, '${entry.key}-${size.toInt()}-bottom');
        await tester.pumpWidget(const SizedBox());
      });
    }
  }
  for (final entry in screens.entries) {
    testWidgets('${entry.key} supports large text on a small phone', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(_app(entry.value(_api()), scale: 1.5));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await _capture(tester, '${entry.key}-large-text');
      for (var step = 0; step < 6; step++) {
        await tester.drag(find.byType(ListView).first, const Offset(0, -400));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
      await tester.pumpWidget(const SizedBox());
    });
  }
  testWidgets(
    'checkout displays only selected items and preserves the quoted total',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      await tester.pumpWidget(
        _app(
          CheckoutScreen(
            cartItemIds: [11],
            api: _api(requests: requests),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text(_product(1)['name'] as String), findsOneWidget);
      expect(find.text('Unselected item'), findsNothing);
      expect(find.text('₱12,049.50'), findsWidgets);
      final preview = requests.singleWhere(
        (r) => (r['path'] as String).endsWith('/orders/preview'),
      );
      expect(jsonDecode(preview['body'] as String)['cart_item_ids'], [11]);
      expect(
        requests.any((r) => (r['path'] as String).endsWith('/orders/checkout')),
        false,
      );
    },
  );
  testWidgets('order filters retain processing orders under To Ship', (
    tester,
  ) async {
    await tester.pumpWidget(_app(OrdersScreen(api: _api())));
    await tester.pumpAndSettle();
    await tester.tap(find.text('To Ship'));
    await tester.pumpAndSettle();
    expect(find.text('Order #42'), findsOneWidget);
    expect(find.text('Order #43'), findsNothing);
    expect(tester.takeException(), isNull);
  });
  for (final action in [
    'Rate',
    'Request return',
    'Show delivery code',
    'Cancel Order',
  ]) {
    testWidgets('$action dialog fits a small phone with large text', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final requests = <Map<String, dynamic>>[];
      await tester.pumpWidget(
        _app(
          OrderDetailScreen(
            orderId: 42,
            api: _api(
              requests: requests,
              orderStatus: action == 'Cancel Order'
                  ? 'processing'
                  : 'delivered',
            ),
          ),
          scale: 1.5,
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text(action),
        200,
        scrollable: find
            .descendant(
              of: find.byType(ListView),
              matching: find.byType(Scrollable),
            )
            .first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text(action));
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(tester.takeException(), isNull);
      await _capture(tester, 'dialog-${action.replaceAll(' ', '-')}');
      final dismiss = switch (action) {
        'Rate' => 'Cancel',
        'Request return' => 'Keep item',
        'Show delivery code' => 'Close',
        _ => 'No',
      };
      await tester.tap(find.text(dismiss));
      await tester.pumpAndSettle();
      expect(requests.where((r) => r['method'] != 'GET'), isEmpty);
      expect(tester.takeException(), isNull);
    });
  }
}
