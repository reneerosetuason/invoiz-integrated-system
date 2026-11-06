import 'dart:ui';
import 'package:flutter/material.dart';
import '../core/theme/app_theme.dart';

class LiquidNavSlot {
  const LiquidNavSlot(this.icon, this.activeIcon, this.label, this.index);
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final int index;
}

/// iOS-style translucent tab dock. Scan remains an action, never a selected tab.
class LiquidNavBar extends StatelessWidget {
  const LiquidNavBar({
    super.key,
    required this.selectedIndex,
    required this.onTap,
    this.slots,
    this.scanLabel = 'Scan',
    this.scanTooltip = 'Scan',
  });
  final int selectedIndex;
  final ValueChanged<int> onTap;
  final List<LiquidNavSlot>? slots;
  final String scanLabel;
  final String scanTooltip;

  static double heightFor(BuildContext context) =>
      52 + MediaQuery.textScalerOf(context).scale(10) * 2;
  static const _defaultTabs = [
    LiquidNavSlot(Icons.home_outlined, Icons.home, 'Home', 0),
    LiquidNavSlot(
      Icons.inventory_2_outlined,
      Icons.inventory_2,
      'Deliveries',
      1,
    ),
    LiquidNavSlot(Icons.settings_outlined, Icons.settings, 'Settings', 3),
    LiquidNavSlot(Icons.person_outline, Icons.person, 'Profile', 4),
  ];

  @override
  Widget build(BuildContext context) {
    final tabs = slots ?? _defaultTabs;
    return Padding(
      padding: const EdgeInsets.fromLTRB(10, 4, 10, 8),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
          child: Material(
            color: AppColors.card.withValues(alpha: .96),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(24),
              side: const BorderSide(color: AppColors.border, width: .6),
            ),
            child: SizedBox(
              height: heightFor(context),
              child: Row(
                children: [
                  for (final tab in tabs.take(2))
                    Expanded(child: _tab(context, tab)),
                  Expanded(
                    child: _tab(
                      context,
                      LiquidNavSlot(
                        Icons.qr_code_scanner,
                        Icons.qr_code_scanner,
                        scanLabel,
                        2,
                      ),
                      scan: true,
                    ),
                  ),
                  for (final tab in tabs.skip(2))
                    Expanded(child: _tab(context, tab)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _tab(BuildContext context, LiquidNavSlot tab, {bool scan = false}) {
    final selected = !scan && selectedIndex == tab.index;
    final ink = scan || selected
        ? AppColors.primaryDark
        : AppColors.textSecondary;
    return Semantics(
      label: tab.label,
      button: true,
      selected: selected,
      child: Tooltip(
        message: scan ? scanTooltip : tab.label,
        child: InkWell(
          onTap: () => onTap(tab.index),
          borderRadius: BorderRadius.circular(18),
          child: ExcludeSemantics(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 6),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: scan
                          ? AppColors.primaryDark
                          : selected
                          ? AppColors.tint
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      selected ? tab.activeIcon : tab.icon,
                      color: scan ? Colors.white : ink,
                      size: 22,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    tab.label,
                    maxLines: 2,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: 'Inter',
                      fontSize: 10,
                      height: 1.1,
                      fontWeight: selected || scan
                          ? FontWeight.w600
                          : FontWeight.w500,
                      color: ink,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
