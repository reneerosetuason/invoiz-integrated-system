import 'package:flutter/cupertino.dart';
import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/models/address.dart';
import 'package:invoiz/models/order.dart';
import 'package:invoiz/screens/addresses_screen.dart';
import 'package:invoiz/screens/cart_screen.dart';
import 'package:invoiz/screens/add_address_screen.dart';
import 'package:invoiz/screens/order_detail_screen.dart';
import 'package:invoiz/screens/seller_store_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:invoiz/theme.dart';

http.Response json(Object value, [int status = 200]) => http.Response(
  jsonEncode(value),
  status,
  headers: {'content-type': 'application/json'},
);
Map<String, dynamic> session(String token) => {
  'token': token,
  'user': {
    'id': 7,
    'first_name': 'Test',
    'last_name': 'Buyer',
    'email': 'fixture@example.test',
  },
};
Widget app(Widget screen, {AuthService? auth}) => AuthServiceProvider(
  authService: auth ?? AuthService(),
  child: MaterialApp(theme: AppTheme.light, home: screen),
);
Map<String, dynamic> order({String status = 'delivered'}) => {
  'id': 42,
  'status': status,
  'total_amount': 150,
  'created_at': '2026-10-28T10:00:00Z',
  'can_cancel': false,
  'items': [
    {
      'id': 98,
      'product_id': 6,
      'product_name': 'Fixture item',
      'variant_label': 'Size: L',
      'quantity': 2,
      'price': 50,
      'subtotal': 100,
    },
  ],
  'deliveries': [
    {'id': 83, 'status': status, 'tracking_number': 'TEST-PARCEL'},
  ],
  'returns': [],
};
Map<String, dynamic> store() => {
  'seller_id': 8,
  'business_name': 'Fixture Store',
  'rating': 4.5,
  'review_count': 1,
  'rating_breakdown': {'5': 1},
  'product_count': 0,
  'total_sold': 0,
  'followers': 0,
};
Map<String, dynamic> address(int id, {bool defaultAddress = false}) => {
  'id': id,
  'recipient_name': 'Recipient $id',
  'phone': '09171234567',
  'address_line': '1 Fixture St',
  'barangay': 'Test',
  'city': 'Manila',
  'province': 'Metro Manila',
  'is_default': defaultAddress,
};

