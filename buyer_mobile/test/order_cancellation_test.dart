import 'dart:convert';

import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoiz/screens/order_detail_screen.dart';
import 'package:invoiz/services/api_service.dart';
import 'package:invoiz/services/auth_service.dart';
import 'package:invoiz/theme.dart';
import 'package:invoiz/widgets/auth_service_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

const reasons = {
  'changed_mind': 'Changed my mind',
  'ordered_by_mistake': 'I ordered by mistake',
  'found_better_price': 'I found a better price',
  'delivery_too_long': 'Delivery is taking too long',
  'wrong_item': 'I ordered the wrong item',
  'other': 'Other reason',
};

http.Response json(Object value, [int status = 200]) => http.Response(
  jsonEncode(value),
  status,
  headers: {'content-type': 'application/json'},
);

Future<void> openOrder(
  WidgetTester tester,
  List<Map<String, dynamic>> sent, {
  bool fail = false,
}) async {
  tester.view.physicalSize = const Size(360, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  var cancelled = false;
  final api = ApiService(
    client: MockClient((request) async {
      if (request.method == 'POST' &&
          request.url.path.endsWith('/orders/42/cancel')) {
        sent.add(jsonDecode(request.body) as Map<String, dynamic>);
        if (fail) {
          return json({
            'message': 'This order can no longer be cancelled.',
          }, 422);
        }
        cancelled = true;
        return json({'message': 'Order cancelled.'});
      }
      if (request.url.path.endsWith('/orders/42')) {
        return json({
          'order': {
            'id': 42,
            'status': cancelled ? 'cancelled' : 'pending',
            'can_cancel': !cancelled,
            'total_amount': 150,
            'created_at': '2026-10-29T06:00:00Z',
            'items': [
              {
                'id': 98,
                'product_id': 1,
                'product_name': 'Linen everyday bag',
                'quantity': 1,
                'price': 100,
                'subtotal': 100,
              },
            ],
            'shipping_fee': 50,
            'deliveries': [],
            'status_histories': [],
          },
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
        home: OrderDetailScreen(orderId: 42, api: api),
      ),
    ),
  );
  await tester.pumpAndSettle();
  await openCancellation(tester);
}

Future<void> openCancellation(WidgetTester tester) async {
  await tester.scrollUntilVisible(
    find.text('Cancel Order'),
    300,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.pumpAndSettle();
  await tester.tap(find.text('Cancel Order'));
  await tester.pumpAndSettle();
}

Future<void> chooseReason(WidgetTester tester, String value) async {
  await tester.tap(
    find.descendant(
      of: find.byKey(const ValueKey('cancellation-reason')),
      matching: find.byType(CupertinoButton),
    ),
  );
  await tester.pumpAndSettle();
  for (final label in reasons.values) {
    expect(
      find.widgetWithText(CupertinoActionSheetAction, label),
      findsOneWidget,
    );
  }
  await tester.tap(
    find.widgetWithText(CupertinoActionSheetAction, reasons[value]!),
  );
  await tester.pumpAndSettle();
}

Future<void> submit(WidgetTester tester) async {
  await tester.tap(find.widgetWithText(CupertinoDialogAction, 'Cancel order'));
  await tester.pumpAndSettle();
}

void main() {
  WidgetController.hitTestWarningShouldBeFatal = true;
  setUp(() {
    SharedPreferences.setMockInitialValues({});
    ApiService.setToken('fixture-token');
  });

  testWidgets(
    'no reason is preselected and keeping an order sends no cancellation',
    (tester) async {
      final sent = <Map<String, dynamic>>[];
      await openOrder(tester, sent);
      expect(find.text('Select a reason'), findsOneWidget);
      await submit(tester);
      expect(find.text('Please select a reason.'), findsOneWidget);
      expect(sent, isEmpty);
      await tester.tap(find.text('Keep order'));
      await tester.pumpAndSettle();
      expect(find.byType(CupertinoAlertDialog), findsNothing);
      await openCancellation(tester);
      expect(find.text('Select a reason'), findsOneWidget);
      expect(sent, isEmpty);
      expect(tester.takeException(), isNull);
    },
  );

  for (final reason in reasons.keys.where((value) => value != 'other')) {
    testWidgets('cancellation sends the buyer selected $reason reason', (
      tester,
    ) async {
      final sent = <Map<String, dynamic>>[];
      await openOrder(tester, sent);
      await chooseReason(tester, reason);
      expect(sent, isEmpty);
      await submit(tester);
      expect(sent, [
        {'reason': reason},
      ]);
      expect(find.text('Order cancelled.'), findsOneWidget);
      expect(find.text('Cancel Order'), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'Other requires a valid explanation and sends its trimmed details',
    (tester) async {
      final sent = <Map<String, dynamic>>[];
      await openOrder(tester, sent);
      await chooseReason(tester, 'other');
      await submit(tester);
      expect(
        find.text('Please provide at least 10 characters.'),
        findsOneWidget,
      );
      final details = find.byKey(const ValueKey('cancellation-details'));
      for (final invalid in ['             ', 'short']) {
        await tester.enterText(details, invalid);
        await submit(tester);
        expect(sent, isEmpty);
        expect(
          find.text('Please provide at least 10 characters.'),
          findsOneWidget,
        );
      }
      await tester.enterText(details, '  Ordered the same item twice.  ');
      await submit(tester);
      expect(sent, [
        {'reason': 'other', 'reason_details': 'Ordered the same item twice.'},
      ]);
      expect(find.text('Order cancelled.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('changing from Other omits its explanation from cancellation', (
    tester,
  ) async {
    final sent = <Map<String, dynamic>>[];
    await openOrder(tester, sent);
    await chooseReason(tester, 'other');
    await tester.enterText(
      find.byKey(const ValueKey('cancellation-details')),
      'Ordered the same item twice.',
    );
    await chooseReason(tester, 'wrong_item');
    expect(find.byKey(const ValueKey('cancellation-details')), findsNothing);
    await submit(tester);
    expect(sent, [
      {'reason': 'wrong_item'},
    ]);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'server rejection keeps the order active and permits another attempt',
    (tester) async {
      final sent = <Map<String, dynamic>>[];
      await openOrder(tester, sent, fail: true);
      await chooseReason(tester, 'delivery_too_long');
      await submit(tester);
      expect(sent, [
        {'reason': 'delivery_too_long'},
      ]);
      expect(
        find.text('This order can no longer be cancelled.'),
        findsOneWidget,
      );
      expect(find.text('Order cancelled.'), findsNothing);
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      await openCancellation(tester);
      expect(find.text('Select a reason'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}
