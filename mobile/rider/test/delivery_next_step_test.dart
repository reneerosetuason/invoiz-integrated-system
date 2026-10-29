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
import '../lib/models/delivery.dart';
import '../lib/providers/auth_provider.dart';
import '../lib/providers/delivery_provider.dart';
import '../lib/providers/earnings_provider.dart';
import '../lib/providers/notification_provider.dart';
import '../lib/screens/deliveries/delivery_detail_screen.dart';
import '../lib/screens/deliveries/failed_delivery_screen.dart';
import '../lib/screens/earnings/history_screen.dart';
import '../lib/screens/notifications/notifications_screen.dart';
import '../lib/services/delivery_service.dart';
import '../lib/services/earnings_service.dart';
import '../lib/services/notification_service.dart';

Map<String, dynamic> _delivery({
  String status = 'arrived_at_customer',
  String statusLabel = 'Arrived at Customer',
  bool requested = false,
  bool returned = false,
}) =>
    {
      'id': 77,
      'tracking_number': 'TRK-20260925-NX01',
      'order_id': 9001,
      'status': status,
      'status_label': statusLabel,
      'shop': {'name': 'Shop'},
      'customer': {'name': 'Cust', 'address': '1 Way'},
      'items': [],
      'subtotal': 0,
      'total': 100,
      'payment_method': 'gcash',
      'reattempt_requested_at':
          requested ? '2026-09-25T11:00:00.000Z' : null,
      'returned_at': returned ? '2026-09-25T11:05:00.000Z' : null,
      'return_reason': returned ? 'Customer refused order' : null,
    };

MockClient _backend({int reattemptStatus = 200, int returnStatus = 200}) {
  http.Response json(Object data, [int status = 200]) => http.Response(
        jsonEncode(data),
        status,
        headers: {'content-type': 'application/json'},
      );
  return MockClient((http.BaseRequest request) async {
    final path = request.url.path;
    bool isPath(String suffix) => path.endsWith(suffix);
    if (RegExp(r'/rider/deliveries/\d+$').hasMatch(path) &&
        request.method == 'GET') {
      return json({'delivery': _delivery(status: 'delivery_failed', statusLabel: 'Delivery Failed')});
    }
    if (isPath('/rider/deliveries/77/failed')) {
      return json({
        'message': 'Failed delivery reported.',
        'delivery':
            _delivery(status: 'delivery_failed', statusLabel: 'Delivery Failed'),
      });
    }
    if (isPath('/rider/deliveries/77/reattempt')) {
      if (reattemptStatus != 200) {
        return json({'message': 'Already requested.'}, reattemptStatus);
      }
      return json({
        'message': 'Re-attempt requested.',
        'delivery': _delivery(
            status: 'delivery_failed',
            statusLabel: 'Delivery Failed',
            requested: true),
      });
    }
    if (isPath('/rider/deliveries/77/return-to-center')) {
      if (returnStatus != 200) {
        return json({'message': 'Already returned.'}, returnStatus);
      }
      return json({
        'message': 'Returned.',
        'delivery': _delivery(
            status: 'delivery_failed',
            statusLabel: 'Delivery Failed',
            returned: true),
      });
    }
    if (isPath('/rider/notifications') && request.method == 'GET') {
      return json({
        'notifications': [
          {
            'id': 11,
            'type': 'delivery_assigned',
            'title': 'New Delivery Assignment',
            'body': 'Assigned.',
            'is_read': false,
            'created_at': '2026-09-25T10:00:00.000Z',
            'data': {'delivery_id': 77},
          },
          {
            'id': 12,
            'type': 'announcement',
            'title': 'Holiday schedule',
            'body': 'Closed Friday.',
            'is_read': true,
            'created_at': '2026-09-24T10:00:00.000Z',
          },
        ],
      });
    }
    if (path.contains('/rider/notifications/') && request.method == 'PATCH') {
      return json({'ok': true});
    }
    if (isPath('/rider/history')) {
      return json({
        'history': [
          {
            'id': 77,
            'tracking_number': 'TRK-20260925-NX01',
            'shop_name': 'Shop',
            'customer_name': 'Cust',
            'delivered_at': '2026-09-25T11:00:00.000Z',
            'status': 'delivered',
            'status_label': 'Delivered',
            'payment_method': 'gcash',
            'total': 100,
            'earned': 50,
          },
        ],
        'pagination': {
          'current_page': 1,
          'last_page': 1,
          'total': 1,
          'per_page': 15
        },
      });
    }
    return json({}, 404);
  });
}

