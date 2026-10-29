import 'package:flutter/cupertino.dart';
import 'package:invoiz/widgets/buyer_controls.dart';
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
import 'package:invoiz/screens/home_screen.dart';
import 'package:invoiz/screens/product_list_screen.dart';
import 'package:invoiz/screens/account_screen.dart';
import 'package:invoiz/screens/profile_screen.dart';
import 'package:invoiz/screens/login_screen.dart';
import 'package:invoiz/screens/register_screen.dart';
import 'package:invoiz/screens/verify_email_screen.dart';
import 'package:invoiz/screens/addresses_screen.dart';
import 'package:invoiz/screens/add_address_screen.dart';
import 'package:invoiz/screens/notifications_screen.dart';
import 'package:invoiz/screens/chat_screen.dart';
import 'package:invoiz/screens/wishlist_screen.dart';
import 'package:invoiz/screens/seller_store_screen.dart';
import 'package:invoiz/widgets/product_card.dart';
import 'package:invoiz/widgets/product_gallery.dart';
import 'package:invoiz/widgets/product_photo.dart';
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
    if (r.url.path.endsWith('/products'))
      return _json({
        'data': List.generate(
          6,
          (i) => {..._product(i + 1), 'discount_percent': 15},
        ),
        'current_page': 1,
        'last_page': 1,
        'total': 6,
      });
    if (r.url.path.endsWith('/categories'))
      return _json({
        'categories': [
          {'id': 1, 'name': 'Beauty & Health'},
          {'id': 2, 'name': 'Home essentials'},
        ],
      });
    if (r.url.path.endsWith('/favorites'))
      return _json({
        'favorites': [
          {'product': _product(1)},
          {'product': _product(2)},
        ],
      });
    if (r.url.path.endsWith('/notifications'))
      return _json({
        'notifications': [
          {
            'id': 'fixture-1',
            'type': 'order',
            'title': 'Your order is being prepared',
            'body': 'The store is preparing your items for collection.',
            'read': false,
            'created_at': '2026-10-29T06:00:00Z',
            'order_id': 42,
          },
        ],
        'unread_count': 1,
      });
    if (r.url.path.endsWith('/conversations/9/messages'))
      return _json({
        'messages': [
          {
            'id': 1,
            'conversation_id': 9,
            'sender_id': 8,
            'body': 'Your items are ready for collection.',
            'created_at': '2026-10-29T06:00:00Z',
            'sender': {'first_name': 'Fixture store'},
            'is_read': false,
          },
        ],
      });
    if (r.url.path.endsWith('/conversations'))
      return _json({
        'conversations': [
          {
            'id': 9,
            'subject': 'A question about my purchased items',
            'unread_count': 1,
            'updated_at': '2026-10-29T06:00:00Z',
            'last_message': {'body': 'Your items are ready.'},
          },
        ],
      });
    if (r.url.path.endsWith('/stores/8'))
      return _json({
        'store': {
          'seller_id': 8,
          'business_name': 'The everyday essentials store',
          'rating': 4.8,
          'review_count': 1,
          'product_count': 2,
          'total_sold': 186,
          'followers': 24,
          'rating_breakdown': {'5': 1},
        },
        'products': {
          'data': [_product(1), _product(2)],
          'current_page': 1,
          'last_page': 1,
        },
      });
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

