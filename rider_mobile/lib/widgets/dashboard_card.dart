import 'package:flutter/material.dart';

import '../core/theme/app_theme.dart';
import 'app_ui.dart';

/// A tappable stat card used on the dashboard.
///
/// Icon tile, tabular KPI value, then the label — the same information as
/// before, set in the shared type scale so the four stats read as one row.
class DashboardCard extends StatelessWidget {
  const DashboardCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.all(AppGaps.md),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.md),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconTile(icon: icon, size: 38, iconSize: 20, color: color),
              const SizedBox(height: AppGaps.sm),
              Text(
                value,
                style: AppText.kpi(24).copyWith(color: color),
              ),
              const SizedBox(height: 2),
              Text(
                label,
                style: AppText.caption,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
