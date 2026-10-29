import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/product_detail_screen.dart';
import 'package:invoiz/config.dart';
import 'package:invoiz/models/chat.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/widgets/chat_product_card.dart';
import 'package:invoiz/widgets/product_photo.dart';
import 'package:invoiz/widgets/store_avatar.dart';
import 'package:shared_preferences/shared_preferences.dart';

http.Response json(Object value, [int status = 200]) => http.Response(
  jsonEncode(value),
  status,
  headers: {'content-type': 'application/json'},
);

class _SignedIn extends AuthService {
  @override
  bool get isLoggedIn => true;
}

Widget app(Widget child) => AuthServiceProvider(
  authService: _SignedIn(),
  child: MaterialApp(theme: AppTheme.light, home: child),
);

Map<String, dynamic> product() => {
  'id': 1,
  'seller_id': 8,
  'name': 'Classic White T-Shirt',
  'description': 'Soft cotton tee.',
  'price': 999.99,
  'effective_price': 999.99,
  'stock': 5,
  'status': 'active',
  'sold': 0,
  'image': null,
  'gallery': [],
  'variants': [],
  'reviews': [],
  'category': {'id': 2, 'name': 'Fashion'},
  // The richer shop payload the buyer API now returns.
  'shop': {
    'seller_id': 8,
    'business_name': 'Fixture Store',
    'line_of_business': 'Fashion',
    'rating': 4.8,
    'product_count': 3,
    'followers': 2,
    'logo': 'logos/fixture-store.png',
  },
};

