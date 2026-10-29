import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:invoize_rider/core/network/api_client.dart';
import 'package:invoize_rider/models/delivery.dart';
import 'package:invoize_rider/services/delivery_service.dart';

/// Regression test for the Deliveries-screen crash:
/// `type 'int' is not a subtype of type 'Iterable<dynamic>'`.
///
/// Root cause: `DeliveryService.list` passes `page` as a raw int query value
/// and `ApiClient` forwarded it to `Uri.replace(queryParameters:)`, which only
/// accepts `String` / `Iterable<String>`. The TypeError was thrown before any HTTP
/// request, and [DeliveryProvider.load] surfaced it as "Unable to load
/// deliveries (...)".
void main() {
  // Representative `deliveryPayload` wire shape (items + status_logs).
  Map<String, dynamic> wireDelivery() => {
        'id': 35,
        'tracking_number': 'DVRTEST-ACCEPTED-0001',
        'order_id': null,
        'status': 'accepted',
        'status_label': 'Accepted',
        'shop': {
          'name': 'Test Electronics Shop',
          'phone': '09178881001',
          'address': '1 Test Ave',
          'latitude': 14.279,
          'longitude': 121.432,
        },
        'customer': {
          'name': 'Ramon Test',
          'phone': '09178881011',
          'address': '10 Customer St',
          'latitude': null,
          'longitude': null,
        },
        'items': [
          {
            'id': 32,
            'name': 'Test Product',
            'variant_label': 'Standard',
            'quantity': 2,
            'price': 250,
            'subtotal': 500
          }
        ],
        'subtotal': 500,
        'delivery_fee': 80,
        'total': 580,
        'payment_method': 'cash_on_delivery',
        'amount_to_collect': 500,
        'pickup_pin_required': false,
        'notes': 'Handle with care',
        'weight': '2.50',
        'assigned_at': '2026-09-03T07:04:33+00:00',
        'accepted_at': '2026-09-03T07:59:33+00:00',
        'picked_up_at': null,
        'delivered_at': null,
        'failed_at': null,
        'failure_reason': null,
        'proof': null,
        'status_logs': [
          {
            'status': 'assigned',
            'notes': 'Assigned to Test Rider',
            'created_at': '2026-09-03T08:04:33+00:00'
          },
          {
            'status': 'accepted',
            'notes': 'Delivery accepted by rider.',
            'created_at': '2026-09-03T08:04:33+00:00'
          },
        ],
      };

  test('list() sends int page as query string and parses deliveries', () async {
    Uri? requested;
    // The real list payload carries no `status_logs` key (detail only).
    Map<String, dynamic> listDelivery() {
      final d = wireDelivery()..remove('status_logs');
      return d;
    }

    final api = ApiClient(
      client: MockClient((http.BaseRequest request) async {
        requested = request.url;
        return http.Response(
          jsonEncode({
            'deliveries': [listDelivery(), listDelivery()],
            'pagination': {
              'current_page': 1,
              'last_page': 1,
              'total': 2,
              'per_page': 20,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final page = await DeliveryService(api).list(filter: 'all', page: 1);

    // The int page must be stringified, not crash Uri parsing.
    expect(requested?.queryParameters['status'], 'all');
    expect(requested?.queryParameters['page'], '1');

    expect(page.deliveries, hasLength(2));
    expect(page.total, 2);
    final first = page.deliveries.first;
    expect(first.id, 35);
    expect(first.trackingNumber, 'DVRTEST-ACCEPTED-0001');
    expect(first.items, hasLength(1));
    expect(first.items.first.quantity, 2);
    // List payloads carry no status_logs key -> empty, not a crash.
    expect(first.statusLogs, isEmpty);
  });

  test('detail payload with items + status_logs parses', () {
    final delivery = Delivery.fromJson(wireDelivery());
    expect(delivery.items, hasLength(1));
    expect(delivery.items.first.name, 'Test Product');
    expect(delivery.statusLogs, hasLength(2));
    expect(delivery.statusLogs.first.status, 'assigned');
    expect(delivery.subtotal, 500);
    expect(delivery.isCashOnDelivery, isTrue);
  });

  test('ApiClient drops null query values instead of throwing', () async {
    Uri? requested;
    final api = ApiClient(
      client: MockClient((http.BaseRequest request) async {
        requested = request.url;
        return http.Response('{}', 200,
            headers: {'content-type': 'application/json'});
      }),
    );

    // Mirrors EarningsService.history with absent optional filters.
    await api.get('/rider/history', query: {
      'from': null,
      'to': null,
      'status': 'delivered',
      'page': 2,
    });

    expect(requested?.queryParameters.containsKey('from'), isFalse);
    expect(requested?.queryParameters.containsKey('to'), isFalse);
    expect(requested?.queryParameters['status'], 'delivered');
    expect(requested?.queryParameters['page'], '2');
  });
}
