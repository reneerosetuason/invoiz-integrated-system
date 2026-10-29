import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

// ignore_for_file: avoid_relative_lib_imports
// (Same checkout-wide package-config note as the other runnable tests.)
import '../lib/core/theme/app_theme.dart';
import '../lib/widgets/status_badge.dart';

/// Delivery/parcel status label consistency between the Rider App and the
/// Logistics Web canonical labels. Database values are never changed here —
/// only their user-facing presentation is asserted.
Future<void> _pumpBadge(WidgetTester tester, String status) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(body: StatusBadge(status: status)),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('renders one canonical label per delivery status',
      (tester) async {
    const expected = {
      'waiting_for_rider': 'Waiting for Rider',
      'assigned': 'Assigned',
      'accepted': 'Accepted',
      'going_to_pickup': 'Going to Pickup',
      'arrived_at_shop': 'Arrived at Shop',
      'picked_up': 'Picked Up',
      'out_for_delivery': 'Out for Delivery',
      'arrived_at_customer': 'Arrived at Customer',
      'delivered': 'Delivered',
      'delivery_failed': 'Delivery Failed',
      'cancelled': 'Cancelled',
    };

    for (final entry in expected.entries) {
      await _pumpBadge(tester, entry.key);
      expect(find.text(entry.value), findsOneWidget,
          reason: 'Label mismatch for ${entry.key}');
    }
  });

  test('filter aliases keep their backend-safe colors without new statuses',
      () {
    // Aliases share the color family of the real status they filter to.
    expect(StatusBadge.colorFor('new'), StatusBadge.colorFor('assigned'));
    expect(StatusBadge.colorFor('pickup'),
        StatusBadge.colorFor('going_to_pickup'));
    expect(StatusBadge.colorFor('in_transit'),
        StatusBadge.colorFor('out_for_delivery'));
    expect(
        StatusBadge.colorFor('failed'), StatusBadge.colorFor('delivery_failed'));
  });

  test('terminal statuses use settled colors, cancelled stays quiet', () {
    expect(StatusBadge.colorFor('delivered'), AppColors.success);
    expect(StatusBadge.colorFor('delivery_failed'), AppColors.warning);
    // Cancelled is terminal but visually quiet — never an active color.
    expect(StatusBadge.colorFor('cancelled'), AppColors.textSecondary);
    expect(StatusBadge.colorFor('cancelled'),
        isNot(StatusBadge.colorFor('assigned')));
  });
}