class _SignedInAuth extends AuthService {
  @override
  bool get isLoggedIn => true;
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
    ApiService.onSessionInvalidated = null;
  });

  test(
    'validation errors are actionable and malformed messages never cause a cast error',
    () async {
      final api = ApiService(
        client: MockClient(
          (r) async => json({
            'message': 123,
            'errors': {
              'quantity': ['Only 2 items available.'],
            },
          }, 422),
        ),
      );
      await expectLater(
        api.post('cart/add', {}),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'message',
            'Only 2 items available.',
          ),
        ),
      );
    },
  );
  test(
    'invalid login keeps the credential error instead of reporting an expired session',
    () async {
      final api = ApiService(
        client: MockClient(
          (r) async => json({'message': 'Invalid email or password.'}, 401),
        ),
      );
      await expectLater(
        api.post('mobile/buyer/login', {}),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'message',
            'Invalid email or password.',
          ),
        ),
      );
    },
  );
  test(
    'revoked session clears both memory and persisted credentials',
    () async {
      var revoked = false;
      final api = ApiService(
        client: MockClient(
          (r) async => revoked
              ? json({'message': 'Unauthenticated.'}, 401)
              : json(session('old-token')),
        ),
      );
      final auth = AuthService(api: api);
      await auth.login('fixture@example.test', 'fixture');
      expect(auth.isLoggedIn, isTrue);
      revoked = true;
      await expectLater(api.get('cart'), throwsA(isA<ApiException>()));
      await Future<void>.delayed(Duration.zero);
      expect(auth.isLoggedIn, isFalse);
      expect(ApiService.token, isNull);
      final prefs = await SharedPreferences.getInstance();
      expect(
        prefs.getKeys().where(
          (key) => key.endsWith(':token') || key.endsWith(':user'),
        ),
        isEmpty,
      );
      auth.dispose();
    },
  );
  test(
    'a late 401 from a previous session cannot erase a fresh login',
    () async {
      final delayed = Completer<http.Response>();
      var logins = 0;
      final api = ApiService(
        client: MockClient((r) async {
          if (r.url.path.endsWith('/login'))
            return json(session('token-${++logins}'));
          return delayed.future;
        }),
      );
      final auth = AuthService(api: api);
      await auth.login('fixture@example.test', 'fixture');
      final pending = expectLater(
        api.get('cart'),
        throwsA(isA<ApiException>()),
      );
      await auth.login('fixture@example.test', 'fixture');
      delayed.complete(json({'message': 'Unauthenticated.'}, 401));
      await pending;
      expect(auth.token, 'token-2');
      expect(auth.isLoggedIn, isTrue);
      auth.dispose();
    },
  );
  test('business permission errors do not sign out a valid session', () async {
    var loggedIn = false;
    final api = ApiService(
      client: MockClient(
        (r) async => loggedIn
            ? json({'message': 'This order belongs to another buyer.'}, 403)
            : json(session('valid-token')),
      ),
    );
    final auth = AuthService(api: api);
    await auth.login('fixture@example.test', 'fixture');
    loggedIn = true;
    await expectLater(api.get('orders/999'), throwsA(isA<ApiException>()));
    expect(auth.isLoggedIn, isTrue);
    auth.dispose();
  });
  test('suspension code immediately invalidates the current session', () async {
    var inactive = false;
    final api = ApiService(
      client: MockClient(
        (r) async => inactive
            ? json({'message': 'Inactive.', 'code': 'account_inactive'}, 403)
            : json(session('valid-token')),
      ),
    );
    final auth = AuthService(api: api);
    await auth.login('fixture@example.test', 'fixture');
    inactive = true;
    await expectLater(api.get('cart'), throwsA(isA<ApiException>()));
    await Future<void>.delayed(Duration.zero);
    expect(auth.isLoggedIn, isFalse);
    auth.dispose();
  });
  testWidgets('account consumers rebuild after a login', (tester) async {
    final auth = AuthService(
      api: ApiService(
        client: MockClient((r) async => json(session('valid-token'))),
      ),
    );
    await tester.pumpWidget(
      app(
        Builder(
          builder: (context) => Text(
            AuthServiceProvider.of(context).isLoggedIn ? 'Signed in' : 'Guest',
          ),
        ),
        auth: auth,
      ),
    );
    expect(find.text('Guest'), findsOneWidget);
    await auth.login('fixture@example.test', 'fixture');
    await tester.pump();
    expect(find.text('Signed in'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    auth.dispose();
  });
  test('order model retains all parcel IDs and return status', () {
    final data = order();
    data['deliveries'] = [
      {'id': 1, 'status': 'out_for_delivery'},
      {'id': 2, 'status': 'delivered'},
    ];
    data['returns'] = [
      {'order_item_id': 98, 'status': 'requested'},
    ];
    final parsed = Order.fromJson(data);
    expect(parsed.deliveries.map((d) => d.id), [1, 2]);
    expect(parsed.deliveries.first.canShowOtp, isTrue);
    expect(parsed.returns.single['order_item_id'], 98);
  });
  testWidgets(
    'store reviews read paginated data and display the actual review',
    (tester) async {
      tester.view.resetPhysicalSize();
      final api = ApiService(
        client: MockClient(
          (r) async => r.url.path.endsWith('/reviews')
              ? json({
                  'reviews': {
                    'data': [
                      {
                        'id': 1,
                        'rating': 5,
                        'comment': 'Actual store review',
                        'buyer': {'first_name': 'Test', 'last_name': 'Buyer'},
                      },
                    ],
                    'current_page': 1,
                    'last_page': 1,
                  },
                })
              : json({
                  'store': store(),
                  'products': {'data': [], 'current_page': 1, 'last_page': 1},
                }),
        ),
      );
      await tester.pumpWidget(app(SellerStoreScreen(sellerId: 8, api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Reviews'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Actual store review'),
        200,
        scrollable: find.descendant(
          of: find.byType(ListView),
          matching: find.byType(Scrollable),
        ),
      );
      expect(find.text('Actual store review'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'empty store review breakdown is safe and failed requests can be retried',
    (tester) async {
      var fail = true;
      final info = store()..['rating_breakdown'] = [];
      final api = ApiService(
        client: MockClient(
          (r) async => r.url.path.endsWith('/reviews')
              ? (fail
                    ? json({'message': 'Temporary review error'}, 503)
                    : json({
                        'reviews': {
                          'data': [],
                          'current_page': 1,
                          'last_page': 1,
                        },
                      }))
              : json({
                  'store': info,
                  'products': {'data': [], 'current_page': 1, 'last_page': 1},
                }),
        ),
      );
      await tester.pumpWidget(app(SellerStoreScreen(sellerId: 8, api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Reviews'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Retry reviews'),
        200,
        scrollable: find
            .descendant(
              of: find.byType(ListView).first,
              matching: find.byType(Scrollable),
            )
            .first,
      );
      await tester.pumpAndSettle();
      expect(find.text('Retry reviews'), findsOneWidget);
      fail = false;
      await tester.tap(find.text('Retry reviews'));
      await tester.pumpAndSettle();
      expect(find.text('No reviews yet.'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets('return request uses the purchased order item ID and quantity', (
    tester,
  ) async {
    Map<String, dynamic>? posted;
    final api = ApiService(
      client: MockClient((r) async {
        if (r.method == 'POST') {
          posted = jsonDecode(r.body);
          return json({'message': 'Requested'}, 201);
        }
        return json({'order': order()});
      }),
    );
    await tester.pumpWidget(app(OrderDetailScreen(orderId: 42, api: api)));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Request return'),
      250,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Request return'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();
    expect(posted?['order_item_id'], 98);
    expect(posted?['quantity'], 1);
    expect(posted?.containsKey('product_id'), isFalse);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('delivery verification code uses the selected order and parcel', (
    tester,
  ) async {
    String? requested;
    final api = ApiService(
      client: MockClient((r) async {
        if (r.url.path.endsWith('/otp')) {
          requested = r.url.path;
          return json({'otp': '123456'});
        }
        return json({'order': order(status: 'out_for_delivery')});
      }),
    );
    await tester.pumpWidget(app(OrderDetailScreen(orderId: 42, api: api)));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Show delivery code'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Show delivery code'));
    await tester.pumpAndSettle();
    expect(requested, endsWith('/orders/42/deliveries/83/otp'));
    expect(find.text('123456'), findsOneWidget);
    await tester.tap(find.text('Close'));
    await tester.pumpAndSettle();
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets(
    'address book sets a new default through the owned address endpoint',
    (tester) async {
      String? changed;
      final api = ApiService(
        client: MockClient((r) async {
          if (r.method == 'PUT') {
            changed = r.url.path;
            expect(jsonDecode(r.body)['is_default'], isTrue);
            return json({'message': 'Updated'});
          }
          return json({
            'addresses': [address(1, defaultAddress: true), address(2)],
          });
        }),
      );
      await tester.pumpWidget(app(AddressesScreen(api: api)));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Make default'));
      await tester.pumpAndSettle();
      expect(changed, endsWith('/addresses/2'));
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'edit address saves changes using PUT instead of creating a duplicate',
    (tester) async {
      String? method;
      final api = ApiService(
        client: MockClient((r) async {
          method = r.method;
          expect(r.url.path, endsWith('/addresses/2'));
          return json({'message': 'Updated'});
        }),
      );
      await tester.pumpWidget(
        app(AddAddressScreen(address: Address.fromJson(address(2)), api: api)),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Save Address'),
        200,
        scrollable: find
            .descendant(
              of: find.byType(ListView).first,
              matching: find.byType(Scrollable),
            )
            .first,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save Address'));
      await tester.pumpAndSettle();
      expect(method, 'PUT');
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    },
  );
  testWidgets(
    'unavailable cart lines stay removable and cannot be selected for checkout',
    (tester) async {
      final api = ApiService(
        client: MockClient(
          (r) async => json({
            'cart': {
              'id': 1,
              'subtotal': 100,
              'items': [
                {
                  'id': 1,
                  'product_id': 5,
                  'quantity': 1,
                  'unit_price': 100,
                  'line_total': 100,
                  'available_stock': 0,
                  'product': {
                    'id': 5,
                    'name': 'Unavailable fixture',
                    'price': 100,
                    'stock': 50,
                  },
                },
              ],
            },
          }),
        ),
      );
      await tester.pumpWidget(app(CartScreen(api: api), auth: _SignedInAuth()));
      await tester.pumpAndSettle();
      final check = tester.widget<CupertinoCheckbox>(
        find.byType(CupertinoCheckbox),
      );
      expect(check.value, isFalse);
      expect(check.onChanged, isNull);
      expect(find.text('Unavailable in this quantity'), findsOneWidget);
      expect(find.byIcon(CupertinoIcons.trash), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
    },
  );
}
