import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/wishlist_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';

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

Map<String, dynamic> product({
  int id = 7,
  String name = 'Favorite fixture',
  String status = 'active',
  int stock = 5,
  String category = 'Home essentials',
}) => {
  'id': id,
  'name': name,
  'price': 100,
  'stock': stock,
  'status': status,
  'category': {'id': 2, 'name': category},
};

void main() {
  testWidgets('empty wishlist invites the buyer to browse products', (
    tester,
  ) async {
    final api = ApiService(
      client: MockClient((r) async => json({'favorites': []})),
    );
    await tester.pumpWidget(app(WishlistScreen(api: api)));
    await tester.pumpAndSettle();

    expect(find.text('Your wishlist is empty'), findsOneWidget);
    expect(
      find.text('Save products while browsing and they’ll be collected here.'),
      findsOneWidget,
    );
    expect(find.text('Browse products'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('saved products add to cart and can be removed', (tester) async {
    tester.view.physicalSize = const Size(430, 932);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final cartAdds = <Map<String, dynamic>>[];
    var toggles = 0;
    final api = ApiService(
      client: MockClient((r) async {
        if (r.url.path.endsWith('/cart/add')) {
          cartAdds.add(jsonDecode(r.body) as Map<String, dynamic>);
          return json({'message': 'Item added to cart.'}, 201);
        }
        if (r.url.path.endsWith('/favorites/toggle')) {
          toggles++;
          return json({'message': 'Removed.', 'is_favorited': false});
        }
        return json({
          'favorites': [
            {'id': 1, 'product': product()},
          ],
        });
      }),
    );

    await tester.pumpWidget(app(WishlistScreen(api: api)));
    await tester.pumpAndSettle();

    // Saved product card mirrors the website card details.
    expect(find.text('Favorite fixture'), findsOneWidget);
    expect(find.text('HOME ESSENTIALS'), findsOneWidget);
    expect(find.text('₱100.00'), findsOneWidget);
    expect(find.text('1 saved product'), findsOneWidget);

    await tester.tap(find.text('Add to cart'));
    await tester.pumpAndSettle();
    expect(cartAdds, [
      {'product_id': 7, 'quantity': 1},
    ]);
    expect(find.text('Favorite fixture added to your cart.'), findsOneWidget);

    await tester.tap(find.byIcon(CupertinoIcons.heart_fill));
    await tester.pumpAndSettle();
    expect(toggles, 1);
    expect(find.text('Your wishlist is empty'), findsOneWidget);
    expect(find.text('0 saved products'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('products that are not on sale show as unavailable', (
    tester,
  ) async {
    final api = ApiService(
      client: MockClient(
        (r) async => json({
          'favorites': [
            {'id': 2, 'product': product(id: 8, status: 'inactive')},
          ],
        }),
      ),
    );
    await tester.pumpWidget(app(WishlistScreen(api: api)));
    await tester.pumpAndSettle();

    expect(find.text('Unavailable'), findsOneWidget);
    expect(find.text('Add to cart'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
