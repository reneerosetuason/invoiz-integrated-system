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
  'shop': {
    'seller_id': 8,
    'business_name': 'Fixture Store',
    'line_of_business': 'Fashion',
    'rating': 4.8,
    'product_count': 3,
    'followers': 2,
    'logo': 'logos/fixture-store.jpg',
  },
};

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken('fixture-token');
  });

  testWidgets('tapping the store picture on a product opens it full screen', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(430, 932);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/products/1')) {
          return json({'product': product()});
        }
        return json({'message': 'Not found.'}, 404);
      }),
    );

    await tester.pumpWidget(app(ProductDetailScreen(productId: 1, api: api)));
    await tester.pumpAndSettle();

    final avatar = find.byType(StoreAvatar);
    expect(avatar, findsOneWidget);

    await tester.ensureVisible(avatar);
    await tester.pumpAndSettle();
    await tester.tap(avatar);
    await tester.pumpAndSettle();

    expect(find.text('Photo 1 of 1'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('a store without a logo does not open a viewer', (tester) async {
    tester.view.physicalSize = const Size(430, 932);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final noLogo = product();
    final shop = Map<String, dynamic>.from(noLogo['shop'] as Map);
    shop['logo'] = null;
    noLogo['shop'] = shop;
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/products/1')) {
          return json({'product': noLogo});
        }
        return json({'message': 'Not found.'}, 404);
      }),
    );

    await tester.pumpWidget(app(ProductDetailScreen(productId: 1, api: api)));
    await tester.pumpAndSettle();

    final avatar = find.byType(StoreAvatar);
    await tester.ensureVisible(avatar);
    await tester.pumpAndSettle();
    await tester.tap(avatar);
    await tester.pumpAndSettle();

    expect(find.text('Photo 1 of 1'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
