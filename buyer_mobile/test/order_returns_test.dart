import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/models/order.dart';
import 'package:invoiz/screens/order_detail_screen.dart';
import 'package:invoiz/screens/orders_screen.dart';
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
Map<String, dynamic> returnCase(String status, {int quantity = 1}) => {
  'id': 77,
  'order_item_id': 98,
  'status': status,
  'quantity': quantity,
  'refund_amount': quantity * 100,
  'reason': 'damaged',
  'decision_note': 'Please contact the store.',
};
Map<String, dynamic> purchase(
  int id, {
  String status = 'delivered',
  String name = 'Linen bag',
  List<Map<String, dynamic>> returns = const [],
}) => {
  'id': id,
  'status': status,
  'can_cancel': false,
  'total_amount': 350,
  'shipping_fee': 50,
  'created_at': '2026-10-29T06:00:00Z',
  'returns': returns,
  'deliveries': [],
  'status_histories': [],
  'items': [
    {
      'id': 98,
      'product_id': 1,
      'product_name': name,
      'quantity': 3,
      'price': 100,
      'subtotal': 300,
      'variant_label': 'Size: Large',
    },
  ],
};

class ReturnServer {
  final sent = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> records = [];
  int networkFailures = 0;
  bool loseFirstResponse = false;
  bool invalidSuccess = false;
  Completer<http.Response>? pending;
  List<Map<String, dynamic>>? orders;
  late final api = ApiService(
    client: MockClient((request) async {
      if (request.method == 'POST' &&
          request.url.path.endsWith('/orders/42/returns')) {
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        sent.add(body);
        if (pending != null) return pending!.future;
        if (networkFailures > 0) {
          networkFailures--;
          throw http.ClientException('Disconnected');
        }
        if (invalidSuccess) return json({});
        if (records.isNotEmpty) {
          return json({
            'message': 'Already saved.',
            'return': records.last,
          }, 409);
        }
        records = [returnCase('requested', quantity: body['quantity'] as int)];
        if (loseFirstResponse) {
          throw http.ClientException('Connection lost after saving');
        }
        return json({'return': records.single}, 201);
      }
      if (request.url.path.endsWith('/orders/42')) {
        return json({'order': purchase(42, returns: records)});
      }
      if (request.url.path.endsWith('/orders')) {
        return json({
          'orders': orders ?? [purchase(42, returns: records)],
        });
      }
      return json({'unread_count': 0, 'favorites': [], 'notifications': []});
    }),
  );
}

