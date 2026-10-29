import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/checkout_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

Map<String, dynamic> offer(int id, String name, double savings) => {
  'id': id,
  'name': name,
  'discount_type': 'percent',
  'discount_value': 20,
  'min_spend': 0,
  'checkout_savings': savings,
};

http.Response json(Object value, [int status = 200]) => http.Response(
  jsonEncode(value),
  status,
  headers: {'content-type': 'application/json'},
);

Future<void> openCheckout(
  WidgetTester tester,
  List<Map<String, dynamic>> requests, {
  List<Map<String, dynamic>>? vouchers,
  bool rejectVoucher = false,
  bool shipping = false,
  int previewFailures = 0,
  bool cartFailure = false,
  bool emptyCart = false,
  bool skipVoucherScroll = false,
  double width = 360,
  double textScale = 1,
}) async {
  tester.view.physicalSize = Size(width, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final offers = vouchers ?? [offer(1, 'Store A offer', 20)];
  var previewCalls = 0;
  final api = ApiService(
    client: MockClient((request) async {
      final body = request.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(request.body) as Map<String, dynamic>;
      requests.add({'path': request.url.path, 'body': body});
      if (request.url.path.endsWith('/cart')) {
        if (cartFailure)
          return json({'message': 'Server temporarily unavailable.'}, 503);
        return json({
          'cart': {
            'id': 1,
            'subtotal': 1000,
            'items': [
              for (final id in emptyCart ? <int>[] : [11, 12])
                {
                  'id': id,
                  'product_id': id,
                  'quantity': 1,
                  'unit_price': id == 11 ? 100 : 900,
                  'line_total': id == 11 ? 100 : 900,
                  'product': {
                    'id': id,
                    'seller_id': id == 11 ? 21 : 22,
                    'name': 'Product $id',
                    'price': id == 11 ? 100 : 900,
                    'stock': 10,
                  },
                },
            ],
          },
        });
      }
      if (request.url.path.endsWith('/addresses')) {
        return json({'addresses': []});
      }
      if (request.url.path.endsWith('/vouchers/1/claim')) {
        return json({
          'claim': {'id': 71},
        }, 201);
      }
      if (request.url.path.endsWith('/orders/preview')) {
        if (previewCalls++ < previewFailures) {
          return json({
            'message':
                'Delivery is unavailable because the seller pickup address is incomplete (missing: street address, barangay, city, province).',
          }, 422);
        }
        final applied = body['voucher_claim_id'] == 71;
        if (applied && rejectVoucher) {
          return json({
            'message':
                'This voucher does not apply to the items in your order.',
          }, 422);
        }
        return json({
          'quote': {
            'subtotal': 100,
            'shipping_fee': applied && shipping ? 0 : 50,
            'discount': applied && !shipping ? 20 : 0,
            'shipping_discount': applied && shipping ? 50 : 0,
            'total': applied ? (shipping ? 100 : 130) : 150,
          },
          'vouchers': offers,
        });
      }
      // A global voucher list must never be used by checkout, even if it
      // contains a seemingly more generous offer from an unrelated store.
      if (request.url.path.endsWith('/vouchers')) {
        return json({
          'vouchers': [offer(99, 'Unrelated store offer', 999)],
        });
      }
      return json({'unread_count': 0, 'favorites': [], 'notifications': []});
    }),
  );
  await tester.pumpWidget(
    AuthServiceProvider(
      authService: AuthService(),
      child: MaterialApp(
        theme: AppTheme.light,
        builder: (context, child) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(textScale)),
          child: child!,
        ),
        home: CheckoutScreen(cartItemIds: const [11], api: api),
      ),
    ),
  );
  await tester.pumpAndSettle();
  if (skipVoucherScroll) return;
  await tester.scrollUntilVisible(
    find.text('Voucher'),
    200,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });

  testWidgets(
    'checkout uses only offers returned for the selected cart items',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      await openCheckout(tester, requests);
      expect(find.text('Store A offer'), findsOneWidget);
      expect(find.text('Unrelated store offer'), findsNothing);
      expect(find.text('Suggested voucher: Store A offer'), findsOneWidget);
      expect(find.text('Saves ₱20.00 on this order'), findsOneWidget);
      expect(requests.any((r) => r['path'].endsWith('/vouchers')), false);
      final preview = requests.singleWhere(
        (r) => r['path'].endsWith('/orders/preview'),
      );
      expect(preview['body']['cart_item_ids'], [11]);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'voucher suggestion uses server savings instead of whole-cart percentages',
    (tester) async {
      await openCheckout(
        tester,
        [],
        vouchers: [
          offer(1, 'Store A offer', 5),
          {...offer(2, 'Platform offer', 10), 'discount_value': 1},
        ],
      );
      expect(find.text('Suggested voucher: Platform offer'), findsOneWidget);
      expect(find.text('Saves ₱10.00 on this order'), findsOneWidget);
    },
  );

  testWidgets(
    'eligible voucher can be applied and removed without placing an order',
    (tester) async {
      final requests = <Map<String, dynamic>>[];
      await openCheckout(tester, requests);
      await tester.tap(find.text('Store A offer'));
      await tester.pumpAndSettle();
      expect(find.text('Voucher: Store A offer'), findsOneWidget);
      expect(find.text('Discount: -₱20.00'), findsOneWidget);
      final previews = requests
          .where((r) => r['path'].endsWith('/orders/preview'))
          .toList();
      expect(previews.last['body'], {
        'cart_item_ids': [11],
        'voucher_claim_id': 71,
      });
      await tester.tap(find.byTooltip('Remove voucher'));
      await tester.pumpAndSettle();
      expect(find.text('Voucher: Store A offer'), findsNothing);
      expect(requests.last['body'], {
        'cart_item_ids': [11],
      });
      expect(
        requests.any((r) => r['path'].endsWith('/orders/checkout')),
        false,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('shipping voucher shows its shipping saving after validation', (
    tester,
  ) async {
    await openCheckout(tester, [], shipping: true);
    await tester.tap(find.text('Store A offer'));
    await tester.pumpAndSettle();
    expect(find.text('Discount: -₱50.00'), findsOneWidget);
  });

  testWidgets('empty applicable list does not fall back to global vouchers', (
    tester,
  ) async {
    await openCheckout(tester, [], vouchers: []);
    expect(
      find.text('No vouchers apply to the selected items.'),
      findsOneWidget,
    );
    expect(find.byType(ActionChip), findsNothing);
  });

  testWidgets('a stale rejected voucher is never marked as applied', (
    tester,
  ) async {
    await openCheckout(tester, [], rejectVoucher: true);
    await tester.tap(find.text('Store A offer'));
    await tester.pumpAndSettle();
    expect(find.text('Voucher: Store A offer'), findsNothing);
    expect(find.textContaining('does not apply to the items'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