Future<void> _pump(WidgetTester tester, Widget app, MockClient backend,
    {DeliveryProvider? deliveries,
    NotificationProvider? notifications,
    EarningsProvider? earnings}) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  SharedPreferences.setMockInitialValues({});
  final api = ApiClient(client: backend);
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>.value(
          value: AuthProvider(api: api, storage: TokenStorage()),
        ),
        if (deliveries != null)
          ChangeNotifierProvider<DeliveryProvider>.value(value: deliveries),
        if (notifications != null)
          ChangeNotifierProvider<NotificationProvider>.value(
              value: notifications),
        if (earnings != null)
          ChangeNotifierProvider<EarningsProvider>.value(value: earnings),
      ],
      child: MaterialApp(home: app),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('Delivery model next-step flags', () {
    test('parses reattempt and return timestamps', () {
      final d = Delivery.fromJson(
          _delivery(requested: true, returned: true));
      expect(d.hasReattemptRequest, isTrue);
      expect(d.hasCenterReturn, isTrue);
      expect(d.returnReason, 'Customer refused order');
    });

    test('flags default to absent', () {
      final d = Delivery.fromJson(_delivery());
      expect(d.hasReattemptRequest, isFalse);
      expect(d.hasCenterReturn, isFalse);
    });
  });

  group('DeliveryService next-step contract', () {
    test('reattempt posts and parses flags', () async {
      final api = ApiClient(client: _backend());
      final d = await DeliveryService(api).requestReattempt(77);
      expect(d.hasReattemptRequest, isTrue);
      expect(d.status, 'delivery_failed');
    });

    test('return posts and parses flags', () async {
      final api = ApiClient(client: _backend());
      final d = await DeliveryService(api).returnToCenter(77);
      expect(d.hasCenterReturn, isTrue);
    });
  });

  group('FailedDeliveryScreen next step', () {
    testWidgets('reporting reveals the next-step actions', (tester) async {
      final backend = _backend();
      final api = ApiClient(client: backend);
      final deliveries = DeliveryProvider(DeliveryService(api));
      await _pump(tester, const FailedDeliveryScreen(deliveryId: 77),
          backend,
          deliveries: deliveries);
      // Arrived state loads; report a non-refusal failure so both next-step
      // actions remain available (refusals go straight to RTS).
      await tester.ensureVisible(find.text('Customer unavailable'));
      await tester.tap(find.text('Customer unavailable'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Submit Failed Delivery'));
      await tester.pumpAndSettle();

      expect(find.text('WHAT SHOULD HAPPEN NEXT?'), findsOneWidget);
      expect(find.text('Request Re-attempt'), findsOneWidget);
      expect(find.text('Return to Sorting Center (RTS)'), findsOneWidget);
    });

    testWidgets('requesting a re-attempt succeeds', (tester) async {
      var posts = 0;
      final backend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        if (RegExp(r'/rider/deliveries/\d+$').hasMatch(path)) {
          return json({
            'delivery': _delivery(
                status: 'delivery_failed', statusLabel: 'Delivery Failed')
          });
        }
        if (path.endsWith('/failed')) {
          return json({
            'message': 'Failed delivery reported.',
            'delivery': _delivery(
                status: 'delivery_failed', statusLabel: 'Delivery Failed'),
          });
        }
        if (path.endsWith('/reattempt')) {
          posts++;
          return json({
            'message': 'Re-attempt requested.',
            'delivery': _delivery(
                status: 'delivery_failed',
                statusLabel: 'Delivery Failed',
                requested: true),
          });
        }
        return json({}, 404);
      });
      final api = ApiClient(client: backend);
      final deliveries = DeliveryProvider(DeliveryService(api));
      await _pump(tester, const FailedDeliveryScreen(deliveryId: 77),
          backend,
          deliveries: deliveries);

      await tester.ensureVisible(find.text('Customer unavailable'));
      await tester.tap(find.text('Customer unavailable'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Submit Failed Delivery'));
      await tester.pumpAndSettle();
      // Clear the submit SnackBar: ScaffoldMessenger queues (rather than
      // replaces), and pumpAndSettle returns while a static SnackBar is
      // visible, so the queued next-step message would never surface.
      tester
          .state<ScaffoldMessengerState>(find.byType(ScaffoldMessenger))
          .clearSnackBars();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Request Re-attempt'));
      // Bounded pump: pumpAndSettle would advance past the SnackBar's
      // auto-dismiss window before the assertion runs.
      await tester.pump();

      expect(posts, 1);
      expect(find.text('Re-attempt requested. Logistics will review and reassign.'),
          findsOneWidget);
    });
  });

  group('DeliveryDetailScreen failed section', () {
    testWidgets('failed delivery offers next-step actions', (tester) async {
      final backend = _backend();
      final api = ApiClient(client: backend);
      final deliveries = DeliveryProvider(DeliveryService(api))
        ..loadDetail(77);
      await _pump(tester, const DeliveryDetailScreen(deliveryId: 77),
          backend,
          deliveries: deliveries);

      expect(find.text('Request Re-attempt'), findsOneWidget);
      expect(find.text('Return to Sorting Center (RTS)'), findsOneWidget);
    });

    testWidgets('requested state shows the waiting banner', (tester) async {
      final backend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        return json({
          'delivery': _delivery(
              status: 'delivery_failed',
              statusLabel: 'Delivery Failed',
              requested: true)
        });
      });
      final api = ApiClient(client: backend);
      final deliveries = DeliveryProvider(DeliveryService(api))
        ..loadDetail(77);
      await _pump(tester, const DeliveryDetailScreen(deliveryId: 77),
          backend,
          deliveries: deliveries);

      expect(find.textContaining('waiting on Logistics'), findsOneWidget);
      expect(find.text('Request Re-attempt'), findsNothing);
    });
  });

  group('History detail tap-through', () {
    testWidgets('tapping a history card opens the delivery', (tester) async {
      final backend = _backend();
      final api = ApiClient(client: backend);
      final earnings = EarningsProvider(EarningsService(api))
        ..loadHistory();
      final deliveries = DeliveryProvider(DeliveryService(api));
      SharedPreferences.setMockInitialValues({});
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MultiProvider(
          providers: [
            ChangeNotifierProvider<AuthProvider>.value(
              value: AuthProvider(api: api, storage: TokenStorage()),
            ),
            ChangeNotifierProvider<EarningsProvider>.value(value: earnings),
            ChangeNotifierProvider<DeliveryProvider>.value(value: deliveries),
          ],
          child: const MaterialApp(home: HistoryScreen()),
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('TRK-20260925-NX01').first);
      await tester.pumpAndSettle();

      // Detail for the same tracking number renders.
      expect(find.text('TRK-20260925-NX01'), findsWidgets);
    });
  });

  group('Notification tap-through', () {
    testWidgets('delivery notification opens the delivery', (tester) async {
      final backend = _backend();
      final api = ApiClient(client: backend);
      final notifications =
          NotificationProvider(NotificationService(api))..load();
      // The detail screen reads its own provider for the opened delivery.
      final deliveries = DeliveryProvider(DeliveryService(api));
      await _pump(tester, const NotificationsScreen(), backend,
          notifications: notifications, deliveries: deliveries);

      await tester.tap(find.text('New Delivery Assignment'));
      await tester.pumpAndSettle();

      expect(find.text('TRK-20260925-NX01'), findsWidgets);
    });

    testWidgets('notification without delivery stays put', (tester) async {
      final backend = _backend();
      final api = ApiClient(client: backend);
      final notifications =
          NotificationProvider(NotificationService(api))..load();
      final deliveries = DeliveryProvider(DeliveryService(api));
      await _pump(tester, const NotificationsScreen(), backend,
          notifications: notifications, deliveries: deliveries);

      await tester.tap(find.text('Holiday schedule'));
      await tester.pumpAndSettle();

      expect(find.text('Holiday schedule'), findsOneWidget);
      expect(find.text('TRK-20260925-NX01'), findsNothing);
    });

    testWidgets('unauthorized delivery shows an error, not data',
        (tester) async {
      final backend = MockClient((http.BaseRequest request) async {
        http.Response json(Object data, [int status = 200]) => http.Response(
              jsonEncode(data),
              status,
              headers: {'content-type': 'application/json'},
            );
        final path = request.url.path;
        if (path.endsWith('/rider/notifications') &&
            request.method == 'GET') {
          return json({
            'notifications': [
              {
                'id': 13,
                'type': 'delivery_assigned',
                'title': 'Stale Assignment',
                'body': 'No longer yours.',
                'is_read': false,
                'created_at': '2026-09-25T10:00:00.000Z',
                'data': {'delivery_id': 4242},
              },
            ],
          });
        }
        if (path.contains('/rider/notifications/') &&
            request.method == 'PATCH') {
          return json({'ok': true});
        }
        if (RegExp(r'/rider/deliveries/\d+$').hasMatch(path)) {
          return json({'message': 'This delivery is not assigned to you.'}, 403);
        }
        return json({}, 404);
      });
      final api = ApiClient(client: backend);
      final notifications =
          NotificationProvider(NotificationService(api))..load();
      final deliveries = DeliveryProvider(DeliveryService(api));
      await _pump(tester, const NotificationsScreen(), backend,
          notifications: notifications, deliveries: deliveries);

      await tester.tap(find.text('Stale Assignment'));
      await tester.pumpAndSettle();

      // Detail error state, never the other rider's payload.
      expect(find.text('TRK-20260925-NX01'), findsNothing);
      expect(find.text('Stale Assignment'), findsNothing);
    });
  });
}

