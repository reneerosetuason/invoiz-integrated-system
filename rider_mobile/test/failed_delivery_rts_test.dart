import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/failed_delivery_screen.dart';
import '../lib/services/delivery_service.dart';

Map<String, dynamic> _delivery({
  String status = 'out_for_delivery',
  String? failureReason,
  bool returned = false,
  int failedAttempts = 0,
}) => {
  'id': 55,
  'tracking_number': 'TRK-20261029-FAIL',
  'order_id': 9005,
  'status': status,
  'status_label': status == 'delivery_failed'
      ? 'Delivery Failed'
      : 'Out for Delivery',
  'shop': {'name': 'Shop'},
  'customer': {'name': 'Buyer', 'address': '1 Way'},
  'items': const [],
  'subtotal': 500,
  'total': 500,
  'payment_method': 'cash_on_delivery',
  'amount_to_collect': 500,
  'pickup_pin_required': false,
  'failure_reason': ?failureReason,
  'returned_at': returned ? '2026-11-15T12:00:00Z' : null,
  'failed_attempts': failedAttempts,
};

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

class _Backend {
  _Backend({this.attempts = 0}) {
    client = MockClient((request) async {
      final path = request.url.path;
      if (request.method == 'GET' && path.endsWith('/rider/deliveries/55')) {
        return _json({'delivery': _delivery()});
      }
      if (request.method == 'POST' && path.endsWith('/failed')) {
        final body = jsonDecode(request.body) as Map;
        attempts++;
        return _json({
          'message': 'Failed delivery reported.',
          'delivery': _delivery(
            status: 'delivery_failed',
            failureReason: body['reason'] as String?,
            failedAttempts: attempts,
          ),
        });
      }
      if (request.method == 'POST' && path.endsWith('/return-to-center')) {
        returns++;
        return _json({
          'delivery': _delivery(
            status: 'delivery_failed',
            returned: true,
            failedAttempts: attempts,
          ),
        });
      }
      return _json({'message': 'Not found.'}, 404);
    });
  }

  late final MockClient client;
  int returns = 0;
  int attempts;
}

Future<DeliveryProvider> _load(_Backend backend) async {
  final provider = DeliveryProvider(
    DeliveryService(ApiClient(client: backend.client)),
  );
  await provider.loadDetail(55);
  return provider;
}

Future<void> _pump(WidgetTester tester, DeliveryProvider provider) async {
  await tester.pumpWidget(
    ChangeNotifierProvider<DeliveryProvider>.value(
      value: provider,
      child: const MaterialApp(home: FailedDeliveryScreen(deliveryId: 55)),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _report(WidgetTester tester, String reason) async {
  final tile = find.text(reason);
  await tester.ensureVisible(tile);
  await tester.tap(tile);
  await tester.pump();
  await tester.tap(find.text('Submit Failed Delivery'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  testWidgets(
    'refused delivery waits at destination hub for staff reassignment',
    (tester) async {
      final provider = await _load(_Backend());
      await _pump(tester, provider);

      await _report(tester, 'Customer refused order');

      expect(
        find.textContaining('It will stay there until logistics staff assigns'),
        findsOneWidget,
      );
      expect(find.text('Confirm return to destination hub'), findsOneWidget);
      expect(find.text('Request Re-attempt'), findsNothing);
    },
  );

  testWidgets(
    'other failures allow future delivery attempts without a sender return',
    (tester) async {
      final provider = await _load(_Backend());
      await _pump(tester, provider);

      await _report(tester, 'Customer unavailable');

      expect(find.textContaining('After the 3rd failed attempt'), findsNothing);
      expect(find.text('Request Re-attempt'), findsNothing);
      expect(find.textContaining('Return to Sender'), findsNothing);
      expect(find.text('Confirm return to destination hub'), findsOneWidget);
    },
  );

  testWidgets(
    'hub return requires physical handover confirmation and then shows waiting',
    (tester) async {
      final backend = _Backend();
      final provider = await _load(backend);
      await _pump(tester, provider);
      await _report(tester, 'Customer unavailable');
      await tester.tap(find.text('Confirm return to destination hub'));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('physically handed the parcel back'),
        findsOneWidget,
      );
      expect(backend.returns, 0);
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(backend.returns, 0);
      await tester.tap(find.text('Confirm return to destination hub'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm handover'));
      await tester.pumpAndSettle();
      expect(backend.returns, 1);
      expect(provider.selected!.hasCenterReturn, isTrue);
      expect(find.text('Waiting at destination hub'), findsOneWidget);
      expect(find.text('Confirm return to destination hub'), findsNothing);
    },
  );

  testWidgets(
    'third failed attempt requires seller return or unclaimed decision',
    (tester) async {
      final backend = _Backend(attempts: 2);
      final provider = await _load(backend);
      await _pump(tester, provider);
      await _report(tester, 'Customer unavailable');
      expect(provider.selected!.deliveryAttemptsExhausted, isTrue);
      expect(find.text('Failed attempts: 3 / 3'), findsOneWidget);
      expect(
        find.textContaining(
          'The seller must choose return to seller or unclaimed storage',
        ),
        findsOneWidget,
      );
      expect(find.text('Request Re-attempt'), findsNothing);
      expect(find.text('Confirm return to destination hub'), findsOneWidget);
    },
  );
}
