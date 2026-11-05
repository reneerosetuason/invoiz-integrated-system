import 'dart:convert';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';
import 'package:invoiz/screens/checkout_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

http.Response json(Map<String, dynamic> data, [int code = 200]) =>
    http.Response(
      jsonEncode(data),
      code,
      headers: {'content-type': 'application/json'},
    );

class CheckoutApi extends ApiService {
  Map<String, String>? fields;
  Uint8List? receipt;
  CheckoutApi({bool receivingQrReady = true})
    : super(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/cart'))
            return json({
              'cart': {
                'id': 1,
                'subtotal': 100,
                'items': [
                  {
                    'id': 11,
                    'product_id': 7,
                    'quantity': 1,
                    'unit_price': 100,
                    'line_total': 100,
                    'product': {
                      'id': 7,
                      'seller_id': 21,
                      'name': 'Test item',
                      'price': 100,
                      'stock': 10,
                    },
                  },
                ],
              },
            });
          if (request.url.path.endsWith('/addresses'))
            return json({
              'addresses': [
                {
                  'id': 1,
                  'recipient_name': 'Test Buyer',
                  'phone': '09171234567',
                  'address_line': '1 Street',
                  'barangay': 'Test Barangay',
                  'city': 'Manila',
                  'province': 'Metro Manila',
                  'is_default': true,
                },
              ],
            });
          if (request.url.path.endsWith('/orders/preview'))
            return json({
              'quote': {
                'subtotal': 100,
                'shipping_fee': 50,
                'discount': 0,
                'shipping_discount': 0,
                'total': 150,
                'gcash_accounts': [
                  {
                    'seller_id': 21,
                    'store_name': 'Test Store',
                    'account_name': 'Test Seller',
                    'amount': 150,
                    'available': receivingQrReady,
                    'unavailable_reason': receivingQrReady
                        ? null
                        : 'seller_qr_missing',
                    'qr_url': receivingQrReady
                        ? 'https://example.test/qr.png'
                        : null,
                  },
                ],
              },
              'vouchers': [],
            });
          return json({
            'unread_count': 0,
            'conversations': [],
            'favorites': [],
            'notifications': [],
          });
        }),
      );

  @override
  Future<dynamic> postForm(
    String path,
    Map<String, String> fields, {
    List<http.MultipartFile>? files,
  }) async {
    this.fields = fields;
    receipt = await files!.single.finalize().toBytes();
    throw ApiException(statusCode: 422, message: 'Receipt captured by test.');
  }
}

Future<void> open(WidgetTester tester, CheckoutApi api, XFile file) async {
  tester.view.physicalSize = const Size(390, 900);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    AuthServiceProvider(
      authService: AuthService(),
      child: MaterialApp(
        theme: AppTheme.light,
        home: CheckoutScreen(
          api: api,
          cartItemIds: const [11],
          pickReceipt: () async => file,
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.scrollUntilVisible(
    find.text('GCash'),
    150,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.tap(find.text('GCash'));
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });
  final bytes = base64Decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
  );

  testWidgets('missing store QR prevents receipt submission', (tester) async {
    final api = CheckoutApi(receivingQrReady: false);
    await open(
      tester,
      api,
      XFile.fromData(bytes, path: 'receipt.png', mimeType: 'image/png'),
    );
    expect(
      find.text(
        'GCash is unavailable for this store. Choose COD or check out other stores separately.',
      ),
      findsOneWidget,
    );
    expect(find.text('Upload GCash receipt'), findsNothing);
    await tester.tap(find.text('Place order · GCash'));
    await tester.pumpAndSettle();
    expect(
      find.text(
        'A store receiving QR is unavailable. Please choose cash on delivery.',
      ),
      findsOneWidget,
    );
    expect(api.fields, isNull);
  });

  testWidgets(
    'GCash requires receipt and multipart preserves selected item IDs',
    (tester) async {
      final api = CheckoutApi();
      await open(
        tester,
        api,
        XFile.fromData(
          bytes,
          path: 'receipt.png',
          name: 'receipt.png',
          mimeType: 'image/png',
        ),
      );
      await tester.tap(find.text('Place order · GCash'));
      await tester.pumpAndSettle();
      expect(api.fields, isNull);
      expect(find.text('Please upload your GCash receipt.'), findsOneWidget);
      await Scrollable.ensureVisible(
        tester.element(find.text('Upload GCash receipt')),
        alignment: 0.5,
      );
      await tester.pumpAndSettle();
      await Scrollable.ensureVisible(
        tester.element(find.text('Upload GCash receipt')),
        alignment: 0.4,
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Upload GCash receipt'));
      await tester.pumpAndSettle();
      expect(find.text('receipt.png'), findsOneWidget);
      await tester.tap(find.text('Place order · GCash'));
      await tester.pumpAndSettle();
      expect(api.fields, containsPair('payment_method', 'gcash'));
      expect(api.fields, containsPair('cart_item_ids[0]', '11'));
      expect(api.receipt, bytes);
      expect(find.text('Receipt captured by test.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('switching to COD removes uploaded receipt', (tester) async {
    await open(
      tester,
      CheckoutApi(),
      XFile.fromData(bytes, path: 'receipt.png', name: 'receipt.png'),
    );
    await Scrollable.ensureVisible(
      tester.element(find.text('Upload GCash receipt')),
      alignment: 0.5,
    );
    await tester.pumpAndSettle();
    await Scrollable.ensureVisible(
      tester.element(find.text('Upload GCash receipt')),
      alignment: 0.4,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Upload GCash receipt'));
    await tester.pumpAndSettle();
    await Scrollable.ensureVisible(
      tester.element(find.text('Cash on Delivery (COD)')),
      alignment: 0.5,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cash on Delivery (COD)'));
    await tester.pumpAndSettle();
    expect(find.text('receipt.png'), findsNothing);
    expect(find.text('Place order · Cash on delivery'), findsOneWidget);
  });

  testWidgets('oversized receipt is rejected before upload', (tester) async {
    final api = CheckoutApi();
    await open(
      tester,
      api,
      XFile.fromData(Uint8List(5 * 1024 * 1024 + 1), name: 'large.png'),
    );
    await Scrollable.ensureVisible(
      tester.element(find.text('Upload GCash receipt')),
      alignment: 0.4,
    );
    await tester.pumpAndSettle();
    await Scrollable.ensureVisible(
      tester.element(find.text('Upload GCash receipt')),
      alignment: 0.4,
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Upload GCash receipt'));
    await tester.pumpAndSettle();
    expect(
      find.text('Choose a JPG, PNG or WebP receipt no larger than 5 MB.'),
      findsOneWidget,
    );
    expect(api.fields, isNull);
  });
}