void main() {
  setUpAll(() async {
    for (final entry in {
      'Inter': 'assets/fonts/Inter.ttf',
      'Fraunces': 'assets/fonts/Fraunces.ttf',
    }.entries) {
      final loader = FontLoader(entry.key)
        ..addFont(rootBundle.load(entry.value));
      await loader.load();
    }
    final icons = FontLoader('packages/cupertino_icons/CupertinoIcons')
      ..addFont(
        rootBundle.load('packages/cupertino_icons/assets/CupertinoIcons.ttf'),
      );
    await icons.load();
  });
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken('fixture-token');
  });

  testWidgets('messaging from a product sends a product-specific inquiry', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    Map<String, dynamic>? sent;
    final api = ApiService(
      client: MockClient((request) async {
        final path = request.url.path;
        if (path.endsWith('/products/1')) {
          return json({'product': product()});
        }
        if (path.endsWith('/conversations') && request.method == 'POST') {
          sent = jsonDecode(request.body) as Map<String, dynamic>;
          return json({
            'message': 'Message sent.',
            'conversation': {'id': 9, 'seller_id': 8},
          }, 201);
        }
        if (path.endsWith('/conversations/9/messages')) {
          return json({
            'messages': [
              {
                'id': 1,
                'conversation_id': 9,
                'sender_id': 7,
                'body': sent?['body'] ?? '',
                'product_card': {
                  'id': 1,
                  'name': 'Classic White T-Shirt',
                  'image': 'products/t-shirt.png',
                  'price': 999.99,
                },
                'created_at': '2026-10-29T06:00:00Z',
                'is_read': false,
              },
            ],
          });
        }
        return json({'conversations': []});
      }),
    );

    await tester.pumpWidget(app(ProductDetailScreen(productId: 1, api: api)));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Message seller'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Message seller'));
    await tester.pumpAndSettle();

    expect(sent, isNotNull);
    expect(sent!['seller_id'], 8);
    expect(sent!['product_id'], 1);
    expect(sent!.containsKey('product_card'), isFalse);
    expect('${sent!['subject']}', contains('Classic White T-Shirt'));
    expect('${sent!['body']}', contains('Classic White T-Shirt'));
    expect('${sent!['body']}', contains('999.99'));
    expect('${sent!['body']}', contains('Fixture Store'));
    // The thread opens with that message already in it.
    expect(find.textContaining('Classic White T-Shirt'), findsWidgets);
    expect(find.byKey(const ValueKey('chat-product-1')), findsOneWidget);
    expect(
      tester.widget<ProductPhoto>(find.byType(ProductPhoto)).path,
      'products/t-shirt.png',
    );
    expect(tester.takeException(), isNull);
    await tester.tap(find.byType(ChatProductCard));
    await tester.pumpAndSettle();
    expect(find.text('Message seller'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Visit Store opens the seller storefront', (tester) async {
    tester.view.physicalSize = const Size(430, 932);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final visited = <String>[];
    final api = ApiService(
      client: MockClient((request) async {
        final path = request.url.path;
        if (path.endsWith('/products/1')) {
          return json({'product': product()});
        }
        if (path.endsWith('/stores/8')) {
          visited.add(path);
          return json({
            'store': {
              'seller_id': 8,
              'business_name': 'Fixture Store',
              'rating': 4.8,
              'product_count': 0,
              'total_sold': 0,
              'followers': 2,
              'is_following': false,
              'logo': 'logos/fixture-store.png',
            },
            'products': {
              'data': [],
              'current_page': 1,
              'last_page': 1,
              'total': 0,
            },
          });
        }
        return json({'message': 'Not found.'}, 404);
      }),
    );

    await tester.pumpWidget(app(ProductDetailScreen(productId: 1, api: api)));
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Visit Store'));
    await tester.pumpAndSettle();
    final productAvatar = tester.widget<StoreAvatar>(find.byType(StoreAvatar));
    expect(productAvatar.path, 'logos/fixture-store.png');
    final productImage = tester.widget<Image>(
      find.descendant(
        of: find.byType(StoreAvatar),
        matching: find.byType(Image),
      ),
    );
    expect(
      (productImage.image as NetworkImage).url,
      AppConfig.storageUrl('logos/fixture-store.png'),
    );
    await tester.tap(find.text('Visit Store'));
    await tester.pumpAndSettle();

    expect(visited, ['/api/stores/8']);
    expect(find.text('Fixture Store'), findsWidgets);
    expect(find.text('Store not found.'), findsNothing);
    expect(
      tester.widget<StoreAvatar>(find.byType(StoreAvatar)).path,
      'logos/fixture-store.png',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('store avatar keeps an initial when no logo was uploaded', (
    tester,
  ) async {
    await tester.pumpWidget(
      app(const Center(child: StoreAvatar(name: 'Fixture Store'))),
    );
    expect(find.text('F'), findsOneWidget);
    expect(find.byType(Image), findsNothing);
    expect(tester.takeException(), isNull);
  });

  for (final textScale in [1.0, 2.0]) {
    testWidgets('product message card fits 320px with text scale $textScale', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(320, 740);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final capture = GlobalKey();
      await tester.pumpWidget(
        app(
          RepaintBoundary(
            key: capture,
            child: MediaQuery(
              data: MediaQueryData(
                size: const Size(320, 740),
                textScaler: TextScaler.linear(textScale),
              ),
              child: const Scaffold(
                body: Center(
                  child: SizedBox(
                    width: 224,
                    child: ChatProductCard(
                      card: ChatProduct(
                        id: 1,
                        name:
                            'Linen everyday bag with an adjustable shoulder strap',
                        price: 11999.50,
                        option: 'Size: Extra large / Natural linen',
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('₱11,999.50'), findsOneWidget);
      final priceParagraph = tester.renderObject<RenderParagraph>(
        find.text('₱11,999.50'),
      );
      expect(
        priceParagraph.getBoxesForSelection(
          const TextSelection(baseOffset: 0, extentOffset: 10),
        ),
        hasLength(1),
      );
      expect(find.text('Size: Extra large / Natural linen'), findsOneWidget);
      expect(
        tester.getSize(find.byKey(const ValueKey('chat-product-1'))).height,
        lessThan(textScale == 1 ? 160 : 440),
      );
      expect(tester.takeException(), isNull);
      final output = Platform.environment['INVOIZ_PRODUCT_CHAT_SCREENSHOTS'];
      if (output != null) {
        await tester.runAsync(() async {
          final boundary =
              capture.currentContext!.findRenderObject()
                  as RenderRepaintBoundary;
          final image = await boundary.toImage(pixelRatio: 1);
          final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
          final file = File('$output/product-card-${textScale.toInt()}x.png');
          await file.parent.create(recursive: true);
          await file.writeAsBytes(bytes!.buffer.asUint8List());
          image.dispose();
        });
      }
    });
  }
}
