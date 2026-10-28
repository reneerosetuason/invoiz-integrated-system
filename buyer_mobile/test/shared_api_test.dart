import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:invoiz/config.dart';
import 'package:invoiz/models/cart.dart';
import 'package:invoiz/models/checkout_quote.dart';
import 'package:invoiz/models/product.dart';
import 'package:invoiz/models/voucher.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';

void main() {
  setUp(() {
    ApiService.setToken(null);
    SharedPreferences.setMockInitialValues({});
  });

  test(
    'API requests use the configured shared backend and bearer/JSON headers',
    () async {
      final api = ApiService(
        client: MockClient((request) async {
          expect(request.url.origin, Uri.parse(AppConfig.apiBaseUrl).origin);
          expect(
            request.url.path,
            '${Uri.parse(AppConfig.apiBaseUrl).path}/products',
          );
          expect(request.url.queryParameters['search'], 'fan & light');
          expect(request.headers['Authorization'], 'Bearer synthetic-token');
          expect(request.headers['Accept'], 'application/json');
          return http.Response('{"data":[]}', 200);
        }),
      );
      ApiService.setToken('synthetic-token');
      expect(await api.get('products', query: {'search': 'fan & light'}), {
        'data': [],
      });
    },
  );

  test(
    'API failures preserve verification metadata and handle non-JSON responses',
    () async {
      final denied = ApiService(
        client: MockClient(
          (_) async => http.Response(
            '{"message":"Please verify your email","verification_required":true}',
            403,
          ),
        ),
      );
      await expectLater(
        denied.post('mobile/buyer/login', {}),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'status', 403)
              .having(
                (e) => e.data['verification_required'],
                'verification',
                true,
              ),
        ),
      );
      final bad = ApiService(
        client: MockClient(
          (_) async => http.Response('<html>Server error</html>', 500),
        ),
      );
      await expectLater(
        bad.get('me'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 500)),
      );
    },
  );

  test('uploads use the same authenticated API endpoint', () async {
    final api = ApiService(
      client: MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path.endsWith('/profile'), true);
        expect(
          request.headers['content-type'],
          startsWith('multipart/form-data'),
        );
        expect(request.body, contains('Shared profile'));
        expect(request.body, contains('avatar.png'));
        return http.Response('{"message":"saved"}', 200);
      }),
    );
    await api.postForm(
      'profile',
      {'bio': 'Shared profile'},
      files: [
        http.MultipartFile.fromString(
          'profile_picture',
          'image',
          filename: 'avatar.png',
        ),
      ],
    );
  });

  test(
    'mobile login persists and restores the shared account, then logs out',
    () async {
      final user = {
        'id': 42,
        'email': 'buyer@example.test',
        'first_name': 'Mobile',
        'last_name': 'Buyer',
      };
      final api = ApiService(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/mobile/buyer/login')) {
            return http.Response(
              jsonEncode({'token': 'synthetic-token', 'user': user}),
              200,
            );
          }
          expect(request.headers['Authorization'], 'Bearer synthetic-token');
          return http.Response(
            jsonEncode(request.url.path.endsWith('/me') ? {'user': user} : {}),
            200,
          );
        }),
      );
      final auth = AuthService(api: api);
      await auth.login('buyer@example.test', 'Password123!');
      expect(auth.user?.id, 42);
      final restored = AuthService(api: api);
      await restored.loadFromStorage();
      expect(restored.isLoggedIn, true);
      expect(restored.user?.id, 42);
      await restored.logout();
      expect(restored.isLoggedIn, false);
      expect(ApiService.token, isNull);
    },
  );

  test('revoked shared tokens are removed from local storage', () async {
    SharedPreferences.setMockInitialValues({
      'invoiz:${AppConfig.apiBaseUrl}:token': 'revoked-token',
      'invoiz:${AppConfig.apiBaseUrl}:user': jsonEncode({
        'id': 42,
        'email': 'buyer@example.test',
      }),
    });
    final auth = AuthService(
      api: ApiService(
        client: MockClient(
          (_) async => http.Response('{"message":"Unauthenticated"}', 401),
        ),
      ),
    );
    await auth.loadFromStorage();
    expect(auth.isLoggedIn, false);
    expect(auth.user, isNull);
  });

  test('assets resolve against the shared backend storage', () {
    final origin = Uri.parse(AppConfig.apiBaseUrl).origin;
    expect(
      AppConfig.storageUrl('products/fan.png'),
      '$origin/storage/products/fan.png',
    );
    expect(
      AppConfig.storageUrl('/storage/profile-pictures/avatar.png'),
      '$origin/storage/profile-pictures/avatar.png',
    );
    expect(
      AppConfig.storageUrl('http://127.0.0.1:8000/storage/products/fan.png'),
      '$origin/storage/products/fan.png',
    );
    expect(AppConfig.storageUrl('../.env'), '');
    expect(AppConfig.storageUrl('javascript:alert(1)'), '');
  });

  test(
    'sale prices, hidden voucher codes and server checkout totals decode correctly',
    () {
      final product = Product.fromJson({
        'id': 1,
        'name': 'Fan',
        'price': 100,
        'effective_price': 80,
      });
      expect(product.price, 80);
      final voucher = Voucher.fromJson({
        'id': 1,
        'name': 'Shipping offer',
        'discount_type': 'free_shipping',
        'discount_value': 50,
      });
      expect(voucher.code, 'Shipping offer');
      expect(voucher.descriptionText, contains('Free shipping'));
      final quote = CheckoutQuote.fromJson({
        'subtotal': 200,
        'shipping_fee': 50,
        'discount': 10,
        'shipping_discount': 0,
        'total': 240,
      });
      expect(quote.total, 240);
      expect(quote.shippingFee, 50);
    },
  );

  test('selected checkout keeps only selected cart line IDs', () {
    final cart = Cart.fromJson({
      'cart': {
        'id': 1,
        'subtotal': 300,
        'items': [
          {
            'id': 11,
            'product_id': 1,
            'quantity': 1,
            'unit_price': 100,
            'line_total': 100,
          },
          {
            'id': 12,
            'product_id': 1,
            'quantity': 2,
            'unit_price': 100,
            'line_total': 200,
          },
        ],
      },
    });
    final selected = cart.select([12]);
    expect(selected.items.single.id, 12);
    expect(selected.subtotal, 200);
    expect(cart.items.length, 2);
  });
}
