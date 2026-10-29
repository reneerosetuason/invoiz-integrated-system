import 'package:flutter_test/flutter_test.dart';

// ignore_for_file: avoid_relative_lib_imports
// (Same checkout-wide package-config note as the other runnable tests.)
import '../lib/models/delivery.dart';

/// Sorting-center custody fields on the rider Delivery model: center names
/// parsed from the payload for handoff/pickup confirmations, plus the
/// handoff/pickup timestamp flags the action bar keys off.
Map<String, dynamic> _base() => {
      'id': 7,
      'tracking_number': 'TRK-20260918-ABCD',
      'status': 'assigned',
      'status_label': 'Assigned',
      'shop': {'name': 'Shop'},
      'customer': {'name': 'Cust'},
      'items': [],
      'subtotal': 0,
      'total': 0,
    };

void main() {
  test('parses handling and destination center names when present', () {
    final delivery = Delivery.fromJson({
      ..._base(),
      'logistics_center': {'id': 1, 'name': 'San Pablo Sorting'},
      'destination_center': {'id': 2, 'name': 'Laguna Delivery'},
    });

    expect(delivery.handlingCenterName, 'San Pablo Sorting');
    expect(delivery.destinationCenterName, 'Laguna Delivery');
  });

  test('center names are null when the payload omits them', () {
    final delivery = Delivery.fromJson(_base());

    expect(delivery.handlingCenterName, isNull);
    expect(delivery.destinationCenterName, isNull);
    expect(delivery.hasSortingHandoff, isFalse);
    expect(delivery.hasSortingPickup, isFalse);
  });

  test('handoff and pickup flags follow their timestamps', () {
    final delivery = Delivery.fromJson({
      ..._base(),
      'sorting_center_handoff_at': '2026-09-18T10:00:00Z',
      'sorting_center_pickup_at': '2026-09-18T12:00:00Z',
    });

    expect(delivery.hasSortingHandoff, isTrue);
    expect(delivery.hasSortingPickup, isTrue);
  });

  test('copyWith preserves custody fields', () {
    final delivery = Delivery.fromJson({
      ..._base(),
      'logistics_center': {'id': 1, 'name': 'San Pablo Sorting'},
      'sorting_center_handoff_at': '2026-09-18T10:00:00Z',
    }).copyWith(status: 'accepted');

    expect(delivery.status, 'accepted');
    expect(delivery.handlingCenterName, 'San Pablo Sorting');
    expect(delivery.hasSortingHandoff, isTrue);
  });
}