Future<void> open(
  WidgetTester tester,
  ReturnServer server, {
  bool orders = false,
  double scale = 1,
}) async {
  tester.view.physicalSize = Size(scale > 1 ? 320 : 360, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    AuthServiceProvider(
      authService: AuthService(),
      child: RepaintBoundary(
        key: const ValueKey('return-preview'),
        child: MaterialApp(
          theme: AppTheme.light,
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(
              context,
            ).copyWith(textScaler: TextScaler.linear(scale)),
            child: child!,
          ),
          home: orders
              ? OrdersScreen(api: server.api)
              : OrderDetailScreen(orderId: 42, api: server.api),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> requestDialog(WidgetTester tester) async {
  await tester.scrollUntilVisible(
    find.text('Request return'),
    250,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.text('Request return'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Request return'));
  await tester.pumpAndSettle();
}

Future<void> capture(WidgetTester tester, String name) async {
  final output = Platform.environment['INVOIZ_RETURN_SCREENSHOTS'];
  if (output == null) return;
  await tester.runAsync(() async {
    final boundary = tester.renderObject<RenderRepaintBoundary>(
      find.byKey(const ValueKey('return-preview')),
    );
    final image = await boundary.toImage(pixelRatio: 1);
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    await Directory(output).create(recursive: true);
    await File('$output/$name.png').writeAsBytes(data!.buffer.asUint8List());
    image.dispose();
  });
}

void main() {
  WidgetController.hitTestWarningShouldBeFatal = true;
  setUpAll(() async {
    for (final name in ['Inter', 'Fraunces']) {
      await (FontLoader(
        name,
      )..addFont(rootBundle.load('assets/fonts/$name.ttf'))).load();
    }
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    await (FontLoader('packages/cupertino_icons/CupertinoIcons')..addFont(
          rootBundle.load('packages/cupertino_icons/assets/CupertinoIcons.ttf'),
        ))
        .load();
  });
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken(null);
  });

  testWidgets(
    'Return pill finds saved cases and combines with search without changing delivery filters',
    (tester) async {
      final server = ReturnServer()
        ..orders = [
          purchase(41, name: 'Unreturned purchase'),
          purchase(42, returns: [returnCase('requested')]),
          purchase(43, status: 'processing', name: 'Preparing purchase'),
          purchase(
            44,
            name: 'Rejected purchase',
            returns: [returnCase('rejected')],
          ),
          purchase(
            45,
            name: 'Refund purchase',
            returns: [returnCase('refunded')],
          ),
          purchase(46, status: 'cancelled', name: 'Cancelled purchase'),
          purchase(47, status: 'out_for_delivery', name: 'Travelling purchase'),
        ];
      await open(tester, server, orders: true);
      for (final label in [
        'All',
        'To Ship',
        'In Transit',
        'Delivered',
        'Return',
        'Cancelled',
      ]) {
        expect(find.widgetWithText(ChoiceChip, label), findsOneWidget);
      }
      await tester.ensureVisible(find.widgetWithText(ChoiceChip, 'Return'));
      await tester.tap(find.widgetWithText(ChoiceChip, 'Return'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('order-card-41')), findsNothing);
      expect(find.byKey(const ValueKey('order-card-42')), findsOneWidget);
      expect(find.byKey(const ValueKey('order-card-44')), findsOneWidget);
      await capture(tester, 'return-filter-360');
      final search = find.descendant(
        of: find.byKey(const ValueKey('orders-search')),
        matching: find.byType(CupertinoTextField),
      );
      await tester.enterText(search, 'Rejected');
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('order-card-42')), findsNothing);
      expect(find.text('Return rejected'), findsOneWidget);
      await tester.enterText(search, '');
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.widgetWithText(ChoiceChip, 'To Ship'));
      await tester.tap(find.widgetWithText(ChoiceChip, 'To Ship'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('order-card-43')), findsOneWidget);
      expect(find.byKey(const ValueKey('order-card-42')), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'failed return stays editable with short details and retries successfully on a small phone',
    (tester) async {
      final server = ReturnServer()..networkFailures = 1;
      await open(tester, server, scale: 1.5);
      await requestDialog(tester);
      await tester.enterText(
        find.byKey(const ValueKey('return-details')),
        'broken',
      );
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(find.byType(CupertinoAlertDialog), findsOneWidget);
      expect(find.byKey(const ValueKey('return-submit-error')), findsOneWidget);
      expect(find.text('broken'), findsOneWidget);
      expect(server.records, isEmpty);
      await capture(tester, 'return-failed-320-large-text');
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(server.sent.length, 2);
      expect(server.sent.last, {
        'order_item_id': 98,
        'reason': 'damaged',
        'quantity': 1,
        'reason_details': 'broken',
      });
      expect(find.byType(CupertinoAlertDialog), findsNothing);
      expect(find.textContaining('Return #77 saved.'), findsOneWidget);
      expect(find.textContaining('Awaiting seller review'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'uncertain response resolves the existing request on retry without a duplicate',
    (tester) async {
      final server = ReturnServer()..loseFirstResponse = true;
      await open(tester, server);
      await requestDialog(tester);
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('return-submit-error')), findsOneWidget);
      expect(server.records.length, 1);
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(server.records.length, 1);
      expect(find.textContaining('Return #77 saved.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'Other requires an explanation and malformed success never claims a saved return',
    (tester) async {
      final server = ReturnServer()..invalidSuccess = true;
      await open(tester, server);
      await requestDialog(tester);
      await tester.tap(find.text('Damaged item'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(CupertinoActionSheetAction, 'Other'),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(server.sent, isEmpty);
      await tester.enterText(
        find.byKey(const ValueKey('return-details')),
        'The stitching is coming apart.',
      );
      await tester.tap(find.text('Send request'));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('return-submit-error')), findsOneWidget);
      expect(find.textContaining('Return #77 saved.'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('only remaining units can be requested after a partial refund', (
    tester,
  ) async {
    final server = ReturnServer()
      ..records = [returnCase('refunded', quantity: 2)];
    await open(tester, server);
    await requestDialog(tester);
    expect(find.text('Quantity (up to 1)'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('return-quantity')), '2');
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();
    expect(server.sent, isEmpty);
    expect(find.text('Enter a remaining returnable quantity.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'sending blocks duplicate taps and cancellation until the request finishes',
    (tester) async {
      final server = ReturnServer()..pending = Completer<http.Response>();
      await open(tester, server);
      await requestDialog(tester);
      await tester.tap(find.text('Send request'));
      await tester.pump();
      expect(server.sent.length, 1);
      final cancel = tester.widget<CupertinoDialogAction>(
        find.widgetWithText(CupertinoDialogAction, 'Keep item'),
      );
      expect(cancel.onPressed, isNull);
      server.pending!.complete(json({'return': returnCase('requested')}, 201));
      await tester.pumpAndSettle();
      expect(server.sent.length, 1);
      expect(find.textContaining('Return #77 saved.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'return history counts refunded units without consuming rejected quantities',
    () {
      final order = Order.fromJson(
        purchase(
          42,
          returns: [
            returnCase('refunded', quantity: 1),
            {...returnCase('rejected', quantity: 3), 'id': 78},
          ],
        ),
      );
      expect(order.returnableQuantity(order.items.single), 2);
      expect(order.returnSummary, 'Return rejected');
      expect(order.status, 'delivered');
    },
  );
}
