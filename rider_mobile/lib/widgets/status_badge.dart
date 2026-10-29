import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';

/// Colored status badge for delivery statuses.
class StatusBadge extends StatelessWidget {
  const StatusBadge({super.key, required this.status, this.label});

  final String status;
  final String? label;

  static Color colorFor(String status) {
    switch (status) {
      case 'assigned':
      case 'new':
        return const Color(0xFF1D6FE0);
      case 'accepted':
      case 'going_to_pickup':
      case 'arrived_at_shop':
      case 'pickup':
        return AppColors.secondary;
      case 'picked_up':
      case 'out_for_delivery':
      case 'arrived_at_customer':
      case 'in_transit':
        return const Color(0xFF8A4BDF);
      case 'delivered':
        return AppColors.success;
      case 'delivery_failed':
      case 'failed':
        return AppColors.warning;
      case 'cancelled':
        return AppColors.textSecondary;
      case 'waiting_for_rider':
        return AppColors.textSecondary;
      default:
        return AppColors.textSecondary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = label ?? _defaultLabel(status);
    final color = colorFor(status);
    // The amber accent alone on a pale fill is low-contrast; use a darker
    // ink for the label so the status stays readable while keeping the dot
    // in the accent color.
    final ink = color == AppColors.secondary ? const Color(0xFF8A5A00) : color;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            text,
            style: TextStyle(
              color: ink,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  static String _defaultLabel(String status) {
    switch (status) {
      case 'waiting_for_rider':
        return 'Waiting for Rider';
      case 'assigned':
        return 'Assigned';
      case 'accepted':
        return 'Accepted';
      case 'going_to_pickup':
        return 'Going to Pickup';
      case 'arrived_at_shop':
        return 'Arrived at Shop';
      case 'picked_up':
        return 'Picked Up';
      case 'out_for_delivery':
        return 'Out for Delivery';
      case 'arrived_at_customer':
        return 'Arrived at Customer';
      case 'delivered':
        return 'Delivered';
      case 'delivery_failed':
        return 'Delivery Failed';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status.toUpperCase();
    }
  }
}
