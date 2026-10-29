import 'package:flutter_test/flutter_test.dart';
import 'package:invoize_rider/models/delivery.dart';

/// Rider Status History: only actually recorded rider-workflow events are
/// shown, oldest first. Logistics parcel events and unknown statuses are
/// excluded, and backend order is never trusted blindly.
Map<String, dynamic> historyPayload({
  required String status,
  required List<Map<String, dynamic>> logs,
}) =>
    {
      'id': 1,
      'tracking_number': 'HIST-1',
      'status': status,
      'status_label': status,
      'shop': const {'name': 'Shop'},
      'customer': const {'name': 'Customer'},
      'items': const [],
      'subtotal': 0,
      'total': 0,
      'status_logs': logs,
    };

Map<String, dynamic> log(String status, String createdAt) => {
      'status': status,
      'notes': '$status note',
      'created_at': createdAt,
    };

void main() {
  test('newly assigned delivery shows only assigned', () {
    final delivery = Delivery.fromJson(historyPayload(status: 'assigned', logs: [
      log('scanned', '2026-09-05T10:20:00+00:00'),
      log('assigned', '2026-09-05T10:30:00+00:00'),
      log('received', '2026-09-05T10:10:00+00:00'),
      log('waiting_for_rider', '2026-09-05T10:00:00+00:00'),
      log('sorted', '2026-09-05T10:25:00+00:00'),
    ]));

    expect(
      delivery.statusLogs.map((l) => l.status).toList(),
      ['assigned'],
    );
  });

  test('accepted delivery shows assigned then accepted', () {
    final delivery = Delivery.fromJson(historyPayload(status: 'accepted', logs: [
      log('accepted', '2026-09-05T10:35:00+00:00'),
      log('assigned', '2026-09-05T10:30:00+00:00'),
    ]));

    expect(
      delivery.statusLogs.map((l) => l.status).toList(),
      ['assigned', 'accepted'],
    );
  });

  test('progressed delivery shows only recorded steps in order', () {
    final delivery = Delivery.fromJson(historyPayload(
      status: 'out_for_delivery',
      // Shuffled, with logistics noise and a bogus future entry.
      logs: [
        log('picked_up', '2026-09-05T10:42:00+00:00'),
        log('delivered', '2026-09-05T11:30:00+00:00'),
        log('sorted', '2026-09-05T10:25:00+00:00'),
        log('assigned', '2026-09-05T10:30:00+00:00'),
        log('out_for_delivery', '2026-09-05T10:50:00+00:00'),
        log('accepted', '2026-09-05T10:35:00+00:00'),
        log('archived', '2026-09-05T10:55:00+00:00'),
      ],
    ));

    // delivered/archived are real rows here, so they appear in place;
    // the point is logistics noise is gone and order is chronological.
    expect(
      delivery.statusLogs.map((l) => l.status).toList(),
      [
        'assigned',
        'accepted',
        'picked_up',
        'out_for_delivery',
        'delivered',
      ],
    );
  });

  test('stale future logs for an assigned delivery are still ordered',
      () {
    // Regression shape of the reported bug: an assigned delivery carrying
    // a previous lifecycle's logs. They are real records, so they render —
    // but strictly oldest-first, never as "upcoming".
    final delivery = Delivery.fromJson(historyPayload(status: 'assigned', logs: [
      log('delivered', '2026-09-05T08:08:00+00:00'),
      log('assigned', '2026-09-05T08:04:00+00:00'),
      log('accepted', '2026-09-05T08:07:00+00:00'),
    ]));

    final statuses =
        delivery.statusLogs.map((l) => l.status).toList();
    expect(statuses, ['assigned', 'accepted', 'delivered']);
  });

  test('failed delivery shows history through delivery_failed', () {
    final delivery = Delivery.fromJson(historyPayload(
      status: 'delivery_failed',
      logs: [
        log('delivery_failed', '2026-09-05T11:00:00+00:00'),
        log('picked_up', '2026-09-05T10:42:00+00:00'),
        log('assigned', '2026-09-05T10:30:00+00:00'),
      ],
    ));

    expect(
      delivery.statusLogs.map((l) => l.status).toList(),
      ['assigned', 'picked_up', 'delivery_failed'],
    );
  });

  test('unknown statuses never appear in history', () {
    final delivery = Delivery.fromJson(historyPayload(status: 'assigned', logs: [
      log('assigned', '2026-09-05T10:30:00+00:00'),
      log('teleported', '2026-09-05T10:40:00+00:00'),
    ]));

    expect(
      delivery.statusLogs.map((l) => l.status).toList(),
      ['assigned'],
    );
  });

  test('missing history parses to empty', () {
    final delivery = Delivery.fromJson(historyPayload(
      status: 'assigned',
      logs: [],
    ));

    expect(delivery.statusLogs, isEmpty);
  });
}
