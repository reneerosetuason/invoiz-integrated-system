import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/core/storage/token_storage.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/delivery_detail_screen.dart';
import '../lib/services/delivery_service.dart';

Map<String, dynamic> _delivery() => {
  'id': 66,
  'tracking_number': 'TRK-20261029-MSG1',
  'order_id': 9001,
  'status': 'out_for_delivery',
  'status_label': 'Out for Delivery',
  'shop': {'name': 'Shop'},
  'customer': {
    'name': 'Buyer Name',
    'phone': '09171234567',
    'address': '1 Way',
  },
  'items': const [],
  'subtotal': 500,
  'total': 500,
  'payment_method': 'cash_on_delivery',
  'amount_to_collect': 500,
  'otp_required': true,
  'pickup_pin_required': false,
};

http.Response _json(Object body) => http.Response(
  jsonEncode(body),
  200,
  headers: {'content-type': 'application/json'},
);

ApiClient _api() => ApiClient(
  client: MockClient((request) async {
    final path = request.url.path;
    if (request.method == 'GET' && path.endsWith('/rider/deliveries/66')) {
      return _json({'delivery': _delivery()});
    }
    if (request.method == 'GET' && path.endsWith('/rider/conversations')) {
      return _json({
        'conversations': [
          {
            'id': 3,
            'name': 'Juan Buyer',
            'type': 'buyer',
            'type_label': 'Buyer',
            'order_id': 9001,
            'tracking': 'TRK-20261029-MSG1',
            'preview': 'I am home.',
            'unread': 0,
          },
        ],
        'pagination': {'current_page': 1, 'last_page': 1},
      });
    }
    if (request.method == 'GET' && path.endsWith('/rider/conversations/3')) {
      return _json({
        'messages': [],
        'pagination': {'current_page': 1, 'last_page': 1},
      });
    }
    return http.Response('{}', 404);
  }),
);

void main() {
  testWidgets('delivery detail opens the buyer conversation', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final api = _api();
    final deliveryProvider = DeliveryProvider(DeliveryService(api));
    await deliveryProvider.loadDetail(66);

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<DeliveryProvider>.value(value: deliveryProvider),
          ChangeNotifierProvider<AuthProvider>.value(
            value: AuthProvider(api: api, storage: TokenStorage()),
          ),
        ],
        child: const MaterialApp(home: DeliveryDetailScreen(deliveryId: 66)),
      ),
    );
    await tester.pumpAndSettle();

    final button = find.text('Message Buyer');
    await tester.scrollUntilVisible(
      button,
      300,
      scrollable: find.byType(Scrollable).first,
    );
    // scrollUntilVisible stops as soon as the widget enters the viewport,
    // which can still be behind the bottom action bar. Nudge it up further.
    await tester.drag(find.byType(ListView).first, const Offset(0, -200));
    await tester.pumpAndSettle();
    await tester.tap(button);
    await tester.pumpAndSettle();

    expect(find.text('Juan Buyer'), findsOneWidget);
    expect(find.textContaining('Buyer · TRK-20261029-MSG1'), findsOneWidget);
  });
}
