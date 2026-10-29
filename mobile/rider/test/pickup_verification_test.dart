import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';

// ignore_for_file: avoid_relative_lib_imports
import '../lib/core/network/api_client.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/screens/deliveries/pickup_screen.dart';
import '../lib/services/delivery_service.dart';

const _tracking = 'TRK-20261029-AB12';

Map<String, dynamic> _delivery({
  String status = 'arrived_at_shop',
  String? pickedUpAt,
}) => {
  'id': 42,
  'tracking_number': _tracking,
  'order_id': 9002,
  'status': status,
  'status_label': status == 'picked_up' ? 'Picked Up' : 'Arrived at Shop',
  'shop': {'name': 'Pickup Shop', 'address': '1 Shop St', 'phone': '09171234567'},
  'customer': {'name': 'Buyer', 'address': '2 Buyer St'},
  'items': const [],
  'subtotal': 0,
  'total': 500,
  'payment_method': 'cash_on_delivery',
  'amount_to_collect': 500,
  'pickup_pin_required': false,
  'picked_up_at': ?pickedUpAt,
};

http.Response _json(Object body, [int status = 200]) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json'},
);

({
  ApiClient api,
  void Function() reset,
  int Function() posts,
})
_backend({String status = 'arrived_at_shop', String? pickedUpAt}) {
  var posts = 0;
  final client = MockClient((request) async {
    final path = request.url.path;
    if (request.method == 'GET' && path.endsWith('/rider/deliveries/42')) {
      return _json({
        'delivery': _delivery(status: status, pickedUpAt: pickedUpAt),
      });
    }
    if (request.method == 'POST' && path.endsWith('/pickup')) {
      posts++;
      return _json({
        'message': 'Pickup confirmed.',
        'delivery': _delivery(
          status: 'picked_up',
          pickedUpAt: '2026-10-29T10:00:00Z',
        ),
      });
    }
    return _json({'message': 'Not found.'}, 404);
  });
  return (
    api: ApiClient(client: client),
    reset: () => posts = 0,
    posts: () => posts,
  );
}

Future<DeliveryProvider> _load(
  ApiClient api, {
  String status = 'arrived_at_shop',
}) async {
  final provider = DeliveryProvider(DeliveryService(api));
  await provider.loadDetail(42);
  expect(provider.selected?.status, status);
  return provider;
}

Future<void> _pump(WidgetTester tester, DeliveryProvider provider) async {
  tester.view.physicalSize = const Size(900, 1800);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  await tester.pumpWidget(
    ChangeNotifierProvider<DeliveryProvider>.value(
      value: provider,
      child: const MaterialApp(
        home: PickupScreen(deliveryId: 42, cameraEnabled: false),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('pickup shows seller, address, contact, tracking and items', (
    tester,
  ) async {
    final backend = _backend();
    final provider = await _load(backend.api);
    await _pump(tester, provider);

    expect(find.text('Pickup Shop'), findsOneWidget);
    expect(find.text('1 Shop St'), findsOneWidget);
    expect(find.text('09171234567'), findsOneWidget);
    expect(find.text(_tracking), findsOneWidget);
    expect(find.text('ITEMS TO PICK UP'), findsOneWidget);
  });

  testWidgets('pickup is blocked until the tracking number is verified', (
    tester,
  ) async {
    final backend = _backend();
    final provider = await _load(backend.api);
    await _pump(tester, provider);

    await tester.tap(find.text('Confirm Pickup'));
    await tester.pump();

    expect(
      find.textContaining('Scan the waybill or type the tracking number'),
      findsAtLeastNWidgets(1),
    );
    expect(backend.posts(), 0);
    expect(provider.selected?.status, 'arrived_at_shop');
  });

  testWidgets('a different tracking number is rejected and blocks pickup', (
    tester,
  ) async {
    final backend = _backend();
    final provider = await _load(backend.api);
    await _pump(tester, provider);

    final field = find.byKey(const Key('pickupTrackingField'));
    await tester.ensureVisible(field);
    await tester.enterText(field, 'TRK-0000000');
    await tester.tap(find.text('Verify Parcel'));
    await tester.pump();

    expect(
      find.text('Tracking number does not match this job.'),
      findsAtLeastNWidgets(1),
    );
    expect(find.text('Verified'), findsNothing);

    await tester.tap(find.text('Confirm Pickup'));
    await tester.pump();

    expect(backend.posts(), 0);
    expect(provider.selected?.status, 'arrived_at_shop');
  });

  testWidgets('typing the matching tracking number confirms pickup', (
    tester,
  ) async {
    final backend = _backend();
    final provider = await _load(backend.api);
    await _pump(tester, provider);

    final field = find.byKey(const Key('pickupTrackingField'));
    await tester.ensureVisible(field);
    // Lower case input is normalized before comparison.
    await tester.enterText(field, 'trk-20261029-ab12');
    await tester.tap(find.text('Verify Parcel'));
    await tester.pump();

    expect(find.text('Verified'), findsOneWidget);

    await tester.tap(find.text('Confirm Pickup'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(backend.posts(), 1);
    expect(provider.selected?.status, 'picked_up');
  });

  testWidgets('an already picked-up parcel cannot be scanned again', (
    tester,
  ) async {
    final backend = _backend(
      status: 'picked_up',
      pickedUpAt: '2026-10-29T09:30:00Z',
    );
    final provider = await _load(backend.api, status: 'picked_up');
    await _pump(tester, provider);

    expect(find.textContaining('already picked up'), findsOneWidget);
    expect(find.text('Already Picked Up'), findsOneWidget);

    await tester.tap(find.text('Already Picked Up'));
    await tester.pump();

    expect(backend.posts(), 0);
  });
}
