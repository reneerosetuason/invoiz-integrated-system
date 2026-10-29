import 'package:flutter/cupertino.dart';
import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/config.dart';
import 'package:invoiz/models/product.dart';
import 'package:invoiz/screens/cart_screen.dart';
import 'package:invoiz/screens/home_screen.dart';
import 'package:invoiz/screens/product_detail_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/services/recently_viewed_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/catalog_browser.dart';
import 'package:invoiz/widgets/product_gallery.dart';

class SignedIn extends AuthService {
  @override
  bool get isLoggedIn => true;
}

Map<String, dynamic> product(int id, String name) => {
  'id': id,
  'name': name,
  'price': 120,
  'stock': 4,
  'image': null,
  'gallery': [],
};
http.Response response(Object data, [int code = 200]) => http.Response(
  jsonEncode(data),
  code,
  headers: {'content-type': 'application/json'},
);
http.Response products(
  List<Map<String, dynamic>> items, {
  int page = 1,
  int last = 1,
}) => response({
  'data': items,
  'current_page': page,
  'last_page': last,
  'total': last == 1 ? items.length : 2,
});
Widget app(Widget child, {bool signedIn = false}) => AuthServiceProvider(
  authService: signedIn ? SignedIn() : AuthService(),
  child: MaterialApp(
    theme: AppTheme.light,
    home: Scaffold(body: child),
  ),
);
void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });
  test(
    'media URLs follow the current deployment and preserve external CDNs',
    () {
      const base = 'https://shop.example.com/api';
      expect(
        AppConfig.resolveStorageUrl(
          'http://192.168.1.20:8000/storage/products/a.jpg',
          base,
        ),
        'https://shop.example.com/storage/products/a.jpg',
      );
      expect(
        AppConfig.resolveStorageUrl('public/storage/products/a.jpg', base),
        'https://shop.example.com/storage/products/a.jpg',
      );
      expect(
        AppConfig.resolveStorageUrl('app/public/products/a.jpg', base),
        'https://shop.example.com/storage/products/a.jpg',
      );
      expect(
        AppConfig.resolveStorageUrl('//cdn.example.com/a.jpg?v=1', base),
        'https://cdn.example.com/a.jpg?v=1',
      );
      expect(
        AppConfig.resolveStorageUrl('products/summer tote.jpg', base),
        'https://shop.example.com/storage/products/summer%20tote.jpg',
      );
      expect(AppConfig.resolveStorageUrl('storage/%2e%2e/.env', base), '');
      expect(AppConfig.resolveStorageUrl('file:///secret', base), '');
      expect(AppConfig.resolveStorageUrl('products/bad%XX.jpg', base), '');
    },
  );
  test(
    'gallery preserves the cover and ignores empty and duplicate photos',
    () {
      final p = Product.fromJson({
        ...product(1, 'Tote'),
        'image': 'products/main.jpg',
        'gallery': ['', 'products/main.jpg', 'products/side.jpg'],
      });
      expect(p.displayImages, ['products/main.jpg', 'products/side.jpg']);
    },
  );
  test('general stock caps variation availability when it is zero', () {
    final p = Product.fromJson({
      ...product(1, 'Tote'),
      'stock': 0,
      'variants': [
        {'id': 1, 'variant_type': 'Color', 'variant_value': 'Blue', 'stock': 4},
        {
          'id': 2,
          'variant_type': 'Color',
          'variant_value': 'Red',
          'stock': 10,
          'status': 'inactive',
        },
      ],
    });
    expect(p.availableStock, 0);
  });
  test(
    'recently viewed does not reuse pictures from the legacy backend cache',
    () async {
      SharedPreferences.setMockInitialValues({
        'recently_viewed': jsonEncode([
          {'id': 9, 'name': 'Legacy', 'image': 'missing.jpg'},
        ]),
      });
      expect(await RecentlyViewedService.load(), isEmpty);
      await RecentlyViewedService.record({
        ...product(1, 'Tote'),
        'image': 'products/main.jpg',
        'effective_price': 80,
      });
      final items = await RecentlyViewedService.load();
      expect(items.single['name'], 'Tote');
      expect(items.single['effective_price'], 80);
      expect(
        (await SharedPreferences.getInstance()).containsKey('recently_viewed'),
        true,
      );
    },
  );
  testWidgets('category, sort and search stay combined in the server request', (
    tester,
  ) async {
    final requests = <Uri>[];
    final api = ApiService(
      client: MockClient((request) async {
        requests.add(request.url);
        return request.url.path.endsWith('/categories')
            ? response({
                'categories': [
                  {'id': 1, 'name': 'Bags'},
                ],
              })
            : products([product(1, 'Cotton tote')]);
      }),
    );
    await tester.pumpWidget(app(CatalogBrowser(api: api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bags'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Sort products'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.widgetWithText(CupertinoActionSheetAction, 'Top rated'),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('catalog-search')),
      'cotton',
    );
    await tester.testTextInput.receiveAction(TextInputAction.search);
    await tester.pumpAndSettle();
    final query = requests
        .lastWhere((uri) => uri.path.endsWith('/products'))
        .queryParameters;
    expect(query['category_id'], '1');
    expect(query['sort'], 'rating');
    expect(query['search'], 'cotton');
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'home navigation search submits and clears without losing filters',
    (tester) async {
      tester.view.physicalSize = const Size(360, 844);
      tester.view.devicePixelRatio = 1;
      tester.view.padding = const FakeViewPadding(top: 24, bottom: 24);
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      addTearDown(tester.view.resetPadding);
      final requests = <Uri>[];
      final api = ApiService(
        client: MockClient((request) async {
          requests.add(request.url);
          return request.url.path.endsWith('/categories')
              ? response({
                  'categories': [
                    {'id': 1, 'name': 'Bags'},
                  ],
                })
              : products([product(1, 'Cotton tote')]);
        }),
      );
      await tester.pumpWidget(app(HomeScreen(api: api), signedIn: true));
      await tester.pumpAndSettle();
      final search = find.byKey(const ValueKey('catalog-search'));
      final navigation = find.byKey(const ValueKey('catalog-navigation'));
      expect(find.descendant(of: navigation, matching: search), findsOneWidget);
      expect(tester.getTopLeft(search).dy, greaterThanOrEqualTo(32));
      await tester.enterText(search, 'cotton');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Bags'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Sort products'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(CupertinoActionSheetAction, 'Top rated'),
      );
      await tester.pumpAndSettle();
      var query = requests
          .lastWhere((uri) => uri.path.endsWith('/products'))
          .queryParameters;
      expect(query['search'], 'cotton');
      expect(query['category_id'], '1');
      expect(query['sort'], 'rating');
      await tester.tap(find.byIcon(CupertinoIcons.xmark_circle_fill));
      await tester.pumpAndSettle();
      query = requests
          .lastWhere((uri) => uri.path.endsWith('/products'))
          .queryParameters;
      expect(query.containsKey('search'), isFalse);
      expect(query['category_id'], '1');
      expect(query['sort'], 'rating');
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('load more requests the next page and keeps previous products', (
    tester,
  ) async {
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/categories'))
          return response({'categories': []});
        final page = int.parse(request.url.queryParameters['page']!);
        return products(
          [product(page, page == 1 ? 'First tote' : 'Next tote')],
          page: page,
          last: 2,
        );
      }),
    );
    await tester.pumpWidget(app(CatalogBrowser(api: api)));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Load more products'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Load more products'));
    await tester.pumpAndSettle();
    expect(find.text('First tote'), findsOneWidget);
    expect(find.text('Next tote'), findsOneWidget);
    expect(find.text('Load more products'), findsNothing);
  });
  testWidgets('catalog retry recovers from a server failure', (tester) async {
    int count = 0;
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/categories'))
          return response({'categories': []});
        if (count++ == 0)
          return response({'message': 'Temporarily offline'}, 503);
        return products([product(1, 'Recovered tote')]);
      }),
    );
    await tester.pumpWidget(app(CatalogBrowser(api: api)));
    await tester.pumpAndSettle();
    expect(find.text('Unable to load products'), findsOneWidget);
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Recovered tote'), findsOneWidget);
  });
  testWidgets('late filter responses cannot replace the current selection', (
    tester,
  ) async {
    final delayed = Completer<http.Response>();
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/categories'))
          return response({
            'categories': [
              {'id': 1, 'name': 'Bags'},
            ],
          });
        if (request.url.queryParameters['category_id'] == '1')
          return delayed.future;
        return products([product(1, 'Current tote')]);
      }),
    );
    await tester.pumpWidget(app(CatalogBrowser(api: api)));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bags'));
    await tester.pump();
    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();
    delayed.complete(products([product(2, 'Stale tote')]));
    await tester.pumpAndSettle();
    expect(find.text('Current tote'), findsOneWidget);
    expect(find.text('Stale tote'), findsNothing);
  });
  testWidgets('photos open full screen and next and close controls work', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(
        const SizedBox(
          width: 360,
          child: ProductGallery(
            images: ['products/a.jpg', 'products/b.jpg'],
            label: 'Tote',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('1 / 2'));
    await tester.pumpAndSettle();
    expect(find.text('Photo 1 of 2'), findsOneWidget);
    expect(find.byType(InteractiveViewer), findsWidgets);
    await tester.tap(find.byTooltip('Next photo'));
    await tester.pumpAndSettle();
    expect(find.text('Photo 2 of 2'), findsOneWidget);
    await tester.tap(find.byTooltip('Close photos'));
    await tester.pumpAndSettle();
    expect(find.text('1 / 2'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'failed Buy Now stays on the product and shows the server error',
    (tester) async {
      final api = ApiService(
        client: MockClient(
          (request) async => request.method == 'POST'
              ? response({
                  'message': 'Stock changed. Try another quantity.',
                }, 422)
              : response({'product': product(1, 'Tote')}),
        ),
      );
      await tester.pumpWidget(
        app(ProductDetailScreen(productId: 1, api: api), signedIn: true),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Buy Now'));
      await tester.pumpAndSettle();
      expect(find.text('Stock changed. Try another quantity.'), findsOneWidget);
      expect(find.byType(CartScreen), findsNothing);
      expect(find.byType(ProductDetailScreen), findsOneWidget);
    },
  );
  testWidgets(
    'the first stocked variant is selected and quantity uses its limit',
    (tester) async {
      Map<String, dynamic>? submitted;
      final api = ApiService(
        client: MockClient((request) async {
          if (request.method == 'POST') {
            submitted = jsonDecode(request.body);
            return response({'message': 'Added'});
          }
          return response({
            'product': {
              ...product(1, 'Tote'),
              'stock': 2,
              'variants': [
                {
                  'id': 4,
                  'variant_type': 'Color',
                  'variant_value': 'Red',
                  'stock': 0,
                },
                {
                  'id': 5,
                  'variant_type': 'Color',
                  'variant_value': 'Blue',
                  'stock': 2,
                },
              ],
            },
          });
        }),
      );
      await tester.pumpWidget(
        app(ProductDetailScreen(productId: 1, api: api), signedIn: true),
      );
      await tester.pumpAndSettle();
      final option = find.widgetWithText(ChoiceChip, 'Blue');
      await tester.scrollUntilVisible(
        option,
        180,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      expect(tester.widget<ChoiceChip>(option).selected, true);
      await tester.scrollUntilVisible(
        find.byIcon(CupertinoIcons.add),
        150,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.byIcon(CupertinoIcons.add));
      await tester.pump();
      await tester.tap(find.byIcon(CupertinoIcons.add));
      await tester.pump();
      await tester.tap(find.text('Add to Cart'));
      await tester.pumpAndSettle();
      expect(submitted?['variant_id'], 5);
      expect(submitted?['quantity'], 2);
    },
  );
  testWidgets('unselected cart items stay unselected after a quantity update', (
    tester,
  ) async {
    int qty = 1;
    final api = ApiService(
      client: MockClient((request) async {
        if (request.method == 'PUT') {
          qty = jsonDecode(request.body)['quantity'];
          return response({});
        }
        return response({
          'cart': {
            'id': 1,
            'subtotal': 120 * (qty + 1),
            'items': [
              {
                'id': 11,
                'product_id': 1,
                'quantity': qty,
                'unit_price': 120,
                'line_total': 120 * qty,
                'product': product(1, 'First tote'),
              },
              {
                'id': 12,
                'product_id': 2,
                'quantity': 1,
                'unit_price': 120,
                'line_total': 120,
                'product': product(2, 'Second tote'),
              },
            ],
          },
        });
      }),
    );
    await tester.pumpWidget(app(CartScreen(api: api), signedIn: true));
    await tester.pumpAndSettle();
    final second = find.descendant(
      of: find.byKey(const ValueKey('cart-selection-12')),
      matching: find.byType(CupertinoCheckbox),
    );
    final cartScroll = find
        .descendant(
          of: find.byType(ListView),
          matching: find.byType(Scrollable),
        )
        .first;
    await tester.scrollUntilVisible(second, 150, scrollable: cartScroll);
    await tester.pumpAndSettle();
    await tester.tap(second);
    await tester.pump();
    await tester.ensureVisible(find.byIcon(CupertinoIcons.add).first);
    await tester.pumpAndSettle();
    await tester.tap(find.byIcon(CupertinoIcons.add).first);
    await tester.pumpAndSettle();
    expect(qty, 2);
    await tester.scrollUntilVisible(second, 150, scrollable: cartScroll);
    await tester.pumpAndSettle();
    expect(tester.widget<CupertinoCheckbox>(second).value, false);
  });
  testWidgets('search and categories stay pinned while products scroll', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = ApiService(
      client: MockClient(
        (request) async => request.url.path.endsWith('/categories')
            ? response({
                'categories': [
                  {'id': 1, 'name': 'Bags'},
                  {'id': 2, 'name': 'Home'},
                ],
              })
            : products([
                for (var i = 1; i <= 12; i++) product(i, 'Product $i'),
              ]),
      ),
    );
    await tester.pumpWidget(app(CatalogBrowser(api: api)));
    await tester.pumpAndSettle();
    final search = tester.getRect(find.byKey(const ValueKey('catalog-search')));
    final categories = tester.getRect(
      find.byKey(const ValueKey('catalog-categories')),
    );
    expect(find.text('Curated for you'), findsNothing);
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -420));
    await tester.pumpAndSettle();
    expect(
      tester.getRect(find.byKey(const ValueKey('catalog-search'))),
      search,
    );
    expect(
      tester.getRect(find.byKey(const ValueKey('catalog-categories'))),
      categories,
    );
    expect(find.text('Bags'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  for (final width in [320.0, 390.0, 768.0]) {
    testWidgets('catalog fits a $width pixel viewport', (tester) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final api = ApiService(
        client: MockClient(
          (request) async => request.url.path.endsWith('/categories')
              ? response({'categories': []})
              : products([
                  product(1, 'A longer product name that wraps gracefully'),
                ]),
        ),
      );
      await tester.pumpWidget(app(CatalogBrowser(api: api, showIntro: true)));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }
}
