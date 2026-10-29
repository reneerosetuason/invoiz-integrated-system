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
  'otp_required': false,
  'pickup_pin_required': false,
  'failure_reason': ?failureReason,
};

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

class _Backend {
  _Backend() {
    client = MockClient((request) async {
      final path = request.url.path;
      if (request.method == 'GET' && path.endsWith('/rider/deliveries/55')) {
        return _json({'delivery': _delivery()});
      }
      if (request.method == 'POST' && path.endsWith('/failed')) {
        final body = jsonDecode(request.body) as Map;
        return _json({
          'message': 'Failed delivery reported.',
          'delivery': _delivery(
            status: 'delivery_failed',
            failureReason: body['reason'] as String?,
          ),
        });
      }
      return _json({'message': 'Not found.'}, 404);
    });
  }

  late final MockClient client;
}

Future<DeliveryProvider> _load(_Backend backend) async {
  final provider = DeliveryProvider(
    DeliveryService(ApiClient(client: backend.client)),
  );
  await provider.loadDetail(55);
  return provider;
}

Future<void> _pump(
  WidgetTester tester,
  DeliveryProvider provider,
) async {
  await tester.pumpWidget(
    ChangeNotifierProvider<DeliveryProvider>.value(
      value: provider,
      child: const MaterialApp(home: FailedDeliveryScreen(deliveryId: 55)),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _report(
  WidgetTester tester,
  String reason,
) async {
  final tile = find.text(reason);
  await tester.ensureVisible(tile);
  await tester.tap(tile);
  await tester.pump();
  await tester.tap(find.text('Submit Failed Delivery'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  testWidgets('refused delivery offers Return to Sender, not re-attempt', (
    tester,
  ) async {
    final provider = await _load(_Backend());
    await _pump(tester, provider);

    await _report(tester, 'Customer refused order');

    expect(
      find.textContaining('Customer refused: this parcel must go back'),
      findsOneWidget,
    );
    expect(find.text('Return to Sorting Center (RTS)'), findsOneWidget);
    expect(find.text('Request Re-attempt'), findsNothing);
  });

  testWidgets('other failures explain the 3-attempt return rule', (
    tester,
  ) async {
    final provider = await _load(_Backend());
    await _pump(tester, provider);

    await _report(tester, 'Customer unavailable');

    expect(
      find.textContaining('After the 3rd failed attempt'),
      findsOneWidget,
    );
    expect(find.text('Request Re-attempt'), findsOneWidget);
    expect(find.text('Return to Sorting Center (RTS)'), findsOneWidget);
  });
}