void _phoneLayoutTest(
  String description,
  Future<void> Function(WidgetTester) body,
) {
  testWidgets(description, (tester) async {
    final previousShadows = debugDisableShadows;
    debugDisableShadows = false;
    try {
      await body(tester);
    } finally {
      debugDisableShadows = previousShadows;
    }
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
    final cupertinoIcons = FontLoader('packages/cupertino_icons/CupertinoIcons')
      ..addFont(
        rootBundle.load('packages/cupertino_icons/assets/CupertinoIcons.ttf'),
      );
    await cupertinoIcons.load();
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
  for (final size in [320.0, 360.0, 390.0, 430.0, 768.0, 1200.0]) {
    for (final entry in screens.entries) {
      _phoneLayoutTest('${entry.key} reads and scrolls at ${size}px', (
        tester,
      ) async {
        tester.view.physicalSize = Size(size, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(_app(entry.value(_api())));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        if (entry.key == 'product' && size == 360) {
          expect(
            tester.getSize(find.byType(ProductGallery)).height,
            lessThanOrEqualTo(205),
          );
        }
        if (size == 360) {
          expect(find.byTooltip('Messages').hitTestable(), findsOneWidget);
          expect(find.byTooltip('Notifications').hitTestable(), findsOneWidget);
          if (entry.key == 'cart') {
            expect(
              tester.getSize(find.byKey(const ValueKey('cart-item-11'))).height,
              lessThanOrEqualTo(164),
            );
            expect(
              find.byTooltip('Remove item').first.hitTestable(),
              findsOneWidget,
            );
          }
          if (entry.key == 'orders') {
            expect(
              tester
                  .getSize(find.byKey(const ValueKey('order-card-42')))
                  .height,
              lessThanOrEqualTo(180),
            );
          }
        }
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
    _phoneLayoutTest('${entry.key} supports large text on a small phone', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(_app(entry.value(_api()), scale: 1.5));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      if (entry.key == 'cart') {
        expect(
          tester.getSize(find.text(_product(1)['name'] as String)).height,
          greaterThan(50),
        );
      }
      await _capture(tester, '${entry.key}-large-text');
      for (var step = 0; step < 6; step++) {
        await tester.drag(find.byType(ListView).first, const Offset(0, -400));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
      await tester.pumpWidget(const SizedBox());
    });
  }
  for (final width in [320.0, 360.0, 390.0, 430.0, 768.0, 1200.0]) {
    _phoneLayoutTest(
      'home uses a compact promotion and proportional grid at $width',
      (tester) async {
        tester.view.physicalSize = Size(width, 844);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(_app(HomeScreen(api: _api())));
        await tester.pumpAndSettle();
        final navigation = find.byKey(const ValueKey('catalog-navigation'));
        final search = find.byKey(const ValueKey('catalog-search'));
        expect(
          find.descendant(of: navigation, matching: search),
          findsOneWidget,
        );
        expect(search, findsOneWidget);
        expect(tester.getSize(search).height, greaterThanOrEqualTo(44));
        expect(find.byTooltip('Messages').hitTestable(), findsOneWidget);
        expect(find.byTooltip('Notifications').hitTestable(), findsOneWidget);
        expect(find.byTooltip('Wishlist').hitTestable(), findsOneWidget);
        expect(find.byType(ProductCard), findsWidgets);
        final first = find.byType(ProductCard).at(0);
        final second = find.byType(ProductCard).at(1);
        expect(tester.getTopLeft(first).dy, tester.getTopLeft(second).dy);
        expect(
          tester.getTopLeft(second).dx,
          greaterThan(tester.getTopLeft(first).dx),
        );
        expect(tester.getTopLeft(first).dy, lessThan(430));
        expect(tester.getSize(first).height, lessThan(width < 600 ? 310 : 450));
        if (width == 360) {
          expect(tester.getTopLeft(first).dy, lessThanOrEqualTo(300));
          expect(tester.getSize(first).height, lessThanOrEqualTo(232));
          final searchPill = tester.getRect(
            find.byKey(const ValueKey('catalog-search')),
          );
          final categoryPill = tester.getRect(
            find.byKey(const ValueKey('catalog-categories')),
          );
          expect(searchPill.bottom, lessThan(categoryPill.top));
          expect(
            categoryPill.bottom,
            lessThanOrEqualTo(tester.getTopLeft(first).dy),
          );
          final photo = find.descendant(
            of: first,
            matching: find.byType(ProductPhoto),
          );
          expect(tester.getSize(photo).height, inInclusiveRange(100, 130));
          final favorite = find.descendant(
            of: first,
            matching: find.byType(BuyerButton),
          );
          expect(tester.getSize(favorite).height, greaterThanOrEqualTo(44));
        }
        expect(tester.takeException(), isNull);
        await _capture(tester, 'home-${width.toInt()}');
        if (width == 360) {
          await tester.tap(find.byTooltip('Sort products'));
          await tester.pumpAndSettle();
          await _capture(tester, 'home-360-sort-sheet');
          await tester.tap(
            find.widgetWithText(
              CupertinoActionSheetAction,
              'Price: high to low',
            ),
          );
          await tester.pumpAndSettle();
          expect(find.text('Price ↓'), findsOneWidget);
          expect(tester.takeException(), isNull);
          await _capture(tester, 'home-360-long-sort');
        }
        await tester.pumpWidget(const SizedBox());
      },
    );
  }
  final secondary = <String, Widget Function(ApiService)>{
    'shop': (api) => ProductListScreen(api: api, showAll: true),
    'account': (_) => const AccountScreen(),
    'profile': (_) => const ProfileScreen(),
    'login': (_) => const LoginScreen(),
    'registration': (_) => const RegisterScreen(),
    'verification': (_) =>
        const VerifyEmailScreen(email: 'fixture@example.test'),
    'addresses': (api) => AddressesScreen(api: api),
    'address-form': (api) => AddAddressScreen(api: api),
    'notifications': (api) => NotificationsScreen(api: api),
    'messages': (api) => ChatScreen(api: api),
    'conversation': (api) =>
        ChatThreadScreen(conversationId: 9, subject: 'Fixture store', api: api),
    'favorites': (api) => WishlistScreen(api: api),
    'store': (api) => SellerStoreScreen(sellerId: 8, api: api),
  };
  _phoneLayoutTest('home adapts its grid to large text on a small phone', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(_app(HomeScreen(api: _api()), scale: 1.5));
    await tester.pumpAndSettle();
    expect(
      tester.getSize(find.byType(ProductCard).first).width,
      greaterThan(250),
    );
    expect(tester.takeException(), isNull);
    expect(find.byKey(const ValueKey('catalog-search')), findsOneWidget);
    expect(find.byKey(const ValueKey('catalog-categories')), findsOneWidget);
    expect(find.text('Curated for you'), findsNothing);
    await _capture(tester, 'home-large-text');
    await tester.pumpWidget(const SizedBox());
  });
  for (final viewport in [(320.0, 1.0), (430.0, 1.0), (320.0, 1.5)]) {
    for (final entry in secondary.entries) {
      _phoneLayoutTest(
        '${entry.key} fits ${viewport.$1} with text scale ${viewport.$2}',
        (tester) async {
          tester.view.physicalSize = Size(viewport.$1, 844);
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetPhysicalSize);
          addTearDown(tester.view.resetDevicePixelRatio);
          await tester.pumpWidget(
            _app(entry.value(_api()), scale: viewport.$2),
          );
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await _capture(
            tester,
            '${entry.key}-${viewport.$1.toInt()}-${viewport.$2}',
          );
          if (find.byType(ListView).evaluate().isNotEmpty) {
            for (var step = 0; step < 5; step++) {
              await tester.drag(
                find.byType(ListView).first,
                const Offset(0, -350),
              );
              await tester.pumpAndSettle();
              expect(tester.takeException(), isNull);
            }
          }
          await tester.pumpWidget(const SizedBox());
        },
      );
    }
  }
  final keyboardScreens = <String, Widget Function(ApiService)>{
    'home': (api) => HomeScreen(api: api),
    'cart': (api) => CartScreen(api: api),
    'orders': (api) => OrdersScreen(api: api),
    'store': (api) => SellerStoreScreen(sellerId: 8, api: api),
    'login': (_) => const LoginScreen(),
    'registration': (_) => const RegisterScreen(),
    'profile': (_) => const ProfileScreen(),
    'verification': (_) =>
        const VerifyEmailScreen(email: 'fixture@example.test'),
    'address-form': (api) => AddAddressScreen(api: api),
    'conversation': (api) =>
        ChatThreadScreen(conversationId: 9, subject: 'Fixture store', api: api),
  };
  for (final entry in keyboardScreens.entries) {
    _phoneLayoutTest(
      '${entry.key} remains usable with a keyboard on a short phone',
      (tester) async {
        tester.view.physicalSize = const Size(320, 640);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        addTearDown(tester.view.resetViewInsets);
        await tester.pumpWidget(_app(entry.value(_api())));
        await tester.pumpAndSettle();
        final field = find.byType(CupertinoTextField).first;
        await tester.ensureVisible(field);
        await tester.pumpAndSettle();
        await tester.tap(field);
        tester.view.viewInsets = const FakeViewPadding(bottom: 280);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await _capture(tester, '${entry.key}-keyboard');
        tester.view.resetViewInsets();
        await tester.pumpWidget(const SizedBox());
      },
    );
  }
  _phoneLayoutTest(
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
  _phoneLayoutTest('order filters retain processing orders under To Ship', (
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
    _phoneLayoutTest('$action dialog fits a small phone with large text', (
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
      expect(find.byType(CupertinoAlertDialog), findsOneWidget);
      expect(tester.takeException(), isNull);
      await _capture(tester, 'dialog-${action.replaceAll(' ', '-')}');
      if (action == 'Cancel Order') {
        await tester.tap(find.text('Select a reason'));
        await tester.pumpAndSettle();
        await tester.tap(
          find.widgetWithText(CupertinoActionSheetAction, 'Other reason'),
        );
        await tester.pumpAndSettle();
        await _capture(tester, 'dialog-Cancel-Order-other');
      }
      if (action == 'Rate' ||
          action == 'Request return' ||
          action == 'Cancel Order') {
        final input = find
            .descendant(
              of: find.byType(CupertinoAlertDialog),
              matching: find.byType(CupertinoTextField),
            )
            .last;
        if (action != 'Cancel Order') {
          await tester.ensureVisible(input);
        }
        await tester.pumpAndSettle();
        await tester.tap(input);
        tester.view.viewInsets = const FakeViewPadding(bottom: 280);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await _capture(
          tester,
          'dialog-${action.replaceAll(' ', '-')}-keyboard',
        );
        tester.view.resetViewInsets();
        await tester.pumpAndSettle();
      }
      final dismiss = switch (action) {
        'Rate' => 'Cancel',
        'Request return' => 'Keep item',
        'Show delivery code' => 'Close',
        _ => 'Keep order',
      };
      await tester.tap(find.text(dismiss));
      await tester.pumpAndSettle();
      expect(requests.where((r) => r['method'] != 'GET'), isEmpty);
      expect(tester.takeException(), isNull);
    });
  }
}
