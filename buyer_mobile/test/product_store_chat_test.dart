import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/product_detail_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
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
  },
};

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken('fixture-token');
  });

  testWidgets('messaging from a product sends a product-specific inquiry', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(430, 932);
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
    expect('${sent!['subject']}', contains('Classic White T-Shirt'));
    expect('${sent!['body']}', contains('Classic White T-Shirt'));
    expect('${sent!['body']}', contains('999.99'));
    expect('${sent!['body']}', contains('Fixture Store'));
    // The thread opens with that message already in it.
    expect(find.textContaining('Classic White T-Shirt'), findsWidgets);
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
    await tester.tap(find.text('Visit Store'));
    await tester.pumpAndSettle();

    expect(visited, ['/api/stores/8']);
    expect(find.text('Fixture Store'), findsWidgets);
    expect(find.text('Store not found.'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
